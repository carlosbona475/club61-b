<?php

declare(strict_types=1);

define('CLUB61_BASE_PATH', dirname(__DIR__));

require_once CLUB61_BASE_PATH . '/config/security_headers.php';
require_once CLUB61_BASE_PATH . '/config/session.php';
club61_security_headers();
club61_session_bootstrap();

$autoload = CLUB61_BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Club61\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = CLUB61_BASE_PATH . '/app/' . $rel . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

use Club61\Core\Application;
use Club61\Core\Container;

$container = new Container();
Application::registerBindings($container);
Application::init($container);
