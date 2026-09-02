<?php
$all_plants = $conn->query("SELECT COUNT(*) FROM plants WHERE status = 1")->fetchColumn();
$all_units  = $conn->query("SELECT COUNT(*) FROM units  WHERE status = 1")->fetchColumn();

// Untuk user all_access, tampilkan info Kantor Pusat
$user_info = $conn->prepare("SELECT all_access FROM users WHERE user_id = ?");
$user_info->execute([$_SESSION['user_id']]);
$is_all_access = !empty($user_info->fetchColumn());

$nama = $_SESSION['full_name'];
$jam  = (int)date('H');
if ($jam >= 5  && $jam < 12) $sapa = 'Selamat Pagi';
elseif ($jam >= 12 && $jam < 15) $sapa = 'Selamat Siang';
elseif ($jam >= 15 && $jam < 18) $sapa = 'Selamat Sore';
else $sapa = 'Selamat Malam';

$hari_id  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tgl_indo = $hari_id[date('w')] . ', ' . date('d') . ' ' . $bulan_id[(int)date('n')-1] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - User</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">

            <!-- Greeting -->
            <div style="margin-bottom:28px">
                <h1 class="page-title" style="margin-bottom:4px"><?= $sapa ?>, <?= htmlspecialchars($nama) ?> 👋</h1>
                <p style="color:var(--text-secondary);font-size:14px">
                    <?= $tgl_indo ?> &nbsp;·&nbsp; PLN Performance Test System
                </p>
            </div>

            <!-- Stat Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-building"></i></div>
                    <div class="stat-info"><h3>Total Plants</h3><p><?= $all_plants ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div class="stat-info"><h3>Total Units</h3><p><?= $all_units ?></p></div>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
