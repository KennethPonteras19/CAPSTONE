#Requires -Version 7.0
[CmdletBinding()]
param([switch]$Watch, [switch]$Once, [switch]$Status, [switch]$Stop)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$settings = Get-Content -LiteralPath (Join-Path $projectRoot 'deploy/hostinger/sync.json') -Raw | ConvertFrom-Json
$stateRoot = Join-Path $projectRoot 'storage/app/hostinger-sync'
New-Item -ItemType Directory -Force -Path $stateRoot | Out-Null
$statePath = Join-Path $stateRoot 'state.json'
$logPath = Join-Path $stateRoot 'sync.log'
$stopPath = Join-Path $stateRoot 'stop'
$lockPath = Join-Path $stateRoot 'watcher.lock'
$statusPath = Join-Path $stateRoot 'status.json'
$taskName = 'TMTA Hostinger Auto Sync'

function Write-SyncLog([string]$message) {
    $line = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $message
    if ((Test-Path -LiteralPath $logPath) -and (Get-Item -LiteralPath $logPath).Length -gt 2MB) {
        Move-Item -LiteralPath $logPath -Destination ($logPath + '.previous') -Force
    }
    Add-Content -LiteralPath $logPath -Value $line
    Write-Host $line
}

function Set-SyncStatus([string]$phase, [string]$message) {
    @{ phase = $phase; message = $message; pid = $PID; updated = (Get-Date).ToString('o') } |
        ConvertTo-Json | Set-Content -LiteralPath $statusPath
}

if ($Status) {
    if (Test-Path -LiteralPath $statusPath) {
        $reportedStatus = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        # A terminated process cannot update its last heartbeat. Check the lock
        # rather than trusting an old "watching" message or a reused process ID.
        $watcherRunning = $false
        try {
            $statusLock = [IO.File]::Open($lockPath, 'OpenOrCreate', 'ReadWrite', 'None')
            $statusLock.Dispose()
        } catch [IO.IOException] {
            if (($_.Exception.HResult -band 0xffff) -in @(32, 33)) { $watcherRunning = $true }
            else { throw }
        }
        if (!$watcherRunning -and $reportedStatus.phase -in @('watching', 'uploading', 'error')) {
            $reportedStatus.phase = 'stopped'
            $reportedStatus.message = 'No sync process is running. The timestamp is the last recorded update. Use Hostinger: Start automatic sync to resume.'
        }
        $reportedStatus | ConvertTo-Json
    }
    else { Write-Host 'The watcher has not started.' }
    if (Test-Path -LiteralPath $logPath) { Get-Content -LiteralPath $logPath -Tail 12 }
    exit
}
if ($Stop) {
    Set-Content -LiteralPath $stopPath -Value 'stop'
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    if ($task) { Disable-ScheduledTask -TaskName $taskName | Out-Null }
    Write-Host 'Stop requested; automatic start at sign-in disabled.'
    exit
}
if (!$Watch -and !$Once) { throw 'Choose -Watch, -Once, -Status, or -Stop.' }
try { $processLock = [IO.File]::Open($lockPath, 'OpenOrCreate', 'ReadWrite', 'None') }
catch { Write-Host 'The Hostinger sync watcher is already running.'; exit }

function Invoke-Native([string]$program, [string[]]$arguments, [int]$timeoutSeconds = 180) {
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $program
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    foreach ($argument in $arguments) { $start.ArgumentList.Add($argument) }
    $process = [Diagnostics.Process]::Start($start)
    $stdout = $process.StandardOutput.ReadToEndAsync()
    $stderr = $process.StandardError.ReadToEndAsync()
    if (!$process.WaitForExit($timeoutSeconds * 1000)) {
        $process.Kill($true)
        throw ($program + ' timed out; pending changes will be retried.')
    }
    $output = $stdout.GetAwaiter().GetResult()
    $errorText = $stderr.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) { throw ($program + ': ' + $errorText + $output) }
    return $output
}

$sshOptions = @('-i', $settings.privateKey, '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3')
$destination = $settings.username + '@' + $settings.host
function Invoke-Receiver([string]$operation) {
    $raw = Invoke-Native 'ssh.exe' ($sshOptions + @('-p', [string]$settings.port, $destination, ('php ' + $settings.remoteDirectory + '/receiver.php ' + $operation)))
    $response = $raw | ConvertFrom-Json -AsHashtable
    if (!$response.ok) { throw $response.error }
    return $response
}

function Test-SyncPath([string]$path) {
    if ($path -match '(^|/)\.env' -or $path -match '(^|/)\.\.?(/|$)' -or $path -match '[\x00-\x1f\\]') { return $false }
    if ($path -match '^(bootstrap/cache|public/storage)(/|$)' -or $path -eq 'public/hot') { return $false }
    if ($path -match '(~|\.(tmp|swp|bak))$') { return $false }
    return ($path -match '^(app|bootstrap|config|resources|routes|public|docs)/' -or $path -match '^database/(migrations|factories|seeders)/' -or $path -in @('artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','deploy/hostinger/index.php'))
}

