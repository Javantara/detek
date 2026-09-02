<?php
// ============================================================
// API: Upload Excel (.xlsx) untuk daftar address/tag
// Menggantikan form manual "Tambah Address Baru"
// Excel format: address_no | tag_id | deskripsi | satuan
// ============================================================
require_login();
require_role(['superadmin', 'admin']);

$unit_id  = intval($_SESSION['selected_unit_id']  ?? 0);
$plant_id = intval($_SESSION['selected_plant_id'] ?? 0);

if (!$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Sesi unit tidak ditemukan']); exit;
}

if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File Excel gagal diupload']); exit;
}

$fname = $_FILES['excel_file']['name'];
$ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'])) {
    echo json_encode(['success' => false, 'message' => 'Hanya file .xlsx atau .xls yang diperbolehkan']); exit;
}

// Cek library PhpSpreadsheet
$autoload_paths = [
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    '/var/www/html/vendor/autoload.php',
];
$autoload_found = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoload_found = true;
        break;
    }
}

if (!$autoload_found || !class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
    // Fallback: parse xlsx manual menggunakan ZipArchive + SimpleXML
    $result = parse_xlsx_manual($_FILES['excel_file']['tmp_name']);
} else {
    $result = parse_xlsx_phpspreadsheet($_FILES['excel_file']['tmp_name']);
}

if (!$result['success']) {
    echo json_encode($result); exit;
}

$rows = $result['rows'];
if (empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'File Excel kosong atau tidak ada data valid']); exit;
}

// Koneksi ke database unit
$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) {
    echo json_encode(['success' => false, 'message' => 'Gagal koneksi ke database unit']); exit;
}

// Mode: replace semua atau append
$mode = $_POST['mode'] ?? 'append';

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$errors   = [];

$unit_conn->beginTransaction();
try {
    if ($mode === 'replace') {
        // Hapus semua tag_master & tag_data untuk unit ini
        $unit_conn->exec("DELETE FROM tag_data");
        $unit_conn->exec("DELETE FROM tag_master");
    }

    $stmt_check = $unit_conn->prepare("SELECT tag_id FROM tag_master WHERE tag_id = ?");
    $stmt_insert = $unit_conn->prepare("
        INSERT INTO tag_master (tag_id, address_no, deskripsi, satuan) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE address_no=VALUES(address_no), deskripsi=VALUES(deskripsi), satuan=VALUES(satuan), updated_at=NOW()
    ");

    foreach ($rows as $i => $row) {
        $address_no = trim($row['address_no'] ?? '');
        $tag_id     = intval($row['tag_id'] ?? 0);
        $deskripsi  = trim($row['deskripsi'] ?? '');
        $satuan     = trim($row['satuan'] ?? '');

        if (!$tag_id || !$address_no || !$deskripsi) {
            $skipped++;
            continue;
        }

        // Cek apakah sudah ada
        $stmt_check->execute([$tag_id]);
        $exists = $stmt_check->fetchColumn();

        $stmt_insert->execute([$tag_id, $address_no, $deskripsi, $satuan]);

        if ($exists) $updated++; else $inserted++;
    }

    $unit_conn->commit();

    // Simpan path file Excel ke tabel units
    $upload_dir = __DIR__ . '/../../public/uploads/excel/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $save_name = 'unit_' . $unit_id . '_address.xlsx';
    move_uploaded_file($_FILES['excel_file']['tmp_name'], $upload_dir . $save_name);
    $conn->prepare("UPDATE units SET excel_path = ? WHERE unit_id = ?")
         ->execute([$save_name, $unit_id]);

    $total = $unit_conn->query("SELECT COUNT(*) FROM tag_master")->fetchColumn();

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'total'    => $total,
        'message'  => "Berhasil: $inserted tag baru, $updated diperbarui, $skipped dilewati. Total: $total tag terdaftar."
    ]);

} catch (PDOException $e) {
    $unit_conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}

