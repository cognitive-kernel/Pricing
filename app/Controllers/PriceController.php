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

    private function rowStatus($marketDate, ?array $rahkaran): array
    {
        if ($marketDate instanceof \DateTimeInterface) {
            $marketDate = $marketDate->format('Y-m-d H:i:s');
        } elseif ($marketDate !== null && $marketDate !== false) {
            $marketDate = (string) $marketDate;
        } else {
            $marketDate = null;
        }

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
        if ($marketDate instanceof \DateTimeInterface) {
            $marketDate = $marketDate->format('Y-m-d H:i:s');
        } elseif ($marketDate !== false && $marketDate !== null) {
            $marketDate = (string) $marketDate;
        }

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
        $s = $pdo->query('SELECT ProductCode, MAX(InquiryDate) AS market_latest_date FROM ProductPriceMonitor GROUP BY ProductCode');
        foreach ($s->fetchAll() as $r) {
            $marketDate = $r['market_latest_date'] ?? null;
            if ($marketDate instanceof \DateTimeInterface) {
                $marketDate = $marketDate->format('Y-m-d H:i:s');
            } elseif ($marketDate !== null && $marketDate !== false) {
                $marketDate = (string) $marketDate;
            } else {
                $marketDate = null;
            }
            $marketLatestByCode[trim((string) $r['ProductCode'])] = $marketDate;
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
            $s = $pdo->prepare('SELECT PriceMonitorID,ProductCode,InquiryDate,Source,Price,UnitID,CreatedDate FROM ProductPriceMonitor WHERE ProductCode IN (' . $in . ') ORDER BY ProductCode,InquiryDate DESC,PriceMonitorID DESC');
            $s->execute($codes);
            foreach ($s->fetchAll() as $r) {
                $r['price_received_at'] = $r['InquiryDate'];
                $r['supplier'] = $r['Source'];
                $r['unit_price'] = $r['Price'];
                $r['unit_id'] = $r['UnitID'];
                $prices[trim((string) $r['ProductCode'])][] = $r;
            }
        }

        $products = [];
        foreach ($pageRows as $row) {
            $code = trim((string) ($row['Code'] ?? ''));
            $marketDate = $marketLatestByCode[$code] ?? null;
            $unitName = (string) ($row['Announcement_unit'] ?? '');
            // UnitID must come from Rahkaran's Product.UnitRef, not from prior market rows.
            $unitId = (int) ($row['Announcement_unit_id'] ?? 0);
            if ($unitId < 1 && !empty($prices[$code][0]['unit_id'])) {
                $unitId = (int) $prices[$code][0]['unit_id'];
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
        $productCode = trim((string) ($_GET['product_code'] ?? ''));
        if ($productCode === '') {
            http_response_code(400);
            exit('کد کالا ارسال نشده است.');
        }

        // History must display the authoritative unit name from Rahkaran,
        // not the numeric UnitID or a stale value passed in the URL.
        $rahkaran = RahkaranPriceService::instance()->latestByCodes([$productCode]);
        $rahkaranRow = $rahkaran[$productCode] ?? null;

        $product = [
            'id' => 0,
            'product_code' => $productCode,
            'product_name' => trim((string) ($_GET['product_name'] ?? '')),
            'unit_id' => (int) ($rahkaranRow['Announcement_unit_id'] ?? ($_GET['unit_id'] ?? 0)),
            'unit_name' => trim((string) ($rahkaranRow['Announcement_unit'] ?? '')),
            'registered_at' => null,
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;

        $count = $pdo->prepare('SELECT COUNT(*) FROM dbo.ProductPriceMonitor WHERE ProductCode=:code');
        $count->execute(['code' => $productCode]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $s = $pdo->prepare(
            'SELECT PriceMonitorID,ProductCode,
                    CONVERT(varchar(19),InquiryDate,120) AS InquiryDate,
                    Source,Price,UnitID,
                    CONVERT(varchar(19),CreatedDate,120) AS CreatedDate
             FROM dbo.ProductPriceMonitor
             WHERE ProductCode=:code
             ORDER BY InquiryDate DESC,PriceMonitorID DESC
             OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY'
        );
        $s->bindValue(':code', $productCode, PDO::PARAM_STR);
        $s->bindValue(':offset', $offset, PDO::PARAM_INT);
        $s->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $s->execute();
        $history = $s->fetchAll();

        View::render('prices/history', compact('product', 'history', 'page', 'pages', 'perPage', 'total'));
    }

    public function historyChart(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.history.view');

        $productCode = trim((string) ($_GET['product_code'] ?? ''));
        if ($productCode === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'کد کالا ارسال نشده است.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Database::connection();
        $s = $pdo->prepare(
            'SELECT CONVERT(varchar(19),InquiryDate,120) AS InquiryDate,
                    Price
             FROM dbo.ProductPriceMonitor
             WHERE ProductCode=:code
             ORDER BY InquiryDate ASC,PriceMonitorID ASC'
        );
        $s->execute(['code' => $productCode]);

        $points = [];
        foreach ($s->fetchAll() as $row) {
            $points[] = [
                'date' => Jalali::fromGregorian((string) $row['InquiryDate']),
                'price' => (float) $row['Price'],
            ];
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['points' => $points], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function form(?int $id = null): void
    {
        Auth::requireLogin();
        $editing = $id !== null;
        PermissionService::require(Auth::userId(), $editing ? 'prices.edit' : 'prices.create');

        $pdo = Database::connection();
        $product = null;
        $prices = [];
        $requiredSources = 0;
        $paymentAgreements = [];

        if ($editing) {
            // This form is intentionally independent of products/Rahkaran relations.
            // The first page passes the product context directly in the URL.
            $productCode = trim((string) ($_GET['product_code'] ?? ''));
            $productName = trim((string) ($_GET['product_name'] ?? ''));
            $unitId = (int) ($_GET['unit_id'] ?? 0);
            $unitName = trim((string) ($_GET['unit_name'] ?? ''));
            $requiredSources = max(0, min(3, (int) ($_GET['required_sources'] ?? 0)));
            try {
                $paymentAgreements = RahkaranPriceService::instance()->paymentAgreements();
            } catch (\Throwable $e) {
                error_log('Payment agreement lookup failed: ' . $e->getMessage());
                http_response_code(500);
                exit('دریافت فهرست توافق پرداخت ناموفق بود.');
            }

            if ($productCode === '' || $productName === '') {
                http_response_code(400);
                exit('اطلاعات کالا ارسال نشده است.');
            }

            if ($unitId < 1) {
                $u = $pdo->prepare('SELECT TOP 1 UnitID FROM dbo.ProductPriceMonitor WHERE ProductCode=:code AND UnitID IS NOT NULL ORDER BY InquiryDate DESC,PriceMonitorID DESC');
                $u->execute(['code' => $productCode]);
                $unitId = (int) ($u->fetchColumn() ?: 0);
            }

            $product = [
                'id' => 0,
                'product_code' => $productCode,
                'product_name' => $productName,
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'registered_at' => null,
            ];

            $s = $pdo->prepare('SELECT m.PriceMonitorID,m.ProductCode,CONVERT(varchar(19),m.InquiryDate,120) AS InquiryDate,m.Source,m.Price,m.UnitID,m.PaymentAgreementID,CONVERT(varchar(19),m.CreatedDate,120) AS CreatedDate FROM dbo.ProductPriceMonitor m WHERE m.ProductCode=:code ORDER BY m.InquiryDate DESC,m.PriceMonitorID DESC');
            $s->execute(['code' => $productCode]);
            $prices = $s->fetchAll();

            if ($requiredSources === 0) {
                $marketDate = $prices[0]['InquiryDate'] ?? null;
                $requiredSources = $this->requiredSourcesFromMarketDate($marketDate);
            }
        }

        View::render($editing ? 'prices/edit' : 'prices/create', compact('product', 'prices', 'requiredSources', 'paymentAgreements'));
    }

    private function requiredSourcesFromMarketDate(?string $marketDate): int
    {
        if (!$marketDate) {
            return 1;
        }
        $days = max(0, (int) ((time() - strtotime($marketDate)) / 86400));
        if ($days < 7) {
            return 0;
        }
        if ($days <= 15) {
            return 2;
        }
        return 3;
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

        // UnitID must be read from Rahkaran at save time; never allow a NULL market UnitID.
        $unitId = RahkaranPriceService::instance()->unitIdByCode($postedCode);
        if ($unitId === null) {
            http_response_code(422);
            exit('واحد کالا در راهکاران پیدا نشد.');
        }
        $paymentAgreementId = (int) ($_POST['payment_agreement_id'] ?? 0);
        if ($paymentAgreementId < 1) {
            http_response_code(422);
            exit('توافق پرداخت الزامی است.');
        }

        $rows = $_POST['prices'] ?? [];
        if (!is_array($rows) || count($rows) > 3) {
            http_response_code(422);
            exit('حداکثر سه رصد جدید در هر ثبت مجاز است.');
        }

        $valid = [];
        foreach ($rows as $row) {
            $source = trim((string) ($row['source'] ?? ''));
            $price = str_replace(',', '', trim((string) ($row['price'] ?? '')));
            $rowUnitId = (int) ($row['unit_id'] ?? 0);
            $date = trim((string) ($row['inquiry_date'] ?? ''));

            // Optional rows have today's date prefilled, so an untouched row
            // must still be considered empty when source and price are both blank.
            if ($source === '' && $price === '') {
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

        $requiredSources = max(0, min(3, (int) ($_POST['required_sources'] ?? 0)));

        if ($requiredSources > 0 && count($valid) < $requiredSources) {
            http_response_code(422);
            exit('برای این وضعیت حداقل ' . $requiredSources . ' قیمت جدید بازار باید ثبت شود.');
        }

        $s = $pdo->prepare('INSERT INTO ProductPriceMonitor(ProductCode,InquiryDate,Source,Price,UnitID,CreatedDate) VALUES(:code,DATEFROMPARTS(:year,:month,:day),:source,:price,:unit,:payment_agreement_id,GETDATE())');
        foreach ($valid as $row) {
            $s->execute([
                'code' => $postedCode,
                'year' => (int) substr($row[3], 0, 4),
                'month' => (int) substr($row[3], 5, 2),
                'day' => (int) substr($row[3], 8, 2),
                'source' => $row[0],
                'price' => $row[1],
                'unit' => $unitId,
                'payment_agreement_id' => $paymentAgreementId,
            ]);
        }

        AuditService::log(Auth::userId(), 'price.monitor.create', 'ProductPriceMonitor', $postedCode, null, [
            'code' => $postedCode,
            'count' => count($valid),
        ]);

        Security::redirect('/prices');
    }



}
