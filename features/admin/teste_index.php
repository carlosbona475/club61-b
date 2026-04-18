<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/admin_guard.php';

echo 'PRE-INDEX OK<br>';

ob_start();
require_once __DIR__ . '/index.php';
$out = ob_get_clean();

echo 'INDEX CARREGADO<br>';
