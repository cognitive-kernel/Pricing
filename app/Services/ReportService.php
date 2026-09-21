<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class ReportService{
 public static function dashboard():array{
  $pdo=Database::connection();$q=fn(string $sql)=>(int)$pdo->query($sql)->fetchColumn();
  return ['products'=>$q("SELECT COUNT(*) FROM products WHERE is_active=1"),'without_price'=>$q("SELECT COUNT(*) FROM products p WHERE p.is_active=1 AND NOT EXISTS(SELECT 1 FROM product_prices x WHERE x.product_id=p.id)"),'expired'=>$q("SELECT COUNT(*) FROM products p WHERE p.is_active=1 AND EXISTS(SELECT 1 FROM product_prices x WHERE x.product_id=p.id) AND DATEDIFF(DAY,(SELECT MAX(y.price_received_at) FROM product_prices y WHERE y.product_id=p.id),SYSDATETIME())>=30"),'queue'=>$q("SELECT COUNT(*) FROM price_queue WHERE status IN('new','in_progress')"),'critical'=>$q("SELECT COUNT(*) FROM price_queue WHERE priority_level='critical' AND status IN('new','in_progress')"),'suppliers'=>$q("SELECT COUNT(*) FROM suppliers WHERE is_active=1")];
 }
 public static function prices():array{return Database::connection()->query("SELECT p.product_code,p.product_name,u.name unit_name,MAX(pp.price_received_at) latest_date,MIN(pp.unit_price) min_price,MAX(pp.unit_price) max_price,AVG(pp.unit_price) avg_price FROM products p JOIN units u ON u.id=p.unit_id LEFT JOIN product_prices pp ON pp.product_id=p.id GROUP BY p.product_code,p.product_name,u.name ORDER BY p.product_code")->fetchAll();}
}