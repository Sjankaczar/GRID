<?php

session_start();
require_once 'config/config.php';
require_once 'koneksi.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';

redirect_if_logged_in();

$msg_map = [
    'timeout'         => ['warning', 'Sesi anda telah berakhir. Silakan login kembali.'],
    'unauthenticated' => ['warning', 'Anda harus login untuk mengakses halaman tersebut.'],
    'logged_out'      => ['info',    'Anda telah berhasil logout.'],
];
$url_msg = $_GET['msg'] ?? '';
if (isset($msg_map[$url_msg])) {
    set_flash($msg_map[$url_msg][0], $msg_map[$url_msg][1]);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    //  Rate Limiting 
    if (!rate_limit_check('login', 5, 60)) {
        $errors[] = 'Terlalu banyak percobaan login. Tunggu 1 menit.';
    } else {
        // Validasi CSRF
        csrf_verify();

        // Ambil Input
        $login    = trim($_POST['login'] ?? '');    
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $errors[] = 'Username/email dan password wajib diisi.';
        } else {
            // Cari User Berdasarkan Username atau Email
            $stmt = $pdo->prepare('
                SELECT id, username, nama_lengkap, email, password_hash, role, avatar_url, is_active, organization_id
                FROM users
                WHERE username = ? OR email = ?
                LIMIT 1
            ');
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                // Pesan error untuk keamanan
                $errors[] = 'Username/email atau password salah.';
            } elseif (!(bool)$user['is_active']) {
                $errors[] = 'Akun anda belum diaktifkan. Tunggu persetujuan dari Admin.';
            } else {
                // Login Berhasil
                create_user_session($user);

                // Redirect sesuai role
                switch ($user['role']) {
                    case ROLE_ADMIN:
                        redirect(APP_URL . '/admin/dashboard.php');
                        break;
                    case ROLE_MEMBER:
                        redirect(APP_URL . '/member/dashboard.php');
                        break;
                    default:
                        redirect(APP_URL . '/index.php');
                        break;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="grid-auth-body">

<div class="auth-wrapper">
    <div class="auth-card">

        <!-- Logo & Judul -->
        <div class="auth-header text-center mb-4">
            <span class="auth-logo">GRID</span>
            <p class="auth-subtitle">Game Repository &amp; Indie Devlog</p>
        </div>

        <h5 class="mb-1 fw-semibold">Masuk ke Portal</h5>
        <p class="text-muted small mb-4">Login menggunakan username atau email.</p>

        <!-- Flash Message -->
        <?php render_flash(); ?>

        <!-- Error -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" action="login.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="login" class="form-label">Username atau Email</label>
                <input
                    type="text"
                    class="form-control"
                    id="login"
                    name="login"
                    placeholder="username atau email@studio.com"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Password"
                        autocomplete="current-password"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fa fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fa fa-sign-in-alt me-2"></i>Login
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">
                Belum punya akun?
                <a href="register.php" class="text-decoration-none">Daftar di sini</a>
            </small>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        var input = document.getElementById('password');
        var icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
</script>
</body>
</html>
