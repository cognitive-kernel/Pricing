<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;
use App\Core\Database;
use App\Services\PermissionService;
use App\Services\RahkaranPriceService;
final class MarketGreenExportController {
 public function excel(): never {
  Auth::requireLogin(); PermissionService::require(Auth::userId(),'prices.view');
  $rows=RahkaranPriceService::instance()->allRows(); $pdo=Database::connection();
  $latest=[]; foreach($pdo->query('SELECT m.ProductCode,m.Price,m.InquiryDate FROM ProductPriceMonitor m INNER JOIN (SELECT ProductCode,MAX(InquiryDate) InquiryDate FROM ProductPriceMonitor GROUP BY ProductCode) x ON x.ProductCode=m.ProductCode AND x.InquiryDate=m.InquiryDate')->fetchAll() as $r) $latest[trim((string)$r['ProductCode'])]=$r;
  $headers=['کد محصول','عنوان محصول','کد گروه محصول','واحد سنجش','عامل کنترل موجودی اول','عامل کنترل موجودی دوم','عامل کنترل موجودی سوم','عامل کنترل موجودی چهارم','عامل کنترل موجودی پنجم','بسته بندی','تا مقدار','فی','قطعیت','تلرانس کاهش','تلرانس افزایش'];
  $xml='<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="رصد بازار"><Table><Row>';
  foreach($headers as $h) $xml.='<Cell><Data ss:Type="String">'.htmlspecialchars($h,ENT_XML1|ENT_QUOTES,'UTF-8').'</Data></Cell>';
  $xml.='</Row>';
  foreach($rows as $r){$code=trim((string)($r['Code']??''));$m=$latest[$code]??null;if(!$m)continue;$d=$m['InquiryDate'];if($d instanceof \DateTimeInterface)$d=$d->format('Y-m-d H:i:s');if((int)((time()-strtotime((string)$d))/86400)>=7)continue;$vals=[$code,(string)($r['Name']??''),'',(string)($r['Announcement_unit']??''),'','','','','','','',$m['Price']??'','','',''];$xml.='<Row>';foreach($vals as $v){$type=is_numeric($v)&&$v!==''?'Number':'String';$xml.='<Cell><Data ss:Type="'.$type.'">'.htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8').'</Data></Cell>';} $xml.='</Row>';}
  $xml.='</Table></Worksheet></Workbook>'; header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="green-market-prices.xls"'); echo $xml; exit;
 }
}
