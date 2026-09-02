<?php
// ============================================================
// INDEX.PHP — Satu-satunya pintu masuk aplikasi PLN
// Semua request masuk ke sini, tidak ada file lain yang
// bisa diakses langsung via browser.
// ============================================================

require_once __DIR__ . '/../app/core/config.php';

$route = trim($_GET['page'] ?? $_POST['page'] ?? '');

// ============================================================
// Simpan halaman/menu terakhir TANPA menampilkan ?page=... di URL.
// Kenapa ditaruh di PHP? Karena script header membersihkan URL sangat cepat,
// jadi JS kadang tidak sempat membaca ?page=... sebelum refresh.
// Dengan session + cookie, refresh di /public/ tetap membuka menu terakhir.
// ============================================================
function pln_is_savable_route(string $page): bool {
    return $page !== ''
        && preg_match('/^[a-zA-Z0-9_.-]+$/', $page)
        && !in_array($page, ['login', 'logout'], true);
}

if (is_logged_in()) {
    if (pln_is_savable_route($route)) {
        $_SESSION['pln_last_page'] = $route;
        setcookie('pln_last_page', $route, [
            'expires'  => time() + (60 * 60 * 24 * 30),
            'path'     => '/',
            'samesite' => 'Lax'
        ]);
    } elseif ($route === '') {
        $saved_route = trim((string)($_SESSION['pln_last_page'] ?? ($_COOKIE['pln_last_page'] ?? '')));
        if (pln_is_savable_route($saved_route)) {
            $route = $saved_route;
        }
    }
}

// ── API Handler ───────────────────────────────────────────────
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    switch ($_GET['api']) {
        case 'get-units':
            require_once APP . 'api/get_units.php';
            break;
        case 'toggle-status':
            require_login();
            require_role('superadmin');
            require_once APP . 'api/toggle_status.php';
            break;
        case 'save-theme':
            require_login();
            require_once APP . 'api/save_theme.php';
            break;
        case 'save-quick-access':
            require_login();
            require_once APP . 'api/save_quick_access.php';
            break;
        case 'pm-chart-data':
            require_login();
            require_once APP . 'api/pm_chart_data.php';
            break;
        case 'pm-addresses':
            require_login();
            require_once APP . 'api/pm_addresses.php';
            break;
        case 'pm-add-address':
            require_login();
            require_once APP . 'api/pm_add_address.php';
            break;
        case 'pm-edit-address':
            require_login();
            require_once APP . 'api/pm_edit_address.php';
            break;
        case 'pm-delete-address':
            require_login();
            require_once APP . 'api/pm_delete_address.php';
            break;
        case 'pm-delete-data':
            require_login();
            require_once APP . 'api/pm_delete_data.php';
            break;
        case 'pm-upload':
            require_login();
            require_once APP . 'api/pm_upload.php';
            break;
        case 'pm-machine-ingest':
            // Tidak butuh require_login() - pakai API key autentikasi sendiri
            header('Content-Type: application/json');
            require_once APP . 'api/pm_machine_ingest.php';
            break;
        case 'pm-machine-status':
            require_login();
            header('Content-Type: application/json');
            require_once APP . 'api/pm_machine_status.php';
            break;
        case 'pm-generate-api-key':
            require_login();
            header('Content-Type: application/json');
            require_once APP . 'api/pm_generate_api_key.php';
            break;
        case 'pm-revoke-api-key':
            require_login();
            header('Content-Type: application/json');
            require_once APP . 'api/pm_revoke_api_key.php';
            break;
        case 'pm-upload-excel':
            require_login();
            require_once APP . 'api/pm_upload_excel.php';
            break;
        case 'bearing-upload':
            require_login();
            require_once APP . 'api/bearing_upload.php';
            break;
        case 'bearing-proxy':
            require_login();
            require_once APP . 'api/bearing_proxy.php';
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'API tidak ditemukan']);
    }
    exit;
}

// ── Belum login → paksa ke halaman login ─────────────────────
if (!is_logged_in()) {
    if ($route !== 'login' && $route !== '') redirect('login');
    require_once VIEWS . 'auth/login.php';
    exit;
}

