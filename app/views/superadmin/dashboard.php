<?php
$total_plants   = $conn->query("SELECT COUNT(*) FROM plants WHERE status = 1")->fetchColumn();
$total_units    = $conn->query("SELECT COUNT(*) FROM units  WHERE status = 1")->fetchColumn();
$total_users    = $conn->query("SELECT COUNT(*) FROM users  WHERE status = 'active'")->fetchColumn();
$total_activity = $conn->query("SELECT COUNT(*) FROM user_activity WHERE DATE(created_at) = CURDATE()")->fetchColumn();

$recent_activities = $conn->query("
    SELECT ua.*, p.description as plant_name, un.unit_name
    FROM user_activity ua
    LEFT JOIN plants p  ON ua.plant_id = p.plant_id
    LEFT JOIN units un  ON ua.unit_id  = un.unit_id
    ORDER BY ua.created_at DESC LIMIT 10
");

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
    <title>Dashboard - Super Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <div style="margin-bottom:28px">
                <h1 class="page-title" style="margin-bottom:4px"><?= $sapa ?>, <?= htmlspecialchars($_SESSION['full_name']) ?> 👋</h1>
                <p style="color:var(--text-secondary);font-size:14px">
                    <?= $tgl_indo ?> &nbsp;·&nbsp; PLN Performance Test System
                </p>
            </div>
            <?= flash() ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-building"></i></div>
                    <div class="stat-info"><h3>Total Plants</h3><p><?= $total_plants ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div class="stat-info"><h3>Total Units</h3><p><?= $total_units ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-info"><h3>Total Users</h3><p><?= $total_users ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bi"><i class="bi bi-bar-chart-fill"></i></div>
                    <div class="stat-info"><h3>Activity Today</h3><p><?= $total_activity ?></p></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Activity</h2>
                    <a href="?page=superadmin.user-history" class="btn btn-sm btn-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Waktu</th><th>User</th><th>Action</th><th>Plant</th><th>Unit</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($a = $recent_activities->fetch()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                                <td><?= htmlspecialchars($a['full_name']) ?></td>
                                <td><span class="badge <?= $a['action']=='login'?'badge-success':'badge-info' ?>"><?= ucfirst($a['action']) ?></span></td>
                                <td><?= htmlspecialchars($a['plant_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['unit_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['ip_address']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
