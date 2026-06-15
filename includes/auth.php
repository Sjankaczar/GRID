<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/config.php';

// SESSION TIMEOUT CHECK
if (isset($_SESSION['user_id'])) {
    $last_activity = $_SESSION['last_activity'] ?? 0;
    if ((time() - $last_activity) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/login.php?msg=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// FUNGSI CEK STATUS LOGIN

/**
 * Kembalikan true jika user sedang login.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Kembalikan role user yang sedang login.
 */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Kembalikan ID user yang sedang login.
 */
function current_user_id(): ?string {
    return $_SESSION['user_id'] ?? null;
}

// FUNGSI PROTEKSI HALAMAN

/**
 * Paksa user untuk login sebelum bisa mengakses halaman.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php?msg=unauthenticated');
        exit;
    }
}

/**
 * Paksa user memiliki salah satu role yang diizinkan.
 * @param string[] $allowed_roles
 */
function require_role(array $allowed_roles): void {
    require_login();

    $role = current_role();
    if (!in_array($role, $allowed_roles, true)) {
        // Redirect ke dashboard role masing-masing, bukan 403 langsung
        switch ($role) {
            case ROLE_ADMIN:
                header('Location: ' . APP_URL . '/admin/dashboard.php');
                break;
            case ROLE_MEMBER:
                header('Location: ' . APP_URL . '/member/dashboard.php');
                break;
            default:
                header('Location: ' . APP_URL . '/index.php');
                break;
        }
        exit;
    }
}

/**
 * Redirect user yang sudah login ke dashboard yang sesuai.
 */
function redirect_if_logged_in(): void {
    if (!is_logged_in()) return;

    switch (current_role()) {
        case ROLE_ADMIN:
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            break;
        case ROLE_MEMBER:
            header('Location: ' . APP_URL . '/member/dashboard.php');
            break;
        default:
            header('Location: ' . APP_URL . '/index.php');
            break;
    }
    exit;
}

// FUNGSI BUAT SESSION SETELAH LOGIN

/**
 * Set session user setelah login berhasil diverifikasi.
 * @param array $user Row dari tabel users
 */
function create_user_session(array $user): void {
    // Regenerate session ID untuk mencegah session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']         = $user['id'];
    $_SESSION['username']        = $user['username'];
    $_SESSION['nama_lengkap']    = $user['nama_lengkap'];
    $_SESSION['role']            = $user['role'];
    $_SESSION['avatar_url']      = $user['avatar_url'];
    $_SESSION['organization_id'] = $user['organization_id'] ?? null;
    $_SESSION['last_activity']   = time();
}
