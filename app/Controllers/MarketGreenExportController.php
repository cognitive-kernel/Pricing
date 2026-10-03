<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\PermissionService;
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
        $sql = 'SELECT ProductCode, InquiryDate, Source, Price, UnitID, CreatedDate
                FROM (
                    SELECT m.*, ROW_NUMBER() OVER (
                        PARTITION BY m.ProductCode
                        ORDER BY m.InquiryDate DESC, m.PriceMonitorID DESC
                    ) AS rn
                    FROM ProductPriceMonitor m
                ) x
                WHERE rn = 1';
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $code = trim((string) ($r['ProductCode'] ?? ''));
            if ($code !== '') {
                $latest[$code] = $r;
            }
        }

        $headers = [
            'کد محصول','عنوان محصول','کد گروه محصول','واحد سنجش',
            'عامل کنترل موجودی اول','عامل کنترل موجودی دوم','عامل کنترل موجودی سوم',
            'عامل کنترل موجودی چهارم','عامل کنترل موجودی پنجم','بسته بندی',
            'تا مقدار','فی','قطعیت','تلرانس کاهش','تلرانس افزایش'
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="رصد بازار"><Table><Row>';

        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $r) {
            $code = trim((string) ($r['Code'] ?? ''));
            $market = $latest[$code] ?? null;
            if ($code === '' || $market === null) {
                continue;
            }

            $marketDate = $market['InquiryDate'] ?? null;
            if ($marketDate instanceof \DateTimeInterface) {
                $marketDate = $marketDate->format('Y-m-d');
            }
            $marketTimestamp = strtotime((string) $marketDate);
            if ($marketTimestamp === false) {
                continue;
            }

            $marketDays = max(0, (int) ((time() - $marketTimestamp) / 86400));
            if ($marketDays >= 7) {
                continue;
            }

            $values = [
                $code,
                (string) ($r['Name'] ?? ''),
                '',
                (string) ($r['Announcement_unit'] ?? ''),
                '', '', '', '', '', '', '',
                (string) ($market['Price'] ?? ''),
                '', '', ''
            ];

            $xml .= '<Row>';
            foreach ($values as $value) {
                $type = is_numeric($value) && $value !== '' ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="green-market-prices.xls"');
        echo $xml;
        exit;
    }
}
