<?php
require_role(['superadmin','admin']);
if (!isset($conn)) require_once APP . 'config/database.php';

$role = $_SESSION['role'] ?? 'user';
$user_id = intval($_SESSION['user_id'] ?? 0);
$selected_unit_id = intval($_SESSION['selected_unit_id'] ?? 0);
$message = '';
$message_type = '';

function upload_unit_options(PDO $conn, string $role, int $user_id, int $selected_unit_id): array {
    if ($role === 'superadmin') {
        return $conn->query("SELECT u.unit_id, u.unit_name, u.database_name, p.description AS plant_name
                             FROM units u JOIN plants p ON p.plant_id=u.plant_id
                             WHERE (u.status=1 OR u.status='active') AND u.database_name IS NOT NULL AND u.database_name <> ''
                             ORDER BY p.description, u.unit_name")->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $conn->prepare("SELECT all_access, assigned_units FROM users WHERE user_id=?");
    $st->execute([$user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($u['all_access'])) {
        return $conn->query("SELECT u.unit_id, u.unit_name, u.database_name, p.description AS plant_name
                             FROM units u JOIN plants p ON p.plant_id=u.plant_id
                             WHERE (u.status=1 OR u.status='active') AND u.database_name IS NOT NULL AND u.database_name <> ''
                             ORDER BY p.description, u.unit_name")->fetchAll(PDO::FETCH_ASSOC);
    }
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)($u['assigned_units'] ?? '')))));
    if ($selected_unit_id && !in_array($selected_unit_id, $ids, true)) $ids[] = $selected_unit_id;
    if (empty($ids)) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $q = $conn->prepare("SELECT u.unit_id, u.unit_name, u.database_name, p.description AS plant_name
                         FROM units u JOIN plants p ON p.plant_id=u.plant_id
                         WHERE u.unit_id IN ($ph) AND (u.status=1 OR u.status='active')
                         ORDER BY p.description, u.unit_name");
    $q->execute($ids);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function unit_db_by_id(PDO $conn, int $unit_id): PDO {
    $st = $conn->prepare("SELECT database_name FROM units WHERE unit_id=?");
    $st->execute([$unit_id]);
    $db = trim((string)$st->fetchColumn());
    if ($db === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $db)) throw new Exception('Database unit belum valid.');
    return new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.$db.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>true]);
}

