<?php
// ============================================================
// API: Upload Data CSV ke database unit terpisah
// CSV format: tag_id, timestamp, value
// Tag otomatis terdeteksi dari tag_master di database unit
// ============================================================
require_login();
require_role(['superadmin', 'admin']);

$unit_id = intval($_SESSION['selected_unit_id'] ?? 0);
if (!$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Sesi unit tidak ditemukan']); exit;
}

// Koneksi ke database unit
$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) {
    echo json_encode(['success' => false, 'message' => 'Gagal koneksi ke database unit. Pastikan sudah upload Excel address.']); exit;
}

$mode = $_POST['mode'] ?? 'append'; // append | replace

if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File CSV gagal diupload']); exit;
}

$fname = $_FILES['csv_file']['name'];
if (strtolower(pathinfo($fname, PATHINFO_EXTENSION)) !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Hanya file .csv yang diperbolehkan']); exit;
}

// ── Deteksi tag_id dari nama file ────────────────────────────
// Format nama file: [tag_id]-[keterangan]-[tahun].csv
// Contoh: 85-idf2b-2026a.csv → tag_id = 85
$detected_tag = null;
if (preg_match('/^(\d+)[-_]/', $fname, $m)) {
    $detected_tag = intval($m[1]);
}

// Ambil tag_id dari POST (jika manual) atau dari deteksi file
$tag_id = intval($_POST['tag_id'] ?? $detected_tag ?? 0);

if (!$tag_id) {
    echo json_encode(['success' => false, 'message' => "Tag ID tidak terdeteksi dari nama file '$fname'. Format: [tag_id]-nama.csv"]); exit;
}

// ── Verifikasi tag ada di tag_master unit ini ─────────────────
$tag_check = $unit_conn->prepare("SELECT tag_id, deskripsi, satuan FROM tag_master WHERE tag_id = ?");
$tag_check->execute([$tag_id]);
$tag_info = $tag_check->fetch();

if (!$tag_info) {
    // Cari tag-tag yang tersedia untuk info error
    $available = $unit_conn->query("SELECT tag_id, deskripsi FROM tag_master ORDER BY tag_id LIMIT 10")->fetchAll();
    $available_str = implode(', ', array_map(fn($t) => "#{$t['tag_id']}", $available));
    echo json_encode([
        'success' => false,
        'message' => "Tag ID #$tag_id tidak terdaftar di unit ini. Upload Excel address terlebih dahulu. Tag tersedia: $available_str"
    ]);
    exit;
}

// ── Baca dan proses CSV ───────────────────────────────────────
$handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Tidak bisa membaca file']); exit;
}

// Deteksi header
$first = fgetcsv($handle);
$has_header = ($first && !is_numeric(trim($first[0] ?? '')));
if (!$has_header) rewind($handle);

if ($mode === 'replace') {
    $unit_conn->prepare("DELETE FROM tag_data WHERE tag_id = ?")->execute([$tag_id]);
}

$inserted = 0;
$skipped  = 0;
$batch    = [];

$unit_conn->beginTransaction();
try {
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 3) { $skipped++; continue; }

        $csv_tag = intval(trim($row[0]));
        $ts_raw  = trim($row[1]);
        $val     = floatval(trim($row[2]));

        // Validasi tag sesuai
        if ($csv_tag !== $tag_id) { $skipped++; continue; }

        $ts = strtotime($ts_raw);
        if (!$ts_raw || $ts === false) { $skipped++; continue; }
        $ts_fmt = date('Y-m-d H:i:s', $ts);

        $batch[] = [$tag_id, $ts_fmt, $val];

        if (count($batch) >= 500) {
            insert_batch($unit_conn, $batch);
            $inserted += count($batch);
            $batch = [];
        }
    }

    if (!empty($batch)) {
        insert_batch($unit_conn, $batch);
        $inserted += count($batch);
    }

    $unit_conn->commit();
    fclose($handle);

    $total_tag = $unit_conn->prepare("SELECT COUNT(*) FROM tag_data WHERE tag_id = ?");
    $total_tag->execute([$tag_id]);
    $total = $total_tag->fetchColumn();

    echo json_encode([
        'success'    => true,
        'tag_id'     => $tag_id,
        'tag_desc'   => $tag_info['deskripsi'],
        'inserted'   => $inserted,
        'skipped'    => $skipped,
        'total'      => $total,
        'message'    => "✅ Tag #{$tag_id} ({$tag_info['deskripsi']}): $inserted data diimport" .
                        ($skipped ? ", $skipped dilewati" : "") .
                        ". Total: " . number_format($total) . " data."
    ]);

} catch (Exception $e) {
    $unit_conn->rollBack();
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function insert_batch(PDO $db, array $batch): void {
    $ph   = implode(',', array_fill(0, count($batch), '(?,?,?)'));
    $flat = array_merge(...$batch);
    $db->prepare("INSERT IGNORE INTO tag_data (tag_id, timestamp, value) VALUES $ph")->execute($flat);
}
