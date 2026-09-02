<?php
$user_id = intval($_GET['id'] ?? $_POST['user_id'] ?? 0);
if (!$user_id) { redirect('superadmin.users'); }

$stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) { redirect('superadmin.users'); }

// Ambil plant_id Kantor Pusat
$kp = $conn->query("SELECT plant_id FROM plants WHERE description LIKE '%Kantor Pusat%' LIMIT 1")->fetchColumn();
$KANTOR_PUSAT_ID = $kp ?: 1;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip        = clean_input($_POST['nip']       ?? '');
    $username   = clean_input($_POST['username']  ?? '');
    $email      = clean_input($_POST['email']     ?? '');
    $password   = $_POST['password'] ?? '';
    $full_name  = clean_input($_POST['full_name'] ?? '');
    $role_id    = intval($_POST['role_id'] ?? 3);
    $status     = $_POST['status'] ?? 'active';
    $all_access = isset($_POST['all_access']) ? 1 : 0;

    if ($all_access) {
        $all_plant_ids   = $conn->query("SELECT plant_id FROM plants WHERE status = 1")->fetchAll(PDO::FETCH_COLUMN);
        $all_unit_ids    = $conn->query("SELECT unit_id  FROM units  WHERE status = 1")->fetchAll(PDO::FETCH_COLUMN);
        $assigned_plants = implode(',', $all_plant_ids);
        $assigned_units  = implode(',', $all_unit_ids);
    } else {
        $assigned_plants = isset($_POST['plants']) ? implode(',', array_map('intval', $_POST['plants'])) : '';
        $assigned_units  = isset($_POST['units'])  ? implode(',', array_map('intval', $_POST['units']))  : '';
    }

    if ($nip && $username && $email && $full_name && $role_id) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE (nip=? OR email=?) AND user_id != ?");
        $check->execute([$nip, $email, $user_id]);
        if ($check->rowCount() > 0) {
            $error = 'NIP atau Email sudah digunakan oleh user lain!';
        } else {
            try {
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET nip=?,username=?,email=?,password=?,full_name=?,role_id=?,assigned_plants=?,assigned_units=?,all_access=?,status=? WHERE user_id=?");
                    $stmt->execute([$nip, $username, $email, $hash, $full_name, $role_id, $assigned_plants, $assigned_units, $all_access, $status, $user_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET nip=?,username=?,email=?,full_name=?,role_id=?,assigned_plants=?,assigned_units=?,all_access=?,status=? WHERE user_id=?");
                    $stmt->execute([$nip, $username, $email, $full_name, $role_id, $assigned_plants, $assigned_units, $all_access, $status, $user_id]);
                }
                set_flash('User berhasil diupdate!', 'success');
                redirect('superadmin.users');
            } catch (PDOException $e) {
                $error = 'Gagal update user: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Semua field wajib harus diisi!';
    }
}

$ap = $user['assigned_plants'] ? explode(',', $user['assigned_plants']) : [];
$au = $user['assigned_units']  ? explode(',', $user['assigned_units'])  : [];
$is_all_access = !empty($user['all_access']);

$plants_all = $conn->query("SELECT * FROM plants WHERE status = 1 ORDER BY description")->fetchAll();
$units_all  = $conn->query("SELECT u.*, p.description as plant_desc FROM units u JOIN plants p ON u.plant_id = p.plant_id WHERE u.status = 1 ORDER BY p.description, u.unit_name")->fetchAll();
$all_roles  = $conn->query("SELECT * FROM roles ORDER BY role_id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Super Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .checkbox-group { display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;max-height:220px;overflow-y:auto;padding:15px;background:rgba(26,29,42,.5);border-radius:10px;border:2px solid var(--border-color);transition:opacity .3s }
        .checkbox-item  { display:flex;align-items:center;gap:8px }
        .checkbox-item input[type=checkbox] { width:18px;height:18px;cursor:pointer;flex-shrink:0 }
        .checkbox-item label { margin:0;cursor:pointer;font-size:14px;text-transform:none;letter-spacing:normal }
        .checkbox-item.dimmed { opacity:.35;pointer-events:none }
        .all-access-box { background:rgba(0,217,255,.08);border:2px solid var(--accent-cyan);border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;cursor:pointer;margin-bottom:20px }
        .all-access-box input[type=checkbox] { width:20px;height:20px;cursor:pointer;flex-shrink:0;accent-color:var(--accent-cyan) }
    </style>
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <h1 class="page-title">Edit User: <?= htmlspecialchars($user['full_name']) ?></h1>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card">
                <form method="POST" action="<?= BASE_URL ?>?page=superadmin.user-edit">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                        <div class="form-group"><label>NIP *</label><input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($user['nip']) ?>" required></div>
                        <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required></div>
                        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                        <div class="form-group"><label>Password <small style="font-weight:400;opacity:.6">(kosongkan jika tidak diubah)</small></label><input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah"></div>
                        <div class="form-group"><label>Nama Lengkap *</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required></div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="role_id" class="form-control" required id="roleSelect" onchange="toggleAssignSection()">
                                <?php foreach ($all_roles as $r): ?>
                                <option value="<?= $r['role_id'] ?>" <?= $user['role_id']==$r['role_id']?'selected':'' ?>>
                                    <?= ucfirst(htmlspecialchars($r['role_name'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" class="form-control" required>
                                <option value="active"   <?= $user['status']=='active'?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= $user['status']=='inactive'?'selected':'' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Assignment Section -->
                    <div id="assignmentSection" style="<?= in_array($user['role_name'],['admin','user'])?'':'display:none;' ?> margin-top:24px">
                        <hr style="border-color:var(--border-color);margin:0 0 24px">
                        <h3 style="margin-bottom:16px">Assign Plant &amp; Unit</h3>

                        <!-- Kantor Pusat toggle -->
                        <label class="all-access-box" for="allAccessCheck">
                            <input type="checkbox" name="all_access" id="allAccessCheck" value="1"
                                   <?= $is_all_access ? 'checked' : '' ?>
                                   onchange="toggleAllAccess(this)">
                            <div>
                                <div style="font-weight:700;font-size:15px">
                                    <i class="bi bi-building-fill-check" style="color:var(--accent-cyan);margin-right:6px"></i>
                                    Kantor Pusat — Akses Semua Plant &amp; Unit
                                </div>
                                <div style="font-size:12px;color:var(--text-secondary);margin-top:3px">
                                    Centang ini untuk memberikan akses ke seluruh plant dan unit yang aktif
                                </div>
                            </div>
                        </label>

                        <!-- Plants -->
                        <div class="form-group">
                            <label style="margin-bottom:10px;display:block">Pilih Plant</label>
                            <div class="checkbox-group" id="plantsGroup">
                                <?php foreach ($plants_all as $pl): ?>
                                <div class="checkbox-item <?= $is_all_access ? 'dimmed' : '' ?>" id="plant-item-<?= $pl['plant_id'] ?>">
                                    <input type="checkbox" name="plants[]" value="<?= $pl['plant_id'] ?>"
                                           id="pl<?= $pl['plant_id'] ?>"
                                           <?= (!$is_all_access && in_array($pl['plant_id'], $ap)) ? 'checked' : '' ?>
                                           onchange="updateUnitVisibility()">
                                    <label for="pl<?= $pl['plant_id'] ?>"><?= htmlspecialchars($pl['description']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Units -->
                        <div class="form-group">
                            <label style="margin-bottom:10px;display:block">Pilih Unit</label>
                            <div class="checkbox-group" id="unitsGroup">
                                <?php foreach ($units_all as $un): ?>
                                <?php
                                    $showUnit  = $is_all_access || (!$is_all_access && in_array($un['plant_id'], $ap));
                                    $checked   = !$is_all_access && in_array($un['unit_id'], $au);
                                ?>
                                <div class="checkbox-item unit-item <?= $is_all_access ? 'dimmed' : '' ?>"
                                     data-plant="<?= $un['plant_id'] ?>"
                                     style="display:<?= $showUnit ? 'flex' : 'none' ?>">
                                    <input type="checkbox" name="units[]" value="<?= $un['unit_id'] ?>"
                                           id="un<?= $un['unit_id'] ?>"
                                           <?= $checked ? 'checked' : '' ?>>
                                    <label for="un<?= $un['unit_id'] ?>"><?= htmlspecialchars($un['plant_desc'] . ' — ' . $un['unit_name']) ?></label>
                                </div>
                                <?php endforeach; ?>
                                <p id="unitPlaceholder" style="color:var(--text-secondary);font-size:13px;margin:0;display:<?= (count($ap) > 0 || $is_all_access) ? 'none' : 'block' ?>">Pilih plant terlebih dahulu</p>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:30px">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy" style="margin-right:6px"></i>Update User</button>
                        <a href="?page=superadmin.users" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
const KANTOR_PUSAT_ID = '<?= $KANTOR_PUSAT_ID ?>';

function toggleAssignSection() {
    const sel  = document.getElementById('roleSelect');
    const role = sel.options[sel.selectedIndex]?.text?.toLowerCase() || '';
    document.getElementById('assignmentSection').style.display =
        (role === 'admin' || role === 'user') ? 'block' : 'none';
}

function toggleAllAccess(cb) {
    const isAll = cb.checked;
    document.querySelectorAll('#plantsGroup .checkbox-item').forEach(el => {
        el.classList.toggle('dimmed', isAll);
        if (isAll) el.querySelector('input').checked = false;
    });
    document.querySelectorAll('.unit-item').forEach(el => {
        el.classList.toggle('dimmed', isAll);
        if (isAll) { el.style.display = 'flex'; el.querySelector('input').checked = false; }
    });
    document.getElementById('unitPlaceholder').style.display = 'none';
    if (!isAll) updateUnitVisibility();
}

function updateUnitVisibility() {
    if (document.getElementById('allAccessCheck').checked) return;
    const selected = Array.from(document.querySelectorAll('input[name="plants[]"]:checked')).map(c => c.value);
    let anyVisible = false;
    document.querySelectorAll('.unit-item').forEach(el => {
        const show = selected.includes(el.dataset.plant);
        el.style.display = show ? 'flex' : 'none';
        if (!show) el.querySelector('input').checked = false;
        if (show) anyVisible = true;
    });
    document.getElementById('unitPlaceholder').style.display = anyVisible ? 'none' : 'block';
}

document.addEventListener('DOMContentLoaded', function() {
    // Jangan jalankan updateUnitVisibility kalau all_access aktif
    if (!document.getElementById('allAccessCheck').checked) {
        updateUnitVisibility();
    }
});
</script>
</body>
</html>
