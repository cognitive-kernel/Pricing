<?php
declare(strict_types=1);
namespace App\Core;
use App\Services\AuditService;
final class Auth{
 public static function login(string $username,string $password):bool{
  $pdo=Database::connection();$s=$pdo->prepare('SELECT TOP 1 id,username,name,password,is_active,failed_login_count,locked_until FROM users WHERE username=:username');$s->execute(['username'=>$username]);$u=$s->fetch();
  if(!$u||!(bool)$u['is_active']){if($u)AuditService::log((int)$u['id'],'login.denied','user',(string)$u['id']);return false;}
  if($u['locked_until']!==null && strtotime((string)$u['locked_until'])>time()){AuditService::log((int)$u['id'],'login.locked','user',(string)$u['id']);return false;}
  if(!password_verify($password,(string)$u['password'])){$fails=(int)$u['failed_login_count']+1;$locked=$fails>=5?date('Y-m-d H:i:s',time()+900):null;$s=$pdo->prepare('UPDATE users SET failed_login_count=:f,locked_until=:l,updated_at=SYSDATETIME() WHERE id=:id');$s->execute(['f'=>$fails,'l'=>$locked,'id'=>$u['id']]);return false;}
  session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['user_name']=(string)$u['name'];$s=$pdo->prepare('UPDATE users SET failed_login_count=0,locked_until=NULL,updated_at=SYSDATETIME() WHERE id=:id');$s->execute(['id'=>$u['id']]);$hash=hash('sha256',session_id());$s=$pdo->prepare('INSERT INTO active_sessions(user_id,session_hash,ip_address,user_agent,expires_at) VALUES(:u,:h,:ip,:ua,:e)');$s->execute(['u'=>$u['id'],'h'=>$hash,'ip'=>$_SERVER['REMOTE_ADDR']??null,'ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000),'e'=>date('Y-m-d H:i:s',time()+86400)]);AuditService::log((int)$u['id'],'login.success','user',(string)$u['id']);return true;
 }
 public static function userId():?int{return isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;}
 public static function check():bool{if(self::userId()===null)return false;$pdo=Database::connection();$s=$pdo->prepare('SELECT id FROM active_sessions WHERE session_hash=:h AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>SYSDATETIME())');$s->execute(['h'=>hash('sha256',session_id())]);if($s->fetchColumn()===false){$_SESSION=[];return false;}$s=$pdo->prepare('UPDATE active_sessions SET last_seen_at=SYSDATETIME() WHERE session_hash=:h');$s->execute(['h'=>hash('sha256',session_id())]);return true;}
 public static function requireLogin():void{if(!self::check())Security::redirect('/login');}
 public static function logout():never{$pdo=Database::connection();$s=$pdo->prepare('UPDATE active_sessions SET revoked_at=SYSDATETIME() WHERE session_hash=:h');$s->execute(['h'=>hash('sha256',session_id())]);$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}session_destroy();Security::redirect('/login');}
}