<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Meridian HR needs PHP 8.0 or newer. In Hostinger hPanel open Advanced → PHP Configuration and choose PHP 8.2 or newer.');
}

const APP_NAME    = 'Meridian HR';
const APP_VERSION = '1.0.0';

define('APP_PATH', __DIR__);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'controllers'] as $dir) {
        $file = APP_PATH . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_PATH . '/helpers.php';

set_exception_handler([ErrorHandler::class, 'handle']);
set_error_handler([ErrorHandler::class, 'php']);

date_default_timezone_set('Asia/Qatar');
