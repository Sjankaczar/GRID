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
 
// FILTER DARI GET
$filter_status   = $_GET['status']   ?? null;
$filter_kategori = $_GET['kategori'] ?? null;
 
// Normalkan filter 
if ($filter_status   === '') $filter_status   = null;
if ($filter_kategori === '') $filter_kategori = null;
 
// Validasi nilai filter
$valid_statuses  = ['Pending', 'Approved', 'Rejected'];
$valid_kategori  = ['Sprite', 'Audio', 'Script', 'Other'];
if ($filter_status   && !in_array($filter_status,   $valid_statuses, true))  $filter_status   = null;
if ($filter_kategori && !in_array($filter_kategori, $valid_kategori, true)) $filter_kategori = null;
 
$assets           = get_assets_filtered($pdo, $filter_status, $filter_kategori);
$pending_count    = get_pending_assets_count($pdo);
$all_projects     = get_all_projects($pdo);
 
// RENDER
$page_title  = 'Manajemen Aset';
$active_nav  = 'assets';
$breadcrumbs = [];
 
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
 
<!-- Filter Bar -->
<form method="GET" class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-4">
                <label class="form-label small mb-1">Filter Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">Semua Status</option>
                    <?php foreach (['Pending', 'Approved', 'Rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-4">
                <label class="form-label small mb-1">Filter Kategori</label>
                <select class="form-select form-select-sm" name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach (['Sprite', 'Audio', 'Script', 'Other'] as $k): ?>
                        <option value="<?= $k ?>" <?= $filter_kategori === $k ? 'selected' : '' ?>><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-4">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fa fa-filter me-1"></i>Terapkan Filter
                </button>
            </div>
        </div>
    </div>
</form>
 
<!-- Shortcut filter buttons -->
<div class="d-flex gap-2 flex-wrap mb-3">
    <a href="?status=Pending"
       class="btn btn-sm <?= $filter_status === 'Pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
        <i class="fa fa-hourglass-half me-1"></i>Pending
    </a>
    <a href="?status=Approved"
       class="btn btn-sm <?= $filter_status === 'Approved' ? 'btn-success' : 'btn-outline-success' ?>">
        <i class="fa fa-check me-1"></i>Approved
    </a>
    <a href="?status=Rejected"
       class="btn btn-sm <?= $filter_status === 'Rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">
        <i class="fa fa-times me-1"></i>Rejected
    </a>
    <?php if ($filter_status || $filter_kategori): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-times me-1"></i>Reset Filter
        </a>
    <?php endif; ?>
</div>
 
<!-- Hasil filter info -->
<p class="text-muted small mb-3">
    Menampilkan <strong><?= count($assets) ?></strong> aset
    <?php if ($filter_status):   ?> · Status: <strong><?= e($filter_status) ?></strong><?php endif; ?>
    <?php if ($filter_kategori): ?> · Kategori: <strong><?= e($filter_kategori) ?></strong><?php endif; ?>
</p>
 
<!-- Tabel Aset -->
<?php if (empty($assets)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa fa-image fa-3x mb-3" style="opacity:0.3;"></i>
            <p class="mb-0">Tidak ada aset yang sesuai filter.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="color:var(--text-primary);">
                    <thead style="border-bottom:1px solid var(--border-color);font-size:0.8rem;color:var(--text-secondary);">
                        <tr>
                            <th class="ps-3 py-3" style="width:40px;">#</th>
                            <th class="py-3">Nama Aset</th>
                            <th class="py-3">Proyek</th>
                            <th class="py-3">Kategori</th>
                            <th class="py-3">Ukuran</th>
                            <th class="py-3">Uploader</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody style="font-size:0.88rem;">
                        <?php foreach ($assets as $i => $aset): ?>
                        <tr style="border-color:var(--border-color);">
                            <td class="ps-3 py-3 text-muted"><?= $i + 1 ?></td>
                            <td class="py-3">
                                <div class="fw-500"><?= e($aset['nama_aset']) ?></div>
                                <small class="text-muted">
                                    <?= e($aset['format'] ?? '—') ?> · v<?= e($aset['versi'] ?? '1.0') ?>
                                    <?php if ($aset['tags']): ?>
                                        · <?= e(truncate($aset['tags'], 30)) ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td class="py-3">
                                <small><?= e($aset['project_name'] ?? '—') ?></small>
                            </td>
                            <td class="py-3">
                                <?php
                                $kat_icons = ['Sprite'=>'fa-image','Audio'=>'fa-music','Script'=>'fa-code','Other'=>'fa-file'];
                                $kat_icon  = $kat_icons[$aset['kategori']] ?? 'fa-file';
                                ?>
                                <small><i class="fa <?= $kat_icon ?> me-1"></i><?= e($aset['kategori']) ?></small>
                            </td>
                            <td class="py-3">
                                <small><?= format_filesize((int)$aset['ukuran_kb']) ?></small>
                            </td>
                            <td class="py-3">
                                <small><?= e($aset['uploader_name'] ?? '—') ?></small>
                            </td>
                            <td class="py-3">
                                <span class="badge <?= badge_status_aset($aset['status']) ?>">
                                    <?= e($aset['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 pe-3">
                                <div class="d-flex gap-1 flex-wrap">
                                    <!-- Link download / preview -->
                                    <?php if ($aset['file_url']): ?>
                                    <a href="<?= e(APP_URL . $aset['file_url']) ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Lihat file">
                                        <i class="fa fa-external-link-alt"></i>
                                    </a>
                                    <?php endif; ?>
 
                                    <!-- Approve -->
                                    <?php if ($aset['status'] !== 'Approved'): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action"          value="approve">
                                        <input type="hidden" name="asset_id"        value="<?= e($aset['id']) ?>">
                                        <input type="hidden" name="filter_status"   value="<?= e($filter_status ?? '') ?>">
                                        <input type="hidden" name="filter_kategori" value="<?= e($filter_kategori ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-success"
                                                title="Setujui aset ini"
                                                onclick="return confirm('Setujui aset \'<?= e(addslashes($aset['nama_aset'])) ?>\'?')">
                                            <i class="fa fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
 
                                    <!-- Reject -->
                                    <?php if ($aset['status'] !== 'Rejected'): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action"          value="reject">
                                        <input type="hidden" name="asset_id"        value="<?= e($aset['id']) ?>">
                                        <input type="hidden" name="filter_status"   value="<?= e($filter_status ?? '') ?>">
                                        <input type="hidden" name="filter_kategori" value="<?= e($filter_kategori ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                title="Tolak aset ini"
                                                onclick="return confirm('Tolak aset \'<?= e(addslashes($aset['nama_aset'])) ?>\'?')">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
 
<?php include '../templates/admin_footer.php'; ?>