function ensure_upload_tables(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS sensor (
        tagno VARCHAR(10) NOT NULL PRIMARY KEY,
        deskripsi VARCHAR(255) NULL,
        unit VARCHAR(50) NULL,
        plant VARCHAR(100) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS aktual (
        tagno VARCHAR(10) NOT NULL,
        data_time DATETIME NOT NULL,
        nilai FLOAT NULL,
        PRIMARY KEY (tagno, data_time),
        INDEX idx_aktual_time (data_time),
        INDEX idx_aktual_tag_time (tagno, data_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function normalize_date_time(string $s): ?string {
    $s = trim($s, " \t\r\n\"'");
    if ($s === '') return null;
    $t = strtotime($s);
    if ($t === false) return null;
    return date('Y-m-d H:i:s', $t);
}

function looks_like_time(string $s): bool {
    return normalize_date_time($s) !== null && preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $s);
}

function process_sensor_csv(PDO $db, string $path): array {
    $fh = fopen($path, 'r');
    if (!$fh) throw new Exception('File sensor tidak bisa dibuka.');
    $st = $db->prepare("INSERT INTO sensor (tagno, deskripsi, unit, plant)
                        VALUES (?,?,?,?)
                        ON DUPLICATE KEY UPDATE deskripsi=VALUES(deskripsi), unit=VALUES(unit), plant=VALUES(plant)");
    $ok = 0; $skip = 0; $line = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $line++;
        if (count($row) < 2) { $skip++; continue; }
        $first = strtolower(trim((string)$row[0]));
        if ($line === 1 && in_array($first, ['tagno','tag_id','tag','id'], true)) continue;
        $tag = preg_replace('/\D+/', '', trim((string)$row[0]));
        if ($tag === '') { $skip++; continue; }
        $desc = trim((string)($row[1] ?? 'Sensor '.$tag));
        $unit = trim((string)($row[2] ?? ''));
        $plant = trim((string)($row[3] ?? 'Pacitan'));
        $st->execute([$tag, $desc, $unit, $plant]);
        $ok++;
    }
    fclose($fh);
    return ['ok'=>$ok,'skip'=>$skip];
}

function process_actual_csv(PDO $db, string $path, ?string $tagFromName = null): array {
    $fh = fopen($path, 'r');
    if (!$fh) throw new Exception('File aktual tidak bisa dibuka.');
    $st = $db->prepare("INSERT INTO aktual (tagno, data_time, nilai)
                        VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)");
    $ok = 0; $skip = 0; $line = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $line++;
        if (count($row) < 2) { $skip++; continue; }
        $r0 = trim((string)($row[0] ?? ''));
        $r1 = trim((string)($row[1] ?? ''));
        $r2 = trim((string)($row[2] ?? ''));
        if ($line === 1 && preg_match('/tag|time|waktu|nilai|value/i', implode(',', $row))) {
            // Kalau header, lewati hanya jika tidak mengandung data waktu nyata.
            if (!looks_like_time($r0) && !looks_like_time($r1)) continue;
        }
        $tag = $tagFromName ?: preg_replace('/\D+/', '', $r0);
        $time = null; $value = null;
        if (looks_like_time($r1) && is_numeric($r2)) {
            $tag = preg_replace('/\D+/', '', $r0) ?: ($tagFromName ?: '');
            $time = normalize_date_time($r1);
            $value = (float)$r2;
        } elseif (looks_like_time($r0) && is_numeric($r1)) {
            $time = normalize_date_time($r0);
            $value = (float)$r1;
        } elseif (looks_like_time($r1) && isset($row[2]) && is_numeric($row[2])) {
            $time = normalize_date_time($r1);
            $value = (float)$row[2];
        }
        if ($tag === '' || !$time || $value === null) { $skip++; continue; }
        $st->execute([$tag, $time, $value]);
        $ok++;
    }
    fclose($fh);
    return ['ok'=>$ok,'skip'=>$skip];
}

function process_actual_upload(PDO $db, string $tmp, string $orig): array {
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $totalOk = 0; $totalSkip = 0; $files = 0;
    if ($ext === 'zip') {
        if (!class_exists('ZipArchive')) throw new Exception('PHP ZipArchive belum aktif. Aktifkan extension zip di XAMPP atau upload file CSV satu-satu.');
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) throw new Exception('ZIP tidak bisa dibuka.');
        $dir = sys_get_temp_dir() . '/pln_upload_' . uniqid();
        @mkdir($dir, 0777, true);
        $zip->extractTo($dir);
        $zip->close();
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($rii as $file) {
            if (!$file->isFile()) continue;
            if (strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) !== 'csv') continue;
            $tag = null;
            if (preg_match('/^(\d+)/', $file->getFilename(), $m)) $tag = $m[1];
            $res = process_actual_csv($db, $file->getPathname(), $tag);
            $totalOk += $res['ok']; $totalSkip += $res['skip']; $files++;
        }
    } elseif ($ext === 'csv') {
        $tag = null;
        if (preg_match('/^(\d+)/', basename($orig), $m)) $tag = $m[1];
        $res = process_actual_csv($db, $tmp, $tag);
        $totalOk += $res['ok']; $totalSkip += $res['skip']; $files = 1;
    } else {
        throw new Exception('Untuk data aktual, upload .csv atau .zip saja.');
    }
    return ['files'=>$files,'ok'=>$totalOk,'skip'=>$totalSkip];
}

