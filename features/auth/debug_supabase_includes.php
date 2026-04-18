<?php
/**
 * Diagnóstico local: includes + constantes Supabase.
 * Remover ou proteger em produção (não expor em host público).
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/supabase.php';

echo 'OK - includes carregados<br>';
echo 'SUPABASE_URL: ' . (defined('SUPABASE_URL') ? 'definido' : 'FALTA') . '<br>';
echo 'SUPABASE_SERVICE_KEY: ' . (defined('SUPABASE_SERVICE_KEY') ? 'definido' : 'FALTA') . '<br>';
