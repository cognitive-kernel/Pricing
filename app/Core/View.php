<?php
declare(strict_types=1);
namespace App\Core;
final class View {
    public static function render(string $name,array $data=[]):void{
        extract($data,EXTR_SKIP);$file=BASE_PATH.'/views/'.$name.'.php';
        if(!is_file($file))throw new \RuntimeException('View not found.');
        require $file;
    }
}
