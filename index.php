<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (isset($_SESSION['access_token'])) {
    header('Location: /features/feed/index.php');
    exit;
} else {
    header('Location: /features/auth/login.php');
    exit;
}
