<?php
declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';

header('Location: /features/chat/salas.php');
exit;
