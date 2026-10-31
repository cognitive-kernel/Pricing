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

    public function allRows(): array
    {
        $queryFile = BASE_PATH . '/database/queries/stock_with_latest_prices.sql';
        $sql = (string) file_get_contents($queryFile);

        $plant = (int) ($this->config['plant_ref'] ?? env('RAHKARAN_PLANT_REF', 2));
        $ledger = (int) ($this->config['ledger_ref'] ?? env('RAHKARAN_LEDGER_REF', 1));
        $fiscalYear = (int) ($this->config['fiscal_year_ref'] ?? env('RAHKARAN_FISCAL_YEAR_REF', 9));

        $sql = preg_replace('/DECLARE\s+@PlantRef\s+BIGINT\s*=\s*\d+\s*;/i', 'DECLARE @PlantRef BIGINT = ' . $plant . ';', $sql, 1);
        $sql = preg_replace('/DECLARE\s+@LedgerRef\s+BIGINT\s*=\s*\d+\s*;/i', 'DECLARE @LedgerRef BIGINT = ' . $ledger . ';', $sql, 1);
        $sql = preg_replace('/DECLARE\s+@FiscalYearRef\s+BIGINT\s*=\s*\d+\s*;/i', 'DECLARE @FiscalYearRef BIGINT = ' . $fiscalYear . ';', $sql, 1);

        return $this->pdo->query($sql)->fetchAll();
    }

    public function rowsByCodes(array $codes): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $codes
        ), static fn(string $code): bool => $code !== '')));

        if (!$wanted) {
            return [];
        }

        $queryFile = BASE_PATH . '/database/queries/stock_with_latest_prices.sql';
        $sql = (string) file_get_contents($queryFile);

        $plant = (int) ($this->config['plant_ref'] ?? env('RAHKARAN_PLANT_REF', 2));
        $ledger = (int) ($this->config['ledger_ref'] ?? env('RAHKARAN_LEDGER_REF', 1));
        $fiscalYear = (int) ($this->config['fiscal_year_ref'] ?? env('RAHKARAN_FISCAL_YEAR_REF', 9));

        $sql = preg_replace('/DECLARE\\s+@PlantRef\\s+BIGINT\\s*=\\s*\\d+\\s*;/i', 'DECLARE @PlantRef BIGINT = ' . $plant . ';', $sql, 1);
        $sql = preg_replace('/DECLARE\\s+@LedgerRef\\s+BIGINT\\s*=\\s*\\d+\\s*;/i', 'DECLARE @LedgerRef BIGINT = ' . $ledger . ';', $sql, 1);
        $sql = preg_replace('/DECLARE\\s+@FiscalYearRef\\s+BIGINT\\s*=\\s*\\d+\\s*;/i', 'DECLARE @FiscalYearRef BIGINT = ' . $fiscalYear . ';', $sql, 1);

        $orderBy = 'ORDER BY s.PartID DESC;';
        $position = strripos($sql, $orderBy);
        if ($position === false) {
            throw new \RuntimeException('Unable to apply product filter to Rahkaran price query.');
        }

        $filter = 'WHERE s.Code IN (' . implode(',', array_fill(0, count($wanted), '?')) . ')' . PHP_EOL;
        $sql = substr_replace($sql, $filter, $position, 0);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($wanted);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $code = trim((string) ($row['Code'] ?? ''));
            if ($code !== '') {
                $rows[$code] = $row;
            }
        }

        return $rows;
    }

    public function unitIdByCode(string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $s = $this->pdo->prepare(
            'SELECT TOP 1 p.UnitRef
             FROM SLS3.Product AS p
             WHERE p.Number = :code'
        );
        $s->execute(['code' => $code]);
        $value = $s->fetchColumn();

        return ($value !== false && $value !== null && (int) $value > 0)
            ? (int) $value
            : null;
    }


    public function unitNamesByIds(array $ids): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            static fn($id): int => (int) $id,
            $ids
        ), static fn(int $id): bool => $id > 0)));

        if (!$wanted) {
            return [];
        }

        $in = implode(',', array_fill(0, count($wanted), '?'));
        $s = $this->pdo->prepare(
            'SELECT UnitID, Name FROM gnr3.Unit WHERE UnitID IN (' . $in . ')'
        );
        $s->execute($wanted);
        $result = [];
        foreach ($s->fetchAll() as $row) {
            $result[(int) $row['UnitID']] = (string) $row['Name'];
        }
        return $result;
    }

    public function paymentAgreements(): array
    {
        $sql = 'SELECT [PaymentAgreementID], [Number], [Title]
                FROM [Rahkaran_sg31old].[SLS3].[PaymentAgreement]
                ORDER BY [Title], [Number]';

        return $this->pdo->query($sql)->fetchAll();
    }

    public function paymentAgreementTitlesByIds(array $ids): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            static fn($id): int => (int) $id,
            $ids
        ), static fn(int $id): bool => $id > 0)));

        if (!$wanted) {
            return [];
        }

        $in = implode(',', array_fill(0, count($wanted), '?'));
        $s = $this->pdo->prepare(
            'SELECT [PaymentAgreementID], [Title]
             FROM [Rahkaran_sg31old].[SLS3].[PaymentAgreement]
             WHERE [PaymentAgreementID] IN (' . $in . ')'
        );
        $s->execute($wanted);

        $result = [];
        foreach ($s->fetchAll() as $row) {
            $result[(int) $row['PaymentAgreementID']] = (string) $row['Title'];
        }

        return $result;
    }


    public function latestByCodes(array $codes): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $codes
        ), static fn(string $code): bool => $code !== '')));

        if (!$wanted) {
            return [];
        }

        $result = [];
        foreach ($this->allRows() as $row) {
            $code = trim((string) ($row['Code'] ?? ''));
            if ($code !== '' && in_array($code, $wanted, true)) {
                $result[$code] = $row;
            }
        }
        return $result;
    }

    public function expertsByCodes(array $codes): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $codes
        ), static fn(string $code): bool => $code !== '')));

        if (!$wanted) {
            return [];
        }

        $in = implode(',', array_fill(0, count($wanted), '?'));
        $sql = '
            ;WITH PurchaseGroupPath AS
            (
                SELECT
                    pg.MemberID AS PurchaseItemID,
                    eg.EntityGroupID AS DirectGroupID,
                    eg.Code AS DirectGroupCode,
                    eg.Name AS DirectGroupName,
                    eg.EntityGroupID AS CurrentGroupID,
                    eg.Code AS CurrentGroupCode,
                    eg.Name AS CurrentGroupName,
                    eg.ParentRef
                FROM PRC3.PurchaseItemGroupMembers AS pg
                INNER JOIN GNR3.EntityGroup AS eg
                    ON eg.EntityGroupID = pg.GroupRef
                   AND eg.EntityGroupingRef = pg.GroupingRef
                WHERE pg.GroupingRef = 1897
                  AND pg.MemberID IN (
                      SELECT PurchaseItemID
                      FROM PRC3.PurchaseItem
                      WHERE Code IN (' . $in . ')
                  )

                UNION ALL

                SELECT
                    path.PurchaseItemID,
                    path.DirectGroupID,
                    path.DirectGroupCode,
                    path.DirectGroupName,
                    parent.EntityGroupID,
                    parent.Code,
                    parent.Name,
                    parent.ParentRef
                FROM PurchaseGroupPath AS path
                INNER JOIN GNR3.EntityGroup AS parent
                    ON parent.EntityGroupID = path.ParentRef
                   AND parent.EntityGroupingRef = 1897
                WHERE path.CurrentGroupID NOT IN (953, 954, 955, 1761)
            )
            SELECT DISTINCT
                pi.Code AS code,
                path.CurrentGroupName AS expert_name
            FROM PurchaseGroupPath AS path
            INNER JOIN PRC3.PurchaseItem AS pi
                ON pi.PurchaseItemID = path.PurchaseItemID
            WHERE path.CurrentGroupID IN (953, 954, 955, 1761)
            OPTION (MAXRECURSION 32767)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($wanted);

        $experts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['expert_name'] ?? ''));
            if ($code !== '' && $name !== '' && !in_array($name, $experts[$code] ?? [], true)) {
                $experts[$code][] = $name;
            }
        }

        foreach ($experts as $code => $names) {
            $experts[$code] = implode('، ', $names);
        }

        return $experts;
    }

    public function partGroupMembers(string $groupRef): array
    {
        $groupRef = trim($groupRef);
        if ($groupRef === '') {
            return [];
        }

        $s = $this->pdo->prepare(
            'SELECT p.code, p.name
             FROM [LGS3].[PartGroupMember] AS pgm
             LEFT JOIN [LGS3].[Part] AS p ON p.PartID = pgm.MemberID
             WHERE pgm.GroupRef = :groupRef
             ORDER BY p.code'
        );
        $s->execute(['groupRef' => $groupRef]);

        return $s->fetchAll();
    }
}
