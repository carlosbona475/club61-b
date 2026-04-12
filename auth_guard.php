<?php

require_once __DIR__ . '/config/bootstrap_path.php';
require_once CLUB61_ROOT . '/config/security_headers.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/auth_session.php';

club61_security_headers();
club61_session_start_safe();

if (!club61_is_authenticated()) {
    header('Location: /features/auth/login.php');
    exit;
}

require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/last_seen.php';

club61_touch_last_seen((string) $_SESSION['user_id']);
