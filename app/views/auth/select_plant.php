<?php
$user_id = intval($_SESSION['user_id'] ?? 0);
$role    = $_SESSION['role'] ?? 'user';

$user_theme = 'dark';
$th = $conn->prepare("SELECT theme, all_access, assigned_plants, assigned_units FROM users WHERE user_id = ?");
$th->execute([$user_id]);
$user_data = $th->fetch(PDO::FETCH_ASSOC) ?: [];
$user_theme = $user_data['theme'] ?? 'dark';
$all_access = !empty($user_data['all_access']) || $role === 'superadmin';

function csv_ids($raw): array {
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$raw)))));
}

$assigned_plants = [];
$assigned_units  = [];

if ($all_access) {
    $assigned_plants = $conn->query("SELECT plant_id FROM plants WHERE status = 1 OR status = 'active' ORDER BY description")->fetchAll(PDO::FETCH_COLUMN);
    $assigned_units  = $conn->query("SELECT unit_id FROM units WHERE status = 1 OR status = 'active' ORDER BY unit_name")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $assigned_plants = csv_ids($user_data['assigned_plants'] ?? '');
    $assigned_units  = csv_ids($user_data['assigned_units'] ?? '');

    // Fix utama: kalau assigned_plants kosong/kurang lengkap tapi assigned_units ada,
    // ambil plant dari unit yang diassign. Ini mencegah kasus "Tidak ada unit untuk plant ini" padahal unit ada.
    if (!empty($assigned_units)) {
        $ph = implode(',', array_fill(0, count($assigned_units), '?'));
        $st = $conn->prepare("SELECT DISTINCT plant_id FROM units WHERE unit_id IN ($ph)");
        $st->execute($assigned_units);
        $from_units = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $assigned_plants = array_values(array_unique(array_merge($assigned_plants, $from_units)));
    }
}

if (empty($assigned_plants)) {
    die('<div style="font-family:sans-serif;padding:40px"><h2>Belum ada akses plant</h2><p>Anda belum di-assign ke plant manapun. Hubungi Super Admin.</p><a href="?page=logout">← Logout</a></div>');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plant_id = intval($_POST['plant_id'] ?? 0);
    $unit_id  = intval($_POST['unit_id']  ?? 0);

    if ($plant_id > 0 && $unit_id > 0) {
        $allowed = false;
        if ($all_access) {
            $allowed = true;
        } else {
            $allowed = in_array($plant_id, array_map('intval', $assigned_plants), true)
                       && in_array($unit_id, array_map('intval', $assigned_units), true);
        }

        // Pastikan unit benar-benar milik plant yang dipilih.
        if ($allowed) {
            $ck = $conn->prepare("SELECT COUNT(*) FROM units WHERE unit_id=? AND plant_id=? AND (status=1 OR status='active')");
            $ck->execute([$unit_id, $plant_id]);
            $allowed = ((int)$ck->fetchColumn() > 0);
        }

        if ($allowed) {
            $_SESSION['selected_plant_id'] = $plant_id;
            $_SESSION['selected_unit_id']  = $unit_id;
            log_activity($user_id, 'login', $plant_id, $unit_id);
            $dest = $_SESSION['role'] === 'admin' ? 'admin.dashboard' : 'user.dashboard';
            redirect($dest);
        } else {
            $error = 'Anda tidak memiliki akses ke plant/unit ini!';
        }
    } else {
        $error = 'Pilih Plant dan Unit terlebih dahulu!';
    }
}

$plant_ids_str = implode(',', array_map('intval', $assigned_plants));
$plants = $conn->query("SELECT * FROM plants WHERE plant_id IN ($plant_ids_str) AND (status = 1 OR status = 'active') ORDER BY description");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Plant - PLN Dashboard</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body class="<?= $user_theme === 'light' ? 'light-theme' : '' ?>">
<script>
    const _saved = localStorage.getItem('theme') || '<?= $user_theme ?>';
    if (_saved === 'light') document.body.classList.add('light-theme');
