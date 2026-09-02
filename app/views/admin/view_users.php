<?php
$users = $conn->query("
    SELECT u.*, r.role_name,
           GROUP_CONCAT(DISTINCT p.description SEPARATOR ', ') as plant_names
    FROM users u
    JOIN  roles  r ON u.role_id = r.role_id
    LEFT JOIN plants p ON FIND_IN_SET(p.plant_id, u.assigned_plants)
    GROUP BY u.user_id ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data User - Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <h1 class="page-title">Data User (View Only)</h1>
            <div class="alert alert-info" style="margin-bottom:20px">
                ℹ️ Anda hanya dapat melihat data user. Untuk menambah/edit/hapus, hubungi Super Admin.
            </div>
            <div class="card">
                <div class="card-header">
                    <input type="text" class="form-control" placeholder="🔍 Cari user..."
                           onkeyup="searchTable(this,'userTable')" style="max-width:300px">
                </div>
                <div class="table-responsive">
                    <table id="userTable">
                        <thead>
                            <tr><th>No</th><th>NIP</th><th>Nama</th><th>Email</th><th>Role</th><th>Plants</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php $no=1; while ($u = $users->fetch()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($u['nip']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge <?= $u['role_name']=='superadmin'?'badge-danger':($u['role_name']=='admin'?'badge-info':'badge-success') ?>"><?= ucfirst($u['role_name']) ?></span></td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['plant_names'] ?? '-') ?></td>
                                <td><span class="badge <?= $u['status']=='active'?'badge-success':'badge-danger' ?>"><?= ucfirst($u['status']) ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>assets/js/main.js?v=20260224"></script>
</body>
</html>
