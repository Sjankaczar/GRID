<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';
require_once '../includes/csrf.php';
 
require_role([ROLE_ADMIN]);
 
$errors = [];
 
// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
 
    // Buat proyek baru 
    if ($action === 'store') {
        $data = _validate_project_form($_POST);
        if (!empty($data['errors'])) {
            $errors = $data['errors'];
        } else {
            $cover_url = _handle_cover_upload();
            if (is_array($cover_url)) {
                $errors = array_merge($errors, $cover_url);
            } else {
                $id = generateUUID();
                $stmt = $pdo->prepare('
                    INSERT INTO projects
                        (id, nama_game, deskripsi, genre, platform, engine, status, cover_url, tanggal_mulai, target_rilis, lead_id)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $id,
                    $data['nama_game'], $data['deskripsi'], $data['genre'],
                    $data['platform'],  $data['engine'],    $data['status'],
                    $cover_url,         $data['tanggal_mulai'], $data['target_rilis'],
                    $data['lead_id'] ?: null,
                ]);
                set_flash('success', 'Proyek "' . $data['nama_game'] . '" berhasil dibuat.');
                redirect(APP_URL . '/admin/projects.php');
            }
        }
    }
 
    // Edit proyek
    if ($action === 'update') {
        $id = trim($_POST['project_id'] ?? '');
        if (empty($id)) { set_flash('error', 'ID proyek tidak valid.'); redirect(APP_URL . '/admin/projects.php'); }
 
        $existing = get_project_by_id($pdo, $id);
        if (!$existing) { set_flash('error', 'Proyek tidak ditemukan.'); redirect(APP_URL . '/admin/projects.php'); }
 
        $data = _validate_project_form($_POST);
        if (!empty($data['errors'])) {
            $errors = $data['errors'];
        } else {
            // Cover
            $cover_url = $existing['cover_url'];
            if (!empty($_FILES['cover']['name'])) {
                $result = _handle_cover_upload();
                if (is_array($result)) {
                    $errors = array_merge($errors, $result);
                } else {
                    $cover_url = $result;
                }
            }
 
            if (empty($errors)) {
                $stmt = $pdo->prepare('
                    UPDATE projects SET
                        nama_game = ?, deskripsi = ?, genre = ?, platform = ?,
                        engine = ?, status = ?, cover_url = ?,
                        tanggal_mulai = ?, target_rilis = ?, lead_id = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $data['nama_game'], $data['deskripsi'], $data['genre'],
                    $data['platform'],  $data['engine'],    $data['status'],
                    $cover_url,         $data['tanggal_mulai'], $data['target_rilis'],
                    $data['lead_id'] ?: null, $id,
                ]);
                set_flash('success', 'Proyek berhasil diperbarui.');
                redirect(APP_URL . '/admin/projects.php');
            }
        }
    }
 
    // Hapus proyek
    if ($action === 'delete') {
        $id = trim($_POST['project_id'] ?? '');
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Proyek berhasil dihapus beserta semua aset, devlog, dan tugasnya.');
        }
        redirect(APP_URL . '/admin/projects.php');
    }
 
    // Tambah anggota ke proyek
    if ($action === 'assign') {
        $project_id = trim($_POST['project_id'] ?? '');
        $user_id    = trim($_POST['user_id']    ?? '');
        if ($project_id && $user_id) {
            try {
                $stmt = $pdo->prepare('INSERT INTO project_members (id, project_id, user_id) VALUES (?, ?, ?)');
                $stmt->execute([generateUUID(), $project_id, $user_id]);
                set_flash('success', 'Anggota berhasil ditambahkan ke proyek.');
            } catch (PDOException $e) {
                set_flash('error', 'Anggota sudah ada di proyek ini.');
            }
        }
        redirect(APP_URL . '/admin/projects.php?view=members&id=' . urlencode($project_id));
    }
 
    // Hapus anggota dari proyek
    if ($action === 'unassign') {
        $project_id = trim($_POST['project_id'] ?? '');
        $user_id    = trim($_POST['user_id']    ?? '');
        if ($project_id && $user_id) {
            $stmt = $pdo->prepare('DELETE FROM project_members WHERE project_id = ? AND user_id = ?');
            $stmt->execute([$project_id, $user_id]);
            set_flash('success', 'Anggota berhasil dikeluarkan dari proyek.');
        }
        redirect(APP_URL . '/admin/projects.php?view=members&id=' . urlencode($project_id));
    }
}
 
// TENTUKAN VIEW
$view       = $_GET['view'] ?? 'list';
$edit_id    = $_GET['id']   ?? null;
$project    = null;
$members    = [];
$non_members= [];
$all_users  = get_all_active_users($pdo);
 
