<?php

declare(strict_types=1);

/**
 * Considera online quem atualizou presença nos últimos 60 segundos.
 */
function isUserOnline(?string $lastSeen): bool
{
    if ($lastSeen === null || trim($lastSeen) === '') {
        return false;
    }

    try {
        $ts = (new DateTimeImmutable($lastSeen))->getTimestamp();
    } catch (Exception $e) {
        return false;
    }

    return (time() - $ts) < 60;
}
