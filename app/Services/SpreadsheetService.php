<?php
declare(strict_types=1);
namespace App\Services;
final class SpreadsheetService{
 public static function rows(string $path):array{
  $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
  if($ext==='csv'){$h=fopen($path,'rb');if(!$h)throw new \RuntimeException('فایل قابل خواندن نیست.');$rows=[];while(($row=fgetcsv($h))!==false)$rows[]=$row;fclose($h);return $rows;}
  if(!in_array($ext,['xlsx'],true))throw new \RuntimeException('فرمت فایل پشتیبانی نمی‌شود. CSV یا XLSX مجاز است.');
  if(!class_exists('ZipArchive'))throw new \RuntimeException('افزونه ZipArchive برای XLSX فعال نیست.');
  $zip=new \ZipArchive();if($zip->open($path)!==true)throw new \RuntimeException('فایل XLSX معتبر نیست.');
  $shared=[];$ss=$zip->getFromName('xl/sharedStrings.xml');if($ss!==false){$xml=simplexml_load_string($ss);foreach($xml->si as $si){$shared[]=(string)implode('',array_map('strval',$si->t));}}
  $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();if($sheet===false)throw new \RuntimeException('Sheet اول فایل XLSX یافت نشد.');
  $xml=simplexml_load_string($sheet);$out=[];
  foreach($xml->sheetData->row as $row){$cells=[];foreach($row->c as $cell){$ref=(string)$cell['r'];$col=preg_replace('/\d+/','',$ref);$idx=0;for($i=0;$i<strlen($col);$i++)$idx=$idx*26+(ord(strtoupper($col[$i]))-64);$idx--; $type=(string)$cell['t'];$value=(string)$cell->v;if($type==='s')$value=$shared[(int)$value]??'';elseif($type==='inlineStr')$value=(string)$cell->is->t;$cells[$idx]=$value;}if($cells){$max=max(array_keys($cells));$line=array_fill(0,$max+1,'');foreach($cells as $i=>$v)$line[$i]=$v;$out[]=$line;}}
  return $out;
 }
}
