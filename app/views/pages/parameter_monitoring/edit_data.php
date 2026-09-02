<?php
require_login();
if (!in_array($_SESSION['role'], ['superadmin','admin'])) redirect('parameter-monitoring');

// Handle edit single row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_row') {
        $data_id  = intval($_POST['data_id']);
        $new_ts   = trim($_POST['timestamp'] ?? '');
        $new_val  = floatval($_POST['value']  ?? 0);
        if ($data_id && $new_ts && strtotime($new_ts)) {
            $conn->prepare("UPDATE pm_data SET timestamp=?, value=? WHERE data_id=?")
                 ->execute([date('Y-m-d H:i:s', strtotime($new_ts)), $new_val, $data_id]);
            set_flash('Data berhasil diupdate!', 'success');
        }
    } elseif ($_POST['action'] === 'delete_row') {
        $data_id = intval($_POST['data_id']);
        if ($data_id) {
            $conn->prepare("DELETE FROM pm_data WHERE data_id=?")->execute([$data_id]);
            set_flash('Baris data berhasil dihapus!', 'success');
        }
    } elseif ($_POST['action'] === 'edit_address') {
        $tag_id    = intval($_POST['tag_id']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $satuan    = trim($_POST['satuan']    ?? '');
        $address_no= trim($_POST['address_no']?? '');
        if ($tag_id && $deskripsi && $address_no) {
            $conn->prepare("UPDATE pm_addresses SET deskripsi=?, satuan=?, address_no=? WHERE tag_id=?")
                 ->execute([$deskripsi, $satuan, $address_no, $tag_id]);
            set_flash('Address berhasil diupdate!', 'success');
        }
    }
    redirect('pm.edit-data' . (isset($_GET['tag_id']) ? '&tag_id='.$_GET['tag_id'] : ''));
}