// ── Router Utama ─────────────────────────────────────────────
switch ($route) {

    // ─── Umum ────────────────────────────────────────────────
    case '':
    case 'login':
    case 'dashboard':
        if ($_SESSION['role'] === 'superadmin') {
            redirect('superadmin.dashboard');
        } elseif (!isset($_SESSION['selected_plant_id'])) {
            redirect('select-plant');
        } else {
            redirect($_SESSION['role'] . '.dashboard');
        }
        break;

    case 'select-plant':
        if ($_SESSION['role'] === 'superadmin') redirect('superadmin.dashboard');
        require_once VIEWS . 'auth/select_plant.php';
        break;

    case 'logout':
        if (is_logged_in() && isset($_SESSION['selected_plant_id'], $_SESSION['selected_unit_id'])) {
            log_activity($_SESSION['user_id'], 'logout',
                $_SESSION['selected_plant_id'],
                $_SESSION['selected_unit_id']);
        }
        session_destroy();
        redirect('login');
        break;

    case 'coming-soon':
        require_login();
        require_once VIEWS . 'shared/coming_soon.php';
        break;

    // ─── SUPERADMIN ──────────────────────────────────────────
    case 'superadmin.dashboard':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/dashboard.php';
        break;

    // Manajemen User
    case 'superadmin.users':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_user/index.php';
        break;

    case 'superadmin.user-add':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_user/tambah.php';
        break;

    case 'superadmin.user-edit':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_user/edit.php';
        break;

    case 'superadmin.user-delete':
        require_role('superadmin');
        $del_id = intval($_GET['id'] ?? 0);
        if ($del_id && $del_id !== (int)$_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$del_id]);
            set_flash($stmt->rowCount() > 0 ? 'User berhasil dihapus!' : 'Gagal menghapus user.', $stmt->rowCount() > 0 ? 'success' : 'error');
        } else {
            set_flash('Tidak bisa menghapus akun sendiri!', 'error');
        }
        redirect('superadmin.users');
        break;

    case 'superadmin.user-history':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_user/riwayat.php';
        break;

    // Manajemen Menu
    case 'superadmin.menus':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_menu/index.php';
        break;

    case 'superadmin.menu-add':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_menu/tambah.php';
        break;

    case 'superadmin.menu-edit':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_menu/edit.php';
        break;

    // Kelola Plant
    case 'superadmin.plants':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_plant/index.php';
        break;

    case 'superadmin.plant-add':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_plant/tambah.php';
        break;

    case 'superadmin.plant-edit':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_plant/edit.php';
        break;

    case 'superadmin.plant-delete':
        require_role('superadmin');
        $del_id = intval($_GET['id'] ?? 0);
        if ($del_id) {
            $stmt = $conn->prepare("DELETE FROM plants WHERE plant_id = ?");
            $stmt->execute([$del_id]);
            set_flash($stmt->rowCount() > 0 ? 'Plant berhasil dihapus!' : 'Gagal menghapus plant.', $stmt->rowCount() > 0 ? 'success' : 'error');
        }
        redirect('superadmin.plants');
        break;

    // Kelola Unit
    case 'superadmin.units':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_unit/index.php';
        break;

    case 'superadmin.unit-add':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_unit/tambah.php';
        break;

    case 'superadmin.unit-edit':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_menu/kelola_unit/edit.php';
        break;

    case 'superadmin.unit-delete':
        require_role('superadmin');
        $del_id = intval($_GET['id'] ?? 0);
        if ($del_id) {
            $stmt = $conn->prepare("DELETE FROM units WHERE unit_id = ?");
            $stmt->execute([$del_id]);
            set_flash($stmt->rowCount() > 0 ? 'Unit berhasil dihapus!' : 'Gagal menghapus unit.', $stmt->rowCount() > 0 ? 'success' : 'error');
        }
        redirect('superadmin.units');
        break;

    // ─── ADMIN ───────────────────────────────────────────────
    case 'admin.dashboard':
        require_role('admin');
        require_once VIEWS . 'admin/dashboard.php';
        break;

    case 'admin.users':
        require_role('admin');
        require_once VIEWS . 'admin/view_users.php';
        break;

    // ─── USER ────────────────────────────────────────────────
    case 'user.dashboard':
        require_role('user');
        require_once VIEWS . 'user/dashboard.php';
        break;

    // Ganti Password (semua role)
    case 'ganti-password':
        require_login();
        require_once VIEWS . 'shared/ganti_password.php';
        break;

    // Permintaan Password (superadmin)
    case 'superadmin.permintaan-password':
        require_role('superadmin');
        require_once VIEWS . 'superadmin/manajemen_user/permintaan_password.php';
        break;


    // ─── PARAMETER MONITORING ───────────────────────────────
    case 'parameter-monitoring':
        require_login();
        if (!isset($_SESSION['selected_plant_id'])) redirect('select-plant');
        require_once VIEWS . 'pages/parameter_monitoring/index.php';
        break;

    // pm sub-pages merged into single-page tabs

    // ─── Dynamic Page Router ─────────────────────────────────
    // Cara tambah halaman baru:
    //   1. Buat file di app/views/pages/namafile.php
    //   2. Tambah di Manajemen Menu dengan link = namafile
    //   3. Selesai — tidak perlu edit file ini!
    default:
        require_login();
        $safe_route = preg_replace('/[^a-zA-Z0-9_\-]/', '', $route);
        $page_file  = VIEWS . 'pages/' . $safe_route . '.php';

        if ($safe_route !== '' && file_exists($page_file)) {
            require_once $page_file;
        } else {
            http_response_code(404);
            require_once VIEWS . 'shared/404.php';
        }
        break;
}
