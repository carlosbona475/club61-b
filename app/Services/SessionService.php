<?php

declare(strict_types=1);

namespace Club61\Services;

final class SessionService
{
    public function __construct()
    {
        require_once \CLUB61_BASE_PATH . '/config/session.php';
    }

    public function start(): void
    {
        club61_session_start_safe();
    }

    public function userId(): string
    {
        return isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
    }

    public function isAuthenticated(): bool
    {
        return !empty($_SESSION['access_token']) && $this->userId() !== '';
    }
}

