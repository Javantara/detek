<?php
require_login();
require_role(['superadmin','admin']);

$unit_id = intval($_SESSION['selected_unit_id'] ?? 0);
$tag_id  = intval($_POST['tag_id'] ?? 0);

if (!$tag_id) { echo json_encode(['success'=>false,'message'=>'tag_id wajib']); exit; }

$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) { echo json_encode(['success'=>false,'message'=>'Database unit tidak tersedia']); exit; }

try {
    // Cascade: tag_data akan terhapus otomatis (FK ON DELETE CASCADE)
    $stmt = $unit_conn->prepare("DELETE FROM tag_master WHERE tag_id=?");
    $stmt->execute([$tag_id]);
    echo json_encode(['success'=>true,'message'=>'Address dan semua data berhasil dihapus']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
}
