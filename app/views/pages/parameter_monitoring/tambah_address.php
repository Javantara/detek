<?php
require_login();
// Superadmin & admin bisa tambah
if (!in_array($_SESSION['role'], ['superadmin','admin'])) redirect('parameter-monitoring');

$error = '';
$success = '';

// Ambil semua plants & units
$all_plants = $conn->query("SELECT * FROM plants WHERE status=1 ORDER BY description")->fetchAll();
$all_units  = $conn->query("SELECT u.*, p.description as plant_desc FROM units u JOIN plants p ON u.plant_id=p.plant_id WHERE u.status=1 ORDER BY p.description, u.unit_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plant_id  = intval($_POST['plant_id']  ?? 0);
    $unit_id   = intval($_POST['unit_id']   ?? 0);
    $address_no= trim($_POST['address_no']  ?? '');
    $tag_id    = intval($_POST['tag_id']    ?? 0);
    $deskripsi = trim($_POST['deskripsi']   ?? '');
    $satuan    = trim($_POST['satuan']      ?? '');

    if (!$plant_id || !$unit_id || !$address_no || !$tag_id || !$deskripsi) {
        $error = 'Semua field wajib diisi!';
    } else {
        // Cek duplikat tag_id
        $chk = $conn->prepare("SELECT tag_id FROM pm_addresses WHERE tag_id = ?");
        $chk->execute([$tag_id]);
        if ($chk->fetchColumn()) {
            $error = "Tag ID $tag_id sudah ada dalam sistem!";
        } else {
            try {
                $conn->prepare("INSERT INTO pm_addresses (plant_id,unit_id,address_no,tag_id,deskripsi,satuan) VALUES (?,?,?,?,?,?)")
                     ->execute([$plant_id,$unit_id,$address_no,$tag_id,$deskripsi,$satuan]);
                set_flash("Address Tag #$tag_id berhasil ditambahkan!", 'success');
                redirect('parameter-monitoring');
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Address - Parameter Monitoring</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
                <a href="?page=parameter-monitoring" class="btn btn-secondary" style="padding:8px 12px">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title" style="margin:0">Tambah Address Baru</h1>
            </div>

            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card" style="max-width:700px">
                <form method="POST">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                        <div class="form-group">
                            <label>Plant *</label>
                            <select name="plant_id" id="plantSel" class="form-control" required onchange="filterUnits()">
                                <option value="">-- Pilih Plant --</option>
                                <?php foreach ($all_plants as $pl): ?>
                                <option value="<?= $pl['plant_id'] ?>" <?= (isset($_POST['plant_id']) && $_POST['plant_id']==$pl['plant_id'])? 'selected':'' ?>>
                                    <?= htmlspecialchars($pl['description']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Unit *</label>
                            <select name="unit_id" id="unitSel" class="form-control" required>
                                <option value="">-- Pilih Plant dulu --</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:end">
                        <div class="form-group" style="width:140px">
                            <label>Tag ID *</label>
                            <input type="number" name="tag_id" class="form-control" required min="1"
                                   value="<?= htmlspecialchars($_POST['tag_id'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Address No *</label>
                            <input type="text" name="address_no" class="form-control" required
                                   placeholder="OPC.AW2002.2KAI.AI131303.PNT"
                                   value="<?= htmlspecialchars($_POST['address_no'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi *</label>
                        <input type="text" name="deskripsi" class="form-control" required
                               placeholder="IDF B DRIVED END BEARING VIBRATING ON Y DIRECTION"
                               value="<?= htmlspecialchars($_POST['deskripsi'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="max-width:200px">
                        <label>Satuan</label>
                        <input type="text" name="satuan" class="form-control"
                               placeholder="mm/s, °C, A, ..."
                               value="<?= htmlspecialchars($_POST['satuan'] ?? '') ?>">
                    </div>

                    <div style="display:flex;gap:10px;margin-top:10px">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy" style="margin-right:6px"></i>Simpan Address
                        </button>
                        <a href="?page=parameter-monitoring" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
const BASE_URL  = '<?= BASE_URL ?>';
const ALL_UNITS = <?= json_encode($all_units) ?>;
const SAVED_UNIT = '<?= htmlspecialchars($_POST['unit_id'] ?? '') ?>';

function filterUnits() {
    const plantId = document.getElementById('plantSel').value;
    const sel = document.getElementById('unitSel');
    sel.innerHTML = '<option value="">-- Pilih Unit --</option>';
    ALL_UNITS.filter(u => u.plant_id == plantId).forEach(u => {
        const o = document.createElement('option');
        o.value = u.unit_id;
        o.textContent = u.unit_name;
        if (o.value == SAVED_UNIT) o.selected = true;
        sel.appendChild(o);
    });
}
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('plantSel').value) filterUnits();
});
</script>
</body>
</html>
