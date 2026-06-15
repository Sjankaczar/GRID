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
 * Ambil proyek yang ter-scope ke satu organisasi tertentu.
 */
function get_all_projects_by_org(PDO $pdo, ?string $org_id): array {
    if ($org_id === null) return get_all_projects($pdo);

    $stmt = $pdo->prepare('
        SELECT p.*,
               u.nama_lengkap AS lead_name,
               (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS total_members
        FROM projects p
        LEFT JOIN users u ON p.lead_id = u.id
        WHERE p.organization_id = ?
        ORDER BY p.nama_game ASC
    ');
    $stmt->execute([$org_id]);
    return $stmt->fetchAll();
}

/**
 * Ambil semua user aktif (non-Guest) dalam satu organisasi untuk dropdown.
 */
function get_active_users_by_org(PDO $pdo, ?string $org_id): array {
    if ($org_id === null) return get_all_active_users($pdo);

    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, role
        FROM users
        WHERE is_active = TRUE
          AND role != ?
          AND organization_id = ?
        ORDER BY nama_lengkap ASC
    ');
    $stmt->execute([ROLE_GUEST, $org_id]);
    return $stmt->fetchAll();
}

/**
 * Ambil user aktif yang BELUM menjadi anggota proyek, dibatasi ke organisasi tertentu.
 */
function get_non_project_members_by_org(PDO $pdo, string $project_id, ?string $org_id): array {
    if ($org_id === null) return get_non_project_members($pdo, $project_id);

    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, role
        FROM users
        WHERE is_active = TRUE
          AND role != ?
          AND organization_id = ?
          AND id NOT IN (
              SELECT user_id FROM project_members WHERE project_id = ?
          )
        ORDER BY nama_lengkap ASC
    ');
    $stmt->execute([ROLE_GUEST, $org_id, $project_id]);
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
 * Ambil daftar user aktif yang belum menjadi anggota proyek tertentu.
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
 * Ambil aset dengan filter.
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
 * Ambil aset yang ter-scope ke satu organisasi (via project.organization_id).
 */
function get_assets_filtered_by_org(
    PDO $pdo,
    ?string $org_id,
    ?string $status   = null,
    ?string $kategori = null
): array {
    if ($org_id === null) return get_assets_filtered($pdo, $status, $kategori);

    $sql    = '
        SELECT a.*,
               u.nama_lengkap AS uploader_name,
               p.nama_game    AS project_name
        FROM assets a
        LEFT JOIN users    u ON a.uploader_id = u.id
        LEFT JOIN projects p ON a.project_id  = p.id
        WHERE p.organization_id = ?
    ';
    $params = [$org_id];

    if ($status !== null) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }
    if ($kategori !== null) {
        $sql .= ' AND a.kategori = ?';
        $params[] = $kategori;
    }

    $sql .= ' ORDER BY a.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Hitung aset Pending yang ter-scope ke organisasi tertentu.
 */
function get_pending_assets_count_by_org(PDO $pdo, ?string $org_id): int {
    if ($org_id === null) return get_pending_assets_count($pdo);

    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM assets a
        LEFT JOIN projects p ON a.project_id = p.id
        WHERE a.status = "Pending"
          AND p.organization_id = ?
    ');
    $stmt->execute([$org_id]);
    return (int) $stmt->fetchColumn();
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
 * Ambil semua devlog.
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
 * Ambil satu devlog by ID.
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


// TASKS (KANBAN)

/**
 * Ambil semua task dalam sebuah proyek, dikelompokkan untuk Kanban.
 */
function get_tasks_by_project(PDO $pdo, string $project_id): array {
    $stmt = $pdo->prepare('
        SELECT t.*,
               u.nama_lengkap AS assignee_name,
               u.avatar_url   AS assignee_avatar
        FROM tasks t
        LEFT JOIN users u ON t.assignee_id = u.id
        WHERE t.project_id = ?
        ORDER BY
            FIELD(t.prioritas, "Critical","High","Medium","Low"),
            t.deadline ASC
    ');
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

/**
 * Ambil satu task by ID.
 */
function get_task_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare('
        SELECT t.*,
               u.nama_lengkap AS assignee_name
        FROM tasks t
        LEFT JOIN users u ON t.assignee_id = u.id
        WHERE t.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Hitung total jam estimasi tugas yang sudah "Done" pada suatu proyek.
 */
function get_project_done_hours(PDO $pdo, string $project_id): int {
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(estimasi_jam), 0)
        FROM tasks
        WHERE project_id = ? AND status_kolom = "Done"
    ');
    $stmt->execute([$project_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Hitung ringkasan task per status untuk suatu proyek.
 */
function get_task_summary_by_project(PDO $pdo, string $project_id): array {
    $stmt = $pdo->prepare('
        SELECT status_kolom, COUNT(*) AS total
        FROM tasks
        WHERE project_id = ?
        GROUP BY status_kolom
    ');
    $stmt->execute([$project_id]);
    $rows = $stmt->fetchAll();

    $map = ['To Do' => 0, 'In Progress' => 0, 'Review' => 0, 'Done' => 0];
    foreach ($rows as $row) {
        $map[$row['status_kolom']] = (int) $row['total'];
    }
    return $map;
}


// BUG REPORTS

/**
 * Ambil semua bug report dalam sebuah proyek.
 */
function get_bugs_by_project(PDO $pdo, string $project_id): array {
    $stmt = $pdo->prepare('
        SELECT b.*,
               u.nama_lengkap AS reporter_name,
               t.judul        AS task_judul
        FROM bug_reports b
        LEFT JOIN users u ON b.reporter_id = u.id
        LEFT JOIN tasks t ON b.task_id     = t.id
        WHERE b.project_id = ?
        ORDER BY
            FIELD(b.prioritas, "Critical","High","Medium","Low"),
            b.created_at DESC
    ');
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

/**
 * Ambil satu bug report by ID.
 */
function get_bug_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare('
        SELECT b.*,
               u.nama_lengkap AS reporter_name,
               t.judul        AS task_judul,
               p.nama_game    AS project_name
        FROM bug_reports b
        LEFT JOIN users    u ON b.reporter_id = u.id
        LEFT JOIN tasks    t ON b.task_id     = t.id
        LEFT JOIN projects p ON b.project_id  = p.id
        WHERE b.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Hitung ringkasan bug per status untuk suatu proyek.
 */
function get_bug_summary_by_project(PDO $pdo, string $project_id): array {
    $stmt = $pdo->prepare('
        SELECT status, COUNT(*) AS total
        FROM bug_reports
        WHERE project_id = ?
        GROUP BY status
    ');
    $stmt->execute([$project_id]);
    $rows = $stmt->fetchAll();

    $map = ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0, 'Closed' => 0];
    foreach ($rows as $row) {
        $map[$row['status']] = (int) $row['total'];
    }
    return $map;
}


// REPORTS / STATISTIK

/**
 * Ambil data lengkap satu proyek untuk generate laporan.
 */
function get_project_report_data(PDO $pdo, string $project_id): ?array {
    $project = get_project_by_id($pdo, $project_id);
    if (!$project) return null;

    $project['task_summary']    = get_task_summary_by_project($pdo, $project_id);
    $project['bug_summary']     = get_bug_summary_by_project($pdo, $project_id);
    $project['members']         = get_project_members($pdo, $project_id);
    $project['total_tasks']     = array_sum($project['task_summary']);
    $project['done_hours']      = get_project_done_hours($pdo, $project_id);

    $project['completion_pct']  = $project['total_tasks'] > 0
        ? round(($project['task_summary']['Done'] / $project['total_tasks']) * 100)
        : 0;

    return $project;
}
