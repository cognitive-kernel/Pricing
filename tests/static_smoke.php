<?php
declare(strict_types=1);

$routes = file_get_contents(__DIR__.'/../routes/web.php');
preg_match_all("/\[([A-Za-z]+Controller)::class,'([A-Za-z0-9_]+)'\]/", $routes, $matches, PREG_SET_ORDER);
$controllerFiles = glob(__DIR__.'/../app/Controllers/*Controller.php');
$controllerMethods = [];
foreach ($controllerFiles as $file) {
    $name = basename($file, '.php');
    $src = file_get_contents($file);
    preg_match_all('/public function ([A-Za-z0-9_]+)\s*\(/', $src, $m);
    $controllerMethods[$name] = array_flip($m[1]);
}
$errors = [];
foreach ($matches as $m) {
    $controller = $m[1];
    $method = $m[2];
    if (!isset($controllerMethods[$controller][$method])) {
        $errors[] = "Route references missing method: {$controller}::{$method}";
    }
}
foreach (glob(__DIR__.'/../app/Controllers/*Controller.php') as $file) {
    $src = file_get_contents($file);
    preg_match_all("/View::render\('([^']+)'/", $src, $views);
    foreach (array_unique($views[1]) as $view) {
        $base = __DIR__.'/../views/'.$view.'.php';
        if (!is_file($base)) {
            $errors[] = "Missing view wrapper: {$view}.php";
        }
    }
}
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
echo "Static smoke test passed.".PHP_EOL;
