param([string]$SqlCmdPath="sqlcmd",[string]$Server="192.168.1.76,1433",[string]$Database="pricing",[string]$BackupFile="pricing_backup.bak")
& $SqlCmdPath -S $Server -d master -E -Q "BACKUP DATABASE [$Database] TO DISK=N'$BackupFile' WITH INIT, COMPRESSION, CHECKSUM"
if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}