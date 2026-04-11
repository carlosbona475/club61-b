<?php

declare(strict_types=1);

/**
 * Rate limiting via sessão (compatível com hospedagem compartilhada).
 */

function club61_rate_limit_consume(string $bucket, int $maxPerWindow, int $windowSeconds): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $key = 'rl_' . $bucket;
    $now = time();
    $data = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];

    if (($now - $data['start']) > $windowSeconds) {
        $data = ['count' => 0, 'start' => $now];
    }

    $data['count']++;
    $_SESSION[$key] = $data;

    $allowed = $data['count'] <= $maxPerWindow;
    $retryAfter = $allowed ? 0 : ($data['start'] + $windowSeconds - $now);

    return ['allowed' => $allowed, 'retry_after' => max(0, $retryAfter)];
}

function club61_login_rate_is_locked(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $data = $_SESSION['rl_login_failures'] ?? ['count' => 0, 'start' => time()];
    $locked = ($data['count'] ?? 0) >= 8;
    $retryAfter = $locked ? max(0, ($data['start'] + 900) - time()) : 0;

    if ($retryAfter <= 0 && $locked) {
        unset($_SESSION['rl_login_failures']);
        return ['locked' => false, 'retry_after' => 0];
    }

    return ['locked' => $locked, 'retry_after' => $retryAfter];
}

function club61_login_rate_record_failure(int $max, int $windowSeconds): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $now = time();
    $data = $_SESSION['rl_login_failures'] ?? ['count' => 0, 'start' => $now];

    if (($now - $data['start']) > $windowSeconds) {
        $data = ['count' => 0, 'start' => $now];
    }

    $data['count']++;
    $_SESSION['rl_login_failures'] = $data;

    $blocked = $data['count'] >= $max;
    $retryAfter = $blocked ? max(0, ($data['start'] + $windowSeconds) - $now) : 0;

    return ['blocked' => $blocked, 'retry_after' => $retryAfter];
}

function club61_login_rate_reset(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    unset($_SESSION['rl_login_failures']);
}
