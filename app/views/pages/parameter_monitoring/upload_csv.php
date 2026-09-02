<?php
require_login();
if (!in_array($_SESSION['role'], ['superadmin','admin'])) redirect('parameter-monitoring');

$plant_id = intval($_SESSION['selected_plant_id'] ?? 0);
$unit_id  = intval($_SESSION['selected_unit_id']  ?? 0);
$error = ''; $success = '';

// Ambil semua address untuk plant & unit ini
$addresses = $conn->prepare("SELECT tag_id, deskripsi, satuan, address_no FROM pm_addresses WHERE plant_id=? AND unit_id=? ORDER BY tag_id");
$addresses->execute([$plant_id, $unit_id]);
$addr_list = $addresses->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode    = $_POST['mode'] ?? 'new'; // 'new' atau 'update'
    $tag_sel = intval($_POST['tag_id_sel'] ?? 0);

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File CSV harus diupload!';
    } elseif ($tag_sel <= 0) {
        $error = 'Pilih tag terlebih dahulu!';
    } else {
        // Cek file extension
        $filename = $_FILES['csv_file']['name'];
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'csv') {
            $error = 'Hanya file .csv yang diperbolehkan!';
        } else {
            // Ambil tag_id dari nama file (angka sebelum tanda -)
            $file_tag = intval(explode('-', $filename)[0]);

            // Verify tag exists untuk plant/unit ini
            $chk = $conn->prepare("SELECT tag_id, deskripsi FROM pm_addresses WHERE tag_id=? AND plant_id=? AND unit_id=?");
            $chk->execute([$tag_sel, $plant_id, $unit_id]);
            $tag_info = $chk->fetch();

            if (!$tag_info) {
                $error = "Tag ID $tag_sel tidak ditemukan untuk plant/unit ini!";
            } else {
                $tmpfile = $_FILES['csv_file']['tmp_name'];
                $handle  = fopen($tmpfile, 'r');
                $inserted = 0; $skipped = 0; $bad = 0;

                // Jika mode update: hapus data lama dulu
                if ($mode === 'update') {
                    $conn->prepare("DELETE FROM pm_data WHERE tag_id=?")->execute([$tag_sel]);
                }

                $conn->beginTransaction();
                $stmt = $conn->prepare("INSERT IGNORE INTO pm_data (tag_id, timestamp, value) VALUES (?,?,?)");

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) { $bad++; continue; }
                    $csv_tag  = trim($row[0]);
                    $ts       = trim($row[1]);
                    $val      = trim($row[2]);

                    // Validasi
                    if (!is_numeric($val)) { $bad++; continue; }
                    if (!strtotime($ts))   { $bad++; continue; }

                    $stmt->execute([$tag_sel, date('Y-m-d H:i:s', strtotime($ts)), floatval($val)]);
                    $inserted++;
                }
                fclose($handle);
                $conn->commit();

                set_flash("Berhasil import $inserted baris data untuk Tag #{$tag_sel} — {$tag_info['deskripsi']}" . ($bad > 0 ? " ($bad baris dilewati)" : ''), 'success');
                redirect('parameter-monitoring');
            }
        }
    }
}

