<?php
session_start();
require_once 'config/config.php';
require_once 'koneksi.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

redirect_if_logged_in();

// Ambil proyek publik
$public_projects = [];
try {
    $stmt = $pdo->query('
        SELECT p.*, o.nama AS studio_name 
        FROM projects p 
        LEFT JOIN organizations o ON p.organization_id = o.id
        WHERE p.status IN ("Released", "Development") 
        ORDER BY p.tanggal_mulai DESC 
        LIMIT 6
    ');
    $public_projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Ambil devlog publik
$public_devlogs = [];
try {
    $stmt = $pdo->query('
        SELECT d.*, u.nama_lengkap AS penulis, p.nama_game AS project_name
        FROM devlogs d
        LEFT JOIN users u ON d.penulis_id = u.id
        LEFT JOIN projects p ON d.project_id = p.id
        WHERE d.is_public = TRUE AND d.status = "Published"
        ORDER BY d.created_at DESC
        LIMIT 6
    ');
    $public_devlogs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID - Game Repository & Devlog</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex flex-column h-100">

    <!-- Header / Navbar -->
    <nav class="navbar navbar-expand-lg navbar-grid py-3">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fa-solid fa-gamepad me-2"></i>GRID
            </a>
            
            <button class="navbar-toggler text-secondary" type="button" data-bs-toggle="collapse" data-bs-toggle="target" aria-controls="navbarNav">
                <i class="fa-solid fa-bars"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link me-3" href="#portfolio">Portofolio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link me-3" href="#devlog">Devlog Publik</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-grid-outline me-2" href="login.php">Masuk</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-grid-primary" href="register.php">Daftar</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-shrink-0">
        <!-- Hero Section -->
        <section class="container d-flex flex-column justify-content-center align-items-center text-center py-5">
            <div class="row w-100 mt-5">
                <div class="col-lg-8 mx-auto">
                    <div class="badge bg-input text-accent border border-secondary mb-3 py-2 px-3 rounded-pill" style="border-color: var(--border-color) !important;">
                        v1.0 Internal Portal
                    </div>
                    <h1 class="display-4 fw-bold mb-4">
                        Game Repository & <br><span class="text-accent">Devlog</span>
                    </h1>
                    <p class="lead text-secondary mb-5">
                        Platform kolaborasi internal untuk tim pengembangan game. Kelola berbagai aset game, pantau progres dengan Kanban, 
                        dan dokumentasikan perjalanan devlog dengan rapi dalam satu tempat.
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="register.php" class="btn btn-grid-primary btn-lg px-4">
                            <i class="fa-solid fa-rocket me-2"></i>Mulai Proyek
                        </a>
                        <a href="#portfolio" class="btn btn-grid-outline btn-lg px-4">
                            Lihat Portofolio
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Portofolio Section -->
        <section id="portfolio" class="container py-5 border-top" style="border-color: var(--border-color) !important;">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Portofolio Game</h2>
                <p class="text-secondary">Karya-karya terbaik dari berbagai studio</p>
            </div>
            
            <div class="row g-4">
                <?php if (empty($public_projects)): ?>
                <div class="col-12 text-center text-muted py-5">
                    <i class="fa-solid fa-ghost fa-3x mb-3 opacity-25"></i>
                    <p>Belum ada game yang dipublikasikan.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($public_projects as $p): ?>
                    <div class="col-md-4">
                        <div class="card-grid p-4 h-100 text-center">
                            <div class="mb-3 mt-2">
                                <i class="fa-solid fa-gamepad fa-3x text-accent"></i>
                            </div>
                            <h5 class="fw-semibold mb-1"><?= e($p['nama_game']) ?></h5>
                            <div class="text-muted small mb-2"><?= e($p['studio_name'] ?? 'Indie Studio') ?></div>
                            <div class="mb-3">
                                <span class="badge bg-secondary"><?= e($p['genre'] ?? 'N/A') ?></span>
                                <span class="badge bg-secondary"><?= e($p['platform'] ?? 'N/A') ?></span>
                            </div>
                            <p class="text-secondary small mb-0">
                                <?= e(truncate($p['deskripsi'] ?? 'Tidak ada deskripsi', 100)) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Devlog Section -->
        <section id="devlog" class="container py-5 border-top" style="border-color: var(--border-color) !important;">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Devlog Publik</h2>
                <p class="text-secondary">Ikuti perkembangan terbaru dari proses pembuatan game kami</p>
            </div>
            
            <div class="row g-4">
                <?php if (empty($public_devlogs)): ?>
                <div class="col-12 text-center text-muted py-5">
                    <i class="fa-solid fa-newspaper fa-3x mb-3 opacity-25"></i>
                    <p>Belum ada artikel devlog publik.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($public_devlogs as $d): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-grid p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary"><?= e($d['kategori']) ?></span>
                                <small class="text-muted"><?= format_tanggal($d['created_at']) ?></small>
                            </div>
                            <h5 class="fw-semibold mt-2 mb-1"><?= e($d['judul']) ?></h5>
                            <div class="text-muted small mb-3">
                                <i class="fa fa-gamepad me-1"></i> <?= e($d['project_name'] ?? 'General') ?>
                            </div>
                            <p class="text-secondary small mb-3">
                                <?= e(truncate(strip_tags($d['konten']), 120)) ?>
                            </p>
                            <div class="mt-auto pt-3 border-top" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary rounded-circle me-2 d-flex justify-content-center align-items-center" style="width:24px;height:24px;">
                                        <i class="fa fa-user" style="font-size:10px;"></i>
                                    </div>
                                    <small class="text-muted"><?= e($d['penulis'] ?? 'Anonymous') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="footer mt-auto py-4 border-top" style="border-color: var(--border-color) !important;">
        <div class="container text-center">
            <p class="text-secondary small mb-0">
                &copy; <?= date('Y') ?> GRID - Game Repository & Devlog Studio. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- smooth scroll -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
