<?php
require_login();
require_role(['superadmin','admin']);

$unit_id   = intval($_SESSION['selected_unit_id'] ?? 0);
$tag_id    = intval($_POST['tag_id'] ?? 0);
$date_from = $_POST['date_from'] ?? '';
$date_to   = $_POST['date_to']   ?? '';

if (!$tag_id || !$date_from || !$date_to) {
    echo json_encode(['success'=>false,'message'=>'Parameter tidak lengkap']); exit;
}

$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) { echo json_encode(['success'=>false,'message'=>'Database unit tidak tersedia']); exit; }

try {
    $stmt = $unit_conn->prepare("DELETE FROM tag_data WHERE tag_id=? AND DATE(timestamp) BETWEEN ? AND ?");
    $stmt->execute([$tag_id, $date_from, $date_to]);
    echo json_encode(['success'=>true,'deleted'=>$stmt->rowCount()]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
}
