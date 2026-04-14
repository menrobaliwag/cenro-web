param(
  [string]$PhpExe = "C:\xampp\php\php.exe",
  [string]$TaskName = "CityEnroAutoDbBackup",
  [string]$StartTime = "02:00"
)

$ErrorActionPreference = "Stop"

$scriptPath = Join-Path $PSScriptRoot "auto_db_backup.php"
if (-not (Test-Path $scriptPath)) {
  throw "Backup script not found: $scriptPath"
}

$taskCommand = '"' + $PhpExe + '" "' + $scriptPath + '"'

Write-Host "Registering task: $TaskName"
Write-Host "Command: $taskCommand"

schtasks /Create /TN $TaskName /SC DAILY /ST $StartTime /TR $taskCommand /F | Out-Null

Write-Host "Done. Daily backup task created at $StartTime."
Write-Host "To test now, run:"
Write-Host ('  "' + $PhpExe + '" "' + $scriptPath + '" --dry-run')

