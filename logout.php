<?php

session_start();
require_once 'config/config.php';
require_once 'includes/csrf.php';

// Verifikasi CSRF 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Hapus semua data session
session_unset();
session_destroy();

// Hapus session cookie di browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

header('Location: ' . APP_URL . '/login.php?msg=logged_out');
exit;
