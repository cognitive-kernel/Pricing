<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

final class RahkaranPriceService
{
    private static ?self $instance = null;
    private PDO $pdo;
    private array $config;
    private ?array $cache = null;

    private function __construct()
    {
        $configFile = BASE_PATH . '/config/rahkaran.php';
        $this->config = is_file($configFile) ? (array) require $configFile : [
            'server' => env('RAHKARAN_SERVER', '192.168.1.100'),
            'database' => env('RAHKARAN_DATABASE', 'Rahkaran_sg3'),
            'uid' => env('RAHKARAN_UID', ''),
            'pwd' => env('RAHKARAN_PWD', ''),
            'charset' => env('RAHKARAN_CHARSET', 'UTF-8'),
            'trust_server_certificate' => (bool) env('RAHKARAN_TRUST_SERVER_CERTIFICATE', true),
        ];

        $server = (string) ($this->config['server'] ?? '');
        $database = (string) ($this->config['database'] ?? '');
        $dsn = 'sqlsrv:Server=' . $server . ';Database=' . $database;
        if (!empty($this->config['charset'])) {
            $dsn .= ';CharacterSet=' . $this->config['charset'];
        }
        if (array_key_exists('trust_server_certificate', $this->config)) {
            $dsn .= ';TrustServerCertificate=' . ((bool) $this->config['trust_server_certificate'] ? '1' : '0');
        }

        try {
            $this->pdo = new PDO(
                $dsn,
                (string) ($this->config['uid'] ?? ''),
                (string) ($this->config['pwd'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Rahkaran connection failed: ' . $e->getMessage());
            throw new PDOException('Rahkaran database connection failed.');
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function latestByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $codes
        ), static fn(string $code): bool => $code !== '')));

        if (!$codes) {
            return [];
        }

        if ($this->cache === null) {
            $queryFile = BASE_PATH . '/database/queries/stock_with_latest_prices.sql';
            $sql = (string) file_get_contents($queryFile);

            $plant = (int) ($this->config['plant_ref'] ?? env('RAHKARAN_PLANT_REF', 2));
            $ledger = (int) ($this->config['ledger_ref'] ?? env('RAHKARAN_LEDGER_REF', 1));
            $fiscalYear = (int) ($this->config['fiscal_year_ref'] ?? env('RAHKARAN_FISCAL_YEAR_REF', 9));

            $sql = preg_replace('/DECLARE\s+@PlantRef\s+BIGINT\s*=\s*\d+\s*;/i', 'DECLARE @PlantRef BIGINT = ' . $plant . ';', $sql, 1);
            $sql = preg_replace('/DECLARE\s+@LedgerRef\s+BIGINT\s*=\s*\d+\s*;/i', 'DECLARE @LedgerRef BIGINT = ' . $ledger . ';', $sql, 1);
            $sql = preg_replace('/DECLARE\s+@FiscalYearRef\s+BIGINT\s*=\s*\d+\s*;/i', 'DECLARE @FiscalYearRef BIGINT = ' . $fiscalYear . ';', $sql, 1);

            $quotedCodes = implode(',', array_map(
                static fn(string $code): string => "'" . str_replace("'", "''", $code) . "'",
                $codes
            ));
            $sql = preg_replace(
                '/ORDER BY\s+s\.PartID\s+DESC\s*;?\s*$/i',
                'AND s.Code IN (' . $quotedCodes . ') ORDER BY s.PartID DESC;',
                $sql,
                1
            );

            $this->cache = [];
            foreach ($this->pdo->query($sql)->fetchAll() as $row) {
                $code = trim((string) ($row['Code'] ?? ''));
                if ($code !== '') {
                    $this->cache[$code] = $row;
                }
            }
        }

        $result = [];
        foreach ($codes as $code) {
            if (isset($this->cache[$code])) {
                $result[$code] = $this->cache[$code];
            }
        }
        return $result;
    }
}
