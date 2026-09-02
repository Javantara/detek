<?php
$role = $_SESSION['role'] ?? 'user';
$cur  = $_GET['page'] ?? '';

function isActive(string $page): string {
    global $cur;
    return ($cur === $page) ? ' active' : '';
}

function isOpen(string ...$pages): string {
    global $cur;
    foreach ($pages as $p) {
        if ($cur === $p || str_starts_with($cur, $p)) return ' open';
    }
    return '';
}

// ── Ambil menu dinamis dari database ─────────────────────────
global $conn;
$dynamic_menus = [];

try {
    $all_menus = $conn->query(
        "SELECT menu_id, menu_name, menu_link, roles, status
         FROM menus
         WHERE status = 'active'
         ORDER BY menu_order ASC, menu_id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_menus as $m) {
        $roles_raw = trim($m['roles'] ?? 'all');

        // Kosong atau 'all' → semua role bisa lihat
        if ($roles_raw === '' || $roles_raw === 'all') {
            $dynamic_menus[] = $m;
            continue;
        }

        // Cek apakah role user ada di list roles menu
        $roles_list = array_map('trim', explode(',', strtolower($roles_raw)));
        if (in_array('all', $roles_list) || in_array(strtolower($role), $roles_list)) {
            $dynamic_menus[] = $m;
        }
    }
} catch (PDOException $e) {
    // DB belum siap - sidebar tetap jalan tanpa menu dinamis
}

