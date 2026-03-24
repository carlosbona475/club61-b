<?php

declare(strict_types=1);

namespace Club61\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Critério de "acesso autenticado" alinhado com auth_guard.
 */
final class AuthenticatedSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($GLOBALS['club61_session_bootstrapped']);
        ini_set('session.use_cookies', '0');
        session_id('club61auth' . bin2hex(random_bytes(8)));
        club61_session_bootstrap();
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function test_not_authenticated_without_session(): void
    {
        self::assertFalse(club61_is_authenticated());
    }

    public function test_not_authenticated_with_only_token(): void
    {
        $_SESSION['access_token'] = 'tok';
        self::assertFalse(club61_is_authenticated());
    }

    public function test_not_authenticated_with_only_user_id(): void
    {
        $_SESSION['user_id'] = 'uuid';
        self::assertFalse(club61_is_authenticated());
    }

    public function test_authenticated_with_token_and_user_id(): void
    {
        $_SESSION['access_token'] = 'jwt-here';
        $_SESSION['user_id'] = '550e8400-e29b-41d4-a716-446655440000';
        self::assertTrue(club61_is_authenticated());
    }
}
