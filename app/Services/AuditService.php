<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class AuditService{
 public static function log(?int $userId,string $action,?string $entityType=null,?string $entityId=null,mixed $before=null,mixed $after=null):void{
  $s=Database::connection()->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,before_data,after_data,ip_address,user_agent) VALUES(:u,:a,:t,:i,:b,:af,:ip,:ua)');
  $s->execute(['u'=>$userId,'a'=>$action,'t'=>$entityType,'i'=>$entityId,'b'=>$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE),'af'=>$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE),'ip'=>$_SERVER['REMOTE_ADDR']??null,'ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000)]);
 }
}