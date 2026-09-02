<?php
$units = $conn->query("
    SELECT u.*, p.description as plant_name
    FROM units u
    LEFT JOIN plants p ON u.plant_id = p.plant_id
    ORDER BY u.unit_id ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Unit - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <div class="card-header" style="margin-bottom:25px">
                <h1 class="page-title">Kelola Unit</h1>
                <a href="?page=superadmin.unit-add" class="btn btn-primary">➕ Tambah Unit</a>
            </div>

            <?= flash() ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Unit</h3>
                    <input type="text" class="form-control" style="max-width:300px"
                           placeholder="🔍 Cari unit..." onkeyup="searchTable(this,'unitTable')">
                </div>
                <div class="table-responsive">
                    <table id="unitTable">
                        <thead><tr><th>ID</th><th>Nama Unit</th><th>Plant</th><th>Tab Manual</th><th>Database</th><th>Excel File</th><th>Status</th><th style="text-align:center">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($units as $u): ?>
                            <tr>
                                <td><?= $u['unit_id'] ?></td>
                                <td><?= htmlspecialchars($u['unit_name']) ?></td>
                                <td><?= htmlspecialchars($u['plant_name']) ?></td>
                                <td><?= $u['tab_manual_aktif'] ?></td>
                                <td><?= htmlspecialchars($u['database_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($u['excel_file'] ?? '-') ?></td>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" <?= $u['status']==1 ? 'checked' : '' ?>
                                               onchange="toggleStatus('unit',<?= $u['unit_id'] ?>,this)">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="badge <?= $u['status']==1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $u['status']==1 ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td style="text-align:center">
                                    <a href="?page=superadmin.unit-edit&id=<?= $u['unit_id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <button onclick="confirmDelete('superadmin.unit-delete',<?= $u['unit_id'] ?>,'Yakin ingin menghapus unit ini?')"
                                            class="btn btn-danger btn-sm">🗑️ Hapus</button>
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
</body>
</html>
