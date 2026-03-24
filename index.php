<?php

declare(strict_types=1);

/**
 * Raiz do projeto (DirectoryIndex). Redirecionamento leve — não exige Composer.
 * MVC completo entra em /features/feed/index.php via bootstrap/web.php.
 */
require_once __DIR__ . '/config/security_headers.php';
club61_security_headers();
header('Location: /features/feed/index.php');
exit;
