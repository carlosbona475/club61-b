<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['access_token']) || empty($_SESSION['user_id'])) {
    header('Location: /features/auth/login.php');
    exit;
}
