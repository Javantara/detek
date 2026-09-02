<?php
// ============================================================
// API: Machine Data Ingest
// Endpoint untuk menerima data real-time dari mesin/DCS/OPC-UA
//
// Cara pakai:
//   POST /?api=pm-machine-ingest
//   Header: X-API-Key: <your_api_key>
//   Body (JSON):
//   {
//     "plant_id": 1,
//     "unit_id": 1,
//     "data": [
//       { "tag_id": 85, "timestamp": "2026-02-27 08:05:00", "value": 0.821 },
//       { "tag_id": 85, "timestamp": "2026-02-27 08:10:00", "value": 0.834 }
//     ]
//   }
//
// Response:
//   { "success": true, "inserted": 2, "skipped": 0 }
//
// Untuk koneksi dari OPC-UA client, Modbus bridge, atau
// middleware DCS — tidak butuh login session, cukup API key.
// ============================================================

// ── Autentikasi API Key ───────────────────────────────────────
$raw_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

// API key disimpan di database tabel machine_api_keys
// Cek apakah API key valid
$stmt = $conn->prepare("
    SELECT k.*, u.unit_name, p.description as plant_name
    FROM machine_api_keys k
    JOIN units  u ON k.unit_id  = u.unit_id
    JOIN plants p ON k.plant_id = p.plant_id
    WHERE k.api_key = ? AND k.status = 'active'
    LIMIT 1
");
$stmt->execute([$raw_key]);
$key_row = $stmt->fetch();

if (!$key_row) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'API key tidak valid atau tidak aktif']);
    exit;
}

// ── Parse request body ────────────────────────────────────────
$body = file_get_contents('php://input');
$payload = json_decode($body, true);

// Support form-data juga (dari middleware simple)
if (!$payload && !empty($_POST)) {
    $payload = $_POST;
}

if (empty($payload['data']) || !is_array($payload['data'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Field data[] wajib diisi']);
    exit;
}

// Gunakan plant_id & unit_id dari API key (lebih aman, tidak dari payload)
$unit_id  = $key_row['unit_id'];
$plant_id = $key_row['plant_id'];

// Koneksi ke database unit
$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database unit tidak tersedia']);
    exit;
}

// ── Insert data ───────────────────────────────────────────────
$inserted = 0;
$skipped  = 0;
$errors   = [];
$batch    = [];

$unit_conn->beginTransaction();
try {
    foreach ($payload['data'] as $row) {
        $tag_id = intval($row['tag_id'] ?? 0);
        $ts_raw = trim($row['timestamp'] ?? '');
        $val    = floatval($row['value'] ?? 0);

        if (!$tag_id || !$ts_raw) { $skipped++; continue; }

        // Validasi tag ada di tag_master
        $chk = $unit_conn->prepare("SELECT tag_id FROM tag_master WHERE tag_id=?");
        $chk->execute([$tag_id]);
        if (!$chk->fetchColumn()) { $skipped++; $errors[] = "Tag #$tag_id tidak terdaftar"; continue; }

        // Parse & validasi timestamp
        $ts = date('Y-m-d H:i:s', strtotime($ts_raw));
        if (!$ts || $ts === '1970-01-01 07:00:00') { $skipped++; continue; }

        $batch[] = [$tag_id, $ts, $val];

        // Batch insert tiap 200 baris
        if (count($batch) >= 200) {
            $ph   = implode(',', array_fill(0, count($batch), '(?,?,?)'));
            $flat = array_merge(...$batch);
            $unit_conn->prepare("INSERT IGNORE INTO tag_data (tag_id,timestamp,value) VALUES $ph")->execute($flat);
            $inserted += count($batch);
            $batch = [];
        }
    }

    // Sisa batch
    if (!empty($batch)) {
        $ph   = implode(',', array_fill(0, count($batch), '(?,?,?)'));
        $flat = array_merge(...$batch);
        $stmt = $unit_conn->prepare("INSERT IGNORE INTO tag_data (tag_id,timestamp,value) VALUES $ph");
        $stmt->execute($flat);
        $inserted += $stmt->rowCount();
    }

    $unit_conn->commit();

    // Log ke machine_api_logs
    $conn->prepare("
        INSERT INTO machine_api_logs (key_id, unit_id, inserted, skipped, ip, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([$key_row['key_id'], $unit_id, $inserted, $skipped, $_SERVER['REMOTE_ADDR'] ?? '']);

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'errors'   => array_slice(array_unique($errors), 0, 10),
        'message'  => "OK - $inserted data masuk"
    ]);

} catch (Exception $e) {
    $unit_conn->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
}
