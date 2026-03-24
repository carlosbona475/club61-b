<?php

declare(strict_types=1);

/**
 * Rate limiting com ficheiro + flock (por IP).
 */

function club61_rate_limit_dir(): string
{
    $base = defined('CLUB61_BASE_PATH') ? CLUB61_BASE_PATH : dirname(__DIR__);
    $dir = $base . '/storage/rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function club61_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = is_string($ip) ? trim($ip) : '';
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }

    return $ip;
}

/**
 * Consome uma tentativa numa janela fixa (ex.: POSTs de login).
 *
 * @return array{allowed: bool, retry_after: int}
 */
function club61_rate_limit_consume(string $bucket, int $maxPerWindow, int $windowSeconds): array
{
    $dir = club61_rate_limit_dir();
    $key = hash('sha256', club61_client_ip() . '|' . $bucket);
    $path = $dir . '/' . $key . '.json';
    $now = time();

    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return ['allowed' => true, 'retry_after' => 0];
        }
        $raw = stream_get_contents($fp);
        $state = ['count' => 0, 'window_start' => $now];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $state['count'] = (int) ($decoded['count'] ?? 0);
                $state['window_start'] = (int) ($decoded['window_start'] ?? $now);
            }
        }

        if ($now - $state['window_start'] >= $windowSeconds) {
            $state['count'] = 0;
            $state['window_start'] = $now;
        }

        if ($state['count'] >= $maxPerWindow) {
            flock($fp, LOCK_UN);

            return ['allowed' => false, 'retry_after' => $state['window_start'] + $windowSeconds - $now];
        }

        ++$state['count'];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }

    return ['allowed' => true, 'retry_after' => 0];
}

/**
 * Após credenciais inválidas: acumula falhas e bloqueia após limite.
 *
 * @return array{blocked: bool, retry_after: int}
 */
function club61_login_rate_record_failure(int $maxFailures = 8, int $lockoutSeconds = 900): array
{
    $dir = club61_rate_limit_dir();
    $key = hash('sha256', club61_client_ip() . '|login_fail');
    $path = $dir . '/' . $key . '.json';
    $now = time();

    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return ['blocked' => false, 'retry_after' => 0];
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return ['blocked' => false, 'retry_after' => 0];
        }
        $raw = stream_get_contents($fp);
        $state = ['failures' => 0, 'first_at' => $now, 'locked_until' => 0];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $state['failures'] = (int) ($decoded['failures'] ?? 0);
                $state['first_at'] = (int) ($decoded['first_at'] ?? $now);
                $state['locked_until'] = (int) ($decoded['locked_until'] ?? 0);
            }
        }

        if ($state['locked_until'] > $now) {
            flock($fp, LOCK_UN);

            return ['blocked' => true, 'retry_after' => $state['locked_until'] - $now];
        }

        if ($now - $state['first_at'] > $lockoutSeconds) {
            $state['failures'] = 0;
            $state['first_at'] = $now;
        }

        ++$state['failures'];
        $blocked = false;
        $retry = 0;
        if ($state['failures'] >= $maxFailures) {
            $state['locked_until'] = $now + $lockoutSeconds;
            $state['failures'] = 0;
            $state['first_at'] = $now;
            $blocked = true;
            $retry = $lockoutSeconds;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
        flock($fp, LOCK_UN);

        return ['blocked' => $blocked, 'retry_after' => $retry];
    } finally {
        fclose($fp);
    }
}

function club61_login_rate_is_locked(): array
{
    $dir = club61_rate_limit_dir();
    $key = hash('sha256', club61_client_ip() . '|login_fail');
    $path = $dir . '/' . $key . '.json';
    if (!is_readable($path)) {
        return ['locked' => false, 'retry_after' => 0];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return ['locked' => false, 'retry_after' => 0];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['locked' => false, 'retry_after' => 0];
    }
    $until = (int) ($decoded['locked_until'] ?? 0);
    $now = time();
    if ($until > $now) {
        return ['locked' => true, 'retry_after' => $until - $now];
    }

    return ['locked' => false, 'retry_after' => 0];
}

function club61_login_rate_reset(): void
{
    $dir = club61_rate_limit_dir();
    $key = hash('sha256', club61_client_ip() . '|login_fail');
    $path = $dir . '/' . $key . '.json';
    if (is_file($path)) {
        @unlink($path);
    }
}
