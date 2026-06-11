<?php

session_start();
require_once 'config/config.php';
require_once 'koneksi.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';

redirect_if_logged_in();

$errors   = [];
$old      = []; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Rate Limiting 
    if (!rate_limit_check('register', 5, 120)) {
        $errors[] = 'Terlalu banyak percobaan. Tunggu beberapa saat.';
    } else {
        // Validasi CSRF
        csrf_verify();

        // Ambil & Sanitasi Input
        $username     = trim($_POST['username'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';
        $konfirmasi   = $_POST['konfirmasi_password'] ?? '';

        $old = compact('username', 'nama_lengkap', 'email');

        // Validasi Input
        if (empty($username)) {
            $errors[] = 'Username wajib diisi.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username hanya boleh huruf, angka, underscore, dan 3–30 karakter.';
        }

        if (empty($nama_lengkap)) {
            $errors[] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($nama_lengkap) > 100) {
            $errors[] = 'Nama lengkap maksimal 100 karakter.';
        }

        if (empty($email)) {
            $errors[] = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        if (empty($password)) {
            $errors[] = 'Password wajib diisi.';
        } elseif (mb_strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }

        if ($password !== $konfirmasi) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        // Cek Duplikat Username & Email
        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username atau email sudah terdaftar.';
            }
        }

        // Simpan ke Database
        if (empty($errors)) {
            $id            = generateUUID();
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Role default: Member, is_active: FALSE
            $stmt = $pdo->prepare('
                INSERT INTO users (id, username, nama_lengkap, email, password_hash, role, is_active)
                VALUES (?, ?, ?, ?, ?, ?, FALSE)
            ');
            $stmt->execute([$id, $username, $nama_lengkap, $email, $password_hash, ROLE_MEMBER]);

            set_flash('success', 'Registrasi berhasil! Akun kamu sedang menunggu persetujuan Admin.');
            redirect(APP_URL . '/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi — <?= APP_NAME ?></title>
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

        <h5 class="mb-3 fw-semibold">Buat Akun Baru</h5>
        <p class="text-muted small mb-4">
            Akun akan aktif setelah disetujui oleh Admin studio.
        </p>

        <!-- Flash Message -->
        <?php render_flash(); ?>

        <!-- Error List -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Form Registrasi -->
        <form method="POST" action="register.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input
                    type="text"
                    class="form-control"
                    id="username"
                    name="username"
                    value="<?= e($old['username'] ?? '') ?>"
                    placeholder="cth: budi_dev"
                    maxlength="30"
                    required
                >
                <div class="form-text">Huruf, angka, underscore. 3–30 karakter.</div>
            </div>

            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input
                    type="text"
                    class="form-control"
                    id="nama_lengkap"
                    name="nama_lengkap"
                    value="<?= e($old['nama_lengkap'] ?? '') ?>"
                    placeholder="cth: Budi Santoso"
                    maxlength="100"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    value="<?= e($old['email'] ?? '') ?>"
                    placeholder="cth: budi@studio.com"
                    maxlength="100"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Minimal 8 karakter"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fa fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label for="konfirmasi_password" class="form-label">Konfirmasi Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="konfirmasi_password"
                    name="konfirmasi_password"
                    placeholder="Ulangi password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fa fa-user-plus me-2"></i>Daftar Sekarang
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">
                Sudah punya akun?
                <a href="login.php" class="text-decoration-none">Login di sini</a>
            </small>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle show/hide password
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
