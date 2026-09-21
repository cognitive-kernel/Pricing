<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));
date_default_timezone_set((string)(getenv('APP_TIMEZONE') ?: 'Asia/Tehran'));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

require BASE_PATH . '/config/config.php';
$secure = (bool) env('SESSION_SECURE_COOKIE', false);
session_name((string) env('SESSION_NAME', 'pricing_session'));
session_set_cookie_params([
    'lifetime' => (int) env('SESSION_LIFETIME', 120) * 60,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
if (!isset($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));

set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    http_response_code(500);
    echo (bool)env('APP_DEBUG', false)
        ? '<pre>'.htmlspecialchars((string)$e, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</pre>'
        : 'خطای داخلی سرور.';
});