</script>
<div class="login-theme-toggle">
    <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">
        <i class="bi bi-<?= $user_theme === 'light' ? 'sun-fill' : 'moon-stars-fill' ?>" id="themeIcon"></i>
    </button>
</div>

<div class="login-container">
    <div class="login-card" style="max-width:550px">
        <div class="logo-section" style="text-align:center;margin-bottom:30px">
            <div class="logo" style="justify-content:center">
                <img src="<?= BASE_URL ?>assets/img/logo-pln.png" alt="PLN" style="width:50px">
                <span class="logo-text">PLN</span>
            </div>
            <h2 style="font-size:22px;margin-top:15px;margin-bottom:6px">Pilih Lokasi Kerja</h2>
            <p style="color:var(--text-secondary);font-size:13px">Halo, <?= htmlspecialchars($_SESSION['full_name'] ?? '-') ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>?page=select-plant" id="plantForm">
            <div class="form-group">
                <label class="form-label">Plant *</label>
                <select name="plant_id" id="plantSelect" class="form-control" required onchange="loadUnits()">
                    <option value="">-- Pilih Plant --</option>
                    <?php while ($pl = $plants->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?= (int)$pl['plant_id'] ?>"><?= htmlspecialchars($pl['description']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Unit *</label>
                <select name="unit_id" id="unitSelect" class="form-control" required disabled>
                    <option value="">-- Pilih Plant dulu --</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="submitBtn" style="width:100%;margin-top:25px" disabled>Lanjutkan</button>
        </form>

        <div style="text-align:center;margin-top:20px">
            <a href="<?= BASE_URL ?>?page=logout" style="color:var(--text-secondary);text-decoration:none;font-size:13px">← Logout</a>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
window.history.replaceState(null, '', window.location.pathname);

(function() {
    const saved = localStorage.getItem('theme') || '<?= $user_theme ?>';
    if (saved === 'light') {
        document.body.classList.add('light-theme');
        const icon = document.getElementById('themeIcon');
        if (icon) icon.className = 'bi bi-sun-fill';
    }
})();

function toggleTheme() {
    const isLight = document.body.classList.toggle('light-theme');
    document.getElementById('themeIcon').className = isLight ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
    fetch(BASE_URL + '?api=save-theme', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'theme=' + (isLight ? 'light' : 'dark')
    });
}

function loadUnits() {
    const plantId = document.getElementById('plantSelect').value;
    const unitSel = document.getElementById('unitSelect');
    const submitBtn = document.getElementById('submitBtn');
    unitSel.disabled = true;
    submitBtn.disabled = true;
    if (!plantId) {
        unitSel.innerHTML = '<option value="">-- Pilih Plant dulu --</option>';
        return;
    }
    unitSel.innerHTML = '<option value="">Memuat unit...</option>';
    fetch(BASE_URL + '?api=get-units&plant_id=' + encodeURIComponent(plantId), {cache:'no-store'})
        .then(r => r.json())
        .then(data => {
            unitSel.innerHTML = '';
            if (data.success && data.units && data.units.length) {
                unitSel.appendChild(new Option('-- Pilih Unit --', ''));
                data.units.forEach(unit => {
                    const label = unit.database_name
                        ? `${unit.unit_name} (${unit.database_name})`
                        : unit.unit_name;
                    unitSel.appendChild(new Option(label, unit.unit_id));
                });
                unitSel.disabled = false;
            } else {
                unitSel.appendChild(new Option('Tidak ada unit untuk plant ini', ''));
            }
        })
        .catch(() => {
            unitSel.innerHTML = '<option value="">Error memuat unit</option>';
        });
}

document.getElementById('unitSelect').addEventListener('change', function() {
    document.getElementById('submitBtn').disabled = !this.value;
});
</script>
</body>
</html>
