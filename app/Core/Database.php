<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
use PDOException;
final class Database {
    private static ?PDO $pdo=null;
    public static function connection(): PDO {
        if(self::$pdo instanceof PDO)return self::$pdo;
        $server=(string)env('DB_HOST','127.0.0.1');
        $port=(string)env('DB_PORT','1433');
        $database=(string)env('DB_DATABASE','pricing');
        $encrypt=env('DB_ENCRYPT', true);
        $trustServerCertificate=env('DB_TRUST_SERVER_CERTIFICATE', true);
        $dsn='sqlsrv:Server='.$server.','.$port.';Database='.$database
            .';Encrypt='.($encrypt ? 'yes' : 'no')
            .';TrustServerCertificate='.($trustServerCertificate ? 'yes' : 'no');
        try{
            self::$pdo=new PDO(
                $dsn,
                (string)env('DB_USERNAME',''),
                (string)env('DB_PASSWORD',''),
                [
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES=>false,
                ]
            );
        }catch(PDOException $e){
            error_log($e->getMessage());
            throw new PDOException('Database connection failed.');
        }
        return self::$pdo;
    }
    public static function transaction(callable $callback):mixed{
        $pdo=self::connection();$pdo->beginTransaction();
        try{$result=$callback($pdo);$pdo->commit();return $result;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
