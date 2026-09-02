<?php
$page_title = htmlspecialchars($_GET['title'] ?? 'Fitur');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - PLN Dashboard</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
    <div class="layout">
        <?php include VIEWS . 'shared/sidebar.php'; ?>
        <div class="main-content">
            <?php include VIEWS . 'shared/header.php'; ?>
            <div class="content">
                <div class="coming-soon">
                    <div class="coming-soon-icon">🚧</div>
                    <h2><?= $page_title ?></h2>
                    <p>Fitur ini masih dalam tahap pengembangan</p>
                    <p style="margin-top:20px">
                        <a href="?page=<?= $_SESSION['role'] ?>.dashboard" class="btn btn-primary">
                            ← Kembali ke Dashboard
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
