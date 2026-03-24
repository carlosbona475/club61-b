<?php

declare(strict_types=1);

/**
 * Estado de autenticação na sessão (espelha o que auth_guard exige).
 */
function club61_is_authenticated(): bool
{
    return !empty($_SESSION['access_token']) && !empty($_SESSION['user_id']);
}
