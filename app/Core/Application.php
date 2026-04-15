<?php

declare(strict_types=1);

namespace Club61\Core;

final class Application
{
    private static ?Container $container = null;

    public static function init(Container $container): void
    {
        self::$container = $container;
    }

    public static function registerBindings(Container $container): void
    {
        $container->set('app.booted_at', time());
    }

    public static function getContainer(): ?Container
    {
        return self::$container;
    }

    public static function runHttp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['access_token']) && $_SESSION['access_token'] !== '') {
            header('Location: /features/feed/index.php');
            exit;
        }

        header('Location: /features/auth/login.php');
        exit;
    }
}