// ── Parse XLSX tanpa library (ZipArchive + SimpleXML) ─────────
function parse_xlsx_manual(string $tmp_path): array {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'message' => 'ZipArchive tidak tersedia. Install php-zip.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp_path) !== true) {
        return ['success' => false, 'message' => 'Tidak bisa membuka file Excel'];
    }

    // Baca shared strings
    $shared_strings = [];
    $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss_xml) {
        $ss = simplexml_load_string($ss_xml);
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $shared_strings[] = (string)$si->t;
            } else {
                $str = '';
                foreach ($si->r as $r) $str .= (string)($r->t ?? '');
                $shared_strings[] = $str;
            }
        }
    }

    // Baca sheet pertama
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$sheet_xml) {
        return ['success' => false, 'message' => 'Sheet pertama tidak ditemukan'];
    }

    $sheet = simplexml_load_string($sheet_xml);
    $rows_raw = [];

    foreach ($sheet->sheetData->row as $row) {
        $row_data = [];
        foreach ($row->c as $cell) {
            $col_ref = preg_replace('/\d/', '', (string)$cell['r']);
            $col_idx = col_ref_to_index($col_ref);
            $t = (string)($cell['t'] ?? '');
            $v = (string)($cell->v ?? '');

            if ($t === 's') {
                $val = $shared_strings[(int)$v] ?? '';
            } elseif ($t === 'str' || $t === 'inlineStr') {
                $val = (string)($cell->is->t ?? $v);
            } else {
                $val = $v;
            }
            $row_data[$col_idx] = $val;
        }
        $rows_raw[] = $row_data;
    }

    if (empty($rows_raw)) {
        return ['success' => false, 'message' => 'Tidak ada data di sheet'];
    }

    // Deteksi header (baris pertama)
    $header = $rows_raw[0];
    $col_map = [];
    foreach ($header as $idx => $h) {
        $h_lower = strtolower(trim($h));
        if (str_contains($h_lower, 'address')) $col_map['address_no'] = $idx;
        elseif ($h_lower === 'tag_id' || $h_lower === 'tag id') $col_map['tag_id'] = $idx;
        elseif (str_contains($h_lower, 'deskripsi') || str_contains($h_lower, 'description')) $col_map['deskripsi'] = $idx;
        elseif (str_contains($h_lower, 'satuan') || str_contains($h_lower, 'unit')) $col_map['satuan'] = $idx;
    }

    // Fallback: urutan kolom jika header tidak dikenali
    if (empty($col_map)) {
        $col_map = ['address_no' => 0, 'tag_id' => 1, 'deskripsi' => 2, 'satuan' => 3];
        $start = 0;
    } else {
        $start = 1;
    }

    $rows = [];
    for ($i = $start; $i < count($rows_raw); $i++) {
        $r = $rows_raw[$i];
        $tag_id_raw = $r[$col_map['tag_id']] ?? '';
        if (!is_numeric($tag_id_raw)) continue;
        $rows[] = [
            'address_no' => $r[$col_map['address_no']] ?? '',
            'tag_id'     => intval($tag_id_raw),
            'deskripsi'  => $r[$col_map['deskripsi']] ?? '',
            'satuan'     => $r[$col_map['satuan']] ?? '',
        ];
    }

    return ['success' => true, 'rows' => $rows];
}

function col_ref_to_index(string $ref): int {
    $ref = strtoupper($ref);
    $idx = 0;
    for ($i = 0; $i < strlen($ref); $i++) {
        $idx = $idx * 26 + (ord($ref[$i]) - ord('A') + 1);
    }
    return $idx - 1;
}

// ── Parse XLSX dengan PhpSpreadsheet ─────────────────────────
function parse_xlsx_phpspreadsheet(string $tmp_path): array {
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_path);
        $ws = $spreadsheet->getActiveSheet();
        $rows_raw = $ws->toArray(null, true, true, false);

        if (empty($rows_raw)) return ['success' => false, 'message' => 'File kosong'];

        $header  = array_map('strtolower', array_map('trim', $rows_raw[0]));
        $col_map = [];
        foreach ($header as $i => $h) {
            if (str_contains($h, 'address')) $col_map['address_no'] = $i;
            elseif ($h === 'tag_id' || $h === 'tag id') $col_map['tag_id'] = $i;
            elseif (str_contains($h, 'deskripsi') || str_contains($h, 'description')) $col_map['deskripsi'] = $i;
            elseif (str_contains($h, 'satuan') || str_contains($h, 'unit')) $col_map['satuan'] = $i;
        }
        if (empty($col_map)) {
            $col_map = ['address_no' => 0, 'tag_id' => 1, 'deskripsi' => 2, 'satuan' => 3];
            $start = 0;
        } else {
            $start = 1;
        }

        $rows = [];
        for ($i = $start; $i < count($rows_raw); $i++) {
            $r = $rows_raw[$i];
            if (!is_numeric($r[$col_map['tag_id']] ?? '')) continue;
            $rows[] = [
                'address_no' => $r[$col_map['address_no']] ?? '',
                'tag_id'     => intval($r[$col_map['tag_id']]),
                'deskripsi'  => $r[$col_map['deskripsi']] ?? '',
                'satuan'     => $r[$col_map['satuan']] ?? '',
            ];
        }
        return ['success' => true, 'rows' => $rows];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Error baca Excel: ' . $e->getMessage()];
    }
}
