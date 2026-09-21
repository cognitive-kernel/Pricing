<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$router = new App\Core\Router();
require dirname(__DIR__) . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
