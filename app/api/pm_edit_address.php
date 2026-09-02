<?php
require_login();
require_role(['superadmin','admin']);

$unit_id   = intval($_SESSION['selected_unit_id'] ?? 0);
$tag_id    = intval($_POST['tag_id'] ?? 0);
$address_no= trim($_POST['address_no'] ?? '');
$deskripsi = trim($_POST['deskripsi'] ?? '');
$satuan    = trim($_POST['satuan'] ?? '');

if (!$tag_id || !$address_no || !$deskripsi) {
    echo json_encode(['success'=>false,'message'=>'Field tidak lengkap']); exit;
}

$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) { echo json_encode(['success'=>false,'message'=>'Database unit tidak tersedia']); exit; }

try {
    $stmt = $unit_conn->prepare("UPDATE tag_master SET address_no=?, deskripsi=?, satuan=?, updated_at=NOW() WHERE tag_id=?");
    $stmt->execute([$address_no, $deskripsi, $satuan, $tag_id]);
    echo json_encode(['success'=>true,'message'=>'Address berhasil diperbarui']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
}