$unit_options = upload_unit_options($conn, $role, $user_id, $selected_unit_id);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        @set_time_limit(0);
        $unit_id = intval($_POST['unit_id'] ?? 0);
        $jenis = $_POST['jenis_upload'] ?? 'aktual';
        if ($unit_id <= 0) throw new Exception('Pilih unit dulu.');
        if (!isset($_FILES['file_upload']) || $_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) throw new Exception('File belum dipilih.');
        $db = unit_db_by_id($conn, $unit_id);
        ensure_upload_tables($db);
        $tmp = $_FILES['file_upload']['tmp_name'];
        $orig = $_FILES['file_upload']['name'];
        if ($jenis === 'sensor') {
            if (strtolower(pathinfo($orig, PATHINFO_EXTENSION)) !== 'csv') throw new Exception('Master sensor harus CSV.');
            $res = process_sensor_csv($db, $tmp);
            $message = "Sensor berhasil masuk/update: {$res['ok']} baris. Dilewati: {$res['skip']} baris.";
        } else {
            $res = process_actual_upload($db, $tmp, $orig);
            $message = "Data aktual berhasil masuk/update: {$res['ok']} baris dari {$res['files']} file CSV. Dilewati: {$res['skip']} baris.";
        }
        $message_type = 'ok';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $message_type = 'err';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload CSV Baru</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<style>
    .upload-wrap{max-width:980px;display:flex;flex-direction:column;gap:18px}.panelx{background:var(--bg-card);border:1px solid var(--border-color);border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}.page-titlex{font-size:28px;font-weight:900;margin:0 0 8px;color:var(--text-primary);display:flex;gap:10px;align-items:center}.page-titlex i{color:var(--accent-cyan)}.muted{color:var(--text-secondary)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field label{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:7px}.input,.select{width:100%;height:50px;background:var(--input-bg);border:1.5px solid var(--border-color);border-radius:13px;color:var(--text-primary);padding:0 14px;font-size:15px;font-weight:700;outline:none}.drop{border:2px dashed var(--border-color);border-radius:16px;padding:36px;text-align:center;cursor:pointer;background:rgba(0,217,255,.03)}.drop:hover{border-color:var(--accent-cyan)}.btnx{height:46px;border:none;border-radius:13px;padding:0 20px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-size:15px;background:var(--accent-cyan);color:#061220}.notice{border-radius:14px;padding:14px 16px}.ok{border:1px solid rgba(33,208,122,.25);background:rgba(33,208,122,.10);color:#7ef0ae}.err{border:1px solid rgba(255,91,110,.25);background:rgba(255,91,110,.10);color:#ff9aa6}.help{font-size:13px;line-height:1.7;color:var(--text-secondary)}code{color:var(--accent-cyan)}@media(max-width:760px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="layout">
<?php include VIEWS . 'shared/sidebar.php'; ?>
<div class="main-content">
<?php include VIEWS . 'shared/header.php'; ?>
<div class="content upload-wrap">
    <div class="panelx">
        <h1 class="page-titlex"><i class="bi bi-cloud-upload"></i>Upload CSV Baru</h1>
        <div class="muted">Menu ini hanya untuk <b>sensor</b> dan <b>data aktual</b>. Prediksi XGBoost dan Deep Learning dibuat dari tombol <b>Prediksi AI</b> di menu Deteksi Anomali.</div>
    </div>

    <?php if ($message): ?><div class="notice <?= $message_type === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="panelx">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="grid">
                <div class="field">
                    <label>Unit</label>
                    <select name="unit_id" class="select" required>
                        <?php foreach ($unit_options as $u): ?>
                        <option value="<?= (int)$u['unit_id'] ?>" <?= ((int)$u['unit_id']===$selected_unit_id)?'selected':'' ?>><?= htmlspecialchars(($u['plant_name'] ?? '-') . ' - ' . ($u['unit_name'] ?? '-')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Jenis Upload</label>
                    <select name="jenis_upload" id="jenisUpload" class="select" onchange="changeHelp()">
                        <option value="aktual">Data Aktual</option>
                        <option value="sensor">Master Sensor</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:16px">
                <div class="drop" onclick="document.getElementById('fileUpload').click()">
                    <i class="bi bi-file-earmark-spreadsheet" style="font-size:42px;color:var(--accent-cyan);display:block;margin-bottom:8px"></i>
                    <div id="fileText">Klik untuk pilih file CSV/ZIP</div>
                    <div class="muted" style="font-size:12px;margin-top:6px" id="fileHelp">Aktual boleh .csv atau .zip data_2025_pisah.zip</div>
                </div>
                <input type="file" name="file_upload" id="fileUpload" accept=".csv,.zip" style="display:none" required onchange="pickFile(this)">
            </div>

            <button type="submit" class="btnx" style="margin-top:16px" id="submitBtn"><i class="bi bi-upload"></i>Upload & Masukkan ke Database</button>
        </form>
    </div>

    <div class="panelx help">
        <b>Format yang diterima:</b><br>
        <span id="helpText">Data aktual: <code>tagno, data_time, nilai</code> atau file ZIP seperti <code>data_2025_pisah.zip</code>.</span><br><br>
        <b>Masuk ke tabel:</b><br>
        Master Sensor → <code>sensor</code><br>
        Data Aktual → <code>aktual</code><br>
        XGBoost → <code>XGBoost__prediksi</code>, dibuat dari tombol Prediksi AI<br>
        Deep Learning → <code>Deep_Learning__prediksi_autoencoder</code>, dibuat dari tombol Prediksi AI
    </div>
</div>
</div>
</div>
<script>
function pickFile(input){
    const f = input.files[0];
    if(!f) return;
    document.getElementById('fileText').innerHTML = `<b style="color:#7ef0ae">${f.name}</b><br><small>${(f.size/1024/1024).toFixed(2)} MB</small>`;
}
function changeHelp(){
    const jenis = document.getElementById('jenisUpload').value;
    const file = document.getElementById('fileUpload');
    const help = document.getElementById('helpText');
    const small = document.getElementById('fileHelp');
    if(jenis === 'sensor'){
        file.setAttribute('accept','.csv');
        small.textContent = 'Sensor hanya CSV';
        help.innerHTML = 'Master sensor: <code>tagno, deskripsi, unit, plant</code>.';
    } else {
        file.setAttribute('accept','.csv,.zip');
        small.textContent = 'Aktual boleh .csv atau .zip data_2025_pisah.zip';
        help.innerHTML = 'Data aktual: <code>tagno, data_time, nilai</code> atau file ZIP seperti <code>data_2025_pisah.zip</code>.';
    }
}
document.getElementById('uploadForm').addEventListener('submit', function(){
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-hourglass-split"></i> Sedang import... jangan ditutup';
    document.getElementById('submitBtn').disabled = true;
});
</script>
</body>
</html>
