<?php

declare(strict_types=1);

namespace Club61\Controllers;

use Club61\Core\Request;

final class AuthController
{
    public function login(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/auth/login.php';
    }

    public function register(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/auth/register.php';
    }

    public function logout(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/auth/logout.php';
    }
}

