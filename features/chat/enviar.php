<?php

/**
 * POST /features/chat/enviar.php e /chat/enviar (rewrite) → LegacyController::enviarMensagem
 * (JSON; multipart com texto e/ou arquivo `arquivo` ou `media`).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use Club61\Controllers\LegacyController;

(new LegacyController())->enviarMensagem();
