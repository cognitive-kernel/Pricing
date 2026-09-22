<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\Security;use App\Core\View;final class AuthController {
 public function login():void{if(Auth::check())Security::redirect('/prices');$error=null;if($_SERVER['REQUEST_METHOD']==='POST'){Security::verifyCsrf();$u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');if($u===''||$p===''||!Auth::login($u,$p))$error='نام کاربری یا رمز عبور صحیح نیست.';else Security::redirect('/prices');}View::render('auth/login',compact('error'));}
 public function logout():void{Security::verifyCsrf();Auth::logout();}
 public function password():void{Auth::requireLogin();$error=null;$success=null;if($_SERVER['REQUEST_METHOD']==='POST'){Security::verifyCsrf();$current=(string)($_POST['current_password']??'');$password=(string)($_POST['password']??'');$confirmation=(string)($_POST['password_confirmation']??'');if(strlen($password)<8||!hash_equals($password,$confirmation))$error='رمز جدید حداقل ۸ کاراکتر و تکرار آن باید یکسان باشد.';else{$s=Database::connection()->prepare('SELECT password FROM users WHERE id=:id AND is_active=1');$s->execute(['id'=>Auth::userId()]);$hash=$s->fetchColumn();if(!$hash||!password_verify($current,(string)$hash))$error='رمز عبور فعلی صحیح نیست.';else{$s=Database::connection()->prepare('UPDATE users SET password=:password,updated_at=GETDATE() WHERE id=:id');$s->execute(['password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>Auth::userId()]);$success='رمز عبور تغییر کرد.';}}}View::render('auth/password',compact('error','success'));}
}
