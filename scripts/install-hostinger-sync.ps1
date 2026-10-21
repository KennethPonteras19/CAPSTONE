#Requires -Version 7.0
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$scriptPath = Join-Path $PSScriptRoot 'hostinger-sync.ps1'
$launcherPath = Join-Path $PSScriptRoot 'hostinger-sync-hidden.vbs'
$powershellPath = (Get-Command pwsh.exe).Source
$scriptHostPath = Join-Path $env:WINDIR 'System32/wscript.exe'
$account = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$taskName = 'TMTA Hostinger Auto Sync'
# Launch through the GUI script host so Windows Terminal never opens a console.
# The launcher waits for the watcher, preserving IgnoreNew and failure recovery.
$action = New-ScheduledTaskAction -Execute $scriptHostPath -Argument ('//B //NoLogo "' + $launcherPath + '" "' + $powershellPath + '" "' + $scriptPath + '"') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $account
# IgnoreNew keeps a healthy watcher running. This repeating trigger also
# restarts it after an interruption, without waiting for the next sign-in.
$recoveryTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId $account -LogonType Interactive -RunLevel Limited
$options = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger @($trigger, $recoveryTrigger) -Principal $principal -Settings $options -Description ('Sync saved TMTA project code to Hostinger from ' + $projectRoot + '. Excludes secrets, database data, and uploads.') -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Output 'Automatic Hostinger sync enabled now and at this Windows account sign-in.'
