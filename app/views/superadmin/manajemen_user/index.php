<?php
$users = $conn->query("
    SELECT u.*,
           r.role_name,
           r.label as role_label,
           GROUP_CONCAT(DISTINCT p.description  SEPARATOR ', ') as plant_names,
           GROUP_CONCAT(DISTINCT un.unit_name   SEPARATOR ', ') as unit_names
    FROM users u
    JOIN  roles  r  ON u.role_id  = r.role_id
    LEFT JOIN plants p  ON FIND_IN_SET(p.plant_id,  u.assigned_plants)
    LEFT JOIN units  un ON FIND_IN_SET(un.unit_id,  u.assigned_units)
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Super Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <div class="flex justify-between align-center" style="margin-bottom:25px">
                <h1 class="page-title" style="margin-bottom:0">Manajemen User</h1>
                <a href="?page=superadmin.user-add" class="btn btn-primary">+ Tambah User</a>
            </div>

            <?= flash() ?>

            <div class="card">
                <div class="card-header">
                    <input type="text" class="form-control" placeholder="🔍 Cari user..."
                           onkeyup="searchTable(this,'userTable')" style="max-width:300px">
                    <select class="form-control" onchange="filterByRole(this.value)" style="max-width:200px">
                        <option value="">All Roles</option>
                        <option value="superadmin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table id="userTable">
                        <thead>
                            <tr><th>No</th><th>NIP</th><th>Nama</th><th>Email</th><th>Role</th><th>Plants</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($u = $users->fetch()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($u['nip']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge <?= $u['role_name']=='superadmin' ? 'badge-danger' : ($u['role_name']=='admin' ? 'badge-info' : 'badge-success') ?>">
                                        <?= $u['role_label'] ?>
                                    </span>
                                </td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis">
                                    <?= htmlspecialchars($u['plant_names'] ?? '-') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $u['status']=='active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=superadmin.user-edit&id=<?= $u['user_id'] ?>" class="btn btn-sm btn-secondary" style="margin-right:5px">✏️</a>
                                    <button onclick="confirmDelete('superadmin.user-delete',<?= $u['user_id'] ?>,'Yakin ingin menghapus user ini?')"
                                            class="btn btn-sm btn-danger">🗑️</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function filterByRole(role) {
    const rows = document.querySelectorAll('#userTable tbody tr');
    rows.forEach(row => {
        const cell = row.cells[4].textContent.trim().toLowerCase();
        row.style.display = (!role || cell === role) ? '' : 'none';
    });
}
</script>
</body>
</html>