// Bersihkan menu lama/debug supaya sidebar tidak bikin bingung.
// Yang ditampilkan di Menu Fitur cukup fitur yang dipakai: Deteksi Anomali + Upload CSV Baru.
$dynamic_menus = array_values(array_filter($dynamic_menus, function($m) {
    $name = strtolower(trim((string)($m['menu_name'] ?? '')));
    $link = strtolower(trim((string)($m['menu_link'] ?? '')));
    if ($link === 'bearing-anomali' || str_contains($name, 'deteksi anomali')) return true;
    return false;
}));
?>
<div class="sidebar">
    <div class="logo-section">
        <div class="logo">
            <img src="<?= BASE_URL ?>assets/img/logo-pln.png" alt="PLN">
            <span class="logo-text">PLN</span>
        </div>
    </div>

    <div class="menu">

        <!-- Dashboard -->
        <a href="?page=<?= $role ?>.dashboard"
           class="menu-item<?= isActive($role . '.dashboard') ?>">
            <i class="bi bi-house"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'superadmin'): ?>

        <!-- Plant & Unit -->
        <div class="menu-item has-submenu<?= isOpen('superadmin.plants','superadmin.plant','superadmin.units','superadmin.unit') ?>"
             onclick="toggleSubmenu(this)">
            <i class="bi bi-box"></i>
            <span>Plant &amp; Unit</span>
        </div>
        <div class="submenu<?= str_contains(isOpen('superadmin.plants','superadmin.plant','superadmin.units','superadmin.unit'), 'open') ? ' show' : '' ?>">
            <a href="?page=superadmin.plants" class="menu-item<?= isActive('superadmin.plants') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Kelola Plant</span>
            </a>
            <a href="?page=superadmin.units" class="menu-item<?= isActive('superadmin.units') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Kelola Unit</span>
            </a>
        </div>

        <!-- Manajemen User -->
        <div class="menu-item has-submenu<?= isOpen('superadmin.users','superadmin.user','superadmin.permintaan-password') ?>"
             onclick="toggleSubmenu(this)">
            <i class="bi bi-people"></i>
            <span>Manajemen User</span>
        </div>
        <div class="submenu<?= str_contains(isOpen('superadmin.users','superadmin.user','superadmin.permintaan-password'), 'open') ? ' show' : '' ?>">
            <a href="?page=superadmin.users" class="menu-item<?= isActive('superadmin.users') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Data User</span>
            </a>
            <a href="?page=superadmin.user-history" class="menu-item<?= isActive('superadmin.user-history') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Riwayat User</span>
            </a>
            <a href="?page=superadmin.permintaan-password" class="menu-item<?= isActive('superadmin.permintaan-password') ?>">
                <i class="bi bi-arrow-return-right"></i>
                <span style="display:flex;align-items:center;gap:6px">
                    Permintaan Password
                    <?php
                    try {
                        $n = (int)$conn->query("SELECT COUNT(*) FROM password_requests WHERE status='pending'")->fetchColumn();
                        if ($n > 0) echo '<span style="background:#ff6b7a;color:white;border-radius:50%;min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;padding:0 4px">' . $n . '</span>';
                    } catch (PDOException $e) {}
                    ?>
                </span>
            </a>
        </div>

        <!-- Manajemen -->
        <?php
        $mgmt_open = in_array($cur, ['superadmin.menus','superadmin.menu-add','superadmin.menu-edit']);
        ?>
        <div class="menu-item has-submenu<?= $mgmt_open ? ' open' : '' ?>"
             onclick="toggleSubmenu(this)">
            <i class="bi bi-list"></i>
            <span>Manajemen</span>
        </div>
        <div class="submenu<?= $mgmt_open ? ' show' : '' ?>">
            <a href="?page=superadmin.menus" class="menu-item<?= isActive('superadmin.menus') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Manajemen Menu</span>
            </a>

        </div>

        <?php elseif ($role === 'admin'): ?>

        <!-- Manajemen User (admin) -->
        <div class="menu-item has-submenu<?= isOpen('admin.users') ?>"
             onclick="toggleSubmenu(this)">
            <i class="bi bi-people"></i>
            <span>Manajemen User</span>
        </div>
        <div class="submenu<?= str_contains(isOpen('admin.users'), 'open') ? ' show' : '' ?>">
            <a href="?page=admin.users" class="menu-item<?= isActive('admin.users') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Data User</span>
            </a>
        </div>

        <?php endif; ?>

        <!-- ── Menu Fitur (dari database) ──────────────────── -->
        <?php if (!empty($dynamic_menus)): ?>
        <?php
        // Cek apakah ada menu dinamis yang aktif sekarang
        $fitur_open = ($cur === 'upload-csv-baru');
        foreach ($dynamic_menus as $dm) {
            if ($dm['menu_link'] !== '' && $cur === $dm['menu_link']) {
                $fitur_open = true;
                break;
            }
        }
        ?>
        <div class="menu-item has-submenu<?= $fitur_open ? ' open' : '' ?>"
             onclick="toggleSubmenu(this)">
            <i class="bi bi-layers"></i>
            <span>Menu Fitur</span>
        </div>
        <div class="submenu<?= $fitur_open ? ' show' : '' ?>">
            <?php foreach ($dynamic_menus as $dm):
                $slug    = trim($dm['menu_link'] ?? '');
                $href    = ($slug !== '')
                           ? ('?page=' . htmlspecialchars($slug))
                           : ('?page=coming-soon&title=' . urlencode($dm['menu_name']));
                $active  = ($slug !== '' && $cur === $slug) ? ' active' : '';
            ?>
            <a href="<?= $href ?>" class="menu-item<?= $active ?>">
                <i class="bi bi-arrow-return-right"></i>
                <span><?= htmlspecialchars($dm['menu_name']) ?></span>
            </a>
            <?php endforeach; ?>
            <?php if (in_array($role, ['superadmin','admin'], true)): ?>
            <a href="?page=upload-csv-baru" class="menu-item<?= isActive('upload-csv-baru') ?>">
                <i class="bi bi-arrow-return-right"></i>
                <span>Upload CSV Baru</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Pengaturan -->
        <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
            <i class="bi bi-gear"></i>
            <span>Pengaturan</span>
        </div>
        <div class="submenu">
            <?php if ($role !== 'superadmin'): ?>
            <a href="?page=select-plant" class="menu-item">
                <i class="bi bi-arrow-return-right"></i><span>Ganti Plant/Unit</span>
            </a>
            <?php endif; ?>
            <a href="?page=ganti-password" class="menu-item<?= isActive('ganti-password') ?>">
                <i class="bi bi-arrow-return-right"></i><span>Ganti Password</span>
            </a>
            <a href="?page=logout" class="menu-item">
                <i class="bi bi-arrow-return-right"></i><span>Logout</span>
            </a>
        </div>

    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<script src="<?= BASE_URL ?>assets/js/main.js?v=20260224"></script>
<script>
</script>
