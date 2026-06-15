<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';

require_role([ROLE_MEMBER, ROLE_ADMIN]);

$user_id = current_user_id();

// Filter status dari query string
$filter_status = $_GET['status'] ?? '';
$allowed_statuses = ['', 'Pending', 'Approved', 'Rejected'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = '';
}

// Ambil semua aset milik user ini
$sql = '
    SELECT a.*,
           p.nama_game AS project_name
    FROM assets a
    LEFT JOIN projects p ON a.project_id = p.id
    WHERE a.uploader_id = ?
';
$params = [$user_id];

if ($filter_status !== '') {
    $sql .= ' AND a.status = ?';
    $params[] = $filter_status;
}
$sql .= ' ORDER BY a.created_at DESC';

$my_assets = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $my_assets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Hitung per-status untuk badge
$counts = ['all' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
try {
    $stmt_c = $pdo->prepare('
        SELECT status, COUNT(*) AS total
        FROM assets
        WHERE uploader_id = ?
        GROUP BY status
    ');
    $stmt_c->execute([$user_id]);
    foreach ($stmt_c->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];
        $counts['all'] += (int) $row['total'];
    }
} catch (PDOException $e) { error_log($e->getMessage()); }

$page_title = 'Aset Saya';
$active_nav = 'my_assets';
$breadcrumbs = [['label' => 'Aset Saya', 'url' => '']];
include '../templates/member_header.php';
?>

<!-- Filter Tabs -->
<div class="mb-4">
    <ul class="nav nav-pills gap-1">
        <li class="nav-item">
            <a class="nav-link <?= $filter_status === '' ? 'active' : '' ?>"
               href="my_assets.php">
                Semua
                <span class="badge bg-secondary ms-1"><?= $counts['all'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter_status === 'Pending' ? 'active' : '' ?>"
               href="my_assets.php?status=Pending">
                <i class="fa fa-hourglass-half me-1"></i>Pending
                <span class="badge bg-warning text-dark ms-1"><?= $counts['Pending'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter_status === 'Approved' ? 'active' : '' ?>"
               href="my_assets.php?status=Approved">
                <i class="fa fa-check-circle me-1"></i>Disetujui
                <span class="badge bg-success ms-1"><?= $counts['Approved'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter_status === 'Rejected' ? 'active' : '' ?>"
               href="my_assets.php?status=Rejected">
                <i class="fa fa-times-circle me-1"></i>Ditolak
                <span class="badge bg-danger ms-1"><?= $counts['Rejected'] ?></span>
            </a>
        </li>
    </ul>
</div>

<!-- Tombol Upload -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">
        Menampilkan <strong><?= count($my_assets) ?></strong> aset
        <?= $filter_status !== '' ? '— Status: <span class="badge ' . badge_status_aset($filter_status) . '">' . e($filter_status) . '</span>' : '' ?>
    </p>
    <a href="upload_aset.php" class="btn btn-sm btn-primary">
        <i class="fa fa-upload me-1"></i>Upload Aset Baru
    </a>
</div>

<!-- Tabel Aset -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($my_assets)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa fa-inbox fa-3x mb-3 d-block"></i>
                Belum ada aset yang diunggah<?= $filter_status !== '' ? ' dengan status ini' : '' ?>.
                <div class="mt-3">
                    <a href="upload_aset.php" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-upload me-1"></i>Upload Sekarang
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0" style="color:var(--text-primary); font-size:0.88rem;">
                    <thead style="color:var(--text-secondary); font-size:0.8rem; border-color:var(--border-color);">
                        <tr>
                            <th class="px-4 py-3">Nama Aset</th>
                            <th class="py-3">Kategori</th>
                            <th class="py-3">Proyek</th>
                            <th class="py-3">Ukuran</th>
                            <th class="py-3">Tanggal Upload</th>
                            <th class="py-3 text-center">Status Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_assets as $aset): ?>
                        <tr style="border-color:var(--border-color);">
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <!-- Icon berdasarkan kategori -->
                                    <?php
                                    $icon_map = [
                                        'Sprite' => 'fa-image text-info',
                                        'Audio'  => 'fa-music text-success',
                                        'Script' => 'fa-code text-warning',
                                        'Other'  => 'fa-file text-secondary',
                                    ];
                                    $icon_class = $icon_map[$aset['kategori']] ?? 'fa-file text-secondary';
                                    ?>
                                    <i class="fa <?= $icon_class ?>" style="width:20px; text-align:center;"></i>
                                    <div>
                                        <div class="fw-500"><?= e(truncate($aset['nama_aset'], 45)) ?></div>
                                        <?php if ($aset['tags']): ?>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            <?php foreach (explode(',', $aset['tags']) as $tag): ?>
                                                <span class="badge bg-input border border-secondary me-1" style="font-weight:400; font-size:0.7rem;"><?= e(trim($tag)) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-secondary"><?= e($aset['kategori']) ?></span>
                            </td>
                            <td class="py-3 text-muted">
                                <?= e($aset['project_name'] ?? '—') ?>
                            </td>
                            <td class="py-3 text-muted">
                                <?= format_filesize($aset['ukuran_kb']) ?>
                            </td>
                            <td class="py-3 text-muted" style="white-space:nowrap;">
                                <?= format_tanggal($aset['created_at']) ?>
                            </td>
                            <td class="py-3 text-center">
                                <?php
                                $status_config = [
                                    'Pending'  => ['class' => 'bg-warning text-dark', 'icon' => 'fa-hourglass-half', 'text' => 'Menunggu Review'],
                                    'Approved' => ['class' => 'bg-success',           'icon' => 'fa-check-circle',   'text' => 'Disetujui'],
                                    'Rejected' => ['class' => 'bg-danger',            'icon' => 'fa-times-circle',   'text' => 'Ditolak'],
                                ];
                                $sc = $status_config[$aset['status']] ?? $status_config['Pending'];
                                ?>
                                <span class="badge <?= $sc['class'] ?> d-inline-flex align-items-center gap-1 px-2 py-1">
                                    <i class="fa <?= $sc['icon'] ?>" style="font-size:0.75rem;"></i>
                                    <?= $sc['text'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../templates/member_footer.php'; ?>
