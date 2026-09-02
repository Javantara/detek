<?php
// ============================================================
// API: Machine Connection Status
// GET /?api=pm-machine-status  (butuh login)
// Menampilkan status koneksi mesin dan data terakhir
// ============================================================
require_login();
$unit_id = intval($_SESSION['selected_unit_id'] ?? 0);

$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) {
    echo json_encode(['success'=>false,'message'=>'DB unit tidak tersedia']); exit;
}

// Ambil API key aktif untuk unit ini
$key_stmt = $conn->prepare("SELECT * FROM machine_api_keys WHERE unit_id=? AND status='active' ORDER BY created_at DESC LIMIT 1");
$key_stmt->execute([$unit_id]);
$api_key = $key_stmt->fetch();

// Statistik data terakhir masuk
$last_stmt = $unit_conn->prepare("
    SELECT tag_id, MAX(timestamp) as last_ts, COUNT(*) as total_today
    FROM tag_data
    WHERE DATE(timestamp) = CURDATE()
    GROUP BY tag_id
");
$last_stmt->execute();
$today_stats = $last_stmt->fetchAll();

// Log koneksi terakhir
$log_stmt = $conn->prepare("
    SELECT * FROM machine_api_logs WHERE unit_id=? ORDER BY created_at DESC LIMIT 5
");
$log_stmt->execute([$unit_id]);
$logs = $log_stmt->fetchAll();

echo json_encode([
    'success'      => true,
    'has_api_key'  => !empty($api_key),
    'api_key_id'   => $api_key['key_id'] ?? null,
    'today_stats'  => $today_stats,
    'recent_logs'  => $logs,
    'server_time'  => date('Y-m-d H:i:s'),
]);
