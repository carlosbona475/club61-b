<?php

declare(strict_types=1);

namespace Club61\Services;

final class CsrfService
{
    public function __construct()
    {
        require_once \CLUB61_BASE_PATH . '/config/csrf.php';
    }

    public function token(): string
    {
        return csrf_token();
    }

    public function validate(?string $token): bool
    {
        return csrf_validate($token);
    }
}

