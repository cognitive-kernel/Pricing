<?php
declare(strict_types=1);
$routes=file_get_contents(__DIR__.'/../routes/web.php');
$seed=file_get_contents(__DIR__.'/../database/seed.php');
$errors=[];
preg_match_all("/\[([A-Za-z]+Controller)::class,'([A-Za-z0-9_]+)'\]/",$routes,$matches,PREG_SET_ORDER);
$controllerMethods=[];
foreach(glob(__DIR__.'/../app/Controllers/*Controller.php') as $file){$name=basename($file,'.php');$src=file_get_contents($file);preg_match_all('/public function ([A-Za-z0-9_]+)\s*\(/',$src,$m);$controllerMethods[$name]=array_flip($m[1]);preg_match_all("/(?:PermissionService::(?:require|allows)\([^,]+,\s*['\"]([^'\"]+)['\"])/",$src,$pm);foreach($pm[1] as $code)if(!preg_match("/['\"]".preg_quote($code,'/')."['\"]\s*=>/",$seed))$errors[]="Permission used but not seeded: {$code} ({$name})";}
foreach($matches as $m)if(!isset($controllerMethods[$m[1]][$m[2]]))$errors[]="Route references missing method: {$m[1]}::{$m[2]}";
foreach(glob(__DIR__.'/../app/Controllers/*Controller.php') as $file){$src=file_get_contents($file);preg_match_all("/View::render\('([^']+)'/",$src,$views);foreach(array_unique($views[1]) as $view)if(!is_file(__DIR__.'/../views/'.$view.'.php'))$errors[]="Missing view wrapper: {$view}.php";}
preg_match_all("/\[['\"]([^'\"]+)['\"],['\"]([^'\"]+)['\"],['\"]\/pricing\/public\/[^'\"]+['\"]\]/",file_get_contents(__DIR__.'/../views/layout.php'),$mm,PREG_SET_ORDER);
foreach($mm as $m)if(!preg_match("/['\"]".preg_quote($m[1],'/')."['\"]\s*=>/",$seed))$errors[]="Menu permission not seeded: {$m[1]}";
if($errors){fwrite(STDERR,implode(PHP_EOL,$errors).PHP_EOL);exit(1);}
echo "Static smoke test passed.".PHP_EOL;