// Ambil semua addresses (semua plant)
$filter_plant = intval($_GET['plant_id'] ?? $_SESSION['selected_plant_id'] ?? 0);
$filter_unit  = intval($_GET['unit_id']  ?? $_SESSION['selected_unit_id']  ?? 0);
$selected_tag = intval($_GET['tag_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT a.*, p.description as plant_name, u.unit_name,
           (SELECT COUNT(*) FROM pm_data d WHERE d.tag_id=a.tag_id) as data_count
    FROM pm_addresses a
    JOIN plants p ON a.plant_id=p.plant_id
    JOIN units  u ON a.unit_id=u.unit_id
    WHERE a.plant_id=? AND a.unit_id=?
    ORDER BY a.tag_id
");
$stmt->execute([$filter_plant, $filter_unit]);
$addr_list = $stmt->fetchAll();

// Ambil data rows kalau ada tag dipilih
$data_rows = [];
$selected_addr = null;
if ($selected_tag) {
    $sa = $conn->prepare("SELECT * FROM pm_addresses WHERE tag_id=?");
    $sa->execute([$selected_tag]);
    $selected_addr = $sa->fetch();

    $dr = $conn->prepare("SELECT * FROM pm_data WHERE tag_id=? ORDER BY timestamp DESC LIMIT 500");
    $dr->execute([$selected_tag]);
    $data_rows = $dr->fetchAll();
}

$all_plants = $conn->query("SELECT * FROM plants WHERE status=1 ORDER BY description")->fetchAll();
$all_units  = $conn->query("SELECT u.*, p.description as pn FROM units u JOIN plants p ON u.plant_id=p.plant_id WHERE u.status=1 ORDER BY pn, u.unit_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data - Parameter Monitoring</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .addr-row { display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border:1px solid var(--border-color);border-radius:10px;margin-bottom:8px;transition:all .2s; }
        .addr-row:hover { border-color:var(--accent-cyan); }
        .addr-row.selected { border-color:var(--accent-cyan);background:rgba(0,217,255,.05); }
        .data-table td, .data-table th { padding:8px 12px;font-size:13px; }
        .edit-inline { display:none; }
        .edit-inline input { padding:4px 8px;border:1px solid var(--border-color);border-radius:6px;background:var(--bg-secondary);color:var(--text-primary);font-size:12px; }
    </style>
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
                <h1 class="page-title" style="margin:0">Edit Data Parameter</h1>
            </div>

            <?= flash() ?>

            <!-- Filter Plant/Unit -->
            <div class="card" style="margin-bottom:16px;padding:16px 20px">
                <form method="GET" action="" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                    <input type="hidden" name="page" value="pm.edit-data">
                    <div>
                        <label style="font-size:12px;color:var(--text-secondary);display:block;margin-bottom:4px">Plant</label>
                        <select name="plant_id" id="plantSelEdit" class="form-control" style="min-width:200px" onchange="filterUnitsEdit()">
                            <?php foreach ($all_plants as $pl): ?>
                            <option value="<?= $pl['plant_id'] ?>" <?= $filter_plant==$pl['plant_id']?'selected':'' ?>>
                                <?= htmlspecialchars($pl['description']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--text-secondary);display:block;margin-bottom:4px">Unit</label>
                        <select name="unit_id" id="unitSelEdit" class="form-control" style="min-width:180px">
                            <?php foreach ($all_units as $un): ?>
                            <option value="<?= $un['unit_id'] ?>" <?= $filter_unit==$un['unit_id']?'selected':'' ?>>
                                <?= htmlspecialchars($un['unit_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 16px">
                        <i class="bi bi-search"></i> Tampilkan
                    </button>
                </form>
            </div>

            <div style="display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start">

                <!-- Daftar Address -->
                <div class="card">
                    <h3 style="font-size:14px;margin-bottom:14px"><i class="bi bi-tags" style="color:var(--accent-cyan)"></i> Daftar Address</h3>
                    <?php if (empty($addr_list)): ?>
                    <p style="color:var(--text-secondary);font-size:13px">Tidak ada address untuk plant/unit ini</p>
                    <?php else: ?>
                    <?php foreach ($addr_list as $a): ?>
                    <div class="addr-row <?= $selected_tag==$a['tag_id']?'selected':'' ?>">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:12px;color:var(--accent-cyan);font-weight:700">Tag #<?= $a['tag_id'] ?></div>
                            <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($a['deskripsi']) ?></div>
                            <div style="font-size:11px;color:var(--text-secondary)"><?= number_format($a['data_count']) ?> data · <?= $a['satuan'] ?></div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:4px;margin-left:8px">
                            <a href="?page=pm.edit-data&plant_id=<?= $filter_plant ?>&unit_id=<?= $filter_unit ?>&tag_id=<?= $a['tag_id'] ?>"
                               class="btn btn-sm btn-secondary" style="padding:4px 8px;font-size:11px" title="Edit data">
                                <i class="bi bi-table"></i>
                            </a>
                            <button onclick="editAddress(<?= htmlspecialchars(json_encode($a)) ?>)"
                                    class="btn btn-sm btn-secondary" style="padding:4px 8px;font-size:11px" title="Edit address">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="?page=pm.delete-data&tag_id=<?= $a['tag_id'] ?>&type=address"
                               class="btn btn-sm btn-secondary" style="padding:4px 8px;font-size:11px;color:#ff6b7a"
                               onclick="return confirm('Hapus address Tag #<?= $a['tag_id'] ?> beserta SEMUA datanya?')"
                               title="Hapus address">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Tabel Data -->
                <div class="card">
                    <?php if ($selected_addr): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
                        <div>
                            <h3 style="margin:0;font-size:15px">Tag #<?= $selected_addr['tag_id'] ?> — <?= htmlspecialchars($selected_addr['deskripsi']) ?></h3>
                            <p style="margin:3px 0 0;font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($selected_addr['address_no']) ?> · <?= $selected_addr['satuan'] ?></p>
                        </div>
                        <a href="?page=pm.delete-data&tag_id=<?= $selected_tag ?>&type=data"
                           class="btn btn-secondary" style="padding:8px 14px;font-size:12px;color:#ff6b7a"
                           onclick="return confirm('Hapus SEMUA data untuk Tag #<?= $selected_tag ?>? Action ini tidak dapat dibatalkan!')">
                            <i class="bi bi-trash"></i> Hapus Semua Data
                        </a>
                    </div>
                    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px">
                        Menampilkan 500 data terbaru. Total: <?= number_format(count($data_rows)) ?> ditampilkan.
                    </p>
                    <div class="table-responsive" style="max-height:500px;overflow-y:auto">
                        <table class="data-table">
                            <thead>
                                <tr><th>No</th><th>Timestamp</th><th>Value (<?= htmlspecialchars($selected_addr['satuan']) ?>)</th><th style="width:80px">Aksi</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data_rows as $i => $row): ?>
                            <tr id="row-<?= $row['data_id'] ?>">
                                <td style="color:var(--text-secondary)"><?= $i+1 ?></td>
                                <td>
                                    <span class="view-ts-<?= $row['data_id'] ?>"><?= $row['timestamp'] ?></span>
                                    <span class="edit-inline" id="edit-ts-<?= $row['data_id'] ?>">
                                        <input type="datetime-local" id="inp-ts-<?= $row['data_id'] ?>"
                                               value="<?= date('Y-m-d\TH:i', strtotime($row['timestamp'])) ?>">
                                    </span>
                                </td>
                                <td>
                                    <span class="view-val-<?= $row['data_id'] ?>"><?= $row['value'] ?></span>
                                    <span class="edit-inline" id="edit-val-<?= $row['data_id'] ?>">
                                        <input type="number" step="any" id="inp-val-<?= $row['data_id'] ?>" value="<?= $row['value'] ?>" style="width:120px">
                                    </span>
                                </td>
                                <td style="white-space:nowrap">
                                    <button onclick="startEdit(<?= $row['data_id'] ?>)" id="btn-edit-<?= $row['data_id'] ?>"
                                            class="btn btn-sm btn-secondary" style="padding:3px 7px;font-size:11px" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <span id="btn-save-<?= $row['data_id'] ?>" style="display:none">
                                        <button onclick="saveEdit(<?= $row['data_id'] ?>, <?= $selected_tag ?>)"
                                                class="btn btn-sm btn-primary" style="padding:3px 7px;font-size:11px">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button onclick="cancelEdit(<?= $row['data_id'] ?>)"
                                                class="btn btn-sm btn-secondary" style="padding:3px 7px;font-size:11px">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </span>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action"  value="delete_row">
                                        <input type="hidden" name="data_id" value="<?= $row['data_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary"
                                                style="padding:3px 7px;font-size:11px;color:#ff6b7a"
                                                onclick="return confirm('Hapus baris ini?')" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center;padding:60px 20px;color:var(--text-secondary)">
                        <i class="bi bi-hand-index-thumb" style="font-size:40px;display:block;margin-bottom:12px;opacity:.4"></i>
                        Pilih address dari daftar kiri untuk melihat dan mengedit data
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Address -->
<div id="editAddrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center">
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:28px;width:100%;max-width:520px;margin:20px">
        <h3 style="margin:0 0 20px">Edit Address</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_address">
            <input type="hidden" name="tag_id" id="modal-tag-id">
            <div class="form-group">
                <label>Address No</label>
                <input type="text" name="address_no" id="modal-addr-no" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <input type="text" name="deskripsi" id="modal-deskripsi" class="form-control" required>
            </div>
            <div class="form-group" style="max-width:200px">
                <label>Satuan</label>
                <input type="text" name="satuan" id="modal-satuan" class="form-control">
            </div>
            <div style="display:flex;gap:10px;margin-top:10px">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" onclick="document.getElementById('editAddrModal').style.display='none'" class="btn btn-secondary">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const ALL_UNITS  = <?= json_encode($all_units) ?>;

function editAddress(a) {
    document.getElementById('modal-tag-id').value   = a.tag_id;
    document.getElementById('modal-addr-no').value  = a.address_no;
    document.getElementById('modal-deskripsi').value= a.deskripsi;
    document.getElementById('modal-satuan').value   = a.satuan;
    document.getElementById('editAddrModal').style.display = 'flex';
}

function startEdit(id) {
    document.querySelector('.view-ts-' + id).style.display  = 'none';
    document.querySelector('.view-val-' + id).style.display = 'none';
    document.getElementById('edit-ts-' + id).style.display  = 'inline';
    document.getElementById('edit-val-' + id).style.display = 'inline';
    document.getElementById('btn-edit-' + id).style.display = 'none';
    document.getElementById('btn-save-' + id).style.display = 'inline';
}

function cancelEdit(id) {
    document.querySelector('.view-ts-' + id).style.display  = '';
    document.querySelector('.view-val-' + id).style.display = '';
    document.getElementById('edit-ts-' + id).style.display  = 'none';
    document.getElementById('edit-val-' + id).style.display = 'none';
    document.getElementById('btn-edit-' + id).style.display = '';
    document.getElementById('btn-save-' + id).style.display = 'none';
}

function saveEdit(id, tagId) {
    const ts  = document.getElementById('inp-ts-' + id).value;
    const val = document.getElementById('inp-val-' + id).value;
    const form = document.createElement('form');
    form.method = 'POST';
    [['action','edit_row'],['data_id',id],['timestamp',ts],['value',val]].forEach(([n,v])=>{
        const i = document.createElement('input');
        i.type='hidden'; i.name=n; i.value=v;
        form.appendChild(i);
    });
    document.body.appendChild(form);
    form.submit();
}

function filterUnitsEdit() {
    const pid = document.getElementById('plantSelEdit').value;
    const sel = document.getElementById('unitSelEdit');
    const cur = sel.value;
    sel.innerHTML = '';
    ALL_UNITS.filter(u => u.plant_id == pid).forEach(u => {
        const o = document.createElement('option');
        o.value = u.unit_id; o.textContent = u.unit_name;
        if (o.value == cur) o.selected = true;
        sel.appendChild(o);
    });
}
</script>
</body>
</html>
