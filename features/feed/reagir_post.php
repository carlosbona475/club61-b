<?php

/**
 * POST /post/reagir (Apache: .htaccess → este ficheiro).
 *
 * @see LegacyController::reagirPost
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/app/controllers/LegacyController.php';

use Club61\Controllers\LegacyController;

(new LegacyController())->reagirPost();
