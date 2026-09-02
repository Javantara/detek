<?php
require_role('superadmin');

$error = '';
$menu_id = intval($_GET['id'] ?? 0);

if (!$menu_id) {
    redirect('superadmin.menus');
}

// Ambil data menu yang akan diedit
$stmt = $conn->prepare("SELECT * FROM menus WHERE menu_id = ?");
$stmt->execute([$menu_id]);
$menu = $stmt->fetch();

if (!$menu) {
    set_flash('Menu tidak ditemukan.', 'error');
    redirect('superadmin.menus');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $menu_name  = trim(strip_tags($_POST['menu_name']  ?? ''));
    $menu_link  = trim(strip_tags($_POST['menu_link']  ?? ''));
    $menu_order = intval($_POST['menu_order'] ?? 0);
    $status     = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    $roles_arr = [];
    if (!empty($_POST['roles']) && is_array($_POST['roles'])) {
        foreach ($_POST['roles'] as $r) {
            $r = trim($r);
            if ($r !== '') $roles_arr[] = $r;
        }
    }
    $roles_str = !empty($roles_arr) ? implode(',', $roles_arr) : 'all';

    if ($menu_name === '') {
        $error = 'Nama menu tidak boleh kosong!';
    } else {
        try {
            $stmt2 = $conn->prepare(
                "UPDATE menus SET menu_name=?, menu_link=?, menu_order=?, roles=?, status=?
                 WHERE menu_id=?"
            );
            $stmt2->execute([$menu_name, $menu_link, $menu_order, $roles_str, $status, $menu_id]);

            set_flash('Menu "' . $menu_name . '" berhasil diperbarui!', 'success');
            redirect('superadmin.menus');

        } catch (PDOException $e) {
            $error = 'Gagal update: ' . $e->getMessage();
        }
    }
}

// Parse current roles
$current_roles = array_map('trim', explode(',', $menu['roles'] ?? 'all'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:28px">
                <a href="?page=superadmin.menus" class="btn btn-secondary" style="padding:8px 14px">←</a>
                <h1 class="page-title" style="margin:0">Edit Menu</h1>
            </div>

            <?php if ($error !== ''): ?>
            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:14px">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width:580px;padding:32px">
                <form method="POST" action="?page=superadmin.menu-edit&id=<?= $menu_id ?>">

                    <div class="form-group">
                        <label class="form-label">Judul Menu *</label>
                        <input type="text"
                               name="menu_name"
                               class="form-control"
                               value="<?= htmlspecialchars($menu['menu_name']) ?>"
                               required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Link (nama file tanpa .php)</label>
                        <input type="text"
                               name="menu_link"
                               class="form-control"
                               value="<?= htmlspecialchars($menu['menu_link'] ?? '') ?>"
                               placeholder="contoh: kalkulator">
                        <small style="color:var(--text-secondary)">
                            Kosongkan jika halaman belum dibuat.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input type="number"
                               name="menu_order"
                               class="form-control"
                               value="<?= intval($menu['menu_order']) ?>"
                               min="0" max="999"
                               style="max-width:110px">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="display:flex;gap:12px">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 20px">
                                <input type="radio" name="status" value="active"
                                       <?= ($menu['status'] === 'active') ? 'checked' : '' ?>>
                                Aktif
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="radio" name="status" value="inactive"
                                       <?= ($menu['status'] === 'inactive') ? 'checked' : '' ?>>
                                Nonaktif
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Akses Role</label>
                        <div style="display:flex;gap:10px;flex-wrap:wrap">
                            <?php
                            $has_all = in_array('all', $current_roles) || empty(array_filter($current_roles));
                            $has_sa  = in_array('superadmin', $current_roles);
                            $has_adm = in_array('admin', $current_roles);
                            $has_usr = in_array('user', $current_roles);
                            ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="all"
                                       <?= $has_all ? 'checked' : '' ?>> Semua
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="superadmin"
                                       <?= $has_sa ? 'checked' : '' ?>> Super Admin
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="admin"
                                       <?= $has_adm ? 'checked' : '' ?>> Admin
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="user"
                                       <?= $has_usr ? 'checked' : '' ?>> User
                            </label>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:12px">
                        <button type="submit" class="btn btn-primary" style="padding:12px 32px">
                            💾 Simpan Perubahan
                        </button>
                        <a href="?page=superadmin.menus" class="btn btn-secondary" style="padding:12px 24px">
                            Batal
                        </a>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>
<script>
</script>
</body>
</html>
