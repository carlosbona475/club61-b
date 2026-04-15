<?php
declare(strict_types=1);

use Club61\Core\Application;
use Club61\Core\Container;

if (!defined('CLUB61_BASE_PATH')) {
    define('CLUB61_BASE_PATH', dirname(__DIR__));
}

$composerAutoload = CLUB61_BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Club61\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $baseApp = CLUB61_BASE_PATH . '/app/';
        $candidates = [
            $baseApp . preg_replace('#^Controllers/#', 'controllers/', $relative) . '.php',
            $baseApp . preg_replace('#^Services/#', 'services/', $relative) . '.php',
            $baseApp . $relative . '.php',
            $baseApp . strtolower($relative) . '.php',
            $baseApp . preg_replace('#^Http/Controllers/#', 'Http/Controllers/', $relative) . '.php',
            $baseApp . preg_replace('#^Http/Middleware/#', 'Http/Middleware/', $relative) . '.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    });
}

$container = new Container();
Application::init($container);
Application::registerBindings($container);