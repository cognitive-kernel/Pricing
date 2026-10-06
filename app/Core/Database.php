<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
use PDOException;

final class Database {
    private static ?PDO $pdo = null;

    public static function connection(): PDO {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $configFile = BASE_PATH . '/config/database.php';
        if (!is_file($configFile)) {
            throw new PDOException('Database configuration file not found.');
        }

        $config = require $configFile;
        $server = (string)($config['server'] ?? '');
        $database = (string)($config['database'] ?? '');
        $uid = (string)($config['uid'] ?? '');
        $pwd = (string)($config['pwd'] ?? '');
        $trustServerCertificate = (bool)($config['trust_server_certificate'] ?? true);

        if ($server === '' || $database === '' || $uid === '') {
            throw new PDOException('Database configuration is incomplete.');
        }

        $dsn = 'sqlsrv:Server=' . $server . ';Database=' . $database
            . ';TrustServerCertificate=' . ($trustServerCertificate ? 'yes' : 'no');

        try {
            self::$pdo = new PDO(
                $dsn,
                $uid,
                $pwd,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new PDOException('Database connection failed.', 0, $e);
        }

        return self::$pdo;
    }

    public static function transaction(callable $callback): mixed {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
