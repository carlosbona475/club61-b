<?php

declare(strict_types=1);

/**
 * Funções nativas do PHP 8+ para hospedagem ainda em 7.4 (evita fatal no login / .env).
 */
if (!function_exists('str_starts_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_starts_with($haystack, $needle): bool
    {
        $needle = (string) $needle;

        return $needle === '' || strncmp((string) $haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_contains($haystack, $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos((string) $haystack, (string) $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_ends_with($haystack, $needle): bool
    {
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);

        return $len <= strlen((string) $haystack) && substr_compare((string) $haystack, $needle, -$len) === 0;
    }
}
