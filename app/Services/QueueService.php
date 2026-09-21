<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class QueueService{
 public static function rebuild():int{
  $pdo=Database::connection();$pdo->exec("DELETE FROM price_queue WHERE status IN('new','in_progress')");
  $sql="WITH latest AS(SELECT * FROM(SELECT pp.*,ROW_NUMBER() OVER(PARTITION BY product_id,source_no ORDER BY price_received_at DESC,id DESC) rn FROM product_prices pp)x WHERE rn=1),s AS(SELECT p.id,p.expected_sources,p.importance,MAX(l.price_received_at) latest_date,COUNT(l.id) source_count,MIN(l.unit_price) min_price,MAX(l.unit_price) max_price,AVG(l.unit_price) avg_price FROM products p LEFT JOIN latest l ON l.product_id=p.id WHERE p.is_active=1 GROUP BY p.id,p.expected_sources,p.importance) SELECT * FROM s WHERE latest_date IS NULL OR DATEDIFF(DAY,latest_date,SYSDATETIME())>=7 OR source_count<expected_sources OR (avg_price>0 AND (max_price-min_price)/avg_price>=.10)";
  $rows=$pdo->query($sql)->fetchAll();$ins=$pdo->prepare("INSERT INTO price_queue(product_id,score,priority_level,reason,status) VALUES(:p,:s,:l,:r,'new')");
  foreach($rows as $r){$fresh=100;if($r['latest_date']!==null){$days=max(0,(int)((time()-strtotime($r['latest_date']))/86400));$fresh=$days>=30?80:($days>=15?50:($days>=7?25:0));}$missing=max(0,(int)$r['expected_sources']-(int)$r['source_count'])*15;$spread=0;if((float)$r['avg_price']>0){$x=((float)$r['max_price']-(float)$r['min_price'])/(float)$r['avg_price'];$spread=$x>=.20?30:($x>=.10?15:($x>=.05?8:0));}$mult=match((int)$r['importance']){3=>1.5,2=>1.25,default=>1.0};$score=($fresh+$missing+$spread)*$mult;$level=$score>=100?'critical':($score>=75?'very_high':($score>=50?'high':($score>=25?'medium':'normal')));$reason=[];if($r['latest_date']===null)$reason[]='بدون قیمت';if($r['latest_date']!==null&&((time()-strtotime($r['latest_date']))/86400)>=30)$reason[]='قیمت قدیمی';if($missing>0)$reason[]='منابع ناقص';if($spread>0)$reason[]='اختلاف قیمت';$ins->execute(['p'=>$r['id'],'s'=>$score,'l'=>$level,'r'=>implode('، ',$reason)]);}return count($rows);
 }
}