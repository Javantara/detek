<?php
$plant_id = $_SESSION['selected_plant_id'];
$unit_id  = $_SESSION['selected_unit_id'];

$plant        = $conn->prepare("SELECT * FROM plants WHERE plant_id = ?");
$plant->execute([$plant_id]); $plant = $plant->fetch();

$unit         = $conn->prepare("SELECT * FROM units WHERE unit_id = ?");
$unit->execute([$unit_id]); $unit = $unit->fetch();

$total_units  = $conn->query("SELECT COUNT(*) FROM units WHERE plant_id = $plant_id")->fetchColumn();
$active_units = $conn->query("SELECT COUNT(*) FROM units WHERE plant_id = $plant_id AND status = 1")->fetchColumn();

// Sapaan berdasarkan jam
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
    <title>Dashboard - Admin</title>
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
                <h1 class="page-title" style="margin-bottom:4px"><?= $sapa ?>, <?= htmlspecialchars($_SESSION['full_name']) ?> 👋</h1>
                <p style="color:var(--text-secondary);font-size:14px">
                    <?= $tgl_indo ?> &nbsp;·&nbsp; PLN Performance Test System
                </p>
            </div>
            <?= flash() ?>

            <!-- Info Plant -->
            <div class="card" style="margin-bottom:20px">
                <h2 class="card-title" style="margin-bottom:16px">Informasi Plant</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
                    <div>
                        <p style="color:var(--text-secondary);font-size:12px;margin-bottom:4px">Plant Name</p>
                        <p style="font-size:17px;font-weight:600"><?= htmlspecialchars($plant['description']) ?></p>
                    </div>
                    <div>
                        <p style="color:var(--text-secondary);font-size:12px;margin-bottom:4px">Status</p>
                        <span class="badge <?= $plant['status']==1 ? 'badge-success' : 'badge-danger' ?>">
                            <?= $plant['status']==1 ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </div>
                    <div>
                        <p style="color:var(--text-secondary);font-size:12px;margin-bottom:4px">Current Unit</p>
                        <p style="font-size:17px;font-weight:600"><?= htmlspecialchars($unit['unit_name']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="stats-grid" style="margin-bottom:20px">
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div class="stat-info"><h3>Total Units</h3><p><?= $total_units ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="stat-info"><h3>Active Units</h3><p><?= $active_units ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="stat-info"><h3>Performance Test</h3><p><span class="badge badge-warning">Coming Soon</span></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-activity"></i></div>
                    <div class="stat-info"><h3>Trending Chart</h3><p><span class="badge badge-warning">Coming Soon</span></p></div>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
