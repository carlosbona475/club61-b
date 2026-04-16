<?php

/**
 * POST /follow/enviar | /follow/aceitar | /follow/recusar
 * JSON body (ou form): following_id, follower_id, csrf conforme ação.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/follows_status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);

    exit;
}

$input = [];
$ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input') ?: '';
    $d = json_decode($raw, true);
    if (is_array($d)) {
        $input = $d;
    }
}

$csrf = (string) ($input['csrf'] ?? $_POST['csrf'] ?? '');
if (!csrf_validate($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido']);

    exit;
}

$uid = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);

    exit;
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$action = '';
if (preg_match('#/follow/(enviar|aceitar|recusar|remover)/?$#', $path, $m)) {
    $action = $m[1];
}
if ($action === '') {
    $action = (string) ($_GET['r'] ?? '');
}

if ($action === 'enviar') {
    $followingId = trim((string) ($input['following_id'] ?? $_POST['following_id'] ?? ''));
    if ($followingId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $followingId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'following_id inválido']);

        exit;
    }
    if ($followingId === $uid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Não pode seguir a si mesmo']);

        exit;
    }
    $state = club61_follows_relation_state($uid, $followingId);
    if ($state === 'aceito') {
        echo json_encode([
            'success' => true,
            'state' => 'aceito',
            'followers_count' => club61_follows_count_followers_accepted($followingId),
        ]);

        exit;
    }
    if ($state === 'pendente') {
        echo json_encode(['success' => true, 'state' => 'pendente', 'followers_count' => club61_follows_count_followers_accepted($followingId)]);

        exit;
    }
    $r = club61_follows_enviar($uid, $followingId);
    if (!$r['ok']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $r['error'] ?? 'Falha ao enviar pedido']);

        exit;
    }
    echo json_encode([
        'success' => true,
        'state' => 'pendente',
        'followers_count' => (int) ($r['followers_count'] ?? club61_follows_count_followers_accepted($followingId)),
    ]);

    exit;
}

if ($action === 'aceitar') {
    $followerId = trim((string) ($input['follower_id'] ?? $_POST['follower_id'] ?? ''));
    if ($followerId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $followerId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'follower_id inválido']);

        exit;
    }
    $r = club61_follows_aceitar($uid, $followerId);
    if (!$r['ok']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Não foi possível aceitar']);

        exit;
    }
    echo json_encode([
        'success' => true,
        'followers_count' => club61_follows_count_followers_accepted($uid),
        'pending_count' => club61_follows_pending_incoming_count($uid),
    ]);

    exit;
}

if ($action === 'recusar') {
    $followerId = trim((string) ($input['follower_id'] ?? $_POST['follower_id'] ?? ''));
    if ($followerId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $followerId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'follower_id inválido']);

        exit;
    }
    $r = club61_follows_recusar($uid, $followerId);
    if (!$r['ok']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Não foi possível recusar']);

        exit;
    }
    echo json_encode([
        'success' => true,
        'pending_count' => club61_follows_pending_incoming_count($uid),
    ]);

    exit;
}

if ($action === 'remover') {
    $followingId = trim((string) ($input['following_id'] ?? $_POST['following_id'] ?? ''));
    if ($followingId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $followingId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'following_id inválido']);

        exit;
    }
    if ($followingId === $uid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Operação inválida']);

        exit;
    }
    $r = club61_follows_remover($uid, $followingId);
    if (!$r['ok']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $r['error'] ?? 'Não foi possível remover']);

        exit;
    }
    echo json_encode([
        'success' => true,
        'state' => 'none',
        'followers_count' => (int) ($r['followers_count'] ?? club61_follows_count_followers_accepted($followingId)),
    ]);

    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Ação desconhecida']);
