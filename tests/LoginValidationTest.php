<?php

declare(strict_types=1);

namespace Club61\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regras de validação usadas no fluxo de login (sem chamar Supabase).
 */
final class LoginValidationTest extends TestCase
{
    public function test_login_payload_valid(): void
    {
        $v = club61_validate([
            'email' => 'user@example.com',
            'password' => 'secret123',
        ], [
            'email' => 'required|email|max:320',
            'password' => 'required',
        ]);

        self::assertTrue($v['ok']);
        self::assertSame('user@example.com', $v['data']['email']);
    }

    public function test_login_rejects_empty_email(): void
    {
        $v = club61_validate([
            'email' => '',
            'password' => 'x',
        ], [
            'email' => 'required|email|max:320',
            'password' => 'required',
        ]);

        self::assertFalse($v['ok']);
    }

    public function test_login_rejects_invalid_email(): void
    {
        $v = club61_validate([
            'email' => 'not-an-email',
            'password' => 'x',
        ], [
            'email' => 'required|email|max:320',
            'password' => 'required',
        ]);

        self::assertFalse($v['ok']);
    }

    public function test_login_rejects_empty_password(): void
    {
        $v = club61_validate([
            'email' => 'a@b.co',
            'password' => '',
        ], [
            'email' => 'required|email|max:320',
            'password' => 'required',
        ]);

        self::assertFalse($v['ok']);
    }
}
