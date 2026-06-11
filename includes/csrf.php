<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token dan simpan di session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden input CSRF untuk dipakai di dalam form.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validasi CSRF token dari request POST.
 */
function csrf_verify(): void {
    $token_post    = $_POST['csrf_token'] ?? '';
    $token_session = $_SESSION['csrf_token'] ?? '';

    if (empty($token_post) || !hash_equals($token_session, $token_post)) {
        http_response_code(403);
        die('403 Forbidden: Invalid CSRF token.');
    }

    // Regenerate token setelah validasi sukses (one-time use)
    unset($_SESSION['csrf_token']);
}
