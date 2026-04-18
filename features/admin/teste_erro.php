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

echo 'includes ok<br>';
echo 'user_id: ' . ($_SESSION['user_id'] ?? 'vazio') . '<br>';
echo 'isAdmin: ' . (isCurrentUserAdmin() ? 'SIM' : 'NAO') . '<br>';
