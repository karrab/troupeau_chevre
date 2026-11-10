<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\View;

define('ROOT_PATH', dirname(__DIR__));

$config = require ROOT_PATH . '/config/config.php';
$GLOBALS['__config'] = $config;

date_default_timezone_set($config['app']['timezone']);
mb_internal_encoding('UTF-8');

$isDev = $config['app']['env'] === 'dev';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/storage/logs/php-error.log');

// Autoload : Composer si présent (Dompdf), sinon autoloader PSR-4 minimal
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
if (!function_exists('e')) {
    require ROOT_PATH . '/app/helpers.php';
}

Database::configure($config['db']);
Cache::configure($config['app']['cache_prefix'] . $config['db']['name'] . ':', 300);
View::setBase(ROOT_PATH . '/views');

if (PHP_SAPI !== 'cli') {
    Auth::startSession($config['app']['session_name']);
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
