<?php

declare(strict_types=1);

/**
 * Atualiza last_seen do perfil (service role). Usado por auth_guard e pelo middleware web.
 */
function club61_touch_last_seen(string $userId): void
{
    if ($userId === '' || !defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return;
    }

    $url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId);
    $body = json_encode(['last_seen' => gmdate('Y-m-d\TH:i:s\Z')], JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}
