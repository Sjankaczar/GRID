<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';
require_once '../includes/csrf.php';
 
require_role([ROLE_ADMIN]);
 
// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action   = $_POST['action']   ?? '';
    $asset_id = trim($_POST['asset_id'] ?? '');
 
    if ($asset_id && in_array($action, ['approve', 'reject'], true)) {
        $asset = get_asset_by_id($pdo, $asset_id);
        if ($asset) {
            $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
            update_asset_status($pdo, $asset_id, $new_status);
 
            $label = $new_status === 'Approved' ? 'disetujui' : 'ditolak';
            set_flash('success', 'Aset "' . $asset['nama_aset'] . '" berhasil ' . $label . '.');
        } else {
            set_flash('error', 'Aset tidak ditemukan.');
        }
    }
 
    // Redirect ke filter yang sama
    $qs = http_build_query(array_filter([
        'status'   => $_POST['filter_status']   ?? '',
        'kategori' => $_POST['filter_kategori'] ?? '',
    ]));
    redirect(APP_URL . '/admin/assets.php' . ($qs ? '?' . $qs : ''));
}
 
// Kita ambil semua aset agar filter dilakukan sepenuhnya di sisi klien (Vanilla JS)
$assets           = get_assets_filtered($pdo, null, null);
$pending_count    = get_pending_assets_count($pdo);
$all_projects     = get_all_projects($pdo);
 
// RENDER
$page_title  = 'Manajemen Aset';
$active_nav  = 'assets';
$breadcrumbs = [];
 
