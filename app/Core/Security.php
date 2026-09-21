<?php
declare(strict_types=1);
namespace App\Core;
final class Security {
    public static function e(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function csrf():string{return (string)($_SESSION['_csrf']??'');}
    public static function verifyCsrf():void{
        $token=(string)($_POST['_csrf']??'');
        if($token===''||!hash_equals(self::csrf(),$token)){http_response_code(419);exit('درخواست نامعتبر است.');}
    }
    public static function redirect(string $path):never{header('Location: '.$path);exit;}
}
