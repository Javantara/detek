<?php
// Ambil tema user dari DB
$user_theme = 'dark';
$stmt = $conn->prepare("SELECT theme FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tr = $stmt->fetch();
if ($tr) $user_theme = $tr['theme'] ?? 'dark';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<script>
// Terapkan tema SEGERA dari localStorage (cegah flash)
(function(){
    var saved = localStorage.getItem('theme');
    var dbTheme = '<?= $user_theme ?>';
    var theme = saved || dbTheme;
    if (theme === 'light') document.body.classList.add('light-theme');
    // Sync localStorage dengan DB theme jika beda
    if (!saved) localStorage.setItem('theme', dbTheme);
})();
</script>

<div class="header">
    <div class="header-left">

        <!-- Tombol sidebar: bisa munculin / sembunyiin sidebar -->
        <button class="sidebar-toggle" onclick="toggleSidebarMode()" id="sidebarToggleBtn" title="Tampilkan/Sembunyikan Sidebar">
            <i class="bi bi-layout-sidebar-inset" id="sidebarToggleIcon"></i>
        </button>

        <!-- Tombol tema: ikon bulan/matahari saja -->
        <button class="theme-toggle" onclick="toggleTheme()" id="themeToggleBtn" title="Ganti Tema">
            <i class="bi bi-<?= $user_theme === 'light' ? 'sun-fill' : 'moon-stars-fill' ?>" id="themeIcon"></i>
        </button>

        <!-- Info plant/unit — hanya admin (bukan superadmin dan bukan user) -->
        <?php if ($_SESSION['role'] === 'admin' && isset($_SESSION['selected_plant_id'], $_SESSION['selected_unit_id'])): ?>
            <?php
            $r = $conn->prepare("SELECT description FROM plants WHERE plant_id = ?");
            $r->execute([$_SESSION['selected_plant_id']]);
            $plant_name = $r->fetchColumn() ?: '-';

            $r = $conn->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
            $r->execute([$_SESSION['selected_unit_id']]);
            $unit_name = $r->fetchColumn() ?: '-';
            ?>
            <div style="color:var(--text-secondary);font-size:14px;display:flex;align-items:center;gap:8px">
                <i class="bi bi-geo-alt-fill" style="font-size:14px"></i>
                <strong style="color:var(--text-primary)"><?= htmlspecialchars($plant_name) ?></strong>
                <span style="opacity:0.4">–</span>
                <span><?= htmlspecialchars($unit_name) ?></span>
            </div>
        <?php endif; ?>

    </div>

    <div class="user-info">
        <div>
            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
            <div class="user-role">
                <i class="bi bi-shield-fill" style="font-size:11px;vertical-align:middle;margin-right:3px"></i>
                <?= ucfirst($_SESSION['role']) ?>
            </div>
        </div>
        <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
        </div>
    </div>
</div>

<script>
function applySidebarMode(mode) {
    document.body.classList.remove('sidebar-hidden', 'sidebar-collapsed', 'sidebar-mobile-open');
    if (mode === 'hidden') document.body.classList.add('sidebar-hidden');
    if (mode === 'collapsed') document.body.classList.add('sidebar-collapsed');
    const icon = document.getElementById('sidebarToggleIcon');
    if (icon) {
        icon.className = mode === 'hidden' ? 'bi bi-layout-sidebar-inset' :
                         mode === 'collapsed' ? 'bi bi-layout-sidebar' :
                         'bi bi-layout-sidebar-inset-reverse';
    }
    try { localStorage.setItem('sidebar_mode', mode); } catch(e) {}
    if (window.trendChart && typeof window.trendChart.resize === 'function') {
        setTimeout(() => window.trendChart.resize(), 320);
    }
}

function toggleSidebarMode() {
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    if (isMobile) {
        document.body.classList.toggle('sidebar-mobile-open');
        return;
    }
    const saved = localStorage.getItem('sidebar_mode') || 'open';
    const next = saved === 'open' ? 'collapsed' : (saved === 'collapsed' ? 'hidden' : 'open');
    applySidebarMode(next);
}

(function(){
    const saved = localStorage.getItem('sidebar_mode') || 'open';
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ applySidebarMode(saved); });
    } else {
        applySidebarMode(saved);
    }
})();

function toggleTheme() {
    const isLight = document.body.classList.toggle('light-theme');
    const t = isLight ? 'light' : 'dark';
    document.getElementById('themeIcon').className = isLight ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    localStorage.setItem('theme', t);
    // Sinkronisasi ke DB
    fetch('<?= BASE_URL ?>?api=save-theme', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'theme=' + t
    });
}
// Sinkronisasi ikon tema dengan state DOM saat ini
document.addEventListener('DOMContentLoaded', function(){
    var isLight = document.body.classList.contains('light-theme');
    var icon = document.getElementById('themeIcon');
    if (icon) icon.className = isLight ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
});
if (window.history.replaceState) {
    window.history.replaceState(null, '', window.location.pathname);
}
</script>
