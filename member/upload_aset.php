<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Proteksi halaman untuk Member dan Admin
require_role([ROLE_ADMIN, ROLE_MEMBER]);

$errors   = [];
$success  = false;

$projects = [];
try {
    $stmt = $pdo->query('SELECT id, nama_game FROM projects LIMIT 10');
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Handle POST upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/csrf.php';
    csrf_verify();

    $project_id = trim($_POST['project_id'] ?? '');
    $nama_aset  = trim($_POST['nama_aset'] ?? '');
    $kategori   = $_POST['kategori'] ?? '';
    $tags       = trim($_POST['tags'] ?? '');

    // Validasi input
    if (empty($project_id)) {
        $errors[] = 'Pilih proyek terlebih dahulu.';
    }

    if (empty($nama_aset)) {
        $errors[] = 'Nama aset wajib diisi.';
    } elseif (mb_strlen($nama_aset) > 100) {
        $errors[] = 'Nama aset maksimal 100 karakter.';
    }

    if (empty($kategori) || !in_array($kategori, ['Sprite', 'Audio', 'Script', 'Other'])) {
        $errors[] = 'Kategori aset tidak valid.';
    }

    // Validasi file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File wajib diunggah.';
    } else {
        $file = $_FILES['file'];

        // Cek ukuran file
        if ($file['size'] > (MAX_FILE_SIZE_KB * 1024)) {
            $errors[] = 'Ukuran file terlalu besar. Maksimal ' . MAX_FILE_SIZE_KB . ' KB.';
        } else {
            // Validasi ekstensi file sesuai kategori
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = [];

            switch ($kategori) {
                case 'Sprite':
                    $allowed = ALLOWED_SPRITE_EXT;
                    break;
                case 'Audio':
                    $allowed = ALLOWED_AUDIO_EXT;
                    break;
                case 'Script':
                    $allowed = ALLOWED_SCRIPT_EXT;
                    break;
                default:
                    $allowed = array_merge(ALLOWED_SPRITE_EXT, ALLOWED_AUDIO_EXT, ALLOWED_SCRIPT_EXT);
                    break;
            }

            if (!in_array($ext, $allowed)) {
                $errors[] = 'Tipe file tidak diizinkan untuk kategori ' . $kategori . '.';
            }
        }
    }

    // Simpan file dan database
    if (empty($errors)) {
        try {
            // Buat nama file unik
            $timestamp = time();
            $filename  = $timestamp . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', basename($file['name']));
            $upload_dir = UPLOAD_PATH . strtolower($kategori) . '/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $filepath = $upload_dir . $filename;

            // Move file ke folder upload
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Gagal menyimpan file.');
            }

            // Insert ke database
            $id = generateUUID();
            $file_url = '/uploads/' . strtolower($kategori) . '/' . $filename;
            $ukuran_kb = intval($file['size'] / 1024);
            $uploader_id = current_user_id();

            $stmt = $pdo->prepare('
                INSERT INTO assets (id, project_id, nama_aset, kategori, file_url, ukuran_kb, format, tags, uploader_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $id,
                $project_id,
                $nama_aset,
                $kategori,
                $file_url,
                $ukuran_kb,
                $ext,
                $tags,
                $uploader_id,
                'Pending'
            ]);

            set_flash('success', 'Aset berhasil diunggah! Menunggu persetujuan Admin.');
            $success = true;

        } catch (Exception $e) {
            error_log($e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan aset: ' . $e->getMessage();
        }
    }
}

$page_title = 'Upload Aset Game';
$active_nav = 'upload';
include '../templates/member_header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-lg-8">

            <!-- Error List -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <strong>Terjadi Kesalahan:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form Upload -->
            <div class="card">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" novalidate>
                        <?php require_once '../includes/csrf.php'; echo csrf_field(); ?>

                        <!-- Pilih Proyek -->
                        <div class="mb-4">
                            <label for="project_id" class="form-label">Proyek <span class="text-danger">*</span></label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">-- Pilih Proyek --</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?= e($proj['id']) ?>">
                                        <?= e($proj['nama_game']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pilih proyek yang sedang kamu kerjakan.</div>
                        </div>

                        <!-- Nama Aset -->
                        <div class="mb-4">
                            <label for="nama_aset" class="form-label">Nama Aset <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="nama_aset"
                                name="nama_aset"
                                placeholder="cth: Player Character Idle Animation"
                                maxlength="100"
                                required
                            >
                        </div>

                        <!-- Kategori -->
                        <div class="mb-4">
                            <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Sprite">Sprite (PNG, JPG, GIF, WebP, SVG)</option>
                                <option value="Audio">Audio (MP3, WAV, OGG, FLAC)</option>
                                <option value="Script">Script (PHP, JS, Lua, C#, GDScript, Python, Text, JSON, XML, ZIP)</option>
                                <option value="Other">Lainnya</option>
                            </select>
                        </div>

                        <!-- File Upload -->
                        <div class="mb-4">
                            <label for="file" class="form-label">File <span class="text-danger">*</span></label>
                            <input
                                type="file"
                                class="form-control"
                                id="file"
                                name="file"
                                required
                            >
                            <div class="form-text">
                                Ukuran maksimal: <?= MAX_FILE_SIZE_KB ?> KB
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-4">
                            <label for="tags" class="form-label">Tag (opsional)</label>
                            <input
                                type="text"
                                class="form-control"
                                id="tags"
                                name="tags"
                                placeholder="cth: character, idle, animation (pisahkan dengan koma)"
                                maxlength="255"
                            >
                            <div class="form-text">Gunakan tag untuk memudahkan pencarian.</div>
                        </div>

                        <!-- Submit -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-cloud-upload-alt me-2"></i>Upload Aset
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

</div>
</div>

<?php include '../templates/member_footer.php'; ?>
