<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/security_headers.php';
club61_security_headers();
header('Location: /features/feed/index.php');
exit;
