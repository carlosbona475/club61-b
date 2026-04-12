<?php

declare(strict_types=1);

/**
 * Raiz do projeto: DOCUMENT_ROOT em HTTP, ou pasta pai de config/ em CLI.
 */
if (!defined('CLUB61_ROOT')) {
    $doc = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
    $doc = rtrim(str_replace('\\', '/', $doc), '/');
    if ($doc === '') {
        $doc = dirname(__DIR__);
    }
    define('CLUB61_ROOT', $doc);
}

if (!defined('CLUB61_BASE_PATH')) {
    define('CLUB61_BASE_PATH', CLUB61_ROOT);
}
