<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\PermissionService;
use App\Services\MarketPriceCalculator;
use App\Services\RahkaranPriceService;
use PDO;

final class MarketGreenExportController
{
    public function excel(): never
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');

        $rows = RahkaranPriceService::instance()->allRows();
        $pdo = Database::connection();

        $latest = [];
        $sql = 'SELECT m.PriceMonitorID, m.ProductCode, m.InquiryDate, m.Source, m.Price, m.UnitID, m.CreatedDate, m.PaymentAgreementID
                FROM ProductPriceMonitor m
                WHERE m.InquiryDate = CONVERT(date, GETDATE())
                  AND ISNULL(m.isexported, 0) = 0
                ORDER BY m.ProductCode, m.PaymentAgreementID, m.PriceMonitorID';
        $marketRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $agreementIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['PaymentAgreementID'] ?? 0),
            $marketRows
        )));
        $agreementTitles = RahkaranPriceService::instance()->paymentAgreementTitlesByIds($agreementIds);

        $exportedIds = [];
        foreach ($marketRows as $r) {
            $code = trim((string) ($r['ProductCode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $agreementId = (int) ($r['PaymentAgreementID'] ?? 0);
            $r['PaymentAgreementTitle'] = $agreementTitles[$agreementId] ?? '';
            $latest[$code][] = $r;
        }

        $headers = [
            'کد محصول','عنوان محصول','کد گروه محصول','واحد سنجش',
            'عامل کنترل موجودی اول','عامل کنترل موجودی دوم','عامل کنترل موجودی سوم',
            'عامل کنترل موجودی چهارم','عامل کنترل موجودی پنجم','بسته بندی',
            'تا مقدار','توافق پرداخت','فی','قطعیت','تلرانس کاهش','تلرانس افزایش'
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="Decimal6">';
        $xml .= '<NumberFormat ss:Format="#,##0.######"/>';
        $xml .= '</Style>';
        $xml .= '</Styles>';
        $xml .= '<Worksheet ss:Name="رصد بازار"><Table><Row>';

        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $r) {
            $code = trim((string) ($r['Code'] ?? ''));
            $marketRows = $latest[$code] ?? [];
            if ($code === '' || !$marketRows) {
                continue;
            }

            // The query limits this export to today's unexported rows.
            $groups = MarketPriceCalculator::groupByPaymentAgreement($marketRows);
            if (!$groups) {
                continue;
            }
            foreach ($marketRows as $marketRow) {
                $exportedIds[] = (int) $marketRow['PriceMonitorID'];
            }

            foreach ($groups as $group) {
                $values = [
                    $code,
                    (string) ($r['Name'] ?? ''),
                    '',
                    (string) ($r['Announcement_unit'] ?? ''),
                    '', '', '', '', '', '',
                    (string) ($group['PaymentAgreementTitle'] ?? ''),
                    $group['warning'] ? '' : (float) ($group['average_price'] ?? 0),
                    $group['warning'] ? 'هشدار اختلاف قیمت' : 'قطعی',
                    0,
                    0
                ];

                $xml .= '<Row>';
                foreach ($values as $index => $value) {
                    $type = is_numeric($value) && $value !== '' ? 'Number' : 'String';
                    $style = ($index === 12 || $index === 14 || $index === 15) ? ' ss:StyleID="Decimal6"' : '';
                    $xml .= '<Cell' . $style . '><Data ss:Type="' . $type . '">' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
                $xml .= '</Row>';
            }
        }

        $xml .= '</Table></Worksheet></Workbook>';

        // Mark only records represented in the generated workbook as exported.
        $exportedIds = array_values(array_unique(array_filter($exportedIds, static fn(int $id): bool => $id > 0)));
        if ($exportedIds) {
            $placeholders = implode(',', array_fill(0, count($exportedIds), '?'));
            $update = $pdo->prepare(
                'UPDATE ProductPriceMonitor
                 SET isexported = 1
                 WHERE ISNULL(isexported, 0) = 0
                   AND PriceMonitorID IN (' . $placeholders . ')'
            );
            $update->execute($exportedIds);
        }

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="green-market-prices.xls"');
        echo $xml;
        exit;
    }
}
