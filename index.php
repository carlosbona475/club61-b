<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap/app.php';
require_once __DIR__ . '/config/security_headers.php';

club61_security_headers();
\Club61\Core\Application::runHttp();
