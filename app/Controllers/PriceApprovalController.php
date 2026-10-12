<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\PermissionService;
use App\Services\RahkaranPriceService;
use PDO;

final class PriceApprovalController
{
    public function index(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');

        $q = trim((string) ($_GET['q'] ?? ''));
        $pdo = Database::connection();
        $sql = "WITH ranked AS (
            SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,
                   m.CreatedDate,m.PaymentAgreementID,m.discountperc,m.grossprice,m.opration,
                   m.netamount,m.payagrementperc,m.userid,m.Approved,m.isexported,
                   ROW_NUMBER() OVER (PARTITION BY m.ProductCode ORDER BY m.InquiryDate DESC,m.PriceMonitorID DESC) AS rn
            FROM dbo.ProductPriceMonitor m
        )
        SELECT * FROM ranked
        WHERE rn=1 AND ISNULL(Approved,0)=0";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (CONVERT(varchar(100),ProductCode) LIKE :q OR Source LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY InquiryDate DESC,ProductCode";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $agreementIds = array_values(array_unique(array_map(static fn(array $r): int => (int)($r['PaymentAgreementID'] ?? 0), $records)));
        $agreements = RahkaranPriceService::instance()->paymentAgreementTitlesByIds($agreementIds);
        foreach ($records as &$record) {
            $record['PaymentAgreementTitle'] = $agreements[(int)($record['PaymentAgreementID'] ?? 0)] ?? '';
        }
        unset($record);
        View::render('prices/approvals', compact('records','q'));
    }

    public function decision(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');
        Security::verifyCsrf();

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        $decision = (string)($_POST['decision'] ?? '');
        if (!$ids || !in_array($decision, ['approve','reject'], true)) {
            http_response_code(422);
            exit('حداقل یک ردیف و نوع تصمیم معتبر انتخاب کنید.');
        }

        $approved = $decision === 'approve' ? 1 : 2;
        $pdo = Database::connection();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE m SET Approved=?
        FROM dbo.ProductPriceMonitor m
        INNER JOIN (
            SELECT PriceMonitorID,
                   ROW_NUMBER() OVER (PARTITION BY ProductCode ORDER BY InquiryDate DESC,PriceMonitorID DESC) AS rn
            FROM dbo.ProductPriceMonitor
        ) latest ON latest.PriceMonitorID=m.PriceMonitorID
        WHERE latest.rn=1 AND ISNULL(m.Approved,0)=0 AND m.PriceMonitorID IN ($marks)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$approved], $ids));
        Security::redirect('/prices/approvals');
    }

    public function excel(): never
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');
        $pdo = Database::connection();
        $sql = "WITH ranked AS (
            SELECT m.PriceMonitorID,m.ProductCode,m.InquiryDate,m.Source,m.Price,m.UnitID,
                   m.PaymentAgreementID,m.discountperc,m.grossprice,m.opration,m.netamount,
                   m.payagrementperc,m.userid,m.CreatedDate,
                   ROW_NUMBER() OVER (PARTITION BY m.ProductCode ORDER BY m.InquiryDate DESC,m.PriceMonitorID DESC) AS rn
            FROM dbo.ProductPriceMonitor m
        )
        SELECT * FROM ranked
        WHERE rn=1 AND Approved=1 AND ISNULL(isexported,0)=0
        ORDER BY ProductCode";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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

        if ($exportedIds) {
            $marks = implode(',', array_fill(0, count($exportedIds), '?'));
            $update = $pdo->prepare("UPDATE dbo.ProductPriceMonitor SET isexported=1 WHERE Approved=1 AND ISNULL(isexported,0)=0 AND PriceMonitorID IN ($marks)");
            $update->execute($exportedIds);
        }
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="approved-market-prices.xls"');
        echo $xml;
        exit;
    }
}