if ($view === 'form' && $edit_id) {
    $project = get_project_by_id($pdo, $edit_id);
    if (!$project) { set_flash('error', 'Proyek tidak ditemukan.'); redirect(APP_URL . '/admin/projects.php'); }

    if (!empty($errors)) {
        $project = array_merge($project, _map_post_to_project($_POST));
    }
} elseif ($view === 'form' && !empty($errors)) {
    
    $project = _map_post_to_project($_POST);
} elseif ($view === 'members' && $edit_id) {
    $project     = get_project_by_id($pdo, $edit_id);
    if (!$project) { set_flash('error', 'Proyek tidak ditemukan.'); redirect(APP_URL . '/admin/projects.php'); }
    $members     = get_project_members($pdo, $edit_id);
    $non_members = get_non_project_members($pdo, $edit_id);
}
 
// list view
$projects = ($view === 'list') ? get_all_projects($pdo) : [];
 
// HELPER FUNCTIONS 
function _validate_project_form(array $post): array {
    $errors = [];
    $nama_game     = trim($post['nama_game']     ?? '');
    $deskripsi     = trim($post['deskripsi']     ?? '');
    $genre         = trim($post['genre']         ?? '');
    $platform      = trim($post['platform']      ?? '');
    $engine        = trim($post['engine']        ?? '');
    $status        = $post['status']             ?? '';
    $tanggal_mulai = $post['tanggal_mulai']       ?? '';
    $target_rilis  = $post['target_rilis']        ?? '';
    $lead_id       = trim($post['lead_id']       ?? '');
 
    if (empty($nama_game))  $errors[] = 'Nama game wajib diisi.';
    if (mb_strlen($nama_game) > 100) $errors[] = 'Nama game maksimal 100 karakter.';
 
    $valid_status = ['Planning', 'Development', 'Testing', 'Released', 'On Hold'];
    if (!in_array($status, $valid_status, true)) $errors[] = 'Status tidak valid.';
 
    if ($tanggal_mulai && $target_rilis && $target_rilis < $tanggal_mulai) {
        $errors[] = 'Target rilis tidak boleh sebelum tanggal mulai.';
    }
 
    return [
        'errors'        => $errors,
        'nama_game'     => $nama_game,
        'deskripsi'     => $deskripsi,
        'genre'         => $genre,
        'platform'      => $platform,
        'engine'        => $engine,
        'status'        => $status,
        'tanggal_mulai' => $tanggal_mulai ?: null,
        'target_rilis'  => $target_rilis  ?: null,
        'lead_id'       => $lead_id,
    ];
}
 
function _handle_cover_upload() {
    if (empty($_FILES['cover']['name'])) return null;
    $file = $_FILES['cover'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['Gagal mengunggah cover. Coba lagi.'];
 
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) return ['Format cover tidak valid. Gunakan JPG, PNG, atau WebP.'];
    if ($file['size'] > 2097152) return ['Ukuran cover maksimal 2 MB.'];
 
    $upload_dir = ROOT_PATH . 'uploads/covers/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
 
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($file['name']));
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return ['Gagal menyimpan file cover.'];
    }
    return '/uploads/covers/' . $filename;
}
 
function _map_post_to_project(array $post): array {
    return [
        'nama_game'     => trim($post['nama_game']     ?? ''),
        'deskripsi'     => trim($post['deskripsi']     ?? ''),
        'genre'         => trim($post['genre']         ?? ''),
        'platform'      => trim($post['platform']      ?? ''),
        'engine'        => trim($post['engine']        ?? ''),
        'status'        => $post['status']             ?? 'Planning',
        'tanggal_mulai' => $post['tanggal_mulai']      ?? '',
        'target_rilis'  => $post['target_rilis']       ?? '',
        'lead_id'       => trim($post['lead_id']       ?? ''),
        'cover_url'     => null,
    ];
}
 
// RENDER HTML
$page_title  = match($view) {
    'form'    => $edit_id ? 'Edit Proyek' : 'Buat Proyek Baru',
    'members' => 'Kelola Anggota Proyek',
    default   => 'Manajemen Proyek',
};
$active_nav  = 'projects';
$breadcrumbs = [];
 
if ($view === 'form') {
    $breadcrumbs = [['label' => 'Proyek', 'url' => APP_URL . '/admin/projects.php']];
    if ($edit_id) $breadcrumbs[] = ['label' => 'Edit', 'url' => ''];
}
if ($view === 'members' && $project) {
    $breadcrumbs = [
        ['label' => 'Proyek', 'url' => APP_URL . '/admin/projects.php'],
        ['label' => e($project['nama_game']), 'url' => ''],
    ];
}
 
