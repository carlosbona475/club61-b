<?php

declare(strict_types=1);

namespace Club61\Services;

final class ConfigBootstrapService
{
    private bool $feedReady = false;

    public function bootFeedDependencies(): void
    {
        if ($this->feedReady) {
            return;
        }

        require_once \CLUB61_BASE_PATH . '/config/supabase.php';
        require_once \CLUB61_BASE_PATH . '/config/profile_helper.php';
        require_once \CLUB61_BASE_PATH . '/config/feed_interactions.php';
        require_once \CLUB61_BASE_PATH . '/config/online.php';
        require_once \CLUB61_BASE_PATH . '/config/csrf.php';
        $this->feedReady = true;
    }
}

