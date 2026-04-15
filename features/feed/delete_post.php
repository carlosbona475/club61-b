<?php

/**
 * Entrada HTTP para POST /post/delete (Apache: .htaccess → este ficheiro).
 * Delega a Club61\Controllers\LegacyController::deletePost (ver routes/web.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/app/controllers/LegacyController.php';

use Club61\Controllers\LegacyController;

(new LegacyController())->deletePost();
