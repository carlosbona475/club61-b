<?php

declare(strict_types=1);

/**
 * Saída HTML segura (XSS).
 */
function club61_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
