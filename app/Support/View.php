<?php

declare(strict_types=1);

namespace Club61\Support;

final class View
{
    public static function render(string $name, array $data = []): void
    {
        $path = \CLUB61_BASE_PATH . '/resources/views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($path)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

            return;
        }
        extract($data, EXTR_SKIP);
        require $path;
    }
}
