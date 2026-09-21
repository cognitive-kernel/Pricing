<?php
declare(strict_types=1);
function env(string $key, mixed $default = null): mixed
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $file = BASE_PATH . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k,$v] = explode('=', $line, 2);
                $values[trim($k)] = trim($v, " \t\n\r\0\x0B\"");
            }
        }
    }
    if (array_key_exists($key,$values)) {
        $v=$values[$key];
        if (strtolower($v)==='true') return true;
        if (strtolower($v)==='false') return false;
        return $v;
    }
    $v=getenv($key);
    return $v===false?$default:$v;
}
define('APP_NAME','Pricing');
