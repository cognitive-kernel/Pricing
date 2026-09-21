<?php
declare(strict_types=1);
namespace App\Core;
final class Router {
    private array $routes=[];
    public function get(string $path,callable|array $handler):void{$this->add('GET',$path,$handler);}
    public function post(string $path,callable|array $handler):void{$this->add('POST',$path,$handler);}
    private function add(string $method,string $path,callable|array $handler):void{$this->routes[]=[$method,$path,$handler];}
    public function dispatch(string $method,string $uri):void{
        $path=parse_url($uri,PHP_URL_PATH)?:'/';
        $base=rtrim(dirname($_SERVER['SCRIPT_NAME']??'/'),'/\\');
        if($base!==''&&$base!=='/'&&str_starts_with($path,$base))$path=substr($path,strlen($base))?:'/';
        $path='/'.trim($path,'/');if($path!=='/')$path=rtrim($path,'/');
        foreach($this->routes as [$rm,$rp,$handler]){
            if($rm!==$method)continue;
            $pattern='#^'.preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[^/]+)',rtrim($rp,'/')).'/?$#';
            if(preg_match($pattern,$path,$m)){
                $params=[];foreach($m as $k=>$v)if(!is_int($k))$params[]=ctype_digit($v)?(int)$v:$v;
                if(is_array($handler)){[$class,$action]=$handler;(new $class())->$action(...$params);}else $handler(...$params);return;
            }
        }
        http_response_code(404);echo 'صفحه مورد نظر یافت نشد.';
    }
}
