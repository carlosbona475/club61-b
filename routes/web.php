<?php

declare(strict_types=1);

use Club61\Http\Controllers\FeedController;
use Club61\Http\Middleware\AuthenticateMiddleware;
use Club61\Http\Middleware\SessionMiddleware;
use Club61\Http\Middleware\TouchLastSeenMiddleware;

/**
 * Router central: mapeamento de scripts legados → middleware + ação MVC.
 * Adicionar novas páginas aqui e usar entrada fina em features/*/*.php.
 */
return [
    'middleware_groups' => [
        'web' => [
            SessionMiddleware::class,
        ],
        'auth' => [
            SessionMiddleware::class,
            AuthenticateMiddleware::class,
            TouchLastSeenMiddleware::class,
        ],
    ],
    'legacy_files' => [
        'features/feed/index.php' => [
            'middleware' => 'auth',
            'action' => [FeedController::class, 'index'],
        ],
    ],
];
