<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.".PHP_EOL);}
$path=(string)env('DB_BACKUP_PATH',dirname(__DIR__).'/storage/backups');
if(!is_dir($path) && !mkdir($path,0775,true) && !is_dir($path)) throw new RuntimeException('Backup directory cannot be created.');
$file=rtrim($path,"\\/").DIRECTORY_SEPARATOR.'pricing_'.date('Ymd_His').'.bak';
$db=(string)env('DB_DATABASE','pricing');
$s=Database::connection()->prepare("BACKUP DATABASE [".str_replace(']',']]',$db)."] TO DISK=:file WITH INIT, CHECKSUM, STATS=10");
$s->execute(['file'=>$file]);
echo "Backup completed: ".$file.PHP_EOL;