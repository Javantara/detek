<?php
// Pagination - gunakan 'pnum' bukan 'page' agar tidak konflik dengan router
$pnum       = max(1, intval($_GET['pnum'] ?? 1));
$per_page   = 20;
$offset     = ($pnum - 1) * $per_page;

// Filter
$f_user   = trim($_GET['user']   ?? '');
$f_action = trim($_GET['action'] ?? '');
$f_date   = trim($_GET['date']   ?? '');

// Build WHERE
$where  = []; $params = [];
if ($f_user) {
    $where[] = "(ua.nip LIKE ? OR ua.full_name LIKE ? OR ua.email LIKE ?)";
    $s = "%$f_user%"; $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($f_action) { $where[] = "ua.action = ?"; $params[] = $f_action; }
if ($f_date)   { $where[] = "DATE(ua.created_at) = ?"; $params[] = $f_date; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$cq = "SELECT COUNT(*) as total FROM user_activity ua $wsql";
if ($params) {
    $st = $conn->prepare($cq); $st->execute($params);
    $total = $st->fetch()['total'];
} else {
    $total = $conn->query($cq)->fetch()['total'];
}
$total_pages = ceil($total / $per_page);

// Data
$q = "SELECT ua.*, p.description as plant_name, u.unit_name
      FROM user_activity ua
      LEFT JOIN plants p ON ua.plant_id = p.plant_id
      LEFT JOIN units  u ON ua.unit_id  = u.unit_id
      $wsql ORDER BY ua.created_at DESC LIMIT $per_page OFFSET $offset";
if ($params) {
    $st = $conn->prepare($q); $st->execute($params);
    $activities = $st;
} else {
    $activities = $conn->query($q);
}

// Helper untuk build pagination URL
function pageUrl(int $p, string $user, string $action, string $date): string {
    return '?page=superadmin.user-history&pnum=' . $p
         . ($user   ? '&user='   . urlencode($user)   : '')
         . ($action ? '&action=' . urlencode($action)  : '')
         . ($date   ? '&date='   . urlencode($date)    : '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat User - Super Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <h1 class="page-title">Riwayat User Activity</h1>

            <!-- Filter form — GET agar bisa di-bookmark, JS akan bersihkan URL -->
            <div class="card">
                <form method="GET" action="<?= BASE_URL ?>">
                    <input type="hidden" name="page" value="superadmin.user-history">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px">
                        <div class="form-group" style="margin-bottom:0">
                            <label>User (NIP/Nama/Email)</label>
                            <input type="text" name="user" class="form-control" placeholder="Cari user..." value="<?= htmlspecialchars($f_user) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label>Action</label>
                            <select name="action" class="form-control">
                                <option value="">All Actions</option>
                                <option value="login"  <?= $f_action=='login'  ? 'selected':'' ?>>Login</option>
                                <option value="logout" <?= $f_action=='logout' ? 'selected':'' ?>>Logout</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($f_date) ?>">
                        </div>
                        <div style="display:flex;align-items:flex-end;gap:10px">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?page=superadmin.user-history" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><span>Total: <?= $total ?> records</span></div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>No</th><th>Waktu</th><th>NIP</th><th>Nama</th><th>Email</th><th>Action</th><th>Plant</th><th>Unit</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $offset + 1;
                            while ($a = $activities->fetch()):
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($a['created_at'])) ?></td>
                                <td><?= htmlspecialchars($a['nip']) ?></td>
                                <td><?= htmlspecialchars($a['full_name']) ?></td>
                                <td><?= htmlspecialchars($a['email']) ?></td>
                                <td><span class="badge <?= $a['action']=='login' ? 'badge-success' : 'badge-info' ?>"><?= ucfirst($a['action']) ?></span></td>
                                <td><?= htmlspecialchars($a['plant_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['unit_name']  ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['ip_address']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($total == 0): ?>
                                <tr><td colspan="9" style="text-align:center;padding:40px">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div style="margin-top:20px;display:flex;justify-content:center;gap:10px">
                    <?php if ($pnum > 1): ?>
                        <a href="<?= pageUrl($pnum-1,$f_user,$f_action,$f_date) ?>" class="btn btn-sm btn-secondary">← Prev</a>
                    <?php endif; ?>
                    <span style="padding:10px;color:var(--text-secondary)">Page <?= $pnum ?> of <?= $total_pages ?></span>
                    <?php if ($pnum < $total_pages): ?>
                        <a href="<?= pageUrl($pnum+1,$f_user,$f_action,$f_date) ?>" class="btn btn-sm btn-secondary">Next →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
