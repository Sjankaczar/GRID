<?php

session_start();
require_once 'config/config.php';
require_once 'koneksi.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/functions.php';

redirect_if_logged_in();

$all_orgs = [];
try {
    $stmt_orgs = $pdo->query('SELECT nama, kode_unik FROM organizations ORDER BY nama ASC');
    $all_orgs  = $stmt_orgs->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$errors   = [];
$old      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Rate Limiting
    if (!rate_limit_check('register', 5, 120)) {
        $errors[] = 'Terlalu banyak percobaan. Tunggu beberapa saat.';
    } else {
        csrf_verify();

        // Ambil & Sanitasi Input
        $username         = trim($_POST['username'] ?? '');
        $nama_lengkap     = trim($_POST['nama_lengkap'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $konfirmasi       = $_POST['konfirmasi_password'] ?? '';
        $role_pilihan     = $_POST['role_pilihan'] ?? 'Member';
        $kode_organisasi  = strtoupper(trim($_POST['kode_organisasi'] ?? ''));
        $nama_organisasi  = trim($_POST['nama_organisasi'] ?? '');

        $old = compact('username', 'nama_lengkap', 'email', 'kode_organisasi', 'nama_organisasi', 'role_pilihan');

        // Validasi role pilihan
        if (!in_array($role_pilihan, ['Admin', 'Member'])) {
            $role_pilihan = 'Member';
        }

        // Validasi Input Dasar
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

        // Validasi Organisasi
        $org_id   = null;
        $is_admin = ($role_pilihan === 'Admin');

        if ($is_admin) {
            if (empty($nama_organisasi)) {
                $errors[] = 'Nama organisasi wajib diisi untuk akun Admin.';
            } elseif (mb_strlen($nama_organisasi) > 100) {
                $errors[] = 'Nama organisasi maksimal 100 karakter.';
            }
        } else {
            if (empty($kode_organisasi)) {
                $errors[] = 'Kode organisasi wajib diisi. Minta kode ini kepada Admin studio kamu.';
            } elseif (!preg_match('/^[A-Z0-9_]{3,20}$/', $kode_organisasi)) {
                $errors[] = 'Format kode organisasi tidak valid (huruf kapital, angka, underscore, 3–20 karakter).';
            } else {
                // Cek apakah organisasi dengan kode ini ada
                $stmt = $pdo->prepare('SELECT id FROM organizations WHERE kode_unik = ? LIMIT 1');
                $stmt->execute([$kode_organisasi]);
                $org_row = $stmt->fetch();
                if (!$org_row) {
                    $errors[] = 'Kode organisasi tidak ditemukan. Pastikan kode yang kamu masukkan benar.';
                } else {
                    $org_id = $org_row['id'];
                }
            }
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

            if ($is_admin) {
                $org_id      = generateUUID();
                $kode_gen    = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($nama_organisasi)));
                $kode_gen    = substr($kode_gen, 0, 12); 

                $kode_final = $kode_gen;
                $cek = $pdo->prepare('SELECT id FROM organizations WHERE kode_unik = ? LIMIT 1');
                $cek->execute([$kode_final]);
                if ($cek->fetch()) {
                    $kode_final = $kode_gen . rand(10, 99);
                }

                $stmt_org = $pdo->prepare('
                    INSERT INTO organizations (id, nama, kode_unik, owner_id)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt_org->execute([$org_id, $nama_organisasi, $kode_final, $id]);

                // Admin: is_active = TRUE (langsung aktif karena dia pemilik org)
                $stmt = $pdo->prepare('
                    INSERT INTO users (id, username, nama_lengkap, email, password_hash, role, is_active, organization_id)
                    VALUES (?, ?, ?, ?, ?, ?, TRUE, ?)
                ');
                $stmt->execute([$id, $username, $nama_lengkap, $email, $password_hash, ROLE_ADMIN, $org_id]);

                // Update owner_id di organizations (sekarang user sudah ada)
                $pdo->prepare('UPDATE organizations SET owner_id = ? WHERE id = ?')->execute([$id, $org_id]);

                set_flash('success', 'Akun Admin berhasil dibuat! Kode organisasi kamu: <strong>' . htmlspecialchars($kode_final) . '</strong> — bagikan kode ini kepada anggota tim kamu.');
                redirect(APP_URL . '/login.php');
            } else {
                // Member: is_active = FALSE, tunggu approval Admin
                $stmt = $pdo->prepare('
                    INSERT INTO users (id, username, nama_lengkap, email, password_hash, role, is_active, organization_id)
                    VALUES (?, ?, ?, ?, ?, ?, FALSE, ?)
                ');
                $stmt->execute([$id, $username, $nama_lengkap, $email, $password_hash, ROLE_MEMBER, $org_id]);

                set_flash('success', 'Registrasi berhasil! Akun kamu sedang menunggu persetujuan Admin organisasi.');
                redirect(APP_URL . '/login.php');
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

        <!-- Back Button -->
        <a href="index.php" class="text-decoration-none text-muted small d-inline-block mb-4" style="transition: color 0.2s;" onmouseover="this.style.color=\'var(--accent-blue)\'" onmouseout="this.style.color=\'\'">
            <i class="fa fa-arrow-left me-1"></i> Kembali ke Beranda
        </a>

        <!-- Logo & Judul -->
        <div class="auth-header text-center mb-4">
            <span class="auth-logo">GRID</span>
            <p class="auth-subtitle">Game Repository &amp; Indie Devlog</p>
        </div>

        <h5 class="mb-1 fw-semibold">Buat Akun Baru</h5>
        <p class="text-muted small mb-4">Pilih tipe akun sesuai peranmu di studio.</p>

        <!-- Flash Message -->
        <?php render_flash(); ?>

        <!-- Error List -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $err ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Pilih Tipe Akun -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Tipe Akun</label>
            <div class="d-flex gap-2">
                <div id="card-member" class="role-card flex-fill p-3 text-center <?= (($old['role_pilihan'] ?? 'Member') === 'Member') ? 'active' : '' ?>" onclick="selectRole('Member')" style="cursor:pointer; border:2px solid var(--border-color,#333); border-radius:8px; transition:all .2s;">
                    <i class="fa fa-user fa-2x mb-2 text-info"></i>
                    <div class="fw-semibold small">Member Tim</div>
                    <div class="text-muted" style="font-size:0.72rem;">Bergabung ke studio yang sudah ada</div>
                </div>
                <div id="card-admin" class="role-card flex-fill p-3 text-center <?= (($old['role_pilihan'] ?? 'Member') === 'Admin') ? 'active' : '' ?>" onclick="selectRole('Admin')" style="cursor:pointer; border:2px solid var(--border-color,#333); border-radius:8px; transition:all .2s;">
                    <i class="fa fa-crown fa-2x mb-2 text-warning"></i>
                    <div class="fw-semibold small">Admin / Studio Lead</div>
                    <div class="text-muted" style="font-size:0.72rem;">Buat studio game baru</div>
                </div>
            </div>
        </div>

        <!-- Form Registrasi -->
        <form method="POST" action="register.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="role_pilihan" id="role_pilihan" value="<?= e($old['role_pilihan'] ?? 'Member') ?>">

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                    value="<?= e($old['username'] ?? '') ?>"
                    placeholder="cth: budi_dev" maxlength="30" required>
                <div class="form-text">Huruf, angka, underscore. 3–30 karakter.</div>
            </div>

            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap"
                    value="<?= e($old['nama_lengkap'] ?? '') ?>"
                    placeholder="cth: Budi Santoso" maxlength="100" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                    value="<?= e($old['email'] ?? '') ?>"
                    placeholder="cth: budi@studio.com" maxlength="100" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                        placeholder="Minimal 8 karakter" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fa fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label for="konfirmasi_password" class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password"
                    placeholder="Ulangi password" required>
            </div>

            <!-- Field untuk Admin -->
            <div id="field-admin" class="mb-4" style="display:<?= (($old['role_pilihan'] ?? 'Member') === 'Admin') ? 'block' : 'none' ?>;">
                <label for="nama_organisasi" class="form-label">
                    <i class="fa fa-building me-1 text-warning"></i>Nama Studio / Organisasi
                    <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" id="nama_organisasi" name="nama_organisasi"
                    value="<?= e($old['nama_organisasi'] ?? '') ?>"
                    placeholder="cth: Pixel Storm Studio" maxlength="100">
                <div class="form-text">Kode unik organisasi akan dibuat otomatis dari nama ini dan ditampilkan setelah registrasi.</div>
            </div>

            <!-- Field untuk Member -->
            <div id="field-member" class="mb-4" style="display:<?= (($old['role_pilihan'] ?? 'Member') !== 'Admin') ? 'block' : 'none' ?>;">
                <label for="kode_organisasi" class="form-label">
                    <i class="fa fa-building me-1 text-info"></i>Organisasi
                    <span class="text-danger">*</span>
                </label>
                <div class="position-relative">
                    <input
                        type="text"
                        class="form-control"
                        id="kode_organisasi"
                        name="kode_organisasi"
                        list="org_list"
                        value="<?= e($old['kode_organisasi'] ?? '') ?>"
                        placeholder="Pilih atau ketik kode organisasi..."
                        maxlength="20"
                        autocomplete="off"
                        style="text-transform:uppercase; letter-spacing:0.05em;">
                    <datalist id="org_list">
                        <?php foreach ($all_orgs as $org): ?>
                        <option value="<?= e($org['kode_unik']) ?>">
                            <?= e($org['nama']) ?> (<?= e($org['kode_unik']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-text">
                    Pilih studio dari daftar atau ketik kode yang diberikan Admin.
                </div>
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
    function selectRole(role) {
        document.getElementById('role_pilihan').value = role;

        var cardMember = document.getElementById('card-member');
        var cardAdmin  = document.getElementById('card-admin');
        var fieldAdmin = document.getElementById('field-admin');
        var fieldMember = document.getElementById('field-member');

        var accentColor = 'var(--accent-blue, #3b82f6)';
        var borderColor = 'var(--border-color, #333)';

        if (role === 'Admin') {
            cardAdmin.style.borderColor  = '#f59e0b';
            cardMember.style.borderColor = borderColor;
            fieldAdmin.style.display  = 'block';
            fieldMember.style.display = 'none';
            document.getElementById('kode_organisasi').value = '';
        } else {
            cardMember.style.borderColor = accentColor;
            cardAdmin.style.borderColor  = borderColor;
            fieldMember.style.display = 'block';
            fieldAdmin.style.display  = 'none';
            document.getElementById('nama_organisasi').value = '';
        }
    }

    // Inisialisasi tampilan saat halaman load
    selectRole(document.getElementById('role_pilihan').value || 'Member');

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

    var kodeInput = document.getElementById('kode_organisasi');
    if (kodeInput) {
        kodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
</script>
</body>
</html>
