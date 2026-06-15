<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';
require_once '../includes/csrf.php';

require_role([ROLE_ADMIN]);

$admin_org_id = $_SESSION['organization_id'] ?? null;

// PILIH PROYEK
$all_projects       = get_all_projects_by_org($pdo, $admin_org_id);
$selected_project_id = $_GET['project_id'] ?? ($_POST['project_id'] ?? null);

if (!$selected_project_id && !empty($all_projects)) {
    $selected_project_id = $all_projects[0]['id'];
}

// Verifikasi proyek
$report_data = null;
if ($selected_project_id) {
    $report_data = get_project_report_data($pdo, $selected_project_id);
    if (!$report_data || $report_data['organization_id'] !== $admin_org_id) {
        set_flash('error', 'Proyek tidak ditemukan atau bukan milik organisasimu.');
        redirect(APP_URL . '/admin/reports.php');
    }
}

// EKSPOR CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $report_data) {
    if (!rate_limit_check('export_csv', 5, 60)) {
        die('Rate limit exceeded. Coba lagi dalam 1 menit.');
    }

    $nama_proyek = preg_replace('/[^A-Za-z0-9_\-]/', '_', $report_data['nama_game']);
    $filename    = 'laporan_' . $nama_proyek . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Bagian 1: Info Proyek
    fputcsv($out, ['LAPORAN PROYEK GRID']);
    fputcsv($out, ['Nama Proyek', $report_data['nama_game']]);
    fputcsv($out, ['Genre', $report_data['genre']]);
    fputcsv($out, ['Platform', $report_data['platform']]);
    fputcsv($out, ['Engine', $report_data['engine']]);
    fputcsv($out, ['Status', $report_data['status']]);
    fputcsv($out, ['Tanggal Mulai', $report_data['tanggal_mulai'] ?: '-']);
    fputcsv($out, ['Target Rilis', $report_data['target_rilis'] ?: '-']);
    fputcsv($out, ['Project Lead', $report_data['lead_name'] ?: '-']);
    fputcsv($out, ['Total Anggota', count($report_data['members'])]);
    fputcsv($out, ['Persentase Selesai', $report_data['completion_pct'] . '%']);
    fputcsv($out, ['Total Jam Terselesaikan', $report_data['done_hours'] . ' jam']);
    fputcsv($out, []);

    // Bagian 2: Ringkasan Task
    fputcsv($out, ['RINGKASAN TUGAS (KANBAN)']);
    fputcsv($out, ['Status', 'Jumlah']);
    foreach ($report_data['task_summary'] as $status => $count) {
        fputcsv($out, [$status, $count]);
    }
    fputcsv($out, ['Total', $report_data['total_tasks']]);
    fputcsv($out, []);

    // Bagian 3: Ringkasan Bug
    fputcsv($out, ['RINGKASAN BUG']);
    fputcsv($out, ['Status', 'Jumlah']);
    foreach ($report_data['bug_summary'] as $status => $count) {
        fputcsv($out, [$status, $count]);
    }
    fputcsv($out, []);

    // Bagian 4: Daftar Tugas Lengkap
    $all_tasks = get_tasks_by_project($pdo, $selected_project_id);
    fputcsv($out, ['DAFTAR TUGAS LENGKAP']);
    fputcsv($out, ['Judul', 'Status', 'Prioritas', 'Assignee', 'Deadline', 'Estimasi Jam']);
    foreach ($all_tasks as $t) {
        fputcsv($out, [
            $t['judul'],
            $t['status_kolom'],
            $t['prioritas'],
            $t['assignee_name'] ?? '-',
            $t['deadline']      ?: '-',
            $t['estimasi_jam']  ?: '-',
        ]);
    }
    fputcsv($out, []);

    // Bagian 5: Daftar Bug Lengkap
    $all_bugs = get_bugs_by_project($pdo, $selected_project_id);
    fputcsv($out, ['DAFTAR BUG LENGKAP']);
    fputcsv($out, ['Judul', 'Status', 'Prioritas', 'Reporter', 'Task Terkait', 'Tanggal']);
    foreach ($all_bugs as $b) {
        fputcsv($out, [
            $b['judul'],
            $b['status'],
            $b['prioritas'],
            $b['reporter_name'] ?? '-',
            $b['task_judul']    ?? '-',
            $b['created_at'],
        ]);
    }

    // Bagian 6: Daftar Anggota
    fputcsv($out, []);
    fputcsv($out, ['DAFTAR ANGGOTA TIM']);
    fputcsv($out, ['Nama', 'Username', 'Role', 'Bergabung']);
    foreach ($report_data['members'] as $m) {
        fputcsv($out, [
            $m['nama_lengkap'],
            $m['username'],
            $m['role'],
            $m['joined_at'],
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['Digenerate pada', date('Y-m-d H:i:s')]);

    fclose($out);
    exit;
}

// DATA TAMBAHAN UNTUK TAMPILAN
$tasks_full = $report_data ? get_tasks_by_project($pdo, $selected_project_id) : [];
$bugs_full  = $report_data ? get_bugs_by_project($pdo, $selected_project_id)  : [];

$page_title  = 'Laporan Proyek';
$active_nav  = 'reports';
$breadcrumbs = [['label' => 'Laporan', 'url' => '']];
include '../templates/admin_header.php';
?>

<div class="container-fluid" id="report-content">

    <!-- Project Switcher -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print">
        <div>
            <h4 class="mb-0 fw-semibold">
                <i class="fa fa-chart-bar me-2 text-info"></i>
                Laporan Kemajuan Proyek
            </h4>
            <small class="text-muted">Statistik dan ekspor data proyek</small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="reports.php" class="d-flex gap-2 align-items-center">
                <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:200px;">
                    <?php foreach ($all_projects as $p): ?>
                    <option value="<?= e($p['id']) ?>" <?= $p['id'] === $selected_project_id ? 'selected' : '' ?>>
                        <?= e($p['nama_game']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($report_data): ?>
            <a href="reports.php?project_id=<?= urlencode($selected_project_id) ?>&export=csv"
               class="btn btn-success btn-sm">
                <i class="fa fa-file-csv me-1"></i>Ekspor CSV
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <i class="fa fa-print me-1"></i>Cetak / PDF
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php render_flash(); ?>

    <?php if ($report_data): ?>

    <!-- Header Laporan -->
    <div class="print-only mb-4">
        <h3 class="fw-bold">GRID — Laporan Proyek</h3>
        <hr>
    </div>

    <!-- Statistik Utama -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-primary"><?= $report_data['completion_pct'] ?>%</div>
                    <div class="small text-muted">Progres Selesai</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-success"><?= $report_data['task_summary']['Done'] ?></div>
                    <div class="small text-muted">Tugas Selesai</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-danger"><?= $report_data['bug_summary']['Open'] ?></div>
                    <div class="small text-muted">Bug Terbuka</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="display-6 fw-bold text-info"><?= $report_data['done_hours'] ?>j</div>
                    <div class="small text-muted">Jam Terselesaikan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-1 small">
                <span class="fw-semibold">Kemajuan Proyek: <?= e($report_data['nama_game']) ?></span>
                <span><?= $report_data['completion_pct'] ?>%</span>
            </div>
            <div class="progress" style="height:12px;">
                <div class="progress-bar bg-primary" style="width:<?= $report_data['completion_pct'] ?>%"></div>
            </div>
            <div class="row text-center mt-2" style="font-size:0.75rem;">
                <?php foreach ($report_data['task_summary'] as $s => $c): ?>
                <div class="col">
                    <strong><?= $c ?></strong><br><span class="text-muted"><?= e($s) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">

        <!-- Info Proyek -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="fa fa-info-circle me-1"></i>Informasi Proyek</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th class="text-muted fw-normal" style="width:45%">Nama</th><td><?= e($report_data['nama_game']) ?></td></tr>
                        <tr><th class="text-muted fw-normal">Status</th><td><span class="badge <?= badge_status_proyek($report_data['status']) ?>"><?= e($report_data['status']) ?></span></td></tr>
                        <tr><th class="text-muted fw-normal">Genre</th><td><?= e($report_data['genre'] ?: '-') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Platform</th><td><?= e($report_data['platform'] ?: '-') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Engine</th><td><?= e($report_data['engine'] ?: '-') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Mulai</th><td><?= $report_data['tanggal_mulai'] ? format_tanggal($report_data['tanggal_mulai']) : '-' ?></td></tr>
                        <tr><th class="text-muted fw-normal">Target Rilis</th><td><?= $report_data['target_rilis'] ? format_tanggal($report_data['target_rilis']) : '-' ?></td></tr>
                        <tr><th class="text-muted fw-normal">Lead</th><td><?= e($report_data['lead_name'] ?: '-') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Total Anggota</th><td><?= count($report_data['members']) ?> orang</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Ringkasan Bug -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="fa fa-bug me-1 text-danger"></i>Ringkasan Bug</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <?php
                        $bug_colors = [
                            'Open' => 'danger', 'In Progress' => 'primary',
                            'Resolved' => 'success', 'Closed' => 'secondary',
                        ];
                        foreach ($report_data['bug_summary'] as $s => $c):
                        ?>
                        <tr>
                            <th class="text-muted fw-normal"><?= e($s) ?></th>
                            <td>
                                <span class="badge bg-<?= $bug_colors[$s] ?>"><?= $c ?></span>
                            </td>
                            <td class="text-end">
                                <div class="progress" style="height:6px; width:100px; display:inline-flex;">
                                    <?php $total_bugs = max(1, array_sum($report_data['bug_summary'])); ?>
                                    <div class="progress-bar bg-<?= $bug_colors[$s] ?>" style="width:<?= round($c / $total_bugs * 100) ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Tugas Lengkap -->
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fa fa-tasks me-1"></i>Daftar Tugas (<?= count($tasks_full) ?>)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Judul</th>
                            <th>Status</th>
                            <th>Prioritas</th>
                            <th>Assignee</th>
                            <th>Deadline</th>
                            <th class="text-end">Est. Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks_full)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada tugas</td></tr>
                        <?php endif; ?>
                        <?php foreach ($tasks_full as $t): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($t['judul']) ?></td>
                            <td><span class="badge bg-secondary" style="font-size:0.7rem;"><?= e($t['status_kolom']) ?></span></td>
                            <td><span class="badge <?= badge_prioritas($t['prioritas']) ?>" style="font-size:0.7rem;"><?= e($t['prioritas']) ?></span></td>
                            <td class="small text-muted"><?= e($t['assignee_name'] ?? '—') ?></td>
                            <td class="small text-muted"><?= $t['deadline'] ? format_tanggal($t['deadline']) : '—' ?></td>
                            <td class="small text-end text-muted"><?= $t['estimasi_jam'] ? $t['estimasi_jam'] . ' j' : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Bug Lengkap -->
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fa fa-bug me-1 text-danger"></i>Daftar Bug (<?= count($bugs_full) ?>)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Judul</th>
                            <th>Status</th>
                            <th>Prioritas</th>
                            <th>Reporter</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bugs_full)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada bug yang dilaporkan</td></tr>
                        <?php endif; ?>
                        <?php foreach ($bugs_full as $b): ?>
                        <?php
                        $status_class = match($b['status']) {
                            'Open'        => 'bg-danger',
                            'In Progress' => 'bg-primary',
                            'Resolved'    => 'bg-success',
                            'Closed'      => 'bg-secondary',
                            default       => 'bg-secondary',
                        };
                        ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($b['judul']) ?></td>
                            <td><span class="badge <?= $status_class ?>" style="font-size:0.7rem;"><?= e($b['status']) ?></span></td>
                            <td><span class="badge <?= badge_prioritas($b['prioritas']) ?>" style="font-size:0.7rem;"><?= e($b['prioritas']) ?></span></td>
                            <td class="small text-muted"><?= e($b['reporter_name'] ?? '—') ?></td>
                            <td class="small text-muted"><?= format_tanggal($b['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Daftar Anggota -->
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fa fa-users me-1"></i>Tim Proyek (<?= count($report_data['members']) ?> orang)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>Nama</th><th>Username</th><th>Role</th><th>Bergabung</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['members'] as $m): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e($m['nama_lengkap']) ?></td>
                            <td class="small text-muted"><?= e($m['username']) ?></td>
                            <td class="small"><span class="badge bg-info text-dark"><?= e($m['role']) ?></span></td>
                            <td class="small text-muted"><?= format_tanggal($m['joined_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-muted small print-only">Digenerate pada: <?= date('d/m/Y H:i') ?> WIB</div>

    <?php else: ?>
    <div class="alert alert-info">Pilih proyek untuk melihat laporan.</div>
    <?php endif; ?>

</div>

<style>
@media print {
    .no-print, .sidebar, nav, .topbar { display: none !important; }
    .print-only { display: block !important; }
    body, .main-content { background: white !important; color: black !important; }
    .card { border: 1px solid #ccc !important; break-inside: avoid; }
    .badge { border: 1px solid #999 !important; }
}
.print-only { display: none; }
</style>

<?php include '../templates/admin_footer.php'; ?>
