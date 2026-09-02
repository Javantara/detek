<?php
// ============================================================
// API: Ambil daftar address/tag dari database unit terpisah
// ============================================================
require_login();

$unit_id = intval($_GET['unit_id'] ?? $_SESSION['selected_unit_id'] ?? 0);
if (!$unit_id) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Unit tidak dipilih']); exit;
}

$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Database unit belum tersedia']); exit;
}

$stmt = $unit_conn->query("
    SELECT m.*,
           (SELECT COUNT(*) FROM tag_data d WHERE d.tag_id = m.tag_id) as data_count,
           (SELECT MAX(timestamp) FROM tag_data d WHERE d.tag_id = m.tag_id) as last_update
    FROM tag_master m
    ORDER BY m.tag_id ASC
");
echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
