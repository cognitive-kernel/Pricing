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

        // Repair older export rows that were saved before gross/net amounts were
        // consistently populated. If the original monitor amount is missing too,
        // the recorded price is the safe neutral fallback.
        $pdo->exec("UPDATE pa
                    SET grossprice = COALESCE(pa.grossprice, src.calculated_gross, pa.Price),
                        netamount = COALESCE(pa.netamount, src.calculated_net, src.calculated_gross, pa.Price)
                    FROM dbo.PriceApprove pa
                    OUTER APPLY (
                        SELECT TOP 1
                            COALESCE(
                                m.grossprice,
                                ROUND(COALESCE(m.Price, pa.Price) * (1 - ISNULL(m.discountperc, 0) / 100.0), 2)
                            ) AS calculated_gross,
                            COALESCE(
                                m.netamount,
                                ROUND(
                                    COALESCE(m.grossprice, COALESCE(m.Price, pa.Price) * (1 - ISNULL(m.discountperc, 0) / 100.0))
                                    * CASE WHEN ISNULL(m.opration, 1) = 1
                                           THEN 1 + ISNULL(m.payagrementperc, 0) / 100.0
                                           ELSE 1 - ISNULL(m.payagrementperc, 0) / 100.0 END,
                                    2
                                )
                            ) AS calculated_net
                        FROM dbo.ProductPriceMonitor m
                        WHERE m.ProductCode = pa.ProductCode
                          AND m.InquiryDate = pa.InquiryDate
                          AND ISNULL(m.Source, '') = ISNULL(pa.Source, '')
                          AND ISNULL(m.Price, 0) = ISNULL(pa.Price, 0)
                          AND ISNULL(m.UnitID, 0) = ISNULL(pa.UnitID, 0)
                          AND ISNULL(m.PaymentAgreementID, 0) = ISNULL(pa.PaymentAgreementID, 0)
                        ORDER BY m.PriceMonitorID DESC
                    ) src
                    WHERE pa.grossprice IS NULL OR pa.netamount IS NULL");

        $sql = "WITH ranked AS (
            SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,
                   m.PaymentAgreementID,m.discountperc,m.grossprice,m.opration,m.netamount,
                   m.payagrementperc,m.userid,m.CreatedDate,m.Approved,m.isexported,
                   ROW_NUMBER() OVER (PARTITION BY m.ProductCode ORDER BY m.InquiryDate DESC,m.PriceMonitorID DESC) AS rn
            FROM dbo.ProductPriceMonitor m
        )
        SELECT * FROM ranked
        WHERE rn=1 AND Approved=1 AND ISNULL(isexported,0)=0
        ORDER BY ProductCode";
        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->respondError('خروجی اکسل تهیه نشد. خطای پایگاه داده: ستون‌ها و ساختار جدول ProductPriceMonitor را بررسی کنید.', 500);
        }
        $agreementIds = array_values(array_unique(array_map(static fn(array $r): int => (int)($r['PaymentAgreementID'] ?? 0), $rows)));
        $agreements = RahkaranPriceService::instance()->paymentAgreementTitlesByIds($agreementIds);

        $headers = ['کد کالا','تاریخ رصد','منبع','قیمت','واحد','توافق پرداخت','درصد تخفیف','قیمت ناخالص','عملیات','مبلغ نهایی','درصد توافق پرداخت','کاربر','تاریخ ایجاد'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="قیمت‌های تأییدشده"><Table><Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($header, ENT_XML1|ENT_QUOTES, 'UTF-8').'</Data></Cell>';
        }
        $xml .= '</Row>';
        $exportedIds = [];
        foreach ($rows as $r) {
            $id = (int)$r['PriceMonitorID'];
            $exportedIds[] = $id;
            $values = [
                $r['ProductCode'] ?? '',
                $r['InquiryDate'] ?? '',
                $r['Source'] ?? '',
                $r['Price'] ?? '',
                $r['UnitID'] ?? '',
                $agreements[(int)($r['PaymentAgreementID'] ?? 0)] ?? '',
                $r['discountperc'] ?? '',
                $r['grossprice'] ?? '',
                ((int)($r['opration'] ?? 1) === 1) ? 'جمع' : 'منها',
                $r['netamount'] ?? '',
                $r['payagrementperc'] ?? '',
                $r['userid'] ?? '',
                $r['CreatedDate'] ?? '',
            ];
            $xml .= '<Row>';
            foreach ($values as $value) {
                $type = is_numeric($value) && $value !== '' ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="'.$type.'">'.htmlspecialchars((string)$value, ENT_XML1|ENT_QUOTES, 'UTF-8').'</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        if ($rows) {
            // Save precisely the source rows represented in this workbook.
            // Foreign-key-like fields are persisted as IDs, not display labels.
            $insert = $pdo->prepare(
                'INSERT INTO dbo.PriceApprove
                    (ProductCode, InquiryDate, Source, Price, UnitID, PaymentAgreementID, grossprice, netamount, userid)
                 VALUES
                    (:product_code, :inquiry_date, :source, :price, :unit_id, :agreement_id, :grossprice, :netamount, :userid)'
            );

            $pdo->beginTransaction();
            try {
                foreach ($rows as $r) {
                    $inquiryDate = $r['InquiryDate'] ?? null;
                    if ($inquiryDate instanceof \DateTimeInterface) {
                        $inquiryDate = $inquiryDate->format('Y-m-d H:i:s');
                    } elseif ($inquiryDate !== null && $inquiryDate !== false) {
                        $inquiryDate = (string) $inquiryDate;
                    }

                    $price = (float) ($r['Price'] ?? 0);
                    $discountPercent = max(0.0, min(100.0, (float) ($r['discountperc'] ?? 0)));
                    $grossprice = ($r['grossprice'] ?? null) !== null
                        ? (float) $r['grossprice']
                        : round($price * (1 - $discountPercent / 100), 2);
                    $agreementPercent = max(0.0, (float) ($r['payagrementperc'] ?? 0));
                    $isAddOperation = (int) ($r['opration'] ?? 1) === 1;
                    $calculatedNet = round(
                        $isAddOperation
                            ? $grossprice * (1 + $agreementPercent / 100)
                            : $grossprice * (1 - $agreementPercent / 100),
                        2
                    );
                    $netamount = ($r['netamount'] ?? null) !== null ? (float) $r['netamount'] : $calculatedNet;

                    $insert->execute([
                        'product_code' => (string) ($r['ProductCode'] ?? ''),
                        'inquiry_date' => $inquiryDate,
                        'source' => (string) ($r['Source'] ?? ''),
                        'price' => $r['Price'] ?? null,
                        'unit_id' => (int) ($r['UnitID'] ?? 0),
                        'agreement_id' => (int) ($r['PaymentAgreementID'] ?? 0),
                        'grossprice' => $grossprice,
                        'netamount' => $netamount,
                        'userid' => (int) ($r['userid'] ?? 0),
                    ]);
                }

                $exportedIds = array_values(array_unique(array_filter(
                    $exportedIds,
                    static fn(int $id): bool => $id > 0
                )));
                if ($exportedIds) {
                    $marks = implode(',', array_fill(0, count($exportedIds), '?'));
                    $update = $pdo->prepare(
                        "UPDATE dbo.ProductPriceMonitor
                         SET isexported=1
                         WHERE Approved=1 AND ISNULL(isexported,0)=0
                           AND PriceMonitorID IN ($marks)"
                    );
                    $update->execute($exportedIds);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Saving approved export rows failed: ' . $e->getMessage());
                $this->respondError('خروجی ثبت نشد؛ جدول dbo.PriceApprove و نوع ستون‌های آن را بررسی کنید.', 500);
            }
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
