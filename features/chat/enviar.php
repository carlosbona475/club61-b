<?php

/**
 * Alias POST /features/chat/enviar.php → mesmo handler que ?r=send
 * (útil se mod_rewrite não mapear /chat/enviar para chat_actions.php).
 */

declare(strict_types=1);

$_GET['r'] = 'send';
require __DIR__ . '/chat_actions.php';
