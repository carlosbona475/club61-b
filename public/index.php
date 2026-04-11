<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once dirname(__DIR__) . '/config/security_headers.php';

club61_security_headers();
\Club61\Core\Application::runHttp();
