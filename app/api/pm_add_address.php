<?php
require_login();
require_role(['superadmin','admin']);

$plant_id   = intval($_POST['plant_id']  ?? 0);
$unit_id    = intval($_POST['unit_id']   ?? 0);
$tag_id     = intval($_POST['tag_id']    ?? 0);
$address_no = trim($_POST['address_no'] ?? '');
$deskripsi  = trim($_POST['deskripsi']  ?? '');
$satuan     = trim($_POST['satuan']     ?? '');

if (!$plant_id || !$unit_id || !$tag_id || !$address_no || !$deskripsi) {
    echo json_encode(['success'=>false,'message'=>'Semua field wajib diisi']); exit;
}

// Cek duplikat tag_id
$chk = $conn->prepare("SELECT tag_id FROM pm_addresses WHERE tag_id=?");
$chk->execute([$tag_id]);
if ($chk->fetchColumn()) {
    echo json_encode(['success'=>false,'message'=>"Tag ID $tag_id sudah terdaftar di sistem"]); exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO pm_addresses (plant_id,unit_id,address_no,tag_id,deskripsi,satuan) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$plant_id,$unit_id,$address_no,$tag_id,$deskripsi,$satuan]);
    echo json_encode(['success'=>true,'address_id'=>$conn->lastInsertId(),'message'=>'Address berhasil ditambahkan']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
}
