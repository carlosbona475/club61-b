<?php

declare(strict_types=1);

namespace Club61\Services;

final class LastSeenService
{
    public function __construct()
    {
        require_once \CLUB61_BASE_PATH . '/config/supabase.php';
        require_once \CLUB61_BASE_PATH . '/config/last_seen.php';
    }

    public function touch(string $userId): void
    {
        if ($userId === '') {
            return;
        }

        club61_touch_last_seen($userId);
    }
}

