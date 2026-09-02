<?php
$plants = $conn->query("SELECT * FROM plants ORDER BY plant_id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Plant - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <div class="card-header" style="margin-bottom:25px">
                <h1 class="page-title">Kelola Plant</h1>
                <a href="?page=superadmin.plant-add" class="btn btn-primary">➕ Tambah Plant</a>
            </div>

            <?= flash() ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Plant</h3>
                    <input type="text" class="form-control" style="max-width:300px"
                           placeholder="🔍 Cari plant..." onkeyup="searchTable(this,'plantTable')">
                </div>
                <div class="table-responsive">
                    <table id="plantTable">
                        <thead><tr><th>ID</th><th>Nama Plant</th><th>Status</th><th style="text-align:center">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($plants as $p): ?>
                            <tr>
                                <td><?= $p['plant_id'] ?></td>
                                <td><?= htmlspecialchars($p['description']) ?></td>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" <?= $p['status']==1 ? 'checked' : '' ?>
                                               onchange="toggleStatus('plant',<?= $p['plant_id'] ?>,this)">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="badge <?= $p['status']==1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $p['status']==1 ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td style="text-align:center">
                                    <a href="?page=superadmin.plant-edit&id=<?= $p['plant_id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <button onclick="confirmDelete('superadmin.plant-delete',<?= $p['plant_id'] ?>,'Yakin ingin menghapus plant ini?')"
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
