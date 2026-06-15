<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';
require_once '../includes/csrf.php';

require_role([ROLE_MEMBER, ROLE_ADMIN]);

$user_id = current_user_id();
$org_id  = $_SESSION['organization_id'] ?? null;
$errors  = [];

// PILIH PROYEK AKTIF
$selected_project_id = $_GET['project_id'] ?? ($_POST['project_id'] ?? null);

$my_projects = get_user_projects($pdo, $user_id);
if (current_role() === ROLE_ADMIN) {
    $my_projects = get_all_projects_by_org($pdo, $org_id);
}

if (!$selected_project_id && !empty($my_projects)) {
    $selected_project_id = $my_projects[0]['id'];
}

$current_project = null;
if ($selected_project_id) {
    foreach ($my_projects as $p) {
        if ($p['id'] === $selected_project_id) {
            $current_project = $p;
            break;
        }
    }
    if (!$current_project) {
        set_flash('error', 'Kamu tidak memiliki akses ke proyek tersebut.');
        redirect(APP_URL . '/member/bug_report.php');
    }
}

// HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!rate_limit_check('bug_action', 20, 60)) {
        set_flash('error', 'Terlalu banyak aksi. Tunggu sebentar.');
        redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($selected_project_id));
    }

    $action     = $_POST['action'] ?? '';
    $project_id = trim($_POST['project_id'] ?? '');

    // Verifikasi proyek
    if ($project_id) {
        $proj_check = get_project_by_id($pdo, $project_id);
        if (!$proj_check || ($org_id && $proj_check['organization_id'] !== $org_id)) {
            set_flash('error', 'Proyek tidak valid.');
            redirect(APP_URL . '/member/bug_report.php');
        }
    }

    // Laporkan Bug Baru
    if ($action === 'store') {
        $judul     = trim($_POST['judul']    ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $langkah   = trim($_POST['langkah']  ?? '') ?: null;
        $prioritas = $_POST['prioritas']     ?? 'Medium';
        $task_id   = trim($_POST['task_id']  ?? '') ?: null;

        if (empty($judul))    $errors[] = 'Judul bug wajib diisi.';
        elseif (mb_strlen($judul) > 200) $errors[] = 'Judul maksimal 200 karakter.';
        if (empty($deskripsi)) $errors[] = 'Deskripsi bug wajib diisi.';
        if (!in_array($prioritas, ['Low','Medium','High','Critical'], true)) $prioritas = 'Medium';

        // Verifikasi task_id harus milik proyek ini
        if ($task_id) {
            $task_check = get_task_by_id($pdo, $task_id);
            if (!$task_check || $task_check['project_id'] !== $project_id) {
                $errors[] = 'Tugas yang dipilih tidak valid.';
                $task_id  = null;
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('
                INSERT INTO bug_reports (id, project_id, task_id, reporter_id, judul, deskripsi, langkah, prioritas, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Open")
            ');
            $stmt->execute([
                generateUUID(), $project_id, $task_id, $user_id,
                $judul, $deskripsi, $langkah, $prioritas,
            ]);
            set_flash('success', 'Bug "' . $judul . '" berhasil dilaporkan.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }
    }

    // Update Status Bug
    if ($action === 'update_status') {
        $bug_id    = trim($_POST['bug_id']    ?? '');
        $new_status = trim($_POST['new_status'] ?? '');
        $allowed   = ['Open', 'In Progress', 'Resolved', 'Closed'];

        if (!in_array($new_status, $allowed, true)) {
            set_flash('error', 'Status tidak valid.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }

        $bug = get_bug_by_id($pdo, $bug_id);
        if (!$bug || $bug['project_id'] !== $project_id) {
            set_flash('error', 'Bug tidak ditemukan.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }

        $stmt = $pdo->prepare('UPDATE bug_reports SET status = ? WHERE id = ?');
        $stmt->execute([$new_status, $bug_id]);

        // Jika status Resolved, buat task otomatis sebagai referensi 
        if ($new_status === 'Resolved' && $bug['task_id']) {
            $existing_task = get_task_by_id($pdo, $bug['task_id']);
            if ($existing_task && $existing_task['status_kolom'] === 'In Progress') {
                $pdo->prepare('UPDATE tasks SET status_kolom = "Review" WHERE id = ?')
                    ->execute([$bug['task_id']]);
            }
        }

        set_flash('success', 'Status bug diperbarui menjadi "' . $new_status . '".');
        redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
    }

    // Edit Bug
    if ($action === 'update') {
        $bug_id    = trim($_POST['bug_id']    ?? '');
        $judul     = trim($_POST['judul']     ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $langkah   = trim($_POST['langkah']   ?? '') ?: null;
        $prioritas = $_POST['prioritas']      ?? 'Medium';

        $bug = get_bug_by_id($pdo, $bug_id);
        if (!$bug || $bug['project_id'] !== $project_id) {
            set_flash('error', 'Bug tidak ditemukan.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }

        // Hanya reporter atau Admin yang bisa edit
        if ($bug['reporter_id'] !== $user_id && current_role() !== ROLE_ADMIN) {
            set_flash('error', 'Kamu tidak berhak mengedit laporan bug milik orang lain.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }

        if (empty($judul))    $errors[] = 'Judul wajib diisi.';
        if (empty($deskripsi)) $errors[] = 'Deskripsi wajib diisi.';
        if (!in_array($prioritas, ['Low','Medium','High','Critical'], true)) $prioritas = 'Medium';

        if (empty($errors)) {
            $stmt = $pdo->prepare('
                UPDATE bug_reports SET judul = ?, deskripsi = ?, langkah = ?, prioritas = ?
                WHERE id = ?
            ');
            $stmt->execute([$judul, $deskripsi, $langkah, $prioritas, $bug_id]);
            set_flash('success', 'Laporan bug berhasil diperbarui.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }
    }

    // Hapus Bug
    if ($action === 'delete') {
        $bug_id = trim($_POST['bug_id'] ?? '');
        $bug    = get_bug_by_id($pdo, $bug_id);

        if (!$bug || $bug['project_id'] !== $project_id) {
            set_flash('error', 'Bug tidak ditemukan.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }

        // Hanya reporter atau Admin yang bisa hapus
        if ($bug['reporter_id'] !== $user_id && current_role() !== ROLE_ADMIN) {
            set_flash('error', 'Kamu tidak berhak menghapus laporan bug ini.');
            redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
        }

        $pdo->prepare('DELETE FROM bug_reports WHERE id = ?')->execute([$bug_id]);
        set_flash('success', 'Laporan bug berhasil dihapus.');
        redirect(APP_URL . '/member/bug_report.php?project_id=' . urlencode($project_id));
    }
}

// AMBIL DATA UNTUK TAMPILAN
$bugs        = $current_project ? get_bugs_by_project($pdo, $selected_project_id)       : [];
$bug_summary = $current_project ? get_bug_summary_by_project($pdo, $selected_project_id) : [];
$tasks_list  = $current_project ? get_tasks_by_project($pdo, $selected_project_id)       : [];

// Filter status dari URL
$filter_status = $_GET['status'] ?? '';
$status_opts   = ['', 'Open', 'In Progress', 'Resolved', 'Closed'];
if (!in_array($filter_status, $status_opts, true)) $filter_status = '';

$bugs_displayed = $filter_status
    ? array_filter($bugs, fn($b) => $b['status'] === $filter_status)
    : $bugs;

$page_title  = 'Bug Tracker';
$active_nav  = 'bug_report';
$breadcrumbs = [['label' => 'Bug Tracker', 'url' => '']];
include '../templates/member_header.php';
?>

<div class="container-fluid">

    <?php if (empty($my_projects)): ?>
    <div class="alert alert-info">Kamu belum ditugaskan ke proyek manapun.</div>
    <?php else: ?>

    <!-- Header & Project Switcher -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="fa fa-bug me-2 text-danger"></i>
                Bug Tracker — <?= e($current_project['nama_game'] ?? 'Pilih Proyek') ?>
            </h4>
            <?php if ($current_project): ?>
            <small class="text-muted">
                <?= $bug_summary['Open'] ?? 0 ?> open ·
                <?= $bug_summary['In Progress'] ?? 0 ?> in progress ·
                <?= $bug_summary['Resolved'] ?? 0 ?> resolved ·
                <?= $bug_summary['Closed'] ?? 0 ?> closed
            </small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="bug_report.php" class="d-flex gap-2 align-items-center">
                <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px;">
                    <?php foreach ($my_projects as $p): ?>
                    <option value="<?= e($p['id']) ?>" <?= $p['id'] === $selected_project_id ? 'selected' : '' ?>>
                        <?= e($p['nama_game']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:130px;">
                    <option value="">Semua Status</option>
                    <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($current_project): ?>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddBug">
                <i class="fa fa-plus me-1"></i>Laporkan Bug
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php render_flash(); ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul></div>
    <?php endif; ?>

    <?php if ($current_project): ?>

    <!-- Bug Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="bugTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:35%">Judul Bug</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Task Terkait</th>
                            <th>Reporter</th>
                            <th>Tanggal</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bugs_displayed)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            <i class="fa fa-check-circle fa-2x d-block mb-2 text-success opacity-50"></i>
                            Tidak ada bug yang ditemukan
                        </td></tr>
                        <?php endif; ?>

                        <?php foreach ($bugs_displayed as $bug): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= e($bug['judul']) ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= e(truncate($bug['deskripsi'], 80)) ?></div>
                            </td>
                            <td><span class="badge <?= badge_prioritas($bug['prioritas']) ?>"><?= e($bug['prioritas']) ?></span></td>
                            <td>
                                <?php
                                $status_class = match($bug['status']) {
                                    'Open'        => 'bg-danger',
                                    'In Progress' => 'bg-primary',
                                    'Resolved'    => 'bg-success',
                                    'Closed'      => 'bg-secondary',
                                    default       => 'bg-secondary',
                                };
                                ?>
                                <span class="badge <?= $status_class ?>"><?= e($bug['status']) ?></span>
                            </td>
                            <td class="small text-muted"><?= $bug['task_judul'] ? e($bug['task_judul']) : '—' ?></td>
                            <td class="small"><?= e($bug['reporter_name'] ?? '—') ?></td>
                            <td class="small text-muted"><?= e(format_tanggal($bug['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <!-- Update Status -->
                                        <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                                        <?php if ($s !== $bug['status']): ?>
                                        <li>
                                            <form method="POST" action="bug_report.php?project_id=<?= urlencode($selected_project_id) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="project_id" value="<?= e($selected_project_id) ?>">
                                                <input type="hidden" name="bug_id" value="<?= e($bug['id']) ?>">
                                                <input type="hidden" name="new_status" value="<?= e($s) ?>">
                                                <button type="submit" class="dropdown-item small">
                                                    <i class="fa fa-arrow-right me-1"></i>Tandai <?= e($s) ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <!-- Edit -->
                                        <?php if ($bug['reporter_id'] === $user_id || current_role() === ROLE_ADMIN): ?>
                                        <li>
                                            <button class="dropdown-item small btn-edit-bug"
                                                data-id="<?= e($bug['id']) ?>"
                                                data-judul="<?= e($bug['judul']) ?>"
                                                data-deskripsi="<?= e($bug['deskripsi']) ?>"
                                                data-langkah="<?= e($bug['langkah'] ?? '') ?>"
                                                data-prioritas="<?= e($bug['prioritas']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalEditBug">
                                                <i class="fa fa-edit me-1"></i>Edit
                                            </button>
                                        </li>
                                        <li>
                                            <form method="POST" action="bug_report.php?project_id=<?= urlencode($selected_project_id) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="project_id" value="<?= e($selected_project_id) ?>">
                                                <input type="hidden" name="bug_id" value="<?= e($bug['id']) ?>">
                                                <button type="submit" class="dropdown-item text-danger small"
                                                    onclick="return confirm('Hapus laporan bug ini?')">
                                                    <i class="fa fa-trash me-1"></i>Hapus
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php endif; /* current_project */ ?>
    <?php endif; /* my_projects */ ?>

</div>

<!-- Modal: Laporkan Bug -->
<div class="modal fade" id="modalAddBug" tabindex="-1" aria-labelledby="modalAddBugLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="bug_report.php?project_id=<?= urlencode($selected_project_id ?? '') ?>" novalidate>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddBugLabel"><i class="fa fa-bug me-2 text-danger"></i>Laporkan Bug Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="store">
                    <input type="hidden" name="project_id" value="<?= e($selected_project_id ?? '') ?>">

                    <div class="mb-3">
                        <label class="form-label">Judul Bug <span class="text-danger">*</span></label>
                        <input type="text" name="judul" class="form-control" maxlength="200" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Bug <span class="text-danger">*</span></label>
                        <textarea name="deskripsi" class="form-control" rows="3" required placeholder="Apa yang terjadi?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Langkah Reproduksi</label>
                        <textarea name="langkah" class="form-control" rows="3" placeholder="1. Buka halaman X&#10;2. Klik tombol Y&#10;3. Bug muncul"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Prioritas</label>
                            <select name="prioritas" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Terkait Task (opsional)</label>
                            <select name="task_id" class="form-select">
                                <option value="">— Tidak ada —</option>
                                <?php foreach ($tasks_list as $t): ?>
                                <option value="<?= e($t['id']) ?>"><?= e($t['judul']) ?> (<?= e($t['status_kolom']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-bug me-1"></i>Laporkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Bug -->
<div class="modal fade" id="modalEditBug" tabindex="-1" aria-labelledby="modalEditBugLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="bug_report.php?project_id=<?= urlencode($selected_project_id ?? '') ?>" novalidate>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditBugLabel"><i class="fa fa-edit me-2"></i>Edit Laporan Bug</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="project_id" value="<?= e($selected_project_id ?? '') ?>">
                    <input type="hidden" name="bug_id" id="edit_bug_id">

                    <div class="mb-3">
                        <label class="form-label">Judul Bug <span class="text-danger">*</span></label>
                        <input type="text" name="judul" id="edit_bug_judul" class="form-control" maxlength="200" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                        <textarea name="deskripsi" id="edit_bug_deskripsi" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Langkah Reproduksi</label>
                        <textarea name="langkah" id="edit_bug_langkah" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Prioritas</label>
                        <select name="prioritas" id="edit_bug_prioritas" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i>Perbarui</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.btn-edit-bug').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_bug_id').value       = this.dataset.id;
        document.getElementById('edit_bug_judul').value    = this.dataset.judul;
        document.getElementById('edit_bug_deskripsi').value = this.dataset.deskripsi;
        document.getElementById('edit_bug_langkah').value  = this.dataset.langkah;
        document.getElementById('edit_bug_prioritas').value = this.dataset.prioritas;
    });
});
</script>

<?php include '../templates/member_footer.php'; ?>