include '../templates/admin_header.php';
?>
 
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>Terjadi kesalahan:</strong>
    <ul class="mb-0 mt-1 ps-3">
        <?php foreach ($errors as $e_msg): ?>
            <li><?= e($e_msg) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
 
 
<!-- VIEW: LIST -->
<?php if ($view === 'list'): ?>
 
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Semua Proyek <span class="badge bg-primary ms-2"><?= count($projects) ?></span></h5>
    <a href="?view=form" class="btn btn-primary btn-sm">
        <i class="fa fa-plus me-1"></i>Buat Proyek Baru
    </a>
</div>
 
<?php if (empty($projects)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa fa-gamepad fa-3x mb-3" style="opacity:0.3;"></i>
            <p class="mb-0">Belum ada proyek. <a href="?view=form">Buat proyek pertama</a>.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($projects as $proj): ?>
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <!-- Cover -->
                <?php if ($proj['cover_url']): ?>
                    <img src="<?= e(APP_URL . $proj['cover_url']) ?>"
                         alt="Cover <?= e($proj['nama_game']) ?>"
                         style="height:140px;object-fit:cover;border-radius:8px 8px 0 0;">
                <?php else: ?>
                    <div style="height:140px;background:var(--bg-input);border-radius:8px 8px 0 0;display:flex;align-items:center;justify-content:center;">
                        <i class="fa fa-gamepad fa-2x" style="color:var(--text-muted);"></i>
                    </div>
                <?php endif; ?>
 
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0"><?= e($proj['nama_game']) ?></h6>
                        <span class="badge <?= badge_status_proyek($proj['status']) ?>" style="font-size:0.7rem;">
                            <?= e($proj['status']) ?>
                        </span>
                    </div>
 
                    <div class="text-muted small mb-3">
                        <?php if ($proj['genre']): ?>
                            <i class="fa fa-tag me-1"></i><?= e($proj['genre']) ?>
                        <?php endif; ?>
                        <?php if ($proj['platform']): ?>
                            <span class="ms-2"><i class="fa fa-desktop me-1"></i><?= e($proj['platform']) ?></span>
                        <?php endif; ?>
                    </div>
 
                    <div class="text-muted small mb-3">
                        <i class="fa fa-users me-1"></i><?= (int)$proj['total_members'] ?> anggota
                        <?php if ($proj['lead_name']): ?>
                            &nbsp;·&nbsp;<i class="fa fa-user-tie me-1"></i><?= e($proj['lead_name']) ?>
                        <?php endif; ?>
                    </div>
 
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="?view=form&id=<?= urlencode($proj['id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-edit me-1"></i>Edit
                        </a>
                        <a href="?view=members&id=<?= urlencode($proj['id']) ?>" class="btn btn-sm btn-outline-info">
                            <i class="fa fa-users me-1"></i>Anggota
                        </a>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Hapus proyek \'<?= e(addslashes($proj['nama_game'])) ?>\'?\nSemua aset, devlog, dan tugas terkait juga akan dihapus.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"     value="delete">
                            <input type="hidden" name="project_id" value="<?= e($proj['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fa fa-trash me-1"></i>Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
 
 
<!-- VIEW: FORM -->
<?php elseif ($view === 'form'): ?>
 
<div class="row justify-content-center">
<div class="col-12 col-lg-8">
<div class="card">
<div class="card-body p-4">
 
    <form method="POST" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action"     value="<?= $edit_id ? 'update' : 'store' ?>">
        <?php if ($edit_id): ?>
        <input type="hidden" name="project_id" value="<?= e($edit_id) ?>">
        <?php endif; ?>
 
        <div class="row">
            <!-- Nama Game -->
            <div class="col-12 mb-3">
                <label class="form-label">Nama Game <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="nama_game"
                       value="<?= e($project['nama_game'] ?? '') ?>"
                       placeholder="cth: Hollow Knight, Celeste, Stardew Valley"
                       maxlength="100" required>
            </div>
 
            <!-- Deskripsi -->
            <div class="col-12 mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea class="form-control" name="deskripsi" rows="3"
                          placeholder="Deskripsi singkat tentang game..."><?= e($project['deskripsi'] ?? '') ?></textarea>
            </div>
 
            <!-- Genre & Platform -->
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Genre</label>
                <input type="text" class="form-control" name="genre"
                       value="<?= e($project['genre'] ?? '') ?>"
                       placeholder="cth: Platformer, RPG, Puzzle"
                       maxlength="50">
            </div>
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Platform</label>
                <input type="text" class="form-control" name="platform"
                       value="<?= e($project['platform'] ?? '') ?>"
                       placeholder="cth: PC, Android, Web"
                       maxlength="50">
            </div>
 
            <!-- Engine & Status -->
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Game Engine</label>
                <input type="text" class="form-control" name="engine"
                       value="<?= e($project['engine'] ?? '') ?>"
                       placeholder="cth: Godot, Unity, GameMaker"
                       maxlength="50">
            </div>
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" name="status" required>
                    <?php foreach (['Planning','Development','Testing','Released','On Hold'] as $s): ?>
                    <option value="<?= $s ?>"
                        <?= (($project['status'] ?? 'Planning') === $s) ? 'selected' : '' ?>>
                        <?= $s ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
 
            <!-- Tanggal Mulai & Target Rilis -->
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control" name="tanggal_mulai"
                       value="<?= e($project['tanggal_mulai'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Target Rilis</label>
                <input type="date" class="form-control" name="target_rilis"
                       value="<?= e($project['target_rilis'] ?? '') ?>">
            </div>
 
            <!-- Lead -->
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Studio Lead</label>
                <select class="form-select" name="lead_id">
                    <option value="">— Tidak Ditentukan —</option>
                    <?php foreach ($all_users as $u): ?>
                    <option value="<?= e($u['id']) ?>"
                        <?= (($project['lead_id'] ?? '') === $u['id']) ? 'selected' : '' ?>>
                        <?= e($u['nama_lengkap']) ?> (<?= e($u['role']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
 
            <!-- Cover -->
            <div class="col-12 col-md-6 mb-3">
                <label class="form-label">Cover Game</label>
                <?php if (!empty($project['cover_url']) && $edit_id): ?>
                    <div class="mb-2">
                        <img src="<?= e(APP_URL . $project['cover_url']) ?>" alt="Cover saat ini"
                             style="height:60px;border-radius:4px;object-fit:cover;">
                        <span class="text-muted small ms-2">Cover saat ini</span>
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control" name="cover"
                       accept=".jpg,.jpeg,.png,.webp">
                <div class="form-text">JPG/PNG/WebP, maks. 2 MB. Kosongkan untuk mempertahankan cover lama.</div>
            </div>
        </div>
 
        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save me-1"></i>
                <?= $edit_id ? 'Simpan Perubahan' : 'Buat Proyek' ?>
            </button>
            <a href="<?= APP_URL ?>/admin/projects.php" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
 
</div>
</div>
</div>
</div>
 
 
<!-- VIEW: MEMBERS -->
<?php elseif ($view === 'members' && $project): ?>
 
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0"><?= e($project['nama_game']) ?></h5>
        <small class="text-muted">
            <span class="badge <?= badge_status_proyek($project['status']) ?> me-2"><?= e($project['status']) ?></span>
            <?= count($members) ?> anggota
        </small>
    </div>
    <a href="<?= APP_URL ?>/admin/projects.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i>Kembali
    </a>
</div>
 
<div class="row">
    <!-- Current Members -->
    <div class="col-12 col-md-7 mb-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fa fa-users me-2"></i>Anggota Tim
                    <span class="badge bg-primary ms-1"><?= count($members) ?></span>
                </h6>
 
                <?php if (empty($members)): ?>
                    <p class="text-muted small text-center py-3">
                        Belum ada anggota di proyek ini.
                    </p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($members as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center"
                            style="background:transparent;border-color:var(--border-color);color:var(--text-primary);">
                            <div>
                                <strong class="small"><?= e($m['nama_lengkap']) ?></strong>
                                <br>
                                <small class="text-muted">@<?= e($m['username']) ?></small>
                                <span class="badge bg-secondary ms-1" style="font-size:0.65rem;"><?= e($m['role']) ?></span>
                            </div>
                            <form method="POST"
                                  onsubmit="return confirm('Keluarkan <?= e(addslashes($m['nama_lengkap'])) ?> dari proyek ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"     value="unassign">
                                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                <input type="hidden" name="user_id"    value="<?= e($m['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fa fa-user-minus"></i>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
 
    <!-- Add Member -->
    <div class="col-12 col-md-5 mb-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fa fa-user-plus me-2"></i>Tambah Anggota
                </h6>
                <?php if (empty($non_members)): ?>
                    <p class="text-muted small">
                        Semua user aktif sudah menjadi anggota proyek ini.
                    </p>
                <?php else: ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action"     value="assign">
                        <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Pilih User</label>
                            <select class="form-select" name="user_id" required>
                                <option value="">— Pilih anggota —</option>
                                <?php foreach ($non_members as $u): ?>
                                <option value="<?= e($u['id']) ?>">
                                    <?= e($u['nama_lengkap']) ?> — <?= e($u['role']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fa fa-plus me-1"></i>Tambahkan ke Proyek
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
 
<?php endif; ?>
 
<?php include '../templates/admin_footer.php'; ?>