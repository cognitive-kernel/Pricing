<?php
declare(strict_types=1);
namespace App\Core;
final class Auth {
    public static function login(string $username,string $password):bool{
        $s=Database::connection()->prepare('SELECT TOP 1 id,username,name,password,is_active FROM users WHERE username=:username');
        $s->execute(['username'=>$username]);$u=$s->fetch();
        if(!$u||!(bool)$u['is_active']||!password_verify($password,(string)$u['password']))return false;
        session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['user_name']=(string)$u['name'];return true;
    }
    public static function userId():?int{return isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;}
    public static function check():bool{return self::userId()!==null;}
    public static function requireLogin():void{if(!self::check())Security::redirect('/login');}
    public static function logout():never{$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}session_destroy();Security::redirect('/login');}
}
