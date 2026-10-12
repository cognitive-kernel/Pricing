<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\PermissionService;
use App\Services\MarketPriceCalculator;
use App\Services\RahkaranPriceService;
use App\Support\Jalali;
use PDO;

final class PriceApprovalController
{
    public function index(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.approvals.view');

        $q = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();
        $sql = "WITH latest_dates AS (
            SELECT ProductCode, MAX(InquiryDate) AS latest_date
            FROM dbo.ProductPriceMonitor
            GROUP BY ProductCode
        )
        SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,
               m.CreatedDate,m.PaymentAgreementID,m.discountperc,m.grossprice,m.opration,
               m.netamount,m.payagrementperc,m.userid,m.Approved,m.isexported,
               u.username AS CreatorUsername
        FROM dbo.ProductPriceMonitor m
        INNER JOIN latest_dates ld ON ld.ProductCode=m.ProductCode AND ld.latest_date=m.InquiryDate
        LEFT JOIN dbo.users u ON u.id=m.userid
        WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (CONVERT(varchar(100),m.ProductCode) LIKE :qcode OR m.Source LIKE :qsource)";
            $params['qcode'] = '%' . $q . '%';
            $params['qsource'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY m.ProductCode,m.PaymentAgreementID,m.InquiryDate DESC,m.PriceMonitorID DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $agreementIds = array_values(array_unique(array_map(static fn(array $r): int => (int)($r['PaymentAgreementID'] ?? 0), $records)));
        $rahkaran = RahkaranPriceService::instance();
        $agreements = $rahkaran->paymentAgreementTitlesByIds($agreementIds);
        $unitIds = array_values(array_unique(array_map(static fn(array $r): int => (int)($r['UnitID'] ?? 0), $records)));
        $unitNames = $rahkaran->unitNamesByIds($unitIds);

        $byProduct = [];
        foreach ($records as &$record) {
            $record['PaymentAgreementTitle'] = $agreements[(int)($record['PaymentAgreementID'] ?? 0)] ?? '';
            $record['InquiryDateJalali'] = !empty($record['InquiryDate']) ? Jalali::fromGregorian((string)$record['InquiryDate']) : '';
            $record['CreatorUsername'] = (string)($record['CreatorUsername'] ?? '');
            $record['UnitName'] = $unitNames[(int)($record['UnitID'] ?? 0)] ?? '';
            $record['price_received_at'] = $record['InquiryDate'];
            $record['supplier'] = $record['Source'];
            $record['unit_price'] = $record['Price'];
            $record['unit_id'] = $record['UnitID'];
            $byProduct[trim((string)$record['ProductCode'])][] = $record;
        }
        unset($record);

        $approvalGroups = [];
        foreach ($byProduct as $code => $productRows) {
            // Calculate average/outlier status using every record on the latest
            // market date (same inputs as the price-list page), but expose only
            // still-pending records as selectable approval targets.
            $agreementGroups = MarketPriceCalculator::groupByPaymentAgreement($productRows);
            foreach ($agreementGroups as $group) {
                $groupRows = $group['rows'];
                $pendingRows = array_values(array_filter(
                    $groupRows,
                    static fn(array $r): bool => (int)($r['Approved'] ?? 0) === 0
                ));
                if (!$pendingRows) {
                    continue;
                }
                $first = $pendingRows[0];
                $approvalGroups[] = [
                    'ids' => array_values(array_map(static fn(array $r): int => (int)$r['PriceMonitorID'], $pendingRows)),
                    'row' => $first,
                    'count' => (int)$group['count'],
                    'average_price' => $group['average_price'],
                    'warning' => (bool)$group['warning'],
                    'prices' => $group['prices'],
                ];
            }
        }
        View::render('prices/approvals', compact('approvalGroups','q'));
    }

