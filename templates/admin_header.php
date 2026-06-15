<?php
 
require_once ROOT_PATH . 'includes/csrf.php';
 
$_nav_items = [
    'dashboard' => ['url' => APP_URL . '/admin/dashboard.php', 'icon' => 'fa-chart-line', 'label' => 'Dashboard'],
    'projects'  => ['url' => APP_URL . '/admin/projects.php',  'icon' => 'fa-gamepad',    'label' => 'Proyek'],
    'assets'    => ['url' => APP_URL . '/admin/assets.php',    'icon' => 'fa-image',       'label' => 'Aset'],
    'users'     => ['url' => APP_URL . '/admin/users.php',     'icon' => 'fa-users',       'label' => 'Pengguna'],
    'reports'   => ['url' => APP_URL . '/admin/reports.php',   'icon' => 'fa-file-pdf',    'label' => 'Laporan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dashboard.css">
    <?php if (!empty($extra_css)) echo $extra_css; ?>
</head>
<body class="grid-body">
 
<div class="container-fluid">
<div class="row">
 
<!-- SIDEBAR -->
<nav class="col-12 col-md-3 col-lg-2 sidebar">
    <div class="sidebar-sticky p-3">
        <div class="sidebar-header mb-4">
            <span class="sidebar-logo">GRID</span>
            <div class="mt-1" style="font-size:0.7rem;color:var(--text-muted);letter-spacing:0.08em;">ADMIN PORTAL</div>
        </div>
 
        <ul class="nav flex-column">
            <?php foreach ($_nav_items as $_key => $_item): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($active_nav === $_key) ? 'active' : '' ?>"
                   href="<?= $_item['url'] ?>">
                    <i class="fa <?= $_item['icon'] ?> me-2"></i><?= $_item['label'] ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
 
        <hr>
 
        <div class="user-menu">
            <div class="user-info mb-2">
                <small class="text-muted">Logged in as</small><br>
                <strong class="small"><?= e($_SESSION['nama_lengkap']) ?></strong><br>
                <span class="badge bg-success" style="font-size:0.7rem;"><?= e($_SESSION['role']) ?></span>
                <?php if (!empty($_SESSION['organization_id'])): ?>
                <div class="mt-1" style="font-size:0.7rem;color:var(--text-muted);">
                    <?php
                    // Tampilkan nama org dari session jika sudah disimpan, atau query singkat
                    if (!isset($_SESSION['org_nama'])) {
                        global $pdo;
                        $s = $pdo->prepare('SELECT nama, kode_unik FROM organizations WHERE id = ? LIMIT 1');
                        $s->execute([$_SESSION['organization_id']]);
                        $o = $s->fetch();
                        $_SESSION['org_nama']  = $o['nama']    ?? '';
                        $_SESSION['org_kode']  = $o['kode_unik'] ?? '';
                    }
                    ?>
                    <i class="fa fa-building me-1"></i><?= e($_SESSION['org_nama']) ?>
                    <span class="text-muted ms-1">(<?= e($_SESSION['org_kode']) ?>)</span>
                </div>
                <?php endif; ?>
            </div>
            <form method="POST" action="<?= APP_URL ?>/logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                    <i class="fa fa-sign-out-alt me-1"></i>Logout
                </button>
            </form>
        </div>
    </div>
</nav>
 
<!-- MAIN CONTENT -->
<main class="col-12 col-md-9 col-lg-10 ms-sm-auto px-md-4">
 
    <!-- Topbar -->
    <div class="topbar border-bottom py-3 mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="mb-0"><?= e($page_title) ?></h4>
                <?php if (!empty($breadcrumbs)): ?>
                <nav aria-label="breadcrumb" class="mt-1">
                    <ol class="breadcrumb mb-0" style="font-size:0.8rem;">
                        <li class="breadcrumb-item">
                            <a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a>
                        </li>
                        <?php foreach ($breadcrumbs as $_i => $_crumb): ?>
                            <?php if ($_i === count($breadcrumbs) - 1): ?>
                                <li class="breadcrumb-item active"><?= e($_crumb['label']) ?></li>
                            <?php else: ?>
                                <li class="breadcrumb-item">
                                    <a href="<?= e($_crumb['url']) ?>"><?= e($_crumb['label']) ?></a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
            </div>
            <span class="text-muted small pt-1"><?= date('d M Y, H:i') ?></span>
        </div>
    </div>
 
    <!-- Flash Messages -->
    <?php render_flash(); ?>
 
<!-- PAGE CONTENT  -->