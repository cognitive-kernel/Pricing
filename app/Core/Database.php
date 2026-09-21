<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
use PDOException;
final class Database {
    private static ?PDO $pdo=null;
    public static function connection(): PDO {
        if(self::$pdo instanceof PDO)return self::$pdo;
        $dsn='sqlsrv:Server='.env('DB_HOST','127.0.0.1').','.env('DB_PORT','1433').';Database='.env('DB_DATABASE','pricing');
        try{self::$pdo=new PDO($dsn,(string)env('DB_USERNAME',''),(string)env('DB_PASSWORD',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
        catch(PDOException $e){error_log($e->getMessage());throw new PDOException('Database connection failed.');}
        return self::$pdo;
    }
    public static function transaction(callable $callback):mixed{
        $pdo=self::connection();$pdo->beginTransaction();
        try{$result=$callback($pdo);$pdo->commit();return $result;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
