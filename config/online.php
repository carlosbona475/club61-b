<?php

declare(strict_types=1);

/**
 * Considera online quem atualizou presença dentro da janela (padrão: 60s — feed; salas podem usar 180s).
 */
function isUserOnline(?string $lastSeen, int $maxAgeSeconds = 60): bool
{
    if ($lastSeen === null || trim($lastSeen) === '') {
        return false;
    }

    try {
        $ts = (new DateTimeImmutable($lastSeen))->getTimestamp();
    } catch (Exception $e) {
        return false;
    }

    return (time() - $ts) < $maxAgeSeconds;
}
