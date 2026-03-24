<?php

declare(strict_types=1);

namespace Club61\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Token CSRF em sessão (comportamento esperado no login e POSTs).
 */
final class CsrfSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($GLOBALS['club61_session_bootstrapped']);
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        session_id('club61test' . bin2hex(random_bytes(8)));
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

    public function test_token_generated_and_validated(): void
    {
        $t = csrf_token();
        self::assertNotSame('', $t);
        self::assertTrue(csrf_validate($t));
    }

    public function test_wrong_token_rejected(): void
    {
        csrf_token();
        self::assertFalse(csrf_validate('wrong-token'));
    }

    public function test_rotate_invalidates_old_token(): void
    {
        $a = csrf_token();
        self::assertTrue(csrf_validate($a));
        csrf_rotate();
        self::assertFalse(csrf_validate($a));
        self::assertTrue(csrf_validate(csrf_token()));
    }
}