    public function decision(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.approvals.view');
        Security::verifyCsrf();

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        $decision = (string)($_POST['decision'] ?? '');
        if (!$ids || !in_array($decision, ['approve','reject'], true)) {
            $this->respondError('حداقل یک ردیف و نوع تصمیم معتبر انتخاب کنید.', 422);
        }

        $approved = $decision === 'approve' ? 1 : 2;
        $pdo = Database::connection();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE m SET Approved=?
        FROM dbo.ProductPriceMonitor m
        INNER JOIN (
            SELECT ProductCode, MAX(InquiryDate) AS latest_date
            FROM dbo.ProductPriceMonitor
            GROUP BY ProductCode
        ) latest ON latest.ProductCode=m.ProductCode AND latest.latest_date=m.InquiryDate
        WHERE ISNULL(m.Approved,0)=0 AND m.PriceMonitorID IN ($marks)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$approved], $ids));
        } catch (\Throwable $e) {
            $this->respondError('ثبت تصمیم انجام نشد. لطفاً ساختار جدول و ستون Approved را بررسی کنید.', 500);
        }
        if ($this->isAjax()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => 'تصمیم ثبت شد.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        Security::redirect('/prices/approvals');
    }

    public function excel(): never
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.approvals.view');

        $pdo = Database::connection();

        // Use the same latest-date records and payment-agreement grouping as
        // the market price list. Eligibility for export is an additional filter.
        $sql = "WITH latest_dates AS (
            SELECT ProductCode, MAX(InquiryDate) AS latest_date
            FROM dbo.ProductPriceMonitor
            GROUP BY ProductCode
        )
        SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,
               m.CreatedDate,m.PaymentAgreementID,m.discountperc,m.grossprice,m.opration,
               m.netamount,m.payagrementperc,m.userid,m.Approved,m.isexported
        FROM dbo.ProductPriceMonitor m
        INNER JOIN latest_dates ld
            ON ld.ProductCode=m.ProductCode AND ld.latest_date=m.InquiryDate
        ORDER BY m.ProductCode,m.PaymentAgreementID,m.InquiryDate DESC,m.PriceMonitorID DESC";

        try {
            $marketRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $rahkaran = RahkaranPriceService::instance();
            $products = [];
            foreach ($rahkaran->allRows() as $product) {
                $code = trim((string)($product['Code'] ?? ''));
                if ($code !== '') {
                    $products[$code] = $product;
                }
            }

            $agreementIds = array_values(array_unique(array_map(
                static fn(array $r): int => (int)($r['PaymentAgreementID'] ?? 0),
                $marketRows
            )));
            $agreements = $rahkaran->paymentAgreementTitlesByIds($agreementIds);
            $unitIds = array_values(array_unique(array_map(
                static fn(array $r): int => (int)($r['UnitID'] ?? 0),
                $marketRows
            )));
            $unitNames = $rahkaran->unitNamesByIds($unitIds);
        } catch (\Throwable $e) {
            error_log('Approved market export source load failed: '.$e->getMessage());
            $this->respondError('دریافت اطلاعات خروجی اکسل ناموفق بود.', 500);
        }

        $byProduct = [];
        foreach ($marketRows as $row) {
            $code = trim((string)($row['ProductCode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $agreementId = (int)($row['PaymentAgreementID'] ?? 0);
            $row['PaymentAgreementTitle'] = $agreements[$agreementId] ?? '';
            $row['UnitName'] = $unitNames[(int)($row['UnitID'] ?? 0)] ?? '';
            $byProduct[$code][] = $row;
        }

        // Exact column layout requested for the legacy Rahkaran import workbook.
        $headers = [
            'کد محصول','عنوان محصول','کد گروه محصول','واحد سنجش',
            'عامل کنترل موجودی اول','عامل کنترل موجودی دوم','عامل کنترل موجودی سوم',
            'عامل کنترل موجودی چهارم','عامل کنترل موجودی پنجم','بسته بندی',
            'تا مقدار','فی','قطعیت','تلرانس کاهش','تلرانس افزایش'
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles><Style ss:ID="Decimal6"><NumberFormat ss:Format="#,##0.######"/></Style></Styles>';
        $xml .= '<Worksheet ss:Name="رصد بازار"><Table><Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($header, ENT_XML1|ENT_QUOTES, 'UTF-8').'</Data></Cell>';
        }
        $xml .= '</Row>';

        $exportedIds = [];
        foreach ($byProduct as $code => $rowsForProduct) {
            // Match the legacy workbook behavior: only export products present in Rahkaran.
            if (!isset($products[$code])) {
                continue;
            }
            $product = $products[$code];
            $unitName = trim((string)($product['Announcement_unit'] ?? ''));
            if ($unitName === '') {
                $unitName = (string)($unitNames[(int)($rowsForProduct[0]['UnitID'] ?? 0)] ?? '');
            }
            $groups = MarketPriceCalculator::groupByPaymentAgreement($rowsForProduct);
            foreach ($groups as $group) {
                $eligibleRows = array_values(array_filter(
                    $group['rows'],
                    static fn(array $r): bool => (int)($r['Approved'] ?? 0) === 1
                        && (int)($r['isexported'] ?? 0) === 0
                ));
                if (!$eligibleRows) {
                    continue;
                }

                // The exported workbook row is calculated from every source price
                // in this product/agreement group. Mark every source row used in
                // that average as exported, not just the row that triggered export.
                foreach ($group['rows'] as $r) {
                    $id = (int)($r['PriceMonitorID'] ?? 0);
                    if ($id > 0) {
                        $exportedIds[] = $id;
                    }
                }

                $fee = !empty($group['warning']) ? '' : (float)($group['average_price'] ?? 0);
                $values = [
                    $code,
                    (string)($product['Name'] ?? ''),
                    '',
                    $unitName,
                    '', '', '', '', '', '',
                    '',
                    $fee,
                    !empty($group['warning']) ? 'هشدار اختلاف قیمت' : 'قطعی',
                    0,
                    0,
                ];

                $xml .= '<Row>';
                foreach ($values as $index => $value) {
                    $type = is_numeric($value) && $value !== '' ? 'Number' : 'String';
                    $style = in_array($index, [11, 13, 14], true) ? ' ss:StyleID="Decimal6"' : '';
                    $xml .= '<Cell'.$style.'><Data ss:Type="'.$type.'">'.htmlspecialchars((string)$value, ENT_XML1|ENT_QUOTES, 'UTF-8').'</Data></Cell>';
                }
                $xml .= '</Row>';
            }
        }
        $xml .= '</Table></Worksheet></Workbook>';

        $exportedIds = array_values(array_unique($exportedIds));
        if ($exportedIds) {
            $marks = implode(',', array_fill(0, count($exportedIds), '?'));
            $update = $pdo->prepare(
                "UPDATE dbo.ProductPriceMonitor
                 SET isexported=1,
                     Approved=CASE WHEN ISNULL(Approved,0)=2 THEN 2 ELSE 1 END
                 WHERE PriceMonitorID IN ($marks)"
            );
            $update->execute($exportedIds);
        }

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="approved-market-prices.xls"');
        echo $xml;
        exit;
    }

    private function isAjax(): bool
    {
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    private function respondError(string $message, int $status): never
    {
        http_response_code($status);
        if ($this->isAjax()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        exit($message);
    }

}
