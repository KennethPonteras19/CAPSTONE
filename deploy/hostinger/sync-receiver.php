<?php

// CLI-only receiver. Install outside public_html, in ~/tmta-sync/receiver.php.
declare(strict_types=1);

function syncAllowed(string $path): bool
{
    if ($path === '' || str_contains($path, '\\') || preg_match('/[\x00-\x1f\x7f]/', $path)) return false;
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.env')) return false;
    }
    if (preg_match('#^(bootstrap/cache|public/storage)(/|$)#', $path) || $path === 'public/hot') return false;
    if (preg_match('/(?:~|\.(?:tmp|swp|bak))$/i', $path)) return false;
    return (bool) preg_match('#^(app|bootstrap|config|resources|routes|public|docs)/#', $path)
        || (bool) preg_match('#^database/(migrations|factories|seeders)/#', $path)
        || in_array($path, ['artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'deploy/hostinger/index.php'], true);
}

function syncTargets(string $site, string $path): array
{
    if (!syncAllowed($path)) throw new RuntimeException('Excluded or invalid path: '.$path);
    $targets = [$site.'/capstone/'.$path];
    if (str_starts_with($path, 'public/') && $path !== 'public/index.php') {
        array_unshift($targets, $site.'/public_html/'.substr($path, 7));
    }
    if ($path === 'deploy/hostinger/index.php') array_unshift($targets, $site.'/public_html/index.php');
    foreach ($targets as $target) {
        $relative = substr($target, strlen($site) + 1);
        $parent = $site;
        foreach (explode('/', $relative) as $part) {
            $parent .= '/'.$part;
            if (is_link($parent)) throw new RuntimeException('Refusing symbolic-link destination: '.$path);
        }
    }
    return $targets;
}

function syncSnapshot(string $site): array
{
    $root = $site.'/capstone';
    $files = [];
    $scan = function (string $directory) use (&$scan, &$files, $root, $site): void {
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDot() || $file->isLink()) continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->isDir()) {
                if ($path === 'database' || syncAllowed($path.'/probe')) $scan($file->getPathname());
            } elseif (syncAllowed($path)) {
                $target = syncTargets($site, $path)[0];
                if (is_file($target)) $files[$path] = hash_file('sha256', $target);
            }
        }
    };
    $scan($root);
    // deploy/hostinger is intentionally not part of the general recursive scan.
    $entry = 'deploy/hostinger/index.php';
    if (is_file($site.'/public_html/index.php')) $files[$entry] = hash_file('sha256', $site.'/public_html/index.php');
    ksort($files);
    return $files;
}

function syncCommand(array $command, string $directory): void
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $directory);
    if (!is_resource($process)) throw new RuntimeException('Cannot start validation command.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) throw new RuntimeException(implode(' ', array_slice($command, 1)).': '.substr($output, -3000));
}

function syncCaches(string $site): void
{
    foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
        syncCommand([PHP_BINARY, 'artisan', $command, '--no-interaction'], $site.'/capstone');
    }
}

