<?php
require_login();
require_role('superadmin');

$unit_id  = intval($_POST['unit_id']  ?? $_SESSION['selected_unit_id']  ?? 0);
$plant_id = intval($_POST['plant_id'] ?? $_SESSION['selected_plant_id'] ?? 0);
if (!$unit_id || !$plant_id) { echo json_encode(['success'=>false,'message'=>'unit_id & plant_id wajib diisi']); exit; }

// Nonaktifkan key lama
$conn->prepare("UPDATE machine_api_keys SET status='inactive' WHERE unit_id=?")->execute([$unit_id]);

// Generate key baru yang aman
$raw_key = bin2hex(random_bytes(32)); // 64 karakter hex

$conn->prepare("INSERT INTO machine_api_keys (plant_id,unit_id,api_key,label,status,created_by) VALUES (?,?,?,?,?,?)")
     ->execute([$plant_id, $unit_id, $raw_key, 'Auto-generated ' . date('d/m/Y H:i'), 'active', $_SESSION['user_id']]);

echo json_encode(['success'=>true,'api_key'=>$raw_key]);
