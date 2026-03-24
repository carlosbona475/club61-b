<?php

declare(strict_types=1);

/**
 * Sanitização de entrada (normalização; validação em validation.php).
 */
function club61_str(mixed $v, int $maxLen = 65535): string
{
    $s = is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
    $s = trim($s);
    if ($maxLen > 0 && strlen($s) > $maxLen) {
        $s = substr($s, 0, $maxLen);
    }

    return $s;
}

function club61_int(mixed $v, int $default = 0, ?int $min = null, ?int $max = null): int
{
    if (is_int($v)) {
        $n = $v;
    } elseif (is_string($v) && $v !== '' && preg_match('/^-?\d+$/', $v)) {
        $n = (int) $v;
    } else {
        return $default;
    }
    if ($min !== null && $n < $min) {
        return $default;
    }
    if ($max !== null && $n > $max) {
        return $default;
    }

    return $n;
}

function club61_email(mixed $v): string
{
    $e = club61_str($v, 320);
    $e = strtolower($e);

    return $e;
}