function syncApply(string $site, string $syncRoot, string $bundle, callable $refresh): array
{
    $zip = new ZipArchive;
    if ($zip->open($bundle) !== true) throw new RuntimeException('Cannot open sync bundle.');
    $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
    if (!isset($manifest['changes']) || !is_array($manifest['changes']) || !$manifest['changes']) throw new RuntimeException('Empty sync bundle.');
    $batch = $syncRoot.'/backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
    if (!mkdir($batch, 0700, true)) throw new RuntimeException('Cannot create private rollback directory.');
    $prepared = [];
    $seen = [];
    // Validate the entire batch before changing any live file.
    foreach ($manifest['changes'] as $index => $change) {
        $path = $change['path'] ?? '';
        $targets = syncTargets($site, $path);
        if (isset($seen[$path])) throw new RuntimeException('Duplicate sync path.');
        $seen[$path] = true;
        $actual = is_file($targets[0]) ? hash_file('sha256', $targets[0]) : null;
        $delete = ($change['delete'] ?? false) === true;
        // A lost SSH response may be retried after the previous publish succeeded.
        $alreadyApplied = $delete ? $actual === null : $actual === ($change['hash'] ?? null);
        if ($actual !== ($change['expected'] ?? null) && !$alreadyApplied) throw new RuntimeException('Remote file changed separately; review before syncing: '.$path);
        if ($delete && in_array($path, ['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'deploy/hostinger/index.php'], true)) throw new RuntimeException('Refusing to remove a deployment entry point: '.$path);
        $stage = null;
        if (!$delete) {
            $contents = $zip->getFromName('files/'.$path);
            if ($contents === false || hash('sha256', $contents) !== ($change['hash'] ?? null)) throw new RuntimeException('Corrupt uploaded file: '.$path);
            $stage = $batch.'/stage-'.$index;
            if (file_put_contents($stage, $contents) === false) throw new RuntimeException('Cannot stage '.$path);
            if (str_ends_with($path, '.php') || $path === 'artisan') syncCommand([PHP_BINARY, '-l', $stage], $site.'/capstone');
        }
        foreach ($targets as $target) {
            $backup = $batch.'/original-'.count($prepared);
            $existed = is_file($target);
            if ($existed && !copy($target, $backup)) throw new RuntimeException('Cannot back up '.$path);
            $prepared[] = compact('target', 'backup', 'existed', 'delete', 'stage', 'path');
        }
    }
    $zip->close();
    file_put_contents($batch.'/manifest.json', json_encode($prepared, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $changed = [];
    try {
        foreach ($prepared as $item) {
            $target = $item['target'];
            if ($item['delete']) {
                if (is_file($target) && !unlink($target)) throw new RuntimeException('Cannot remove '.$item['path']);
            } else {
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) throw new RuntimeException('Cannot create destination folder.');
                // Keep temporary PHP/source files outside the public web root.
                $temporary = $batch.'/publish-'.bin2hex(random_bytes(4));
                if (!copy($item['stage'], $temporary)) throw new RuntimeException('Cannot prepare destination file.');
                chmod($temporary, 0644);
                if (!rename($temporary, $target)) throw new RuntimeException('Cannot publish '.$item['path']);
            }
            $changed[] = $item;
        }
        $refresh($site);
    } catch (Throwable $failure) {
        foreach (array_reverse($changed) as $item) {
            if ($item['existed']) {
                $restore = $batch.'/restore-'.bin2hex(random_bytes(4));
                if (!copy($item['backup'], $restore) || !chmod($restore, 0644) || !rename($restore, $item['target'])) throw new RuntimeException('Rollback failed; backups: '.$batch, 0, $failure);
            } elseif (is_file($item['target'])) {
                unlink($item['target']);
            }
        }
        try { $refresh($site); } catch (Throwable $restoreError) {
            throw new RuntimeException('Changes rolled back, but cache restoration failed: '.$restoreError->getMessage().'; original error: '.$failure->getMessage(), 0, $failure);
        }
        throw new RuntimeException('Changes rolled back: '.$failure->getMessage(), 0, $failure);
    }
    $warnings = [];
    foreach (array_keys($seen) as $path) {
        if (str_starts_with($path, 'database/migrations/')) $warnings[] = 'Migration file uploaded; review and run migrations separately.';
        if (in_array($path, ['composer.json', 'composer.lock'], true)) $warnings[] = 'Composer file uploaded; install locked dependencies separately.';
        if (in_array($path, ['package.json', 'package-lock.json', 'vite.config.js'], true) || preg_match('#^resources/(css|js)/#', $path)) $warnings[] = 'Vite source uploaded; build locally and sync public/build when needed.';
    }
    return ['ok' => true, 'count' => count($seen), 'backup' => $batch, 'warnings' => array_values(array_unique($warnings))];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    $syncRoot = getenv('HOME').'/tmta-sync';
    $site = getenv('HOME').'/domains/tmta-system.io';
    $bundle = null;
    $exitCode = 0;
    try {
        $lock = fopen($syncRoot.'/receiver.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) throw new RuntimeException('Another deployment is in progress.');
        if (($argv[1] ?? '') === 'snapshot') {
            $result = ['ok' => true, 'files' => syncSnapshot($site)];
        } elseif (($argv[1] ?? '') === 'apply' && preg_match('/^[a-f0-9]{32}\.zip$/', $argv[2] ?? '')) {
            $bundle = $syncRoot.'/'.$argv[2];
            $result = syncApply($site, $syncRoot, $bundle, 'syncCaches');
        } else {
            throw new RuntimeException('Unknown receiver command.');
        }
        echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable $error) {
        echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
        $exitCode = 1;
    } finally {
        if ($bundle && is_file($bundle)) unlink($bundle);
    }
    exit($exitCode);
}
