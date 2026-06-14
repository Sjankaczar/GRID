<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';
require_once '../includes/csrf.php';

require_role([ROLE_ADMIN, ROLE_MEMBER]);

$user_id = current_user_id();
$errors  = [];

// Ambil proyek yang diikuti user
$user_projects = get_user_projects($pdo, $user_id);

// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // Sanitasi konten HTML dari editor
    $allowed_tags = '<b><strong><i><em><u><p><br><ul><ol><li><h2><h3><blockquote>';
    $raw_konten   = $_POST['konten'] ?? '';
    $konten       = strip_tags($raw_konten, $allowed_tags);

    // Buat devlog baru
    if ($action === 'store') {
        $data   = _validate_devlog_form($_POST, $konten, $user_projects);
        $errors = $data['errors'];

        if (empty($errors)) {
            $id = generateUUID();
            $stmt = $pdo->prepare('
                INSERT INTO devlogs
                    (id, project_id, penulis_id, judul, konten, kategori, tags, status, is_public)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $id, $data['project_id'], $user_id,
                $data['judul'], $konten, $data['kategori'],
                $data['tags'],  $data['status'],
                $data['is_public'] ? 1 : 0,
            ]);
            set_flash('success', 'Devlog "' . $data['judul'] . '" berhasil disimpan.');
            redirect(APP_URL . '/member/devlog.php');
        }
    }

    // Edit devlog
    if ($action === 'update') {
        $id      = trim($_POST['devlog_id'] ?? '');
        $devlog  = $id ? get_devlog_by_id($pdo, $id, $user_id) : null;

        if (!$devlog) {
            set_flash('error', 'Devlog tidak ditemukan atau bukan milik kamu.');
            redirect(APP_URL . '/member/devlog.php');
        }

        $data   = _validate_devlog_form($_POST, $konten, $user_projects);
        $errors = $data['errors'];

        if (empty($errors)) {
            $stmt = $pdo->prepare('
                UPDATE devlogs SET
                    project_id = ?, judul = ?, konten = ?, kategori = ?,
                    tags = ?, status = ?, is_public = ?
                WHERE id = ? AND penulis_id = ?
            ');
            $stmt->execute([
                $data['project_id'], $data['judul'], $konten,
                $data['kategori'],   $data['tags'],  $data['status'],
                $data['is_public'] ? 1 : 0,
                $id, $user_id,
            ]);
            set_flash('success', 'Devlog berhasil diperbarui.');
            redirect(APP_URL . '/member/devlog.php');
        }
    }

    // Hapus devlog
    if ($action === 'delete') {
        $id     = trim($_POST['devlog_id'] ?? '');
        $devlog = $id ? get_devlog_by_id($pdo, $id, $user_id) : null;
        if ($devlog) {
            $stmt = $pdo->prepare('DELETE FROM devlogs WHERE id = ? AND penulis_id = ?');
            $stmt->execute([$id, $user_id]);
            set_flash('success', 'Devlog berhasil dihapus.');
        } else {
            set_flash('error', 'Devlog tidak ditemukan atau bukan milik kamu.');
        }
        redirect(APP_URL . '/member/devlog.php');
    }
}

// Tentukan view
$view      = $_GET['view'] ?? 'list';
$edit_id   = $_GET['id']   ?? null;
$devlog    = null;
$devlogs   = [];

if ($view === 'form' && $edit_id) {
    $devlog = get_devlog_by_id($pdo, $edit_id, $user_id);
    if (!$devlog) {
        set_flash('error', 'Devlog tidak ditemukan atau bukan milik kamu.');
        redirect(APP_URL . '/member/devlog.php');
    }

    if (!empty($errors)) {
        $devlog = array_merge($devlog, _map_post_to_devlog($_POST));
    }
} elseif ($view === 'form' && !empty($errors)) {
    $devlog = _map_post_to_devlog($_POST);
} elseif ($view === 'detail' && $edit_id) {
    $devlog = get_devlog_by_id($pdo, $edit_id, $user_id);
    if (!$devlog) {
        set_flash('error', 'Devlog tidak ditemukan atau bukan milik kamu.');
        redirect(APP_URL . '/member/devlog.php');
    }
}

