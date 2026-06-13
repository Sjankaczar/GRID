<?php
session_start();
require_once 'config/config.php';
require_once 'koneksi.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

redirect_if_logged_in();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID - Game Repository & Devlog</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Global Custom CSS (Dark Theme, Solid Card) -->
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
                        <a class="nav-link me-3" href="#features">Fitur</a>
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
        <section class="container d-flex flex-column justify-content-center align-items-center text-center min-vh-80">
            <div class="row w-100">
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
                        <a href="#features" class="btn btn-grid-outline btn-lg px-4">
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="container py-5 border-top" style="border-color: var(--border-color) !important;">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Fitur Utama</h2>
                <p class="text-secondary">Solusi lengkap untuk manajemen proyek pengembangan game Anda</p>
            </div>
            
            <div class="row g-4">
                <!-- Fitur 1 -->
                <div class="col-md-4">
                    <div class="card-grid p-4 h-100 text-center">
                        <div class="mb-3 mt-2">
                            <i class="fa-solid fa-folder-open fa-3x text-accent"></i>
                        </div>
                        <h5 class="fw-semibold">Manajemen Aset</h5>
                        <p class="text-secondary small mb-0">
                            Unggah dan kelola berbagai aset game (sprite, model 3D, audio) dengan sistem filter grid yang cepat dan responsif.
                        </p>
                    </div>
                </div>
                
                <!-- Fitur 2 -->
                <div class="col-md-4">
                    <div class="card-grid p-4 h-100 text-center">
                        <div class="mb-3 mt-2">
                            <i class="fa-solid fa-trello fa-3x text-accent"></i>
                        </div>
                        <h5 class="fw-semibold">Kanban Board</h5>
                        <p class="text-secondary small mb-0">
                            Pantau task dan progres tim dengan Kanban board interaktif. Mendukung drag-and-drop Native HTML5 API untuk efisiensi.
                        </p>
                    </div>
                </div>
                
                <!-- Fitur 3 -->
                <div class="col-md-4">
                    <div class="card-grid p-4 h-100 text-center">
                        <div class="mb-3 mt-2">
                            <i class="fa-solid fa-feather fa-3x text-accent"></i>
                        </div>
                        <h5 class="fw-semibold">Devlog Harian</h5>
                        <p class="text-secondary small mb-0">
                            Catat perjalanan pengembangan game dengan Rich Text Editor yang ringan (memanfaatkan native execCommand dari browser).
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer mt-auto py-4 text-center border-top" style="border-color: var(--border-color) !important; background-color: var(--bg-card);">
        <div class="container">
            <span class="text-muted small">&copy; <?= date('Y') ?> GRID Studio. Hak Cipta Dilindungi.</span>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle (Tanpa jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Simple JS untuk smooth scroll (Vanilla JS) -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    // DOM Manipulation: Menggunakan scrollIntoView bawaan native API untuk performa yang optimal
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
