<?php
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $status      = isset($_POST['status']) ? 1 : 0;

    if (!$description) {
        $error = 'Nama plant tidak boleh kosong!';
    } else {
        $stmt = $conn->prepare("INSERT INTO plants (description, status) VALUES (?, ?)");
        $stmt->execute([$description, $status]);
        set_flash('Plant berhasil ditambahkan!', 'success');
        redirect('superadmin.plants');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Plant - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <h1 class="page-title">Tambah Plant</h1>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="card">
                <form method="POST" action="<?= BASE_URL ?>">
                    <input type="hidden" name="page" value="superadmin.plant-add">
                    <div class="form-group">
                        <label>Nama Plant *</label>
                        <input type="text" name="description" class="form-control" placeholder="Masukkan nama plant" required>
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                            <input type="checkbox" name="status" checked>
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary">💾 Simpan</button>
                        <a href="?page=superadmin.plants" class="btn btn-secondary">← Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
