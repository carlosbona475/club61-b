<?php

require_once __DIR__ . '/config/security_headers.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth_session.php';

club61_security_headers();
club61_session_start_safe();

if (!club61_is_authenticated()) {
    header('Location: /features/auth/login.php');
    exit;
}

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/config/last_seen.php';

club61_touch_last_seen((string) $_SESSION['user_id']);
