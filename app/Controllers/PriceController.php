<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\RahkaranPriceService;
use App\Support\Jalali;
use PDO;

final class PriceController
{
    private function latestPricesSql(): string
    {
        return "SELECT * FROM (
            SELECT
                m.*,
                m.ProductCode product_code,
                m.InquiryDate price_received_at,
                m.Source supplier,
                m.Price unit_price,
                m.UnitID unit_id,
                ROW_NUMBER() OVER (
                    PARTITION BY m.ProductCode
                    ORDER BY m.InquiryDate DESC, m.PriceMonitorID DESC
                ) source_no
            FROM ProductPriceMonitor m
        ) ranked_prices
        WHERE source_no=1";
    }

    private function rowStatus(?string $marketDate, ?array $rahkaran): array
    {
        $marketDays = null;

        if ($marketDate) {
            $marketDays = max(0, (int) ((time() - strtotime($marketDate)) / 86400));
            if ($marketDays < 7) {
                return ['status' => 'green', 'required_sources' => 0, 'market_days' => $marketDays];
            }
        }

        if (!$rahkaran) {
            return ['status' => 'neutral', 'required_sources' => 0, 'market_days' => $marketDays];
        }

        $rahkaranDays = max(0, (int) ($rahkaran['Announcement_diff'] ?? 999));

        if ($marketDate === null && $rahkaranDays < 7) {
            return ['status' => 'yellow', 'required_sources' => 1, 'market_days' => null];
        }

        if (
            $rahkaranDays >= 7
            && $rahkaranDays <= 15
            && ($marketDate === null || ($marketDays !== null && $marketDays >= 7 && $marketDays <= 15))
        ) {
            return ['status' => 'orange', 'required_sources' => 2, 'market_days' => $marketDays];
        }

        if (
            $rahkaranDays > 15
            && ($marketDate === null || ($marketDays !== null && $marketDays > 15))
        ) {
            return ['status' => 'red', 'required_sources' => 3, 'market_days' => $marketDays];
        }

        return ['status' => 'neutral', 'required_sources' => 0, 'market_days' => $marketDays];
    }

    private function currentRequiredSources(array $product, ?array $rahkaranRow = null): int
    {
        $pdo = Database::connection();
        $s = $pdo->prepare('SELECT TOP 1 InquiryDate FROM ProductPriceMonitor WHERE ProductCode=:code ORDER BY InquiryDate DESC,PriceMonitorID DESC');
        $s->execute(['code' => (string) $product['product_code']]);
        $marketDate = $s->fetchColumn();

        if ($rahkaranRow === null) {
            $rahkaran = RahkaranPriceService::instance()->latestByCodes([(string) $product['product_code']]);
            $rahkaranRow = $rahkaran[(string) $product['product_code']] ?? null;
        }

        $row = $this->rowStatus($marketDate ?: null, $rahkaranRow);
        return $row['required_sources'];
    }

    public function index(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');

        $q = trim((string) ($_GET['q'] ?? ''));
        $statusFilter = (string) ($_GET['status_filter'] ?? 'all');
        if (!in_array($statusFilter, ['all', 'green', 'yellow', 'orange', 'red'], true)) {
            $statusFilter = 'all';
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 100;

        try {
            $rahkaranRows = RahkaranPriceService::instance()->allRows();
        } catch (\Throwable $e) {
            error_log('Rahkaran lookup failed on prices page: ' . $e->getMessage());
            http_response_code(500);
            exit('دریافت اطلاعات راهکاران ناموفق بود.');
        }

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rahkaranRows = array_values(array_filter($rahkaranRows, static function (array $row) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($row['Code'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($row['Name'] ?? '')), $needle);
            }));
        }

        $pdo = Database::connection();

        $marketLatestByCode = [];
        $s = $pdo->query('SELECT ProductCode,MAX(InquiryDate) market_latest_date FROM ProductPriceMonitor GROUP BY ProductCode');
        foreach ($s->fetchAll() as $r) {
            $marketLatestByCode[(string) $r['ProductCode']] = $r['market_latest_date'];
        }

        if ($statusFilter !== 'all') {
            $rahkaranRows = array_values(array_filter($rahkaranRows, function (array $row) use ($marketLatestByCode, $statusFilter): bool {
                $code = trim((string) ($row['Code'] ?? ''));
                $state = $this->rowStatus($marketLatestByCode[$code] ?? null, $row);
                return $state['status'] === $statusFilter;
            }));
        }

        $total = count($rahkaranRows);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($rahkaranRows, $offset, $perPage);

        $codes = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['Code'] ?? '')),
            $pageRows
        ))));

        $prices = [];
        if ($codes) {
            $in = implode(',', array_fill(0, count($codes), '?'));
            $s = $pdo->prepare('SELECT m.*,m.InquiryDate price_received_at,m.Source supplier,m.Price unit_price,m.UnitID unit_id FROM ProductPriceMonitor m WHERE m.ProductCode IN (' . $in . ') ORDER BY m.ProductCode,m.InquiryDate DESC,m.PriceMonitorID DESC');
            $s->execute($codes);
            foreach ($s->fetchAll() as $r) {
                $prices[(string) $r['ProductCode']][] = $r;
            }
        }

        $products = [];
        foreach ($pageRows as $row) {
            $code = trim((string) ($row['Code'] ?? ''));
            $marketDate = $marketLatestByCode[$code] ?? null;
            $unitName = (string) ($row['Announcement_unit'] ?? '');

            // ProductPriceMonitor keeps UnitID. For a new code, resolve the
            // local UnitID by the Rahkaran unit name; products are not used.
            $unitId = 0;
            $u = $pdo->prepare('SELECT TOP 1 id FROM units WHERE is_active=1 AND name=:name');
            if ($unitName !== '') {
                $u->execute(['name' => $unitName]);
                $unitId = (int) ($u->fetchColumn() ?: 0);
            }

            $state = $this->rowStatus($marketDate, $row);
            $products[] = [
                'id' => 0,
                'product_code' => $code,
                'product_name' => (string) ($row['Name'] ?? ''),
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'registered_at' => null,
                'rahkaran' => $row,
                'market_latest_date' => $marketDate,
                'row_status' => $state['status'],
                'required_sources' => $state['required_sources'],
                'market_days' => $state['market_days'],
            ];
        }

        $pages = max(1, (int) ceil($total / $perPage));
        $data = compact('products', 'prices', 'q', 'statusFilter', 'page', 'pages', 'perPage', 'total');

        if (isset($_GET['ajax'])) {
            View::render('prices/table', $data);
            return;
        }

        View::render('prices/index', $data);
    }

    public function queue(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');
        $rows = Database::connection()->query("SELECT TOP 500 q.id,q.product_id,q.score,q.priority_level,q.status,q.assigned_to,q.due_at,q.started_at,q.completed_at,q.result_note,q.reason,p.product_code,p.product_name,u.name unit_name,p.importance FROM price_queue q JOIN products p ON p.id=q.product_id JOIN units u ON u.id=p.unit_id WHERE q.status IN('new','in_progress','needs_followup') ORDER BY q.score DESC,q.updated_at ASC,p.product_code ASC")->fetchAll();
        View::render('prices/queue', compact('rows'));
    }

    public function history(int $id): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.history.view');
        $pdo = Database::connection();
        $s = $pdo->prepare('SELECT p.*,u.name unit_name FROM products p JOIN units u ON u.id=p.unit_id WHERE p.id=:id');
        $s->execute(['id' => $id]);
        $product = $s->fetch();
        if (!$product) {
            http_response_code(404);
            exit('رکورد یافت نشد.');
        }
        $s = $pdo->prepare('SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,m.CreatedDate,u.name unit_name FROM ProductPriceMonitor m LEFT JOIN units u ON u.id=m.UnitID WHERE m.ProductCode=:code ORDER BY m.InquiryDate DESC,m.PriceMonitorID DESC');
        $s->execute(['code' => (string) $product['product_code']]);
        $history = $s->fetchAll();
        View::render('prices/history', compact('product', 'history'));
    }

    public function form(?int $id = null): void
    {
        Auth::requireLogin();
        $editing = $id !== null;
        PermissionService::require(Auth::userId(), $editing ? 'prices.edit' : 'prices.create');

        $pdo = Database::connection();
        $units = $pdo->query('SELECT id,name FROM units WHERE is_active=1 ORDER BY name')->fetchAll();
        $product = null;
        $prices = [];
        $requiredSources = 0;

        if ($editing) {
            $productCode = trim((string) ($_GET['product_code'] ?? ''));
            if ($productCode === '') {
                http_response_code(400);
                exit('کد کالا ارسال نشده است.');
            }

            try {
                $rahkaran = RahkaranPriceService::instance()->latestByCodes([$productCode]);
            } catch (\Throwable $e) {
                error_log('Rahkaran market form lookup failed: ' . $e->getMessage());
                http_response_code(500);
                exit('دریافت اطلاعات کالا از راهکاران ناموفق بود.');
            }

            $row = $rahkaran[$productCode] ?? null;
            if (!$row) {
                http_response_code(404);
                exit('کالای موردنظر در راهکاران یافت نشد.');
            }

            $unitName = (string) ($row['Announcement_unit'] ?? '');
            $unitId = (int) ($_GET['unit_id'] ?? 0);
            if ($unitId < 1 && $unitName !== '') {
                $u = $pdo->prepare('SELECT TOP 1 id FROM units WHERE is_active=1 AND name=:name');
                $u->execute(['name' => $unitName]);
                $unitId = (int) ($u->fetchColumn() ?: 0);
            }

            // Keep an existing ProductPriceMonitor UnitID when one exists.
            $u = $pdo->prepare('SELECT TOP 1 UnitID FROM ProductPriceMonitor WHERE ProductCode=:code AND UnitID IS NOT NULL ORDER BY InquiryDate DESC,PriceMonitorID DESC');
            $u->execute(['code' => $productCode]);
            $existingUnitId = (int) ($u->fetchColumn() ?: 0);
            if ($existingUnitId > 0) {
                $unitId = $existingUnitId;
            }

            $product = [
                'id' => 0,
                'product_code' => $productCode,
                'product_name' => (string) ($row['Name'] ?? ''),
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'registered_at' => null,
            ];

            $s = $pdo->prepare('SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,m.CreatedDate,u.name unit_name FROM ProductPriceMonitor m LEFT JOIN units u ON u.id=m.UnitID WHERE m.ProductCode=:code ORDER BY m.InquiryDate DESC,m.PriceMonitorID DESC');
            $s->execute(['code' => $productCode]);
            $prices = $s->fetchAll();

            $requiredSources = $this->currentRequiredSources($product, $row);
        }

        View::render($editing ? 'prices/edit' : 'prices/create', compact('units', 'product', 'prices', 'requiredSources'));
    }

    public function save(?int $id = null): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.edit');
        Security::verifyCsrf();

        $pdo = Database::connection();
        $postedCode = trim((string) ($_POST['product_code'] ?? ''));
        if ($postedCode === '') {
            http_response_code(422);
            exit('کد کالا الزامی است.');
        }

        $rahkaran = RahkaranPriceService::instance()->latestByCodes([$postedCode]);
        $rahkaranRow = $rahkaran[$postedCode] ?? null;
        if (!$rahkaranRow) {
            http_response_code(404);
            exit('کالای موردنظر در راهکاران یافت نشد.');
        }

        $unitId = (int) ($_POST['product_unit_id'] ?? 0);
        if ($unitId < 1) {
            $u = $pdo->prepare('SELECT TOP 1 UnitID FROM ProductPriceMonitor WHERE ProductCode=:code ORDER BY InquiryDate DESC,PriceMonitorID DESC');
            $u->execute(['code' => $postedCode]);
            $unitId = (int) ($u->fetchColumn() ?: 0);
        }
        if ($unitId < 1) {
            $unitName = (string) ($rahkaranRow['Announcement_unit'] ?? '');
            $u = $pdo->prepare('SELECT TOP 1 id FROM units WHERE is_active=1 AND name=:name');
            $u->execute(['name' => $unitName]);
            $unitId = (int) ($u->fetchColumn() ?: 0);
        }

        $rows = $_POST['prices'] ?? [];
        if (!is_array($rows) || count($rows) > 3) {
            http_response_code(422);
            exit('حداکثر سه رصد جدید در هر ثبت مجاز است.');
        }

        $valid = [];
        foreach ($rows as $row) {
            $source = trim((string) ($row['source'] ?? ''));
            $price = trim((string) ($row['price'] ?? ''));
            $rowUnitId = (int) ($row['unit_id'] ?? 0);
            $date = trim((string) ($row['inquiry_date'] ?? ''));

            if ($source === '' && $price === '' && $rowUnitId === 0 && $date === '') {
                continue;
            }

            if ($source === '' || $price === '' || $date === '') {
                http_response_code(422);
                exit('هر رصد قیمت باید منبع، قیمت و تاریخ داشته باشد.');
            }

            if (!is_numeric($price) || (float) $price < 0) {
                http_response_code(422);
                exit('قیمت نامعتبر است.');
            }

            try {
                $gregorianDate = Jalali::toGregorian($date);
            } catch (\Throwable $e) {
                http_response_code(422);
                exit('تاریخ رصد نامعتبر است.');
            }

            $valid[] = [$source, (float) $price, $unitId > 0 ? $unitId : $rowUnitId, $gregorianDate];
        }

        if (!$valid) {
            http_response_code(422);
            exit('حداقل یک رصد قیمت باید ثبت شود.');
        }

        $requiredSources = $this->currentRequiredSources([
            'product_code' => $postedCode,
        ]);

        if ($requiredSources > 0 && count($valid) < $requiredSources) {
            http_response_code(422);
            exit('برای این وضعیت حداقل ' . $requiredSources . ' قیمت جدید بازار باید ثبت شود.');
        }

        if ($unitId < 1) {
            http_response_code(422);
            exit('واحد سنجش کالا در اطلاعات راهکاران/سوابق رصد مشخص نیست.');
        }

        $s = $pdo->prepare('SELECT id FROM units WHERE id=:id AND is_active=1');
        $s->execute(['id' => $unitId]);
        if (!$s->fetchColumn()) {
            http_response_code(422);
            exit('واحد سنجش معتبر نیست.');
        }

        $s = $pdo->prepare('INSERT INTO ProductPriceMonitor(ProductCode,InquiryDate,Source,Price,UnitID,CreatedDate) VALUES(:code,:date,:source,:price,:unit,GETDATE())');
        foreach ($valid as $row) {
            $s->execute([
                'code' => $postedCode,
                'date' => $row[3],
                'source' => $row[0],
                'price' => $row[1],
                'unit' => $unitId,
            ]);
        }

        AuditService::log(Auth::userId(), 'price.monitor.create', 'ProductPriceMonitor', $postedCode, null, [
            'code' => $postedCode,
            'count' => count($valid),
        ]);

        Security::redirect('/prices');
    }


