<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';

// hanya Admin yang bisa akses
require_role([ROLE_ADMIN]);

// Ambil statistik dashboard
$stats = ['total_projects' => 0, 
          'total_users' => 0,
          'pending_approvals' => 0,
          'total_assets' => 0, 
          'total_devlogs' => 0];

try {
    $stats['total_projects']    = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $stats['total_users']       = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = TRUE AND role != "Guest"')->fetchColumn();
    $stats['pending_approvals'] = get_pending_assets_count($pdo);
    $stats['total_assets']      = (int) $pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn();
    $stats['total_devlogs']     = (int) $pdo->query('SELECT COUNT(*) FROM devlogs WHERE status = "Published"')->fetchColumn();

    // Aset pending terbaru (5 item)
    $recent_pending = get_assets_filtered($pdo, 'Pending');
    $recent_pending = array_slice($recent_pending, 0, 5);

    // Proyek terbaru
    $all_projects = get_all_projects($pdo);
    $recent_projects = array_slice($all_projects, 0, 5);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $recent_pending  = [];
    $recent_projects = [];
}

// Akun menunggu aktivasi (hanya dalam organisasi admin yang login)
$pending_users = [];
$admin_org_id  = $_SESSION['organization_id'] ?? null;
try {
    if ($admin_org_id) {
        $stmt = $pdo->prepare('
            SELECT u.id, u.username, u.nama_lengkap, u.email, u.created_at,
                   o.nama AS org_name
            FROM users u
            LEFT JOIN organizations o ON u.organization_id = o.id
            WHERE u.is_active = FALSE
              AND u.organization_id = ?
            ORDER BY u.created_at DESC LIMIT 5
        ');
        $stmt->execute([$admin_org_id]);
    } else {
        $stmt = $pdo->query('
            SELECT u.id, u.username, u.nama_lengkap, u.email, u.created_at, NULL AS org_name
            FROM users u
            WHERE u.is_active = FALSE
            ORDER BY u.created_at DESC LIMIT 5
        ');
    }
    $pending_users = $stmt->fetchAll();
} catch (PDOException $ex) { error_log($ex->getMessage()); }


$page_title = 'Dashboard Admin';
$active_nav = 'dashboard';
include '../templates/admin_header.php';
?>

<!-- Stats Cards -->
<div class="row mb-4">
    <?php
    $stat_items = [
        ['value' => $stats['total_projects'],    'label' => 'Proyek',          'icon' => 'fa-gamepad',       'color' => 'bg-primary'],
        ['value' => $stats['total_users'],        'label' => 'Anggota Aktif',   'icon' => 'fa-users',         'color' => 'bg-success'],
        ['value' => $stats['pending_approvals'],  'label' => 'Aset Pending',    'icon' => 'fa-hourglass-half','color' => 'bg-warning'],
        ['value' => $stats['total_assets'],       'label' => 'Total Aset',      'icon' => 'fa-image',         'color' => 'bg-info'],
        ['value' => $stats['total_devlogs'],      'label' => 'Devlog Published','icon' => 'fa-book-open',     'color' => 'bg-secondary'],
    ];
    foreach ($stat_items as $s):
    ?>
    <div class="col-6 col-lg-auto mb-3 flex-lg-fill">
        <div class="stat-card">
            <div class="stat-icon <?= $s['color'] ?>"><i class="fa <?= $s['icon'] ?>"></i></div>
            <div class="stat-content">
                <span class="stat-value"><?= $s['value'] ?></span>
                <span class="stat-label"><?= $s['label'] ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <!-- Aset Pending -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fa fa-hourglass-half me-2 text-warning"></i>Aset Menunggu Persetujuan
                    </h6>
                    <a href="<?= APP_URL ?>/admin/assets.php?status=Pending"
                       class="btn btn-sm btn-outline-warning">Lihat Semua</a>
                </div>
                <?php if (empty($recent_pending)): ?>
                    <p class="text-muted small text-center py-3">Tidak ada aset yang menunggu. ✓</p>
                <?php else: ?>
                    <?php foreach ($recent_pending as $aset): ?>
                    <div class="d-flex justify-content-between align-items-center py-2"
                         style="border-bottom:1px solid var(--border-color);">
                        <div>
                            <div class="small fw-500"><?= e(truncate($aset['nama_aset'], 35)) ?></div>
                            <small class="text-muted">
                                <?= e($aset['kategori']) ?> · <?= e($aset['project_name'] ?? '—') ?>
                            </small>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="POST" action="<?= APP_URL ?>/admin/assets.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"   value="approve">
                                <input type="hidden" name="asset_id" value="<?= e($aset['id']) ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Setujui">
                                    <i class="fa fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" action="<?= APP_URL ?>/admin/assets.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"   value="reject">
                                <input type="hidden" name="asset_id" value="<?= e($aset['id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Tolak">
                                    <i class="fa fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Akun Menunggu Aktivasi -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fa fa-user-clock me-2 text-info"></i>Akun Menunggu Aktivasi
                    </h6>
                    <a href="<?= APP_URL ?>/admin/users.php"
                       class="btn btn-sm btn-outline-info">Kelola User</a>
                </div>
                <?php if (empty($pending_users)): ?>
                    <p class="text-muted small text-center py-3">Tidak ada akun yang menunggu. ✓</p>
                <?php else: ?>
                    <?php foreach ($pending_users as $u): ?>
                    <div class="d-flex justify-content-between align-items-center py-2"
                         style="border-bottom:1px solid var(--border-color);">
                        <div>
                            <div class="small fw-500"><?= e($u['nama_lengkap']) ?></div>
                            <small class="text-muted">@<?= e($u['username']) ?></small>
                        </div>
                        <small class="text-muted"><?= format_tanggal($u['created_at']) ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Proyek Terbaru -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fa fa-gamepad me-2 text-primary"></i>Proyek
                    </h6>
                    <a href="<?= APP_URL ?>/admin/projects.php" class="btn btn-sm btn-outline-primary">
                        Kelola Proyek
                    </a>
                </div>
                <?php if (empty($recent_projects)): ?>
                    <p class="text-muted small text-center py-3">
                        Belum ada proyek.
                        <a href="<?= APP_URL ?>/admin/projects.php?view=form">Buat sekarang</a>.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0" style="color:var(--text-primary);font-size:0.88rem;">
                            <thead style="color:var(--text-secondary);font-size:0.8rem;border-color:var(--border-color);">
                                <tr>
                                    <th class="pb-2">Nama Game</th>
                                    <th class="pb-2">Genre</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2">Anggota</th>
                                    <th class="pb-2">Target Rilis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_projects as $proj): ?>
                                <tr style="border-color:var(--border-color);">
                                    <td class="py-2">
                                        <a href="<?= APP_URL ?>/admin/projects.php?view=form&id=<?= urlencode($proj['id']) ?>"
                                           class="text-decoration-none fw-500"
                                           style="color:var(--accent-blue);">
                                            <?= e($proj['nama_game']) ?>
                                        </a>
                                    </td>
                                    <td class="py-2 text-muted"><?= e($proj['genre'] ?: '—') ?></td>
                                    <td class="py-2">
                                        <span class="badge <?= badge_status_proyek($proj['status']) ?>">
                                            <?= e($proj['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-2 text-muted"><?= (int)$proj['total_members'] ?></td>
                                    <td class="py-2 text-muted">
                                        <?= $proj['target_rilis'] ? format_tanggal($proj['target_rilis']) : '—' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/admin_footer.php'; ?>