if ($view === 'list') {
    $devlogs = get_devlogs($pdo, $user_id);
}

// HELPER FUNCTIONS
function _validate_devlog_form(array $post, string $konten, array $user_projects): array {
    $errors     = [];
    $project_id = trim($post['project_id'] ?? '');
    $judul      = trim($post['judul']      ?? '');
    $kategori   = $post['kategori']        ?? '';
    $tags       = trim($post['tags']       ?? '');
    $status     = $post['status']          ?? 'Draft';
    $is_public  = isset($post['is_public']) && $post['is_public'] === '1';

    // Validasi proyek
    $valid_project_ids = array_column($user_projects, 'id');
    if (empty($project_id) || !in_array($project_id, $valid_project_ids, true)) {
        $errors[] = 'Pilih proyek yang valid.';
    }

    if (empty($judul)) {
        $errors[] = 'Judul devlog wajib diisi.';
    } elseif (mb_strlen($judul) > 200) {
        $errors[] = 'Judul devlog maksimal 200 karakter.';
    }

    if (empty(strip_tags($konten))) {
        $errors[] = 'Konten devlog tidak boleh kosong.';
    }

    $valid_kategori = ['Update', 'Bugfix', 'Feature', 'Announcement'];
    if (!in_array($kategori, $valid_kategori, true)) {
        $errors[] = 'Kategori tidak valid.';
    }

    $valid_status = ['Draft', 'Published'];
    if (!in_array($status, $valid_status, true)) $status = 'Draft';

    return compact('errors', 'project_id', 'judul', 'kategori', 'tags', 'status', 'is_public');
}

function _map_post_to_devlog(array $post): array {
    return [
        'project_id' => trim($post['project_id'] ?? ''),
        'judul'      => trim($post['judul']       ?? ''),
        'konten'     => $_POST['konten']           ?? '',
        'kategori'   => $post['kategori']          ?? '',
        'tags'       => trim($post['tags']         ?? ''),
        'status'     => $post['status']            ?? 'Draft',
        'is_public'  => isset($post['is_public']) && $post['is_public'] === '1' ? 1 : 0,
    ];
}

// RENDER
$page_title = match($view) {
    'form'   => $edit_id ? 'Edit Devlog' : 'Tulis Devlog Baru',
    'detail' => 'Baca Devlog',
    default  => 'Devlog Saya',
};
$active_nav  = 'devlog';
$breadcrumbs = [];
if (in_array($view, ['form', 'detail'])) {
    $breadcrumbs = [['label' => 'Devlog', 'url' => APP_URL . '/member/devlog.php']];
}