$plant_name = $conn->prepare("SELECT description FROM plants WHERE plant_id=?");
$plant_name->execute([$plant_id]); $plant_name = $plant_name->fetchColumn();
$unit_name  = $conn->prepare("SELECT unit_name FROM units WHERE unit_id=?");
$unit_name->execute([$unit_id]); $unit_name = $unit_name->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Data CSV - Parameter Monitoring</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .mode-card { border:2px solid var(--border-color); border-radius:12px; padding:16px; cursor:pointer; transition:all .2s; }
        .mode-card:has(input:checked) { border-color:var(--accent-cyan); background:rgba(0,217,255,.06); }
        .mode-card input { margin-right:10px; accent-color:var(--accent-cyan); }
        .drop-zone { border:2px dashed var(--border-color); border-radius:12px; padding:40px; text-align:center; transition:all .2s; cursor:pointer; }
        .drop-zone:hover, .drop-zone.over { border-color:var(--accent-cyan); background:rgba(0,217,255,.04); }
        .drop-zone.has-file { border-color:#5dd87e; background:rgba(93,216,126,.06); }
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
                <div>
                    <h1 class="page-title" style="margin-bottom:3px">Upload Data CSV</h1>
                    <p style="color:var(--text-secondary);font-size:13px;margin:0">
                        <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($plant_name) ?> — <?= htmlspecialchars($unit_name) ?>
                    </p>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if (empty($addr_list)): ?>
            <div class="card">
                <div style="text-align:center;padding:40px;color:var(--text-secondary)">
                    <i class="bi bi-tags" style="font-size:40px;display:block;margin-bottom:12px;opacity:.4"></i>
                    <p>Belum ada address/tag untuk plant dan unit ini.</p>
                    <a href="?page=pm.tambah-address" class="btn btn-primary" style="margin-top:12px;display:inline-flex;gap:6px">
                        <i class="bi bi-plus-circle"></i> Tambah Address Dulu
                    </a>
                </div>
            </div>
            <?php else: ?>

            <div class="card" style="max-width:700px">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">

                    <!-- Pilih Mode -->
                    <div class="form-group">
                        <label style="margin-bottom:12px;display:block">Mode Upload *</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <label class="mode-card">
                                <input type="radio" name="mode" value="new" checked>
                                <strong><i class="bi bi-plus-circle" style="color:var(--accent-cyan)"></i> Tambah Data Baru</strong>
                                <p style="margin:6px 0 0 24px;font-size:12px;color:var(--text-secondary)">
                                    Menambahkan data ke data yang sudah ada (ignore duplikat)
                                </p>
                            </label>
                            <label class="mode-card">
                                <input type="radio" name="mode" value="update">
                                <strong><i class="bi bi-arrow-repeat" style="color:#f59e0b"></i> Update / Replace</strong>
                                <p style="margin:6px 0 0 24px;font-size:12px;color:var(--text-secondary)">
                                    Hapus semua data lama, ganti dengan data CSV ini
                                </p>
                            </label>
                        </div>
                    </div>

                    <!-- Pilih Tag -->
                    <div class="form-group">
                        <label>Pilih Tag / Parameter *</label>
                        <select name="tag_id_sel" id="tagSel" class="form-control" required>
                            <option value="">-- Pilih Tag --</option>
                            <?php foreach ($addr_list as $a): ?>
                            <option value="<?= $a['tag_id'] ?>" data-tag="<?= $a['tag_id'] ?>">
                                Tag #<?= $a['tag_id'] ?> — <?= htmlspecialchars($a['deskripsi']) ?> (<?= $a['satuan'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--text-secondary);font-size:12px;margin-top:4px;display:block">
                            <i class="bi bi-info-circle"></i>
                            Nama file CSV harus diawali dengan tag_id. Contoh: <code style="color:var(--accent-cyan)">85-idf2b-2026a.csv</code>
                        </small>
                    </div>

                    <!-- Drop Zone -->
                    <div class="form-group">
                        <label>File CSV *</label>
                        <div class="drop-zone" id="dropZone" onclick="document.getElementById('csvFile').click()">
                            <i class="bi bi-file-earmark-spreadsheet" style="font-size:36px;color:var(--accent-cyan);display:block;margin-bottom:8px"></i>
                            <p id="dropText" style="font-size:14px;margin:0">Klik atau drag & drop file CSV di sini</p>
                            <p id="dropInfo" style="font-size:12px;color:var(--text-secondary);margin:6px 0 0">
                                Format: tag_id, timestamp, value
                            </p>
                        </div>
                        <input type="file" name="csv_file" id="csvFile" accept=".csv" required style="display:none" onchange="onFileSelect(this)">
                    </div>

                    <!-- Preview format -->
                    <div style="background:var(--bg-secondary);border-radius:8px;padding:14px;margin-bottom:20px;font-size:12px">
                        <div style="color:var(--text-secondary);margin-bottom:6px"><i class="bi bi-code"></i> Contoh format CSV:</div>
                        <code style="color:var(--accent-cyan)">85,2026-01-01 00:00:00,0.6109375<br>85,2026-01-01 00:05:00,0.6109375<br>85,2026-01-01 00:10:00,0.7101560</code>
                    </div>

                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-upload" style="margin-right:6px"></i>Upload & Import
                        </button>
                        <a href="?page=parameter-monitoring" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function onFileSelect(input) {
    const file = input.files[0];
    if (!file) return;
    const dz = document.getElementById('dropZone');
    dz.classList.add('has-file');
    document.getElementById('dropText').innerHTML = `<strong style="color:#5dd87e"><i class="bi bi-check-circle-fill"></i> ${file.name}</strong>`;
    document.getElementById('dropInfo').textContent = `${(file.size/1024).toFixed(1)} KB`;

    // Auto-detect tag dari nama file
    const tagNum = parseInt(file.name.split('-')[0]);
    if (!isNaN(tagNum)) {
        const sel = document.getElementById('tagSel');
        for (let opt of sel.options) {
            if (opt.dataset.tag == tagNum) { opt.selected = true; break; }
        }
    }
}

// Drag & drop
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('over'));
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('over');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('csvFile').files = dt.files;
        onFileSelect(document.getElementById('csvFile'));
    }
});

document.getElementById('uploadForm').addEventListener('submit', function() {
    document.getElementById('submitBtn').innerHTML = '<span>⏳ Mengimport...</span>';
    document.getElementById('submitBtn').disabled = true;
});
</script>
</body>
</html>
