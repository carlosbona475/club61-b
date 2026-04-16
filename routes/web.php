<?php

declare(strict_types=1);

use Club61\Controllers\LegacyController;

/**
 * Mapa de rotas amigáveis → scripts PHP (Apache: rewrites em .htaccess na raiz).
 *
 * POST /post/delete → features/feed/delete_post.php → LegacyController::deletePost
 *
 * Chat JSON (Apache .htaccess): GET /chat/messages, POST /chat/send → chat_actions.php;
 * POST /chat/enviar → features/chat/enviar.php (LegacyController::enviarMensagem). Demais /chat/* em chat_actions.php.
 */
return [
    '/admin' => '/features/admin/index.php',
    '/admin/' => '/features/admin/index.php',

    /** @see LegacyController::adminPanel() — em produção o .htaccess aponta /admin para o PHP acima */
    'GET /admin' => [LegacyController::class, 'adminPanel'],
    'GET /admin/' => [LegacyController::class, 'adminPanel'],

    'POST /post/delete' => [LegacyController::class, 'deletePost'],

    /** @see LegacyController::enviarMensagem — em Apache: .htaccess mapeia /chat/enviar → features/chat/enviar.php */
    'POST /chat/enviar' => [LegacyController::class, 'enviarMensagem'],
];