// CSS tambahan
$extra_css = '
<style>
.devlog-toolbar {
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-bottom: none;
    border-radius: 6px 6px 0 0;
    padding: 8px 10px;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.devlog-toolbar button {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.devlog-toolbar button:hover {
    background: rgba(79, 124, 255, 0.15);
    border-color: var(--accent-blue);
    color: var(--accent-blue);
}
.devlog-toolbar .sep {
    width: 1px;
    background: var(--border-color);
    margin: 0 4px;
}
.devlog-editor {
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 0 0 6px 6px;
    color: var(--text-primary);
    font-family: "Inter", sans-serif;
    font-size: 0.92rem;
    line-height: 1.7;
    min-height: 280px;
    padding: 14px 16px;
    outline: none;
    overflow-y: auto;
}
.devlog-editor:focus {
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 2px rgba(79,124,255,0.2);
}
.devlog-editor p    { margin-bottom: 0.6rem; }
.devlog-editor h2   { font-size: 1.2rem; font-weight: 600; margin: 1rem 0 0.4rem; }
.devlog-editor h3   { font-size: 1rem;   font-weight: 600; margin: 0.8rem 0 0.3rem; }
.devlog-editor ul,
.devlog-editor ol   { padding-left: 1.4rem; margin-bottom: 0.6rem; }
.devlog-editor blockquote {
    border-left: 3px solid var(--accent-blue);
    padding-left: 1rem;
    color: var(--text-secondary);
    margin: 0.8rem 0;
}
/* Devlog list card */
.devlog-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    transition: border-color 0.2s;
}
.devlog-card:hover { border-color: var(--accent-blue); }
.devlog-content-preview {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.6;
    /* Batasi tinggi preview */
    max-height: 60px;
    overflow: hidden;
}
</style>
';

// JS untuk editor
$extra_js = '';
if ($view === 'form') {
    $extra_js = '
<script>
function fmt(cmd, val) {
    document.execCommand(cmd, false, val || null);
    document.getElementById("devlog-editor").focus();
}

document.addEventListener("DOMContentLoaded", function() {
    var form   = document.getElementById("devlog-form");
    var editor = document.getElementById("devlog-editor");
    var hidden = document.getElementById("konten-hidden");

    // Saat form submit: copy innerHTML editor ke hidden input
    form.addEventListener("submit", function(e) {
        var content = editor.innerHTML.trim();
        // Anggap kosong jika hanya berisi <br> atau whitespace
        if (!content || content === "<br>") {
            e.preventDefault();
            alert("Konten devlog tidak boleh kosong.");
            editor.focus();
            return;
        }
        hidden.value = content;
    });

    // Paste sebagai plain text (cegah format aneh dari clipboard)
    editor.addEventListener("paste", function(e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData("text/plain");
        document.execCommand("insertText", false, text);
    });
});
</script>
';
}

include '../templates/member_header.php';
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
    <h5 class="mb-0">
        Devlog Saya
        <span class="badge bg-primary ms-2"><?= count($devlogs) ?></span>
    </h5>
    <?php if (!empty($user_projects)): ?>
        <a href="?view=form" class="btn btn-primary btn-sm">
            <i class="fa fa-plus me-1"></i>Tulis Devlog Baru
        </a>
    <?php endif; ?>
</div>

<?php if (empty($user_projects)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa fa-book-open fa-3x mb-3" style="opacity:0.3;"></i>
            <p class="mb-1">Kamu belum tergabung di proyek manapun.</p>
            <small>Minta Admin untuk menambahkan kamu ke sebuah proyek terlebih dahulu.</small>
        </div>
    </div>

<?php elseif (empty($devlogs)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa fa-book-open fa-3x mb-3" style="opacity:0.3;"></i>
            <p class="mb-1">Belum ada entri devlog.</p>
            <a href="?view=form" class="btn btn-primary btn-sm mt-2">
                <i class="fa fa-plus me-1"></i>Tulis Devlog Pertama
            </a>
        </div>
    </div>

<?php else: ?>
    <?php foreach ($devlogs as $dl): ?>
    <div class="devlog-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div class="flex-grow-1">
                <!-- Judul & Badge -->
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <a href="?view=detail&id=<?= urlencode($dl['id']) ?>"
                       class="text-decoration-none"
                       style="color:var(--text-primary);font-weight:500;">
                        <?= e($dl['judul']) ?>
                    </a>
                    <span class="badge bg-secondary" style="font-size:0.7rem;"><?= e($dl['kategori']) ?></span>
                    <span class="badge <?= $dl['status'] === 'Published' ? 'bg-success' : 'bg-secondary' ?>"
                          style="font-size:0.7rem;">
                        <?= e($dl['status']) ?>
                    </span>
                    <?php if ($dl['is_public']): ?>
                        <span class="badge bg-info text-dark" style="font-size:0.7rem;">
                            <i class="fa fa-globe me-1"></i>Publik
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Meta info -->
                <div class="text-muted small mb-2">
                    <i class="fa fa-gamepad me-1"></i><?= e($dl['project_name'] ?? '—') ?>
                    &nbsp;·&nbsp;
                    <i class="fa fa-clock me-1"></i><?= format_tanggal($dl['created_at']) ?>
                    <?php if ($dl['created_at'] !== $dl['updated_at']): ?>
                        &nbsp;·&nbsp;
                        <i class="fa fa-edit me-1"></i>Diperbarui <?= format_tanggal($dl['updated_at']) ?>
                    <?php endif; ?>
                </div>

                <!-- Preview konten -->
                <div class="devlog-content-preview">
                    <?= e(truncate(strip_tags($dl['konten']), 120)) ?>
                </div>

                <?php if ($dl['tags']): ?>
                <div class="mt-2">
                    <?php foreach (explode(',', $dl['tags']) as $tag): ?>
                        <span class="badge bg-secondary me-1" style="font-size:0.7rem;">
                            <?= e(trim($tag)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tombol Aksi -->
            <div class="d-flex gap-1 flex-shrink-0">
                <a href="?view=detail&id=<?= urlencode($dl['id']) ?>"
                   class="btn btn-sm btn-outline-secondary" title="Baca">
                    <i class="fa fa-eye"></i>
                </a>
                <a href="?view=form&id=<?= urlencode($dl['id']) ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="fa fa-edit"></i>
                </a>
                <form method="POST"
                      onsubmit="return confirm('Hapus devlog \'<?= e(addslashes($dl['judul'])) ?>\'?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"    value="delete">
                    <input type="hidden" name="devlog_id" value="<?= e($dl['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>


<!-- VIEW: FORM -->
<?php elseif ($view === 'form'): ?>

<?php if (empty($user_projects)): ?>
    <div class="alert alert-warning">
        Kamu belum tergabung di proyek manapun. Minta Admin untuk menambahkan kamu ke proyek terlebih dahulu.
        <a href="<?= APP_URL ?>/member/devlog.php" class="ms-2">Kembali</a>
    </div>
<?php else: ?>

<div class="row justify-content-center">
<div class="col-12 col-lg-10">

    <div class="card">
        <div class="card-body p-4 p-md-5">
            <h5 class="mb-4 pb-3 border-bottom text-primary" style="font-weight: 600;">
                <i class="fa fa-feather-alt me-2"></i><?= $edit_id ? 'Edit Entri Devlog' : 'Tulis Entri Devlog Baru' ?>
            </h5>
            <form method="POST" id="devlog-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action"    value="<?= $edit_id ? 'update' : 'store' ?>">
        <input type="hidden" name="konten"    id="konten-hidden">
        <?php if ($edit_id): ?>
        <input type="hidden" name="devlog_id" value="<?= e($edit_id) ?>">
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <!-- Judul -->
            <div class="col-12">
                <label class="form-label">Judul Devlog <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="judul"
                       value="<?= e($devlog['judul'] ?? '') ?>"
                       placeholder="cth: Update Minggu 3 — Implementasi Sistem Combat"
                       maxlength="200" required>
            </div>

            <!-- Proyek & Kategori -->
            <div class="col-12 col-md-6">
                <label class="form-label">Proyek <span class="text-danger">*</span></label>
                <select class="form-select" name="project_id" required>
                    <option value="">— Pilih Proyek —</option>
                    <?php foreach ($user_projects as $proj): ?>
                    <option value="<?= e($proj['id']) ?>"
                        <?= (($devlog['project_id'] ?? '') === $proj['id']) ? 'selected' : '' ?>>
                        <?= e($proj['nama_game']) ?>
                        (<?= e($proj['status']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Kategori <span class="text-danger">*</span></label>
                <select class="form-select" name="kategori" required>
                    <option value="">— Pilih Kategori —</option>
                    <?php foreach (['Update', 'Bugfix', 'Feature', 'Announcement'] as $kat): ?>
                    <option value="<?= $kat ?>"
                        <?= (($devlog['kategori'] ?? '') === $kat) ? 'selected' : '' ?>>
                        <?= $kat ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tags -->
            <div class="col-12">
                <label class="form-label">Tag <small class="text-muted">(opsional, pisahkan dengan koma)</small></label>
                <input type="text" class="form-control" name="tags"
                       value="<?= e($devlog['tags'] ?? '') ?>"
                       placeholder="cth: combat, animation, sprint-3"
                       maxlength="255">
            </div>
        </div>

        <!-- Editor Konten -->
        <div class="mb-3">
            <label class="form-label">Konten <span class="text-danger">*</span></label>

            <!-- Toolbar -->
            <div class="devlog-toolbar">
                <button type="button" onclick="fmt('bold')"           title="Bold"><b>B</b></button>
                <button type="button" onclick="fmt('italic')"         title="Italic"><i>I</i></button>
                <button type="button" onclick="fmt('underline')"      title="Underline"><u>U</u></button>
                <div class="sep"></div>
                <button type="button" onclick="fmt('formatBlock','h2')"       title="Heading 2">H2</button>
                <button type="button" onclick="fmt('formatBlock','h3')"       title="Heading 3">H3</button>
                <button type="button" onclick="fmt('formatBlock','p')"        title="Paragraf">¶</button>
                <div class="sep"></div>
                <button type="button" onclick="fmt('insertUnorderedList')"    title="Bullet List">• List</button>
                <button type="button" onclick="fmt('insertOrderedList')"      title="Numbered List">1. List</button>
                <button type="button" onclick="fmt('formatBlock','blockquote')" title="Blockquote">❝</button>
                <div class="sep"></div>
                <button type="button" onclick="fmt('removeFormat')"  title="Hapus Format">✕ Format</button>
            </div>

            <!-- Editable Area -->
            <div id="devlog-editor"
                 contenteditable="true"
                 class="devlog-editor"
                 spellcheck="false"><?php
                
                if (!empty($devlog['konten'])) {
                    echo $devlog['konten'];
                }
            ?></div>
            <div class="form-text">
                Gunakan toolbar di atas untuk format teks.
                Tidak perlu klik "Save" berkala — form ini tidak auto-save.
            </div>
        </div>

        <!-- Status & Publikasi -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <label class="form-label">Status Publikasi</label>
                <select class="form-select" name="status">
                    <option value="Draft"
                        <?= (($devlog['status'] ?? 'Draft') === 'Draft') ? 'selected' : '' ?>>
                        Draft — Hanya kamu yang bisa lihat
                    </option>
                    <option value="Published"
                        <?= (($devlog['status'] ?? '') === 'Published') ? 'selected' : '' ?>>
                        Published — Terlihat oleh seluruh tim
                    </option>
                </select>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-end">
                <div class="form-check pb-2">
                    <input class="form-check-input" type="checkbox"
                           name="is_public" value="1" id="is_public"
                           <?= (!empty($devlog['is_public'])) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="is_public">
                        <i class="fa fa-globe me-1"></i>
                        Jadikan publik (dapat dilihat di Landing Page)
                    </label>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save me-1"></i>
                <?= $edit_id ? 'Simpan Perubahan' : 'Simpan Devlog' ?>
            </button>
            <a href="<?= APP_URL ?>/member/devlog.php" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
    </div>

</div>
</div>
<?php endif; ?>


<!-- VIEW: DETAIL -->
<?php elseif ($view === 'detail' && $devlog): ?>

<div class="row justify-content-center">
<div class="col-12 col-lg-9">

    <div class="card">
        <div class="card-body p-4">

            <!-- Header -->
            <div class="mb-4">
                <h3 class="mb-2"><?= e($devlog['judul']) ?></h3>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-secondary"><?= e($devlog['kategori']) ?></span>
                    <span class="badge <?= $devlog['status'] === 'Published' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= e($devlog['status']) ?>
                    </span>
                    <?php if ($devlog['is_public']): ?>
                        <span class="badge bg-info text-dark">
                            <i class="fa fa-globe me-1"></i>Publik
                        </span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fa fa-gamepad me-1"></i><?= e($devlog['project_name'] ?? '—') ?>
                    &nbsp;·&nbsp;
                    <i class="fa fa-clock me-1"></i><?= format_tanggal($devlog['created_at']) ?>
                    <?php if ($devlog['tags']): ?>
                        &nbsp;·&nbsp;
                        <?php foreach (explode(',', $devlog['tags']) as $tag): ?>
                            <span class="badge bg-secondary me-1" style="font-size:0.7rem;"><?= e(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <hr style="border-color:var(--border-color);">

            <!-- Konten HTML dari editor -->
            <div class="devlog-editor" style="border:none;background:transparent;padding:0;min-height:unset;">
                <?= $devlog['konten'] ?>
            </div>

        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <a href="?view=form&id=<?= urlencode($devlog['id']) ?>" class="btn btn-outline-primary btn-sm">
            <i class="fa fa-edit me-1"></i>Edit
        </a>
        <a href="<?= APP_URL ?>/member/devlog.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i>Kembali ke Daftar
        </a>
    </div>

</div>
</div>

<?php endif; ?>

<?php include '../templates/member_footer.php'; ?>
