<?php
$unit_id = intval($_GET['id'] ?? $_POST['unit_id'] ?? 0);
if (!$unit_id) { redirect('superadmin.units'); }

$stmt = $conn->prepare("SELECT * FROM units WHERE unit_id = ?");
$stmt->execute([$unit_id]);
$unit = $stmt->fetch();
if (!$unit) { redirect('superadmin.units'); }

$plants_list = $conn->query("SELECT plant_id, description FROM plants ORDER BY description")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_name  = trim($_POST['unit_name'] ?? '');
    $plant_id   = intval($_POST['plant_id'] ?? 0);
    $status     = isset($_POST['status']) ? 1 : 0;
    $tab_manual = intval($_POST['tab_manual_aktif'] ?? 0);
    $database_name = trim($_POST['database_name'] ?? '');
    $excel_file = trim($_POST['excel_file'] ?? '');

    if (!$unit_name || !$plant_id) {
        $error = 'Nama unit dan plant harus diisi!';
    } else {
        $stmt = $conn->prepare("UPDATE units SET unit_name=?,plant_id=?,status=?,tab_manual_aktif=?,database_name=?,excel_file=? WHERE unit_id=?");
        $stmt->execute([$unit_name, $plant_id, $status, $tab_manual, $database_name ?: null, $excel_file ?: null, $unit_id]);
        set_flash('Unit berhasil diupdate!', 'success');
        redirect('superadmin.units');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Unit - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <h1 class="page-title">Edit Unit</h1>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="card">
                <form method="POST" action="<?= BASE_URL ?>">
                    <input type="hidden" name="page"    value="superadmin.unit-edit">
                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                    <div class="form-group"><label>Nama Unit *</label><input type="text" name="unit_name" class="form-control" value="<?= htmlspecialchars($unit['unit_name']) ?>" required></div>
                    <div class="form-group">
                        <label>Plant *</label>
                        <select name="plant_id" class="form-control" required>
                            <option value="">Pilih Plant</option>
                            <?php foreach ($plants_list as $p): ?>
                            <option value="<?= $p['plant_id'] ?>" <?= $p['plant_id']==$unit['plant_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['description']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Tab Manual Aktif</label><input type="number" name="tab_manual_aktif" class="form-control" value="<?= $unit['tab_manual_aktif'] ?>" min="0"></div>
                    <div class="form-group"><label>Database Name</label><input type="text" name="database_name" class="form-control" value="<?= htmlspecialchars($unit['database_name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Excel File</label><input type="text" name="excel_file" class="form-control" value="<?= htmlspecialchars($unit['excel_file'] ?? '') ?>"></div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                            <input type="checkbox" name="status" <?= $unit['status']==1 ? 'checked' : '' ?>>
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary">💾 Update</button>
                        <a href="?page=superadmin.units" class="btn btn-secondary">← Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
