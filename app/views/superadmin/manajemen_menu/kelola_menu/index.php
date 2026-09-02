<?php
require_role('superadmin');

// ── Auto-migrate tabel menus (aman, idempotent) ───────────────
try {
    // Pastikan tabel ada
    $conn->exec("CREATE TABLE IF NOT EXISTS menus (
        menu_id    INT AUTO_INCREMENT PRIMARY KEY,
        menu_name  VARCHAR(100) NOT NULL,
        menu_link  VARCHAR(255) NOT NULL DEFAULT '',
        menu_icon  VARCHAR(100) NOT NULL DEFAULT '',
        parent_id  INT DEFAULT 0,
        menu_order INT DEFAULT 0,
        roles      VARCHAR(255) NOT NULL DEFAULT 'all',
        status     ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Pastikan kolom menu_link & menu_icon & roles tidak NOT NULL tanpa default
    $conn->exec("ALTER TABLE menus MODIFY COLUMN menu_link  VARCHAR(255) NOT NULL DEFAULT ''");
    $conn->exec("ALTER TABLE menus MODIFY COLUMN menu_icon  VARCHAR(100) NOT NULL DEFAULT ''");
    $conn->exec("ALTER TABLE menus MODIFY COLUMN roles      VARCHAR(255) NOT NULL DEFAULT 'all'");

    // Fix data lama
    $conn->exec("UPDATE menus SET menu_link = 'coming-soon' WHERE menu_link = 'coming-soon.php'");
} catch (\PDOException $e) {
    // Abaikan error migrasi — tidak memblokir halaman
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM menus WHERE menu_id = ?");
    $stmt->execute([$del_id]);
    if ($stmt->rowCount() > 0) {
        set_flash('Menu berhasil dihapus!', 'success');
    } else {
        set_flash('Gagal menghapus menu.', 'error');
    }
    redirect('superadmin.menus');
}

// Handle toggle status
if (isset($_GET['toggle_id'])) {
    $tid  = intval($_GET['toggle_id']);
    $conn->prepare("UPDATE menus SET status = IF(status='active','inactive','active') WHERE menu_id = ?")->execute([$tid]);
    redirect('superadmin.menus');
}

// Filter
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$sql    = "SELECT * FROM menus WHERE 1=1";
$params = [];
if ($filter === 'active')   { $sql .= " AND status='active'";   }
if ($filter === 'inactive') { $sql .= " AND status='inactive'"; }
if ($search !== '') {
    $sql .= " AND (menu_name LIKE ? OR menu_link LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY menu_order ASC, menu_id ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$menus = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Menu - Super Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .icon-preview {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--hover-bg);
            border-radius: 6px;
            color: var(--accent-cyan);
        }
        .icon-preview i { font-size: 16px; }
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin: 1px;
        }
        .role-superadmin { background: rgba(220,53,69,0.2); color: #ff6b7a; border: 1px solid rgba(220,53,69,0.3); }
        .role-admin      { background: rgba(0,217,255,0.15); color: var(--accent-cyan); border: 1px solid rgba(0,217,255,0.3); }
        .role-user       { background: rgba(40,167,69,0.2); color: #5dd87e; border: 1px solid rgba(40,167,69,0.3); }
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .status-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .dot-active   { background: #5dd87e; }
        .dot-inactive { background: #ff6b7a; }
        .order-cell { font-weight: 700; color: var(--accent-cyan); }
    </style>
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">

            <div class="flex justify-between align-center" style="margin-bottom:25px">
                <h1 class="page-title" style="margin-bottom:0">Manajemen Menu</h1>
                <a href="?page=superadmin.menu-add" class="btn btn-primary">
                    + Tambah Menu
                </a>
            </div>

            <?= flash() ?>

            <div class="card">
                <div class="card-header">
                    <div class="filter-bar">
                        <!-- Filter Dropdown -->
                        <form method="GET" style="display:contents">
                            <input type="hidden" name="page" value="superadmin.menus">
                            <div style="position:relative">
                                <select name="filter" class="form-control" onchange="this.form.submit()"
                                        style="min-width:130px;padding-right:30px">
                                    <option value="" <?= $filter==='' ? 'selected' : '' ?>>Filter ▾</option>
                                    <option value="active"   <?= $filter==='active'   ? 'selected' : '' ?>>Aktif</option>
                                    <option value="inactive" <?= $filter==='inactive' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                        </form>
                        <!-- Search -->
                        <form method="GET" style="display:contents">
                            <input type="hidden" name="page" value="superadmin.menus">
                            <?php if ($filter): ?>
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <?php endif; ?>
                            <input type="text" name="search" class="form-control"
                                   placeholder="Cari......" value="<?= htmlspecialchars($search) ?>"
                                   style="max-width:220px" oninput="this.form.submit()">
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="menuTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Judul Menu</th>
                                <th>Link</th>
                                <th>Role</th>
                                <th>Urutan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($menus)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:var(--text-secondary);padding:40px">
                                    <i class="bi bi-inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;opacity:0.4"></i>
                                    Belum ada menu. Klik "+ Tambah Menu" untuk menambahkan.
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php $no = 1; foreach ($menus as $m): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($m['menu_name']) ?></strong></td>
                                <td style="color:var(--text-secondary);font-family:monospace;font-size:13px">
                                    <?= htmlspecialchars($m['menu_link'] ?? '-') ?>
                                </td>

                                <td>
                                    <?php
                                    $roles = array_filter(array_map('trim', explode(',', $m['roles'] ?? '')));
                                    if (empty($roles) || in_array('all', $roles)):
                                    ?>
                                    <span class="role-badge" style="background:rgba(108,117,125,0.2);color:#aaa;border:1px solid rgba(108,117,125,0.3)">Semua</span>
                                    <?php else: foreach ($roles as $r): ?>
                                    <span class="role-badge role-<?= $r ?>"><?= ucfirst($r) ?></span>
                                    <?php endforeach; endif; ?>
                                </td>
                                <td class="order-cell"><?= intval($m['menu_order']) ?></td>
                                <td>
                                    <a href="?page=superadmin.menus&toggle_id=<?= $m['menu_id'] ?>"
                                       class="badge <?= $m['status']==='active' ? 'badge-success' : 'badge-danger' ?>"
                                       style="cursor:pointer;text-decoration:none"
                                       title="Klik untuk toggle status">
                                        <span class="status-dot <?= $m['status']==='active' ? 'dot-active' : 'dot-inactive' ?>"></span>
                                        <?= $m['status']==='active' ? 'Aktif' : 'Nonaktif' ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="?page=superadmin.menu-edit&id=<?= $m['menu_id'] ?>"
                                       class="btn btn-sm btn-secondary" style="margin-right:4px" title="Edit">
                                        ✏️
                                    </a>
                                    <button onclick="confirmDeleteMenu(<?= $m['menu_id'] ?>, '<?= addslashes(htmlspecialchars($m['menu_name'])) ?>')"
                                            class="btn btn-sm btn-danger" title="Hapus">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;display:none;align-items:center;justify-content:center">
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center">
        <div style="font-size:48px;margin-bottom:16px">🗑️</div>
        <h3 style="margin-bottom:8px">Hapus Menu?</h3>
        <p id="deleteModalText" style="color:var(--text-secondary);margin-bottom:24px"></p>
        <div style="display:flex;gap:12px;justify-content:center">
            <a id="deleteConfirmBtn" href="#" class="btn btn-danger">Ya, Hapus</a>
            <button onclick="document.getElementById('deleteModal').style.display='none'" class="btn btn-secondary">Batal</button>
        </div>
    </div>
</div>

<script>
function confirmDeleteMenu(id, name) {
    document.getElementById('deleteModalText').textContent = 'Yakin ingin menghapus menu "' + name + '"?';
    document.getElementById('deleteConfirmBtn').href = '?page=superadmin.menus&delete_id=' + id;
    document.getElementById('deleteModal').style.display = 'flex';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
<script>
</script>
</body>
</html>