// Tambahan CSS untuk Grid Kartu Aset
$extra_css = '
<style>
.asset-card {
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.asset-card:hover {
    transform: translateY(-3px);
    border-color: var(--accent-blue);
}
.asset-item {
    transition: opacity 0.3s ease;
}
</style>
';

include '../templates/admin_header.php';
?>
 
<!-- Header Bar -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h5 class="mb-1">Repositori Aset</h5>
        <small class="text-muted">
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-warning text-dark me-1"><?= $pending_count ?></span>
                aset menunggu persetujuan
            <?php else: ?>
                Semua aset sudah ditinjau
            <?php endif; ?>
        </small>
    </div>
</div>
 
<!-- Filter Bar (Client-Side JS) -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-4">
                <label class="form-label small mb-1">Filter Status</label>
                <select class="form-select form-select-sm" id="filterStatus">
                    <option value="all">Semua Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div class="col-12 col-sm-4">
                <label class="form-label small mb-1">Filter Kategori</label>
                <select class="form-select form-select-sm" id="filterKategori">
                    <option value="all">Semua Kategori</option>
                    <option value="Sprite">Sprite</option>
                    <option value="Audio">Audio</option>
                    <option value="Script">Script</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-12 col-sm-4">
                <label class="form-label small mb-1">Cari Aset</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-input border-secondary text-muted"><i class="fa fa-search"></i></span>
                    <input type="text" class="form-control border-secondary" id="searchInput" placeholder="Ketik nama aset...">
                </div>
            </div>
        </div>
    </div>
</div>
 
<!-- Hasil filter info -->
<p class="text-muted small mb-3">
    Menampilkan <strong id="assetCount"><?= count($assets) ?></strong> aset di repositori
</p>
 
<!-- Grid Aset -->
<div class="row g-3" id="assetGrid">
    <?php foreach ($assets as $i => $aset): ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3 asset-item" 
         data-status="<?= e($aset['status']) ?>" 
         data-kategori="<?= e($aset['kategori']) ?>"
         data-nama="<?= strtolower(e($aset['nama_aset'])) ?>">
        
        <div class="card h-100 asset-card">
            <div class="card-body d-flex flex-column p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge <?= badge_status_aset($aset['status']) ?>"><?= e($aset['status']) ?></span>
                    <?php
                    $kat_icons = ['Sprite'=>'fa-image','Audio'=>'fa-music','Script'=>'fa-code','Other'=>'fa-file'];
                    $kat_icon  = $kat_icons[$aset['kategori']] ?? 'fa-file';
                    ?>
                    <small class="text-muted"><i class="fa <?= $kat_icon ?> me-1"></i><?= e($aset['kategori']) ?></small>
                </div>
                
                <h6 class="card-title text-truncate mb-1" title="<?= e($aset['nama_aset']) ?>">
                    <?= e($aset['nama_aset']) ?>
                </h6>
                
                <div class="text-muted small mb-3 flex-grow-1">
                    <i class="fa fa-archive text-secondary me-1"></i> <?= e($aset['format'] ?? '—') ?> · <?= format_filesize((int)$aset['ukuran_kb']) ?><br>
                    <i class="fa fa-user text-secondary me-1 mt-2"></i> <?= e($aset['uploader_name'] ?? '—') ?>
                </div>
                
                <div class="mt-auto d-flex gap-1">
                    <?php if ($aset['file_url']): ?>
                    <a href="<?= e(APP_URL . $aset['file_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Lihat/Download">
                        <i class="fa fa-external-link-alt"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($aset['status'] !== 'Approved'): ?>
                    <form method="POST" class="d-inline flex-grow-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="asset_id" value="<?= e($aset['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-success w-100" title="Setujui" onclick="return confirm('Setujui aset ini?')">
                            <i class="fa fa-check"></i>
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if ($aset['status'] !== 'Rejected'): ?>
                    <form method="POST" class="d-inline flex-grow-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="asset_id" value="<?= e($aset['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger w-100" title="Tolak" onclick="return confirm('Tolak aset ini?')">
                            <i class="fa fa-times"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- State saat filter tidak menemukan hasil -->
    <div class="col-12 text-center text-muted py-5 d-none" id="noAssetFound">
        <i class="fa fa-search fa-3x mb-3" style="opacity:0.3;"></i>
        <p>Aset tidak ditemukan untuk filter ini.</p>
    </div>
</div>

<!-- Vanilla JS Client-Side Filter -->
<?php $extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Referensi Elemen DOM
    const filterStatus = document.getElementById("filterStatus");
    const filterKategori = document.getElementById("filterKategori");
    const searchInput = document.getElementById("searchInput");
    const assetItems = document.querySelectorAll(".asset-item");
    const assetCount = document.getElementById("assetCount");
    const noAssetFound = document.getElementById("noAssetFound");

    // Fungsi Utama Filter (DOM Manipulation Langsung)
    function applyFilters() {
        const statusVal = filterStatus.value;
        const kategoriVal = filterKategori.value;
        const searchVal = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        assetItems.forEach(item => {
            const itemStatus = item.getAttribute("data-status");
            const itemKategori = item.getAttribute("data-kategori");
            const itemNama = item.getAttribute("data-nama");

            // Cek kondisi filter
            const matchStatus = statusVal === "all" || itemStatus === statusVal;
            const matchKategori = kategoriVal === "all" || itemKategori === kategoriVal;
            const matchSearch = searchVal === "" || itemNama.includes(searchVal);

            // Terapkan perubahan pada DOM
            if (matchStatus && matchKategori && matchSearch) {
                item.style.display = "block";
                setTimeout(() => item.style.opacity = "1", 10);
                visibleCount++;
            } else {
                item.style.opacity = "0";
                setTimeout(() => item.style.display = "none", 300); // Wait for transition
            }
        });

        // Update indikator teks
        assetCount.textContent = visibleCount;
        
        // Tampilkan placeholder jika kosong
        if (visibleCount === 0) {
            setTimeout(() => noAssetFound.classList.remove("d-none"), 300);
        } else {
            noAssetFound.classList.add("d-none");
        }
    }

    // Event Listener (Tanpa reload halaman)
    filterStatus.addEventListener("change", applyFilters);
    filterKategori.addEventListener("change", applyFilters);
    searchInput.addEventListener("input", applyFilters); // "input" agar berjalan real-time saat mengetik
});
</script>
';
?>
 
<?php include '../templates/admin_footer.php'; ?>