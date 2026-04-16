<?php

/**
 * GET /chat/mensagens (Apache: .htaccess → este ficheiro).
 *
 * @see LegacyController::buscarMensagens
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use Club61\Controllers\LegacyController;

(new LegacyController())->buscarMensagens();
