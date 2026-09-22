# Backup policy

Run from the project root:

`D:\\xampp\\php\\windowsXamppPhp\\php.exe database\\backup.php`

Set `DB_BACKUP_PATH` in `.env` to a directory writable by the SQL Server service account. The SQL Server service account must also have permission to write the backup file.

The script uses SQL Server `BACKUP DATABASE ... WITH INIT, CHECKSUM`. Verify the `.bak` file and periodically perform a restore test in a non-production database.