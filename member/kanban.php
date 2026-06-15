<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';
require_once '../includes/csrf.php';

require_role([ROLE_MEMBER, ROLE_ADMIN]);

$user_id     = current_user_id();
$org_id      = $_SESSION['organization_id'] ?? null;
$errors      = [];

// TENTUKAN PROYEK AKTIF
$selected_project_id = $_GET['project_id'] ?? ($_POST['project_id'] ?? null);

// Ambil daftar proyek yang boleh diakses oleh user ini
$my_projects = get_user_projects($pdo, $user_id);

// Jika Admin, tampilkan semua proyek dalam org-nya
if (current_role() === ROLE_ADMIN) {
    $my_projects = get_all_projects_by_org($pdo, $org_id);
}

// Fallback ke proyek pertama jika belum dipilih
if (!$selected_project_id && !empty($my_projects)) {
    $selected_project_id = $my_projects[0]['id'];
}

// Verifikasi akses
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
        redirect(APP_URL . '/member/kanban.php');
    }
}

// HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!rate_limit_check('kanban_action', 30, 60)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Rate limit']);
            exit;
        }
        set_flash('error', 'Terlalu banyak aksi dalam satu menit. Tunggu sebentar.');
        redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($selected_project_id));
    }

    $action     = $_POST['action'] ?? '';
    $project_id = trim($_POST['project_id'] ?? '');

    // Verifikasi kembali bahwa proyek ini memang milik org user
    if ($project_id) {
        $proj_check = get_project_by_id($pdo, $project_id);
        if (!$proj_check || ($org_id && $proj_check['organization_id'] !== $org_id)) {
            set_flash('error', 'Proyek tidak valid.');
            redirect(APP_URL . '/member/kanban.php');
        }
    }

    // Buat Task Baru
    if ($action === 'store') {
        $judul        = trim($_POST['judul']        ?? '');
        $deskripsi    = trim($_POST['deskripsi']    ?? '');
        $prioritas    = $_POST['prioritas']         ?? 'Medium';
        $assignee_id  = trim($_POST['assignee_id']  ?? '') ?: null;
        $deadline     = trim($_POST['deadline']     ?? '') ?: null;
        $estimasi_jam = (int)($_POST['estimasi_jam'] ?? 0) ?: null;

        // Validasi
        if (empty($judul)) {
            $errors[] = 'Judul tugas wajib diisi.';
        } elseif (mb_strlen($judul) > 200) {
            $errors[] = 'Judul tugas maksimal 200 karakter.';
        }
        if (!in_array($prioritas, ['Low','Medium','High','Critical'], true)) {
            $prioritas = 'Medium';
        }
        if ($deadline && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $errors[] = 'Format deadline tidak valid.';
        }
        if ($estimasi_jam !== null && $estimasi_jam < 0) {
            $errors[] = 'Estimasi jam tidak boleh negatif.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('
                INSERT INTO tasks (id, project_id, judul, deskripsi, status_kolom, prioritas, assignee_id, deadline, estimasi_jam)
                VALUES (?, ?, ?, ?, "To Do", ?, ?, ?, ?)
            ');
            $stmt->execute([
                generateUUID(), $project_id, $judul, $deskripsi ?: null,
                $prioritas, $assignee_id, $deadline, $estimasi_jam,
            ]);
            set_flash('success', 'Tugas "' . $judul . '" berhasil dibuat.');
            redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
        }
    }

    // Update Task (Edit form)
    if ($action === 'update') {
        $task_id      = trim($_POST['task_id']      ?? '');
        $judul        = trim($_POST['judul']        ?? '');
        $deskripsi    = trim($_POST['deskripsi']    ?? '');
        $prioritas    = $_POST['prioritas']         ?? 'Medium';
        $assignee_id  = trim($_POST['assignee_id']  ?? '') ?: null;
        $deadline     = trim($_POST['deadline']     ?? '') ?: null;
        $estimasi_jam = (int)($_POST['estimasi_jam'] ?? 0) ?: null;

        $task = get_task_by_id($pdo, $task_id);
        if (!$task || $task['project_id'] !== $project_id) {
            set_flash('error', 'Tugas tidak ditemukan.');
            redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
        }

        if (empty($judul)) $errors[] = 'Judul tugas wajib diisi.';
        if (!in_array($prioritas, ['Low','Medium','High','Critical'], true)) $prioritas = 'Medium';

        if (empty($errors)) {
            $stmt = $pdo->prepare('
                UPDATE tasks SET
                    judul = ?, deskripsi = ?, prioritas = ?,
                    assignee_id = ?, deadline = ?, estimasi_jam = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $judul, $deskripsi ?: null, $prioritas,
                $assignee_id, $deadline, $estimasi_jam,
                $task_id,
            ]);
            set_flash('success', 'Tugas berhasil diperbarui.');
            redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
        }
    }

    // Update Status Kolom Kanban
    if ($action === 'move') {
        $task_id    = trim($_POST['task_id']    ?? '');
        $new_status = trim($_POST['new_status'] ?? '');
        $allowed    = ['To Do', 'In Progress', 'Review', 'Done'];

        if (!in_array($new_status, $allowed, true)) {
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Invalid status']); exit; }
            set_flash('error', 'Status kolom tidak valid.');
            redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
        }

        $task = get_task_by_id($pdo, $task_id);
        if (!$task || $task['project_id'] !== $project_id) {
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Not found']); exit; }
            set_flash('error', 'Tugas tidak ditemukan.');
            redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
        }

        $stmt = $pdo->prepare('UPDATE tasks SET status_kolom = ? WHERE id = ?');
        $stmt->execute([$new_status, $task_id]);

        if ($is_ajax) {
            $new_token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $new_token;
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $new_status, 'csrf_token' => $new_token]);
            exit;
        }

        set_flash('success', 'Status tugas diperbarui.');
        redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
    }

    // Hapus Task
    if ($action === 'delete') {
        $task_id = trim($_POST['task_id'] ?? '');
        $task    = get_task_by_id($pdo, $task_id);

        if (!$task || $task['project_id'] !== $project_id) {
            set_flash('error', 'Tugas tidak ditemukan.');
            redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
        }

        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$task_id]);
        set_flash('success', 'Tugas berhasil dihapus.');
        redirect(APP_URL . '/member/kanban.php?project_id=' . urlencode($project_id));
    }
}

