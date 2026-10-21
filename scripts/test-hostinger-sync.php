<?php

declare(strict_types=1);
require __DIR__.'/../deploy/hostinger/sync-receiver.php';

$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('FAILED: '.$label);
}
function rejects(callable $action, string $contains): void {
    try { $action(); } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $contains), 'Expected rejection: '.$contains);
        return;
    }
    throw new RuntimeException('Expected rejection: '.$contains);
}
foreach (['.env','config/.env','public/.env.backup','storage/app/public/product.png','public/storage/product.png','bootstrap/cache/config.php','public/hot','../app/a.php','app/../../.env','app\\a.php','vendor/a.php','app/test.php~'] as $path) {
    check(!syncAllowed($path), 'Exclude '.$path);
}
foreach (['app/Http/Controllers/Test.php','config/modules.php','resources/views/test.blade.php','routes/web.php','public/css/test.css','public/.htaccess','database/migrations/test.php','deploy/hostinger/index.php'] as $path) {
    check(syncAllowed($path), 'Allow '.$path);
}
$sandbox = __DIR__.'/../storage/app/hostinger-sync/tests/'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($sandbox.'/site/capstone/database/migrations', 0755, true);
mkdir($sandbox.'/site/capstone/public/css', 0755, true);
mkdir($sandbox.'/site/capstone/app', 0755, true);
mkdir($sandbox.'/site/public_html/css', 0755, true);
mkdir($sandbox.'/receiver', 0700, true);
$site = $sandbox.'/site';
$syncRoot = $sandbox.'/receiver';
file_put_contents($site.'/capstone/.env', 'PRODUCTION_SECRET=untouched');
file_put_contents($site.'/public_html/index.php', 'production-entry');
file_put_contents($site.'/capstone/public/css/test.css', 'old');
file_put_contents($site.'/public_html/css/test.css', 'old');
file_put_contents($site.'/capstone/database/migrations/test.php', '<?php // migration');
$snapshot = syncSnapshot($site);
check(!isset($snapshot['.env']), 'Snapshot excludes environment');
check(isset($snapshot['database/migrations/test.php']), 'Snapshot includes migrations');
check($snapshot['deploy/hostinger/index.php'] === hash('sha256', 'production-entry'), 'Hosted entry mapping');
check(count(syncTargets($site, 'public/css/test.css')) === 2, 'Assets have two destinations');
check(count(syncTargets($site, 'public/index.php')) === 1, 'Local index cannot overwrite hosted entry');
function bundle(array $changes, array $files): string {
    global $sandbox;
    $path = $sandbox.'/'.bin2hex(random_bytes(5)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('manifest.json', json_encode(['changes' => $changes], JSON_THROW_ON_ERROR));
    foreach ($files as $name => $value) $zip->addFromString('files/'.$name, $value);
    $zip->close();
    return $path;
}
$asset = 'public/css/test.css';
$change = ['path' => $asset, 'expected' => hash('sha256', 'old'), 'hash' => hash('sha256', 'new'), 'delete' => false];
$archive = bundle([$change], [$asset => 'new']);
$result = syncApply($site, $syncRoot, $archive, fn () => null);
check($result['ok'], 'Apply successful batch');
check(file_get_contents($site.'/public_html/css/test.css') === 'new', 'Public asset updated');
check(file_get_contents($site.'/capstone/public/css/test.css') === 'new', 'Private asset copy updated');
syncApply($site, $syncRoot, $archive, fn () => null);
check(file_get_contents($site.'/public_html/css/test.css') === 'new', 'Successful deployment retry is idempotent');
$bad = ['path' => 'app/Bad.php', 'expected' => null, 'hash' => hash('sha256', '<?php invalid syntax !'), 'delete' => false];
rejects(fn () => syncApply($site, $syncRoot, bundle([$bad], ['app/Bad.php' => '<?php invalid syntax !']), fn () => null), 'Parse error');
check(!file_exists($site.'/capstone/app/Bad.php'), 'Invalid PHP never published');
$change['expected'] = hash('sha256', 'new');
$change['hash'] = hash('sha256', 'broken');
rejects(fn () => syncApply($site, $syncRoot, bundle([$change], [$asset => 'broken']), function () { throw new RuntimeException('simulated cache failure'); }), 'Changes rolled back');
check(file_get_contents($site.'/public_html/css/test.css') === 'new', 'Cache failure restores public asset');
check(file_get_contents($site.'/capstone/public/css/test.css') === 'new', 'Cache failure restores private asset');
$change['expected'] = hash('sha256', 'wrong');
rejects(fn () => syncApply($site, $syncRoot, bundle([$change], [$asset => 'broken']), fn () => null), 'Remote file changed separately');
rejects(fn () => syncApply($site, $syncRoot, bundle([['path' => '.env', 'delete' => true]], []), fn () => null), 'Excluded or invalid path');
$remove = ['path' => $asset, 'expected' => hash('sha256', 'new'), 'delete' => true];
syncApply($site, $syncRoot, bundle([$remove], []), fn () => null);
check(!is_file($site.'/public_html/css/test.css') && !is_file($site.'/capstone/public/css/test.css'), 'Tracked deletions applied to both copies');
check(file_get_contents($site.'/capstone/.env') === 'PRODUCTION_SECRET=untouched', 'Production credentials preserved');
check(file_get_contents($site.'/public_html/index.php') === 'production-entry', 'Deployment entry preserved');
echo "Passed $checks deployment-sync checks.\n";
