<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('CLUB61_BASE_PATH')) {
    define('CLUB61_BASE_PATH', $root);
}
if (!defined('CLUB61_ROOT')) {
    define('CLUB61_ROOT', $root);
}

require_once $root . '/config/session.php';
require_once $root . '/config/sanitize.php';
require_once $root . '/config/validation.php';
require_once $root . '/config/csrf.php';
require_once $root . '/config/auth_session.php';
require_once $root . '/config/post_input.php';
require_once $root . '/config/upload_validate.php';
