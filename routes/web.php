<?php

declare(strict_types=1);

use Club61\Controllers\LegacyController;

/**
 * Mapa de rotas amigáveis → scripts PHP (Apache: rewrites em .htaccess na raiz).
 *
 * POST /post/delete → features/feed/delete_post.php → LegacyController::deletePost
 */
return [
    '/admin' => '/features/admin/index.php',
    '/admin/' => '/features/admin/index.php',

    'POST /post/delete' => [LegacyController::class, 'deletePost'],
];
