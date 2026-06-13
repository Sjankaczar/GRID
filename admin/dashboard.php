<?php
// =============================================================
// admin/dashboard.php
// Dashboard Admin — hanya Admin yang bisa akses
// =============================================================

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Proteksi: hanya Admin yang bisa akses halaman ini
require_role([ROLE_ADMIN]);

// Ambil statistik dashboard
// TODO: Implementasi query statistik real-time (minggu 2)
$stats = [
    'total_projects'    => 0,
    'total_users'       => 0,
    'pending_approvals' => 0,
    'total_assets'      => 0,
];

try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM projects');
    $stats['total_projects'] = $stmt->fetch()['count'];

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE is_active = TRUE');
    $stats['total_users'] = $stmt->fetch()['count'];

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM assets WHERE status = "Pending"');
    $stats['pending_approvals'] = $stmt->fetch()['count'];

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM assets');
    $stats['total_assets'] = $stmt->fetch()['count'];
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="grid-body">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-12 col-md-3 col-lg-2 sidebar">
            <div class="sidebar-sticky p-3">
                <div class="sidebar-header mb-4">
                    <span class="sidebar-logo">GRID</span>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fa fa-chart-line me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="projects.php">
                            <i class="fa fa-gamepad me-2"></i>Proyek
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assets.php">
                            <i class="fa fa-image me-2"></i>Aset
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fa fa-users me-2"></i>Pengguna
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fa fa-file-pdf me-2"></i>Laporan
                        </a>
                    </li>
                </ul>

                <hr>

                <div class="user-menu">
                    <div class="user-info mb-2">
                        <small class="text-muted">Logged in as</small><br>
                        <strong><?= e($_SESSION['nama_lengkap']) ?></strong><br>
                        <small class="badge bg-success"><?= e($_SESSION['role']) ?></small>
                    </div>
                    <form method="POST" action="../logout.php">
                        <?php include '../includes/csrf.php'; echo csrf_field(); ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="fa fa-sign-out-alt me-1"></i>Logout
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-12 col-md-9 col-lg-10 ms-sm-auto px-md-4">
            <!-- Topbar -->
            <div class="topbar border-bottom py-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Dashboard Admin</h4>
                        <small class="text-muted">Selamat datang kembali, <?= e(explode(' ', $_SESSION['nama_lengkap'])[0]) ?>!</small>
                    </div>
                    <div>
                        <span class="text-muted small"><?= date('d M Y H:i') ?></span>
                    </div>
                </div>
            </div>

            <!-- Flash Message -->
            <div class="mb-4">
                <?php render_flash(); ?>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fa fa-gamepad"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $stats['total_projects'] ?></span>
                            <span class="stat-label">Proyek Aktif</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $stats['total_users'] ?></span>
                            <span class="stat-label">Anggota Aktif</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $stats['pending_approvals'] ?></span>
                            <span class="stat-label">Aset Menunggu</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-image"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $stats['total_assets'] ?></span>
                            <span class="stat-label">Total Aset</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Aksi Cepat</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="projects.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-plus me-1"></i>Buat Proyek
                                </a>
                                <a href="users.php" class="btn btn-sm btn-outline-success">
                                    <i class="fa fa-check me-1"></i>Setujui Pengguna
                                </a>
                                <a href="assets.php" class="btn btn-sm btn-outline-warning">
                                    <i class="fa fa-list me-1"></i>Review Aset
                                </a>
                                <a href="reports.php" class="btn btn-sm btn-outline-info">
                                    <i class="fa fa-download me-1"></i>Ekspor Laporan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
