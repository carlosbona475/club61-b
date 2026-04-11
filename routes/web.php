<?php
declare(strict_types=1);

use Club61\Controllers\AuthController;
use Club61\Controllers\FeedController;
use Club61\Controllers\LegacyController;
use Club61\Http\Middleware\AuthenticateMiddleware;
use Club61\Http\Middleware\SessionMiddleware;
use Club61\Http\Middleware\TouchLastSeenMiddleware;

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
    'routes' => [
        // Home/feed
        'GET /' => ['middleware' => 'auth', 'action' => [FeedController::class, 'index']],
        'GET /feed' => ['middleware' => 'auth', 'action' => [FeedController::class, 'index']],
        'GET /feed/load-more' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedLoadMore']],
        'GET /features/feed/index.php' => ['middleware' => 'auth', 'action' => [FeedController::class, 'index']],
        'GET /features/feed/load_more.php' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedLoadMore']],

        // Auth
        'GET /login' => ['middleware' => 'web', 'action' => [AuthController::class, 'login']],
        'POST /login' => ['middleware' => 'web', 'action' => [AuthController::class, 'login']],
        'GET /register' => ['middleware' => 'web', 'action' => [AuthController::class, 'register']],
        'POST /register' => ['middleware' => 'web', 'action' => [AuthController::class, 'register']],
        'GET /logout' => ['middleware' => 'web', 'action' => [AuthController::class, 'logout']],
        'POST /logout' => ['middleware' => 'web', 'action' => [AuthController::class, 'logout']],
        'GET /features/auth/login.php' => ['middleware' => 'web', 'action' => [AuthController::class, 'login']],
        'POST /features/auth/login.php' => ['middleware' => 'web', 'action' => [AuthController::class, 'login']],
        'GET /features/auth/register.php' => ['middleware' => 'web', 'action' => [AuthController::class, 'register']],
        'POST /features/auth/register.php' => ['middleware' => 'web', 'action' => [AuthController::class, 'register']],
        'GET /features/auth/logout.php' => ['middleware' => 'web', 'action' => [AuthController::class, 'logout']],
        'POST /features/auth/logout.php' => ['middleware' => 'web', 'action' => [AuthController::class, 'logout']],

        // Feature actions transitioned through controller wrappers
        'POST /feed/create-post' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedCreatePost']],
        'POST /feed/toggle-like' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedToggleLike']],
        'POST /feed/add-comment' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedAddComment']],
        'GET /profile' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'profileIndex']],
        'GET /chat/general' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'chatGeneral']],
        'POST /features/feed/create_post.php' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedCreatePost']],
        'POST /features/feed/toggle_like.php' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedToggleLike']],
        'POST /features/feed/add_comment.php' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'feedAddComment']],
        'GET /features/profile/index.php' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'profileIndex']],
        'GET /features/chat/general.php' => ['middleware' => 'auth', 'action' => [LegacyController::class, 'chatGeneral']],
    ],
];