<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class PermissionService {
 public static function allows(int $userId,string $code):bool{$pdo=Database::connection();$s=$pdo->prepare('SELECT allowed FROM user_permission_overrides WHERE user_id=:uid AND permission_code=:code');$s->execute(['uid'=>$userId,'code'=>$code]);$o=$s->fetchColumn();if($o!==false)return(bool)$o;$s=$pdo->prepare('SELECT 1 FROM group_permissions gp INNER JOIN group_user gu ON gu.group_id=gp.group_id INNER JOIN groups g ON g.id=gu.group_id AND g.is_active=1 WHERE gu.user_id=:uid AND gp.permission_code=:code');$s->execute(['uid'=>$userId,'code'=>$code]);return(bool)$s->fetchColumn();}
 public static function require(int $userId,string $code):void{if(!self::allows($userId,$code)){http_response_code(403);exit('دسترسی غیرمجاز.');}}
 public static function effective(int $userId):array{$pdo=Database::connection();$s=$pdo->prepare('SELECT DISTINCT gp.permission_code FROM group_permissions gp INNER JOIN group_user gu ON gu.group_id=gp.group_id WHERE gu.user_id=:uid');$s->execute(['uid'=>$userId]);$codes=array_column($s->fetchAll(),'permission_code');$s=$pdo->prepare('SELECT permission_code,allowed FROM user_permission_overrides WHERE user_id=:uid');$s->execute(['uid'=>$userId]);foreach($s->fetchAll() as $r){if((bool)$r['allowed'])$codes[]=$r['permission_code'];else$codes=array_values(array_diff($codes,[$r['permission_code']]));}return array_values(array_unique($codes));}
}
