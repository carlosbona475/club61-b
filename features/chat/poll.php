<?php

/**
 * GET /chat/poll — mensagens novas após `after` (polling HTTP; não depende de Realtime).
 * Query: sala_id (obrigatório), after (ISO opcional; padrão últimos ~5 s em UTC).
 *
 * Resposta: { ok: true, msgs: [...] } — msgs no formato enriquecido (author, reactions) como chat_messages.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/city_rooms.php';
require_once __DIR__ . '/chat_backend.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$salaId = trim((string) ($_GET['sala_id'] ?? ''));
if (club61_city_room_by_slug($salaId) === null) {
    echo json_encode(['ok' => false, 'msgs' => []], JSON_UNESCAPED_UNICODE);

    exit;
}

$after = trim((string) ($_GET['after'] ?? ''));
if ($after === '') {
    $after = gmdate('c', strtotime('-5 seconds'));
}

$rows = club61_chat_fetch_messages_for_sala($salaId, $after, 20);
$msgs = club61_chat_enrich_messages($rows);

echo json_encode(['ok' => true, 'msgs' => $msgs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
