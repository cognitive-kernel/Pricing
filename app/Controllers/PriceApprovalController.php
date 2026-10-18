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

        $pdo = Database::connection();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        try {
            if ($decision === 'reject') {
                // Reject selected pending records and recursively reject pending
                // queue records based directly on those records.
                $sql = ";WITH rejected_chain AS (
                    SELECT PriceMonitorID
                    FROM dbo.ProductPriceMonitor
                    WHERE ISNULL(Approved,0)=0
                      AND PriceMonitorID IN ($marks)
                    UNION ALL
                    SELECT child.PriceMonitorID
                    FROM dbo.ProductPriceMonitor child
                    INNER JOIN rejected_chain parent
                        ON child.BasedOnPriceMonitorID=parent.PriceMonitorID
                    WHERE ISNULL(child.Approved,0)=0
                )
                UPDATE target
                SET Approved=2
                FROM dbo.ProductPriceMonitor target
                INNER JOIN (
                    SELECT DISTINCT PriceMonitorID FROM rejected_chain
                ) rejected ON rejected.PriceMonitorID=target.PriceMonitorID
                WHERE ISNULL(target.Approved,0)=0
                OPTION (MAXRECURSION 32767)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($ids);
            } else {
                $sql = "UPDATE dbo.ProductPriceMonitor
                        SET Approved=1
                        WHERE ISNULL(Approved,0)=0
                          AND PriceMonitorID IN ($marks)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($ids);
            }

            if ($stmt->rowCount() < 1) {
                $this->respondError('ردیف‌های انتخاب‌شده قبلاً بررسی شده‌اند یا دیگر در انتظار تصمیم نیستند. صفحه را تازه‌سازی کنید.', 409);
            }
        } catch (\Throwable $e) {
            error_log('Price approval decision update failed: '.$e->getMessage());
            $this->respondError('ثبت تصمیم انجام نشد.', 500);
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
        INNER JOIN latest_dates ld
            ON ld.ProductCode=m.ProductCode AND ld.latest_date=m.InquiryDate
        LEFT JOIN dbo.users u ON u.id=m.userid
        ORDER BY m.ProductCode,m.PaymentAgreementID,m.InquiryDate DESC,m.PriceMonitorID DESC";

        try {
            $marketRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $existing = $pdo->query(
                'SELECT ProductCode, CONVERT(varchar(10), InquiryDate, 23) AS InquiryDate, PaymentAgreementID
                 FROM dbo.PriceApprove'
            )->fetchAll(PDO::FETCH_ASSOC);

            $exportedKeys = [];
            foreach ($existing as $saved) {
                $date = substr((string)($saved['InquiryDate'] ?? ''), 0, 10);
                $exportedKeys[
                    trim((string)($saved['ProductCode'] ?? '')) . '|' .
                    $date . '|' .
                    (int)($saved['PaymentAgreementID'] ?? 0)
                ] = true;
            }

            $rahkaran = RahkaranPriceService::instance();
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

            $row['PaymentAgreementTitle'] = $agreements[(int)($row['PaymentAgreementID'] ?? 0)] ?? '';
            $row['UnitName'] = $unitNames[(int)($row['UnitID'] ?? 0)] ?? '';
            $row['CreatorUsername'] = (string)($row['CreatorUsername'] ?? '');
            $dateValue = $row['InquiryDate'] ?? '';
            if ($dateValue instanceof \DateTimeInterface) {
                $row['InquiryDateText'] = $dateValue->format('Y-m-d H:i:s');
            } else {
                $row['InquiryDateText'] = (string)$dateValue;
            }
            $row['InquiryDateJalali'] = $row['InquiryDateText'] !== ''
                ? Jalali::fromGregorian($row['InquiryDateText'])
                : '';
            $row['price_received_at'] = $row['InquiryDate'];
            $row['supplier'] = $row['Source'];
            $row['unit_price'] = $row['Price'];
            $row['unit_id'] = $row['UnitID'];
            $byProduct[$code][] = $row;
        }

        // The Excel rows intentionally mirror the columns and aggregation shown
        // on the approvals screen. A previously logged product/date/agreement
        // group can never be exported a second time.
        $headers = [
            'کد کالا',
            'تاریخ رصد',
            'منبع',
            'قیمت',
            'واحد',
            'توافق پرداخت',
            'قیمت ناخالص',
            'مبلغ نهایی',
            'کاربر ثبت‌کننده',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="تأیید قیمت‌ها"><Table><Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
        }
        $xml .= '</Row>';

        $exportGroups = [];
        foreach ($byProduct as $code => $rowsForProduct) {
            $groups = MarketPriceCalculator::groupByPaymentAgreement($rowsForProduct);
            foreach ($groups as $group) {
                if (!empty($group['warning'])) {
                    continue;
                }

                $groupRows = $group['rows'];
                if (!$groupRows) {
                    continue;
                }

                // A displayed average covers every price in its group, so only
                // export when all contributing records are approved and unexported.
                $allEligible = true;
                foreach ($groupRows as $r) {
                    if ((int)($r['Approved'] ?? 0) !== 1 || (int)($r['isexported'] ?? 0) !== 0) {
                        $allEligible = false;
                        break;
                    }
                }
                if (!$allEligible) {
                    continue;
                }

                $representative = $groupRows[0];
                $dateKey = substr((string)($representative['InquiryDateText'] ?? ''), 0, 10);
                $agreementId = (int)($group['PaymentAgreementID'] ?? $representative['PaymentAgreementID'] ?? 0);
                $duplicateKey = $code.'|'.$dateKey.'|'.$agreementId;
                if (isset($exportedKeys[$duplicateKey])) {
                    continue;
                }

                $source = (string)($representative['Source'] ?? '');
                if (count($groupRows) > 1) {
                    $source .= ' ('.count($groupRows).' قیمت)';
                }

                $averagePrice = (float)($group['average_price'] ?? 0);
                $unitName = (string)($representative['UnitName'] ?? '');
                $agreementTitle = (string)($representative['PaymentAgreementTitle'] ?? '');
                $grossPrice = (float)($representative['grossprice'] ?? 0);
                $netAmount = (float)($representative['netamount'] ?? 0);
                $username = (string)($representative['CreatorUsername'] ?? '');

                $exportGroups[] = [
                    'key' => $duplicateKey,
                    'ids' => array_values(array_map(
                        static fn(array $r): int => (int)($r['PriceMonitorID'] ?? 0),
                        $groupRows
                    )),
                    'price_approve' => [
                        'ProductCode' => $code,
                        'InquiryDate' => $representative['InquiryDateText'],
                        'Source' => $source,
                        'Price' => $averagePrice,
                        'UnitID' => (int)($representative['UnitID'] ?? 0),
                        'PaymentAgreementID' => $agreementId,
                        'grossprice' => $grossPrice,
                        'netamount' => $netAmount,
                        'userid' => (int)($representative['userid'] ?? 0),
                    ],
                    'values' => [
                        $code,
                        $representative['InquiryDateJalali'],
                        $source,
                        number_format($averagePrice, 2, '.', ''),
                        $unitName,
                        $agreementTitle,
                        number_format($grossPrice, 2, '.', ''),
                        number_format($netAmount, 2, '.', ''),
                        $username,
                    ],
                ];
            }
        }

        foreach ($exportGroups as $exportGroup) {
            $xml .= '<Row>';
            foreach ($exportGroup['values'] as $value) {
                $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        if ($exportGroups) {
            try {
                $pdo->beginTransaction();

                $insert = $pdo->prepare(
                    'INSERT INTO dbo.PriceApprove
                        (ProductCode, InquiryDate, Source, Price, UnitID, PaymentAgreementID, grossprice, netamount, userid)
                     VALUES
                        (:code, :inquiry_date, :source, :price, :unit_id, :agreement_id, :grossprice, :netamount, :userid)'
                );
                foreach ($exportGroups as $exportGroup) {
                    $r = $exportGroup['price_approve'];
                    $insert->execute([
                        'code' => $r['ProductCode'],
                        'inquiry_date' => $r['InquiryDate'],
                        'source' => $r['Source'],
                        'price' => number_format((float)$r['Price'], 6, '.', ''),
                        'unit_id' => $r['UnitID'],
                        'agreement_id' => $r['PaymentAgreementID'],
                        'grossprice' => number_format((float)$r['grossprice'], 6, '.', ''),
                        'netamount' => number_format((float)$r['netamount'], 6, '.', ''),
                        'userid' => $r['userid'],
                    ]);
                }

                $update = $pdo->prepare(
                    'UPDATE dbo.ProductPriceMonitor
                     SET isexported=1
                     WHERE PriceMonitorID=:id AND Approved=1 AND ISNULL(isexported,0)=0'
                );
                foreach ($exportGroups as $exportGroup) {
                    foreach ($exportGroup['ids'] as $id) {
                        if ($id > 0) {
                            $update->execute(['id' => $id]);
                        }
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Approved prices export persistence failed: '.$e->getMessage());
                $this->respondError('ثبت خروجی در جدول PriceApprove ناموفق بود؛ فایل صادر نشد.', 500);
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
