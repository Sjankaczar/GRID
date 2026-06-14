<?php
session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';

// hanya Member yang bisa akses
require_role([ROLE_MEMBER]);

$user_id = current_user_id();

// Ambil statistik member
$stats = [
    'my_projects'    => 0,
    'my_assets'      => 0,
    'assets_pending' => 0,
    'my_tasks'       => 0,
];

try {
    // Proyek saya
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM project_members WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $stats['my_projects'] = (int) $stmt->fetchColumn();

    // Aset saya
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM assets WHERE uploader_id = ?');
    $stmt->execute([$user_id]);
    $stats['my_assets'] = (int) $stmt->fetchColumn();

    // Aset saya yang pending
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM assets WHERE uploader_id = ? AND status = "Pending"');
    $stmt->execute([$user_id]);
    $stats['assets_pending'] = (int) $stmt->fetchColumn();

    // Tugas saya (Kanban) yang belum selesai
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status_kolom != "Done"');
    $stmt->execute([$user_id]);
    $stats['my_tasks'] = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$page_title = 'Workspace Member';
$active_nav = 'dashboard';
include '../templates/member_header.php';
?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="fa fa-gamepad"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['my_projects'] ?></span>
                <span class="stat-label">Proyek Saya</span>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-info">
                <i class="fa fa-image"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['my_assets'] ?></span>
                <span class="stat-label">Aset Diunggah</span>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="fa fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['assets_pending'] ?></span>
                <span class="stat-label">Aset Pending</span>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fa fa-tasks"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['my_tasks'] ?></span>
                <span class="stat-label">Tugas Aktif</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3">Jalan Pintas</h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="upload_aset.php" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-upload me-1"></i>Upload Aset Baru
                    </a>
                    <a href="kanban.php" class="btn btn-sm btn-outline-success">
                        <i class="fa fa-trello me-1"></i>Buka Kanban
                    </a>
                    <a href="devlog.php" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-feather me-1"></i>Tulis Devlog
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/member_footer.php'; ?>
