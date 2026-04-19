<?php

declare(strict_types=1);

/**
 * JSON seguro para embutir em HTML/JS: se json_encode falhar (ex.: UTF-8 inválido), devolve "null"
 * em vez de string vazia — evita SyntaxError "Unexpected end of input" no browser.
 *
 * @param mixed $value
 */
function club61_json_for_script($value): string
{
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $s = json_encode($value, $flags);

    return $s !== false ? $s : 'null';
}

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
