<?php

session_start();
require_once '../config/config.php';
require_once '../koneksi.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

require_role([ROLE_ADMIN]);

$errors = [];

// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = $_POST['action'] ?? '';
    $user_id = trim($_POST['user_id'] ?? '');

    if (!$user_id) {
        set_flash('error', 'User ID tidak valid.');
        redirect(APP_URL . '/admin/users.php');
    }

    // Aktifkan user 
    if ($action === 'approve') {
        $stmt = $pdo->prepare('UPDATE users SET is_active = TRUE WHERE id = ?');
        $stmt->execute([$user_id]);

        $user = $pdo->prepare('SELECT nama_lengkap FROM users WHERE id = ?');
        $user->execute([$user_id]);
        $user_data = $user->fetch();

        set_flash('success', 'User "' . ($user_data['nama_lengkap'] ?? 'Unknown') . '" berhasil diaktifkan.');
        redirect(APP_URL . '/admin/users.php?view=pending');
    }

    // Tolak & hapus user
    if ($action === 'reject') {
        $user = $pdo->prepare('SELECT nama_lengkap FROM users WHERE id = ?');
        $user->execute([$user_id]);
        $user_data = $user->fetch();

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user_id]);

        set_flash('success', 'User "' . ($user_data['nama_lengkap'] ?? 'Unknown') . '" telah ditolak dan dihapus.');
        redirect(APP_URL . '/admin/users.php?view=pending');
    }

    // Ubah role user
    if ($action === 'change_role') {
        $new_role = trim($_POST['new_role'] ?? '');
        $valid_roles = [ROLE_ADMIN, ROLE_MEMBER, ROLE_GUEST];

        if (!in_array($new_role, $valid_roles, true)) {
            set_flash('error', 'Role tidak valid.');
            redirect(APP_URL . '/admin/users.php');
        }

        $current_user_id = current_user_id();
        if ($user_id === $current_user_id && $new_role !== ROLE_ADMIN) {
            set_flash('error', 'Kamu tidak bisa mengubah role diri sendiri menjadi bukan Admin.');
            redirect(APP_URL . '/admin/users.php');
        }

        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$new_role, $user_id]);

        set_flash('success', 'Role user berhasil diubah menjadi ' . $new_role . '.');
        redirect(APP_URL . '/admin/users.php');
    }

    // Hapus user
    if ($action === 'delete') {
        $current_user_id = current_user_id();

        if ($user_id === $current_user_id) {
            set_flash('error', 'Kamu tidak bisa menghapus akun diri sendiri.');
            redirect(APP_URL . '/admin/users.php');
        }

        $user = $pdo->prepare('SELECT nama_lengkap FROM users WHERE id = ?');
        $user->execute([$user_id]);
        $user_data = $user->fetch();

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user_id]);

        set_flash('success', 'User "' . ($user_data['nama_lengkap'] ?? 'Unknown') . '" berhasil dihapus.');
        redirect(APP_URL . '/admin/users.php');
    }
}

// AMBIL DATA USER
$view = $_GET['view'] ?? 'list';
$users = [];
$pending_count = 0;

try {
    if ($view === 'pending') {
        $stmt = $pdo->query('
            SELECT id, username, nama_lengkap, email, role, created_at
            FROM users
            WHERE is_active = FALSE
            ORDER BY created_at DESC
        ');
        $users = $stmt->fetchAll();
        $pending_count = count($users);
    } else {
        $stmt = $pdo->query('
            SELECT id, username, nama_lengkap, email, role, is_active, created_at
            FROM users
            ORDER BY nama_lengkap ASC
        ');
        $users = $stmt->fetchAll();
        $pending_count = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = FALSE')->fetchColumn();
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $users = [];
}

// RENDER
$page_title  = ($view === 'pending') ? 'User Menunggu Aktivasi' : 'Manajemen User';
$active_nav  = 'users';
$breadcrumbs = [];

include '../templates/admin_header.php';
?>

<!-- Header with Tab Filter -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h5 class="mb-0">
        <?= ($view === 'pending') ? 'Menunggu Aktivasi' : 'Semua User' ?>
        <?php if ($pending_count > 0): ?>
            <span class="badge bg-warning text-dark ms-2"><?= $pending_count ?></span>
        <?php endif; ?>
    </h5>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= ($view !== 'pending') ? 'active' : '' ?>" href="users.php">
            <i class="fa fa-users me-2"></i>Semua User
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= ($view === 'pending') ? 'active' : '' ?>" href="?view=pending">
            <i class="fa fa-clock me-2"></i>Pending
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-warning text-dark ms-2"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<!-- Tabel User -->
<?php if (empty($users)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fa fa-users fa-3x mb-3" style="opacity:0.3;"></i>
            <p class="mb-0">
                <?php if ($view === 'pending'): ?>
                    Tidak ada user yang menunggu aktivasi.
                <?php else: ?>
                    Tidak ada user terdaftar.
                <?php endif; ?>
            </p>
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
                            <th class="py-3">Nama Lengkap</th>
                            <th class="py-3">Username</th>
                            <th class="py-3">Email</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody style="font-size:0.88rem;">
                        <?php foreach ($users as $idx => $user): ?>
                        <tr style="border-color:var(--border-color);">
                            <td class="ps-3 py-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="py-3 fw-500"><?= e($user['nama_lengkap']) ?></td>
                            <td class="py-3 text-muted">@<?= e($user['username']) ?></td>
                            <td class="py-3 text-muted"><?= e($user['email']) ?></td>
                            <td class="py-3">
                                <?php if ($view === 'pending'): ?>
                                    <!-- User belum aktif, tampilkan role default saja -->
                                    <span class="badge bg-secondary"><?= e($user['role']) ?></span>
                                <?php else: ?>
                                    <!-- Dropdown ubah role untuk user aktif -->
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action"  value="change_role">
                                        <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                        <select name="new_role" class="form-select form-select-sm"
                                                onchange="this.form.submit()"
                                                style="width:auto;display:inline-block;">
                                            <option value="<?= e($user['role']) ?>" selected><?= e($user['role']) ?></option>
                                            <option value="Admin" <?= $user['role'] === 'Admin' ? 'disabled' : '' ?>>Admin</option>
                                            <option value="Member" <?= $user['role'] === 'Member' ? 'disabled' : '' ?>>Member</option>
                                            <option value="Guest" <?= $user['role'] === 'Guest' ? 'disabled' : '' ?>>Guest</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if (isset($user['is_active'])): ?>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Pending view: semua user is_active = FALSE -->
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 pe-3">
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($view === 'pending'): ?>
                                        <!-- Tombol Approve & Reject untuk pending user -->
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action"  value="approve">
                                            <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-success"
                                                    title="Setujui user ini">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Tolak & hapus user \'<?= e(addslashes($user['nama_lengkap'])) ?>\'?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action"  value="reject">
                                            <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    title="Tolak user ini">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Tombol Delete untuk user aktif -->
                                        <?php if (current_user_id() !== $user['id']): ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Hapus user \'<?= e(addslashes($user['nama_lengkap'])) ?>\'?\nTindakan ini tidak dapat dibatalkan.')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action"  value="delete">
                                                <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Hapus user ini">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <small class="text-muted">(Akun kamu sendiri)</small>
                                        <?php endif; ?>
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