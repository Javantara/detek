<?php require_login(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>404 - Halaman Tidak Ditemukan</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;gap:16px">
            <h1 style="font-size:80px;margin:0;color:var(--accent-cyan)">404</h1>
            <p style="color:var(--text-secondary)">Halaman tidak ditemukan.</p>
            <a href="<?= BASE_URL ?>" class="btn btn-primary">← Kembali ke Home</a>
        </div>
    </div>
</div>
</body>
</html>
