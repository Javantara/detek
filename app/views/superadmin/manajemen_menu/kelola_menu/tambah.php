<?php
require_role('superadmin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $menu_name  = trim(strip_tags($_POST['menu_name']  ?? ''));
    $menu_link  = trim(strip_tags($_POST['menu_link']  ?? ''));
    $menu_order = intval($_POST['menu_order'] ?? 0);
    $status     = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    // Roles
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
            // Gunakan positional ? - paling kompatibel dengan semua versi MariaDB/MySQL
            $stmt = $conn->prepare(
                "INSERT INTO menus (menu_name, menu_link, menu_icon, menu_order, roles, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$menu_name, $menu_link, '', $menu_order, $roles_str, $status]);

            if ($stmt->rowCount() > 0) {
                set_flash('Menu "' . $menu_name . '" berhasil ditambahkan!', 'success');
                redirect('superadmin.menus');
            } else {
                $error = 'Perintah INSERT berjalan tapi tidak ada baris yang tersimpan.';
            }

        } catch (PDOException $e) {
            $error = 'Gagal simpan ke database: ' . $e->getMessage();
        }
    }
}

// Hitung default urutan
$max_order = 1;
try {
    $row = $conn->query("SELECT MAX(menu_order) as mx FROM menus")->fetch();
    $max_order = ($row['mx'] ?? 0) + 1;
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Menu - PLN</title>
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
                <h1 class="page-title" style="margin:0">Tambah Menu Baru</h1>
            </div>

            <?php if ($error !== ''): ?>
            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:14px">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width:580px;padding:32px">

                <div style="background:rgba(14,165,233,0.08);border:1px solid rgba(14,165,233,0.25);border-radius:10px;padding:14px 16px;margin-bottom:24px;font-size:13px">
                    <strong>💡 Cara pakai:</strong>
                    Buat file PHP di <code>app/views/pages/<em>namafile</em>.php</code>,
                    lalu isi kolom Link di bawah dengan nama file tersebut (tanpa .php).
                    Menu otomatis muncul di sidebar.
                </div>

                <form method="POST" action="?page=superadmin.menu-add">

                    <div class="form-group">
                        <label class="form-label">Judul Menu *</label>
                        <input type="text"
                               name="menu_name"
                               class="form-control"
                               placeholder="contoh: Kalkulator"
                               value="<?= htmlspecialchars($_POST['menu_name'] ?? '') ?>"
                               required
                               autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Link (nama file tanpa .php)</label>
                        <input type="text"
                               name="menu_link"
                               class="form-control"
                               placeholder="contoh: kalkulator"
                               value="<?= htmlspecialchars($_POST['menu_link'] ?? '') ?>">
                        <small style="color:var(--text-secondary)">
                            Kosongkan jika halaman belum dibuat (akan tampil coming soon).
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input type="number"
                               name="menu_order"
                               class="form-control"
                               value="<?= intval($_POST['menu_order'] ?? $max_order) ?>"
                               min="0" max="999"
                               style="max-width:110px">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="display:flex;gap:12px">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 20px">
                                <input type="radio" name="status" value="active"
                                       <?= (($_POST['status'] ?? 'active') === 'active') ? 'checked' : '' ?>>
                                Aktif
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 20px">
                                <input type="radio" name="status" value="inactive"
                                       <?= (($_POST['status'] ?? '') === 'inactive') ? 'checked' : '' ?>>
                                Nonaktif
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Akses Role</label>
                        <div style="display:flex;gap:10px;flex-wrap:wrap">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="all" checked> Semua
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="superadmin"> Super Admin
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="admin"> Admin
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:10px 16px">
                                <input type="checkbox" name="roles[]" value="user"> User
                            </label>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:12px">
                        <button type="submit" class="btn btn-primary" style="padding:12px 32px">
                            + Tambah Menu
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
