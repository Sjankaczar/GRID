<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db_helpers.php';

require_role([ROLE_MEMBER, ROLE_ADMIN]);

$user_id = current_user_id();

// Ambil proyek yang diikuti user ini (via project_members)
$my_projects = [];
try {
    $stmt = $pdo->prepare('
        SELECT p.*,
               u.nama_lengkap AS lead_name,
               pm.joined_at,
               (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id = p.id) AS total_members,
               (SELECT COUNT(*) FROM assets a WHERE a.project_id = p.id AND a.uploader_id = ?) AS my_assets_count,
               (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.assignee_id = ? AND t.status_kolom != "Done") AS my_tasks_count
        FROM projects p
        INNER JOIN project_members pm ON p.id = pm.project_id
        LEFT JOIN users u ON p.lead_id = u.id
        WHERE pm.user_id = ?
        ORDER BY pm.joined_at DESC
    ');
    $stmt->execute([$user_id, $user_id, $user_id]);
    $my_projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$page_title = 'Proyek Saya';
$active_nav = 'my_projects';
$breadcrumbs = [['label' => 'Proyek Saya', 'url' => '']];
include '../templates/member_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted small mb-0">
        Kamu tergabung dalam <strong><?= count($my_projects) ?></strong> proyek.
    </p>
</div>

<?php if (empty($my_projects)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa fa-gamepad fa-3x mb-3 d-block"></i>
            <p>Kamu belum tergabung dalam proyek apapun.</p>
            <p class="small">Hubungi Admin studio kamu untuk di-assign ke proyek.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($my_projects as $proj): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <!-- Header Proyek -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="fw-semibold mb-1" style="color:var(--text-primary);">
                                <?= e($proj['nama_game']) ?>
                            </h6>
                            <small class="text-muted">
                                Lead: <?= e($proj['lead_name'] ?? '—') ?>
                            </small>
                        </div>
                        <span class="badge <?= badge_status_proyek($proj['status']) ?>" style="white-space:nowrap;">
                            <?= e($proj['status']) ?>
                        </span>
                    </div>

                    <!-- Deskripsi -->
                    <?php if (!empty($proj['deskripsi'])): ?>
                    <p class="text-muted small mb-3" style="line-height:1.5;">
                        <?= e(truncate($proj['deskripsi'], 100)) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Detail -->
                    <div class="mb-3" style="font-size:0.8rem; color:var(--text-secondary);">
                        <?php if ($proj['genre']): ?>
                        <div class="mb-1">
                            <i class="fa fa-tag me-1"></i><?= e($proj['genre']) ?>
                            <?php if ($proj['platform']): ?>· <?= e($proj['platform']) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($proj['target_rilis']): ?>
                        <div class="mb-1">
                            <i class="fa fa-calendar me-1"></i>Target: <?= format_tanggal($proj['target_rilis']) ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <i class="fa fa-users me-1"></i><?= (int)$proj['total_members'] ?> anggota
                            &nbsp;·&nbsp;
                            <i class="fa fa-clock me-1"></i>Bergabung <?= format_tanggal($proj['joined_at']) ?>
                        </div>
                    </div>

                    <!-- Stat Kontribusiku -->
                    <div class="d-flex gap-3 mb-3 p-2 rounded" style="background:var(--bg-input, #1a1a2e); font-size:0.8rem;">
                        <div class="text-center flex-fill">
                            <div class="fw-semibold" style="color:var(--accent-blue, #3b82f6);">
                                <?= (int)$proj['my_assets_count'] ?>
                            </div>
                            <div class="text-muted">Aset Saya</div>
                        </div>
                        <div class="border-start" style="border-color:var(--border-color) !important;"></div>
                        <div class="text-center flex-fill">
                            <div class="fw-semibold text-warning">
                                <?= (int)$proj['my_tasks_count'] ?>
                            </div>
                            <div class="text-muted">Tugas Aktif</div>
                        </div>
                    </div>

                    <!-- Aksi -->
                    <div class="d-flex gap-2">
                        <a href="<?= APP_URL ?>/member/my_assets.php?project_id=<?= urlencode($proj['id']) ?>"
                           class="btn btn-sm btn-outline-info flex-fill">
                            <i class="fa fa-image me-1"></i>Aset Saya
                        </a>
                        <a href="<?= APP_URL ?>/member/upload_aset.php?project_id=<?= urlencode($proj['id']) ?>"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fa fa-upload me-1"></i>Upload
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../templates/member_footer.php'; ?>
