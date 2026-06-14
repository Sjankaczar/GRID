<?php

// PROJECTS
 
/**
 * Ambil semua proyek beserta nama lead dan jumlah anggota.
 */
function get_all_projects(PDO $pdo): array {
    $stmt = $pdo->query('
        SELECT p.*,
               u.nama_lengkap AS lead_name,
               (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS total_members
        FROM projects p
        LEFT JOIN users u ON p.lead_id = u.id
        ORDER BY p.nama_game ASC
    ');
    return $stmt->fetchAll();
}
 
/**
 * Ambil satu proyek by ID termasuk nama lead.
 */
function get_project_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare('
        SELECT p.*, u.nama_lengkap AS lead_name
        FROM projects p
        LEFT JOIN users u ON p.lead_id = u.id
        WHERE p.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
 
/**
 * Ambil daftar users dari sebuah proyek.
 */
function get_project_members(PDO $pdo, string $project_id): array {
    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.nama_lengkap, u.role, u.avatar_url,
               pm.joined_at
        FROM project_members pm
        INNER JOIN users u ON pm.user_id = u.id
        WHERE pm.project_id = ?
        ORDER BY u.nama_lengkap ASC
    ');
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}
 
/**
 * Ambil daftar user aktif yang BELUM menjadi anggota proyek tertentu.
 */
function get_non_project_members(PDO $pdo, string $project_id): array {
    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, role
        FROM users
        WHERE is_active = TRUE
          AND role != ?
          AND id NOT IN (
              SELECT user_id FROM project_members WHERE project_id = ?
          )
        ORDER BY nama_lengkap ASC
    ');
    $stmt->execute([ROLE_GUEST, $project_id]);
    return $stmt->fetchAll();
}
 
/**
 * Ambil semua user aktif untuk dropdown lead_id.
 */
function get_all_active_users(PDO $pdo): array {
    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, role
        FROM users
        WHERE is_active = TRUE AND role != ?
        ORDER BY nama_lengkap ASC
    ');
    $stmt->execute([ROLE_GUEST]);
    return $stmt->fetchAll();
}
 
/**
 * Ambil proyek yang diikuti oleh user tertentu (via project_members).
 */
function get_user_projects(PDO $pdo, string $user_id): array {
    $stmt = $pdo->prepare('
        SELECT p.id, p.nama_game, p.status
        FROM projects p
        INNER JOIN project_members pm ON p.id = pm.project_id
        WHERE pm.user_id = ?
        ORDER BY p.nama_game ASC
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
 
 
// ASSETS
 
/**
 * Ambil aset dengan filter opsional (status, kategori, project_id).
 */
function get_assets_filtered(
    PDO $pdo,
    ?string $status     = null,
    ?string $kategori   = null,
    ?string $project_id = null
): array {
    $sql    = '
        SELECT a.*,
               u.nama_lengkap AS uploader_name,
               p.nama_game    AS project_name
        FROM assets a
        LEFT JOIN users    u ON a.uploader_id = u.id
        LEFT JOIN projects p ON a.project_id  = p.id
        WHERE 1=1
    ';
    $params = [];
 
    if ($status !== null) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }
    if ($kategori !== null) {
        $sql .= ' AND a.kategori = ?';
        $params[] = $kategori;
    }
    if ($project_id !== null) {
        $sql .= ' AND a.project_id = ?';
        $params[] = $project_id;
    }
 
    $sql .= ' ORDER BY a.created_at DESC';
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
 
/**
 * Ambil satu aset by ID beserta info uploader dan proyek.
 */
function get_asset_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare('
        SELECT a.*,
               u.nama_lengkap AS uploader_name,
               p.nama_game    AS project_name
        FROM assets a
        LEFT JOIN users    u ON a.uploader_id = u.id
        LEFT JOIN projects p ON a.project_id  = p.id
        WHERE a.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
 
/**
 * Update status aset.
 */
function update_asset_status(PDO $pdo, string $id, string $status): bool {
    $allowed = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $allowed, true)) return false;
 
    $stmt = $pdo->prepare('UPDATE assets SET status = ? WHERE id = ?');
    return $stmt->execute([$status, $id]);
}
 
/**
 * Hitung jumlah aset berstatus Pending.
 */
function get_pending_assets_count(PDO $pdo): int {
    $stmt = $pdo->query('SELECT COUNT(*) FROM assets WHERE status = "Pending"');
    return (int) $stmt->fetchColumn();
}
 
 
// DEVLOGS
 
/**
 * Ambil semua devlog (opsional filter by penulis_id).
 */
function get_devlogs(PDO $pdo, ?string $user_id = null): array {
    $sql    = '
        SELECT d.*,
               u.nama_lengkap AS penulis_name,
               p.nama_game    AS project_name
        FROM devlogs d
        LEFT JOIN users    u ON d.penulis_id  = u.id
        LEFT JOIN projects p ON d.project_id  = p.id
        WHERE 1=1
    ';
    $params = [];
 
    if ($user_id !== null) {
        $sql .= ' AND d.penulis_id = ?';
        $params[] = $user_id;
    }
 
    $sql .= ' ORDER BY d.created_at DESC';
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
 
/**
 * Ambil satu devlog by ID, dengan cek kepemilikan opsional.
 */
function get_devlog_by_id(PDO $pdo, string $id, ?string $owner_id = null): ?array {
    $sql    = '
        SELECT d.*,
               u.nama_lengkap AS penulis_name,
               p.nama_game    AS project_name
        FROM devlogs d
        LEFT JOIN users    u ON d.penulis_id = u.id
        LEFT JOIN projects p ON d.project_id = p.id
        WHERE d.id = ?
    ';
    $params = [$id];
 
    if ($owner_id !== null) {
        $sql .= ' AND d.penulis_id = ?';
        $params[] = $owner_id;
    }
 
    $sql .= ' LIMIT 1';
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}