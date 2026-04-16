<?php

declare(strict_types=1);

use Club61\Controllers\LegacyController;

/**
 * Mapa de rotas amigáveis → scripts PHP (Apache: rewrites em .htaccess na raiz).
 *
 * POST /post/delete → features/feed/delete_post.php → LegacyController::deletePost
 *
 * Chat JSON (não usa este ficheiro): GET/POST /chat/messages, /chat/send, /chat/enviar,
 * /chat/react, /chat/online, /chat/presence → features/chat/chat_actions.php (ver .htaccess).
 */
return [
    '/admin' => '/features/admin/index.php',
    '/admin/' => '/features/admin/index.php',

    /** @see LegacyController::adminPanel() — em produção o .htaccess aponta /admin para o PHP acima */
    'GET /admin' => [LegacyController::class, 'adminPanel'],
    'GET /admin/' => [LegacyController::class, 'adminPanel'],

    'POST /post/delete' => [LegacyController::class, 'deletePost'],
];
