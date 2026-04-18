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

echo 'OK - todos os includes carregados<br>';
echo 'SUPABASE_URL: ' . (defined('SUPABASE_URL') ? 'definido' : 'FALTA') . '<br>';
echo 'SUPABASE_SERVICE_KEY: ' . (defined('SUPABASE_SERVICE_KEY') ? 'definido' : 'FALTA') . '<br>';
echo 'session user_id: ' . (isset($_SESSION['user_id']) ? 'tem sessao' : 'sem sessao') . '<br>';