$fingerprintCache = @{}
function Get-LocalFiles {
    $files = @{}
    $folders = @('app','bootstrap','config','resources','routes','public','docs','database/migrations','database/factories','database/seeders')
    $singleFiles = @('artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','deploy/hostinger/index.php')
    $items = @($folders | ForEach-Object {
        $folder = Join-Path $projectRoot $_
        if (Test-Path -LiteralPath $folder) { Get-ChildItem -LiteralPath $folder -File -Recurse -Force }
    }) + @($singleFiles | ForEach-Object {
        $file = Join-Path $projectRoot $_
        if (Test-Path -LiteralPath $file) { Get-Item -LiteralPath $file }
    })
    foreach ($file in $items) {
        if ($file.Attributes -band [IO.FileAttributes]::ReparsePoint) { continue }
        $relative = $file.FullName.Substring($projectRoot.Length + 1).Replace('\','/')
        if (!(Test-SyncPath $relative)) { continue }
        $stamp = [string]$file.LastWriteTimeUtc.Ticks + ':' + $file.Length
        if (!$fingerprintCache.ContainsKey($relative) -or $fingerprintCache[$relative].stamp -ne $stamp) {
            $hash = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
            $fingerprintCache[$relative] = @{ stamp = $stamp; hash = $hash }
        }
        $files[$relative] = $fingerprintCache[$relative].hash
    }
    if ($files.Count -lt 10) { throw 'Project files are unavailable; refusing to sync removals.' }
    return $files
}

function Save-SyncState {
    $state | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath ($statePath + '.tmp')
    Move-Item -LiteralPath ($statePath + '.tmp') -Destination $statePath -Force
}

try {
    if (Test-Path -LiteralPath $stopPath) { Remove-Item -LiteralPath $stopPath }
    Write-SyncLog 'Starting saved-file sync to tmta-system.io.'
    if (!(Test-Path -LiteralPath $settings.privateKey)) { throw 'Deployment SSH key is missing.' }
    Add-Type -AssemblyName System.IO.Compression
    $state = if (Test-Path -LiteralPath $statePath) { Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json -AsHashtable } else { $null }
    while ($true) {
        if (Test-Path -LiteralPath $stopPath) { break }
        try {
            $local = Get-LocalFiles
            if (!$state) {
                $remote = (Invoke-Receiver 'snapshot').files
                $state = @{ version = 1; files = @{} }
                foreach ($path in $local.Keys) {
                    if ($remote.ContainsKey($path)) { $state.files[$path] = $remote[$path] }
                }
                Save-SyncState
            }
            $changes = @()
            foreach ($path in ($local.Keys | Sort-Object)) {
                if (!$state.files.ContainsKey($path) -or $state.files[$path] -ne $local[$path]) {
                    $changes += @{ path = $path; expected = $state.files[$path]; hash = $local[$path]; delete = $false }
                }
            }
            foreach ($path in $state.files.Keys) {
                if (!$local.ContainsKey($path)) { $changes += @{ path = $path; expected = $state.files[$path]; delete = $true } }
            }
            if ($changes.Count -gt 0) {
                # Let editor atomic-save operations and related file saves settle.
                Start-Sleep -Seconds $settings.debounceSeconds
                $settled = Get-LocalFiles
                $stale = @($changes | Where-Object { (!$_.delete -and $settled[$_.path] -ne $_.hash) -or ($_.delete -and $settled.ContainsKey($_.path)) })
                if ($stale.Count -gt 0) { continue }
                if (@($changes | Where-Object delete).Count -gt 25) { throw 'More than 25 files were removed; review the local folder before syncing.' }
                Set-SyncStatus 'uploading' ('Publishing ' + $changes.Count + ' saved file(s).')
                $bundleName = [guid]::NewGuid().ToString('N') + '.zip'
                $bundlePath = Join-Path $stateRoot $bundleName
                $zip = [IO.Compression.ZipFile]::Open($bundlePath, 'Create')
                try {
                    $entry = $zip.CreateEntry('manifest.json')
                    $writer = [IO.StreamWriter]::new($entry.Open(), [Text.UTF8Encoding]::new($false))
                    $writer.Write((@{changes = $changes} | ConvertTo-Json -Depth 6 -Compress))
                    $writer.Dispose()
                    foreach ($change in $changes) {
                        if (!$change.delete) {
                            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, (Join-Path $projectRoot $change.path), ('files/' + $change.path), 'Optimal') | Out-Null
                        }
                    }
                } finally { $zip.Dispose() }
                try {
                    Invoke-Native 'scp.exe' ($sshOptions + @('-P', [string]$settings.port, $bundlePath, ($destination + ':' + $settings.remoteDirectory + '/' + $bundleName))) | Out-Null
                    $result = Invoke-Receiver ('apply ' + $bundleName)
                    foreach ($change in $changes) {
                        if ($change.delete) { $state.files.Remove($change.path) }
                        else { $state.files[$change.path] = $change.hash }
                    }
                    Save-SyncState
                    Write-SyncLog ('Synced ' + $changes.Count + ' file(s): ' + (($changes | ForEach-Object path) -join ', '))
                    foreach ($warning in $result.warnings) { Write-SyncLog ('ACTION NEEDED: ' + $warning) }
                } finally {
                    if (Test-Path -LiteralPath $bundlePath) { Remove-Item -LiteralPath $bundlePath }
                }
            }
            Set-SyncStatus 'watching' 'Saved code is in sync. Waiting for changes.'
            if ($Once) { break }
            Start-Sleep -Seconds $settings.pollSeconds
        } catch {
            $message = $_.Exception.Message
            Write-SyncLog ('ERROR: ' + $message)
            Set-SyncStatus 'error' $message
            if ($Once) { throw }
            Start-Sleep -Seconds 15
        }
    }
} finally {
    $processLock.Dispose()
    if ($Watch) { Set-SyncStatus 'stopped' 'Automatic sync stopped.' }
}