// AMBIL DATA UNTUK TAMPILAN
$tasks       = $current_project ? get_tasks_by_project($pdo, $selected_project_id) : [];
$task_summary = $current_project ? get_task_summary_by_project($pdo, $selected_project_id) : [];
$org_members = $current_project ? get_project_members($pdo, $selected_project_id) : [];

// Kelompokkan task berdasarkan status kolom
$columns = [
    'To Do'       => [],
    'In Progress' => [],
    'Review'      => [],
    'Done'        => [],
];
foreach ($tasks as $task) {
    $columns[$task['status_kolom']][] = $task;
}

$page_title  = 'Papan Kanban';
$active_nav  = 'kanban';
$breadcrumbs = [
    ['label' => 'Kanban', 'url' => ''],
];
include '../templates/member_header.php';
?>

<div class="container-fluid">

    <?php if (empty($my_projects)): ?>
    <div class="alert alert-info">
        Kamu belum ditugaskan ke proyek manapun. Hubungi Admin untuk mendapatkan akses.
    </div>
    <?php else: ?>

    <!-- Project Switcher -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="fa fa-columns me-2 text-primary"></i>
                <?= e($current_project['nama_game'] ?? 'Pilih Proyek') ?>
            </h4>
            <?php if ($current_project): ?>
            <small class="text-muted">
                <?= $task_summary['Done'] ?? 0 ?>/<?= array_sum($task_summary) ?> tugas selesai
            </small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="kanban.php" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0 text-nowrap">Proyek:</label>
                <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:200px;">
                    <?php foreach ($my_projects as $p): ?>
                    <option value="<?= e($p['id']) ?>" <?= $p['id'] === $selected_project_id ? 'selected' : '' ?>>
                        <?= e($p['nama_game']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($current_project): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddTask">
                <i class="fa fa-plus me-1"></i>Tambah Tugas
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php render_flash(); ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($current_project): ?>

    <!-- Kanban Board -->
    <div class="row g-3">
        <?php
        $col_colors = [
            'To Do'       => 'secondary',
            'In Progress' => 'primary',
            'Review'      => 'warning',
            'Done'        => 'success',
        ];
        $col_icons = [
            'To Do'       => 'fa-circle',
            'In Progress' => 'fa-spinner',
            'Review'      => 'fa-eye',
            'Done'        => 'fa-check-circle',
        ];
        foreach ($columns as $col_name => $col_tasks): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <span class="fw-semibold">
                        <i class="fa <?= $col_icons[$col_name] ?> me-1 text-<?= $col_colors[$col_name] ?>"></i>
                        <?= e($col_name) ?>
                    </span>
                    <span class="badge bg-<?= $col_colors[$col_name] ?>"><?= count($col_tasks) ?></span>
                </div>
                <div class="card-body p-2 kanban-column" data-column="<?= e($col_name) ?>">
                    <?php foreach ($col_tasks as $task): ?>
                    <div class="card mb-2 task-card" data-task-id="<?= e($task['id']) ?>" draggable="true">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-start justify-content-between gap-1">
                                <span class="fw-semibold small"><?= e($task['judul']) ?></span>
                                <span class="badge <?= badge_prioritas($task['prioritas']) ?> flex-shrink-0" style="font-size:0.65rem;">
                                    <?= e($task['prioritas']) ?>
                                </span>
                            </div>
                            <?php if ($task['deskripsi']): ?>
                            <p class="text-muted small mb-1 mt-1" style="font-size:0.75rem;">
                                <?= e(truncate($task['deskripsi'], 80)) ?>
                            </p>
                            <?php endif; ?>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <div class="text-muted" style="font-size:0.72rem;">
                                    <?php if ($task['deadline']): ?>
                                    <i class="fa fa-clock me-1"></i><?= e(format_tanggal($task['deadline'])) ?>
                                    <?php endif; ?>
                                    <?php if ($task['assignee_name']): ?>
                                    &nbsp;· <i class="fa fa-user me-1"></i><?= e($task['assignee_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <!-- Pindah Kolom -->
                                        <?php foreach (array_keys($columns) as $dest): ?>
                                        <?php if ($dest !== $col_name): ?>
                                        <li>
                                            <form method="POST" action="kanban.php?project_id=<?= urlencode($selected_project_id) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="move">
                                                <input type="hidden" name="project_id" value="<?= e($selected_project_id) ?>">
                                                <input type="hidden" name="task_id" value="<?= e($task['id']) ?>">
                                                <input type="hidden" name="new_status" value="<?= e($dest) ?>">
                                                <button type="submit" class="dropdown-item small">
                                                    <i class="fa fa-arrow-right me-1"></i>Pindah ke <?= e($dest) ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <!-- Edit -->
                                        <li>
                                            <button class="dropdown-item small btn-edit-task"
                                                data-id="<?= e($task['id']) ?>"
                                                data-judul="<?= e($task['judul']) ?>"
                                                data-deskripsi="<?= e($task['deskripsi'] ?? '') ?>"
                                                data-prioritas="<?= e($task['prioritas']) ?>"
                                                data-assignee="<?= e($task['assignee_id'] ?? '') ?>"
                                                data-deadline="<?= e($task['deadline'] ?? '') ?>"
                                                data-estimasi="<?= e($task['estimasi_jam'] ?? '') ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalEditTask">
                                                <i class="fa fa-edit me-1"></i>Edit Tugas
                                            </button>
                                        </li>
                                        <!-- Hapus -->
                                        <li>
                                            <form method="POST" action="kanban.php?project_id=<?= urlencode($selected_project_id) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="project_id" value="<?= e($selected_project_id) ?>">
                                                <input type="hidden" name="task_id" value="<?= e($task['id']) ?>">
                                                <button type="submit" class="dropdown-item text-danger small"
                                                    onclick="return confirm('Hapus tugas ini?')">
                                                    <i class="fa fa-trash me-1"></i>Hapus
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    
                    <div class="text-center text-muted py-4 empty-placeholder" style="font-size:0.8rem; display: <?= empty($col_tasks) ? 'block' : 'none' ?>;">
                        <i class="fa fa-inbox fa-2x mb-2 d-block opacity-25"></i>Tidak ada tugas
                    </div>
                    
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; /* current_project */ ?>
    <?php endif; /* my_projects */ ?>

</div>

<!-- Modal: Tambah Task -->
<div class="modal fade" id="modalAddTask" tabindex="-1" aria-labelledby="modalAddTaskLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="kanban.php?project_id=<?= urlencode($selected_project_id ?? '') ?>" novalidate>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddTaskLabel"><i class="fa fa-plus me-2"></i>Tambah Tugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="store">
                    <input type="hidden" name="project_id" value="<?= e($selected_project_id ?? '') ?>">

                    <div class="mb-3">
                        <label class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" name="judul" class="form-control" maxlength="200" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"></textarea>
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
                            <label class="form-label">Assignee</label>
                            <select name="assignee_id" class="form-select">
                                <option value="">— Tidak ada —</option>
                                <?php foreach ($org_members as $m): ?>
                                <option value="<?= e($m['id']) ?>"><?= e($m['nama_lengkap']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Deadline</label>
                            <input type="date" name="deadline" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Estimasi (jam)</label>
                            <input type="number" name="estimasi_jam" class="form-control" min="0" max="9999">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Task -->
<div class="modal fade" id="modalEditTask" tabindex="-1" aria-labelledby="modalEditTaskLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="kanban.php?project_id=<?= urlencode($selected_project_id ?? '') ?>" novalidate>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditTaskLabel"><i class="fa fa-edit me-2"></i>Edit Tugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="project_id" value="<?= e($selected_project_id ?? '') ?>">
                    <input type="hidden" name="task_id" id="edit_task_id">

                    <div class="mb-3">
                        <label class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" name="judul" id="edit_judul" class="form-control" maxlength="200" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Prioritas</label>
                            <select name="prioritas" id="edit_prioritas" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Assignee</label>
                            <select name="assignee_id" id="edit_assignee" class="form-select">
                                <option value="">— Tidak ada —</option>
                                <?php foreach ($org_members as $m): ?>
                                <option value="<?= e($m['id']) ?>"><?= e($m['nama_lengkap']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Deadline</label>
                            <input type="date" name="deadline" id="edit_deadline" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Estimasi (jam)</label>
                            <input type="number" name="estimasi_jam" id="edit_estimasi" class="form-control" min="0" max="9999">
                        </div>
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

<style>
/* Drag and Drop Visual Feedback */
.task-card {
    cursor: grab;
    transition: box-shadow 0.2s, opacity 0.2s, transform 0.2s;
}
.task-card:active {
    cursor: grabbing;
}
.task-card.dragging {
    opacity: 0.5;
    box-shadow: 0 8px 20px rgba(0,0,0,0.4);
    transform: scale(0.98);
}
.kanban-column {
    min-height: 150px;
    transition: background-color 0.2s, border 0.2s;
    border-radius: 6px;
    border: 2px dashed transparent;
}
.kanban-column.drag-over {
    background-color: rgba(79, 124, 255, 0.05);
    border-color: var(--accent-blue, #4f7cff);
}
</style>

<script>
document.querySelectorAll('.btn-edit-task').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_task_id').value   = this.dataset.id;
        document.getElementById('edit_judul').value     = this.dataset.judul;
        document.getElementById('edit_deskripsi').value = this.dataset.deskripsi;
        document.getElementById('edit_prioritas').value = this.dataset.prioritas;
        document.getElementById('edit_assignee').value  = this.dataset.assignee;
        document.getElementById('edit_deadline').value  = this.dataset.deadline;
        document.getElementById('edit_estimasi').value  = this.dataset.estimasi;
    });
});

// Drag and Drop Logic
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.task-card');
    const columns = document.querySelectorAll('.kanban-column');

    let draggedCard = null;

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            draggedCard = card;
            setTimeout(() => card.classList.add('dragging'), 0);
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
        });

        card.addEventListener('dragend', () => {
            if (draggedCard) {
                draggedCard.classList.remove('dragging');
                draggedCard = null;
            }
            columns.forEach(col => col.classList.remove('drag-over'));
            updateColumnBadges();
        });
    });

    columns.forEach(col => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault(); // Wajib agar event drop bisa ditangkap
            col.classList.add('drag-over');
            
            const afterElement = getDragAfterElement(col, e.clientY);
            if (afterElement == null) {
                col.appendChild(draggedCard);
            } else {
                col.insertBefore(draggedCard, afterElement);
            }
        });

        col.addEventListener('dragenter', (e) => {
            e.preventDefault();
        });

        col.addEventListener('dragleave', (e) => {
            if (!col.contains(e.relatedTarget)) {
                col.classList.remove('drag-over');
            }
        });

        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('drag-over');
            if (!draggedCard) return;

            const taskId = draggedCard.dataset.taskId;
            const newStatus = col.dataset.column;
            
            // Ambil token CSRF dari input tersembunyi yang ada di halaman
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;

            // Buat form data untuk request
            const formData = new FormData();
            formData.append('action', 'move');
            formData.append('task_id', taskId);
            formData.append('new_status', newStatus);
            formData.append('project_id', '<?= e($selected_project_id ?? "") ?>');
            formData.append('csrf_token', csrfToken);

            fetch('kanban.php?project_id=<?= urlencode($selected_project_id ?? "") ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Perbarui semua input CSRF di halaman dengan token baru
                    if (data.csrf_token) {
                        document.querySelectorAll('input[name="csrf_token"]').forEach(el => {
                            el.value = data.csrf_token;
                        });
                    }
                    updateColumnBadges();
                } else {
                    alert('Gagal memindahkan tugas: ' + (data.error || ''));
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan jaringan.');
                window.location.reload();
            });
        });
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateColumnBadges() {
        columns.forEach(col => {
            const count = col.querySelectorAll('.task-card').length;
            const header = col.previousElementSibling;
            if (header) {
                const badge = header.querySelector('.badge');
                if (badge) badge.textContent = count;
            }
            const emptyPlaceholder = col.querySelector('.empty-placeholder');
            if (emptyPlaceholder) {
                emptyPlaceholder.style.display = count === 0 ? 'block' : 'none';
            }
        });
    }
});
</script>

<?php include '../templates/member_footer.php'; ?>

