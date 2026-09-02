<?php
require_login();
require_role('superadmin');

$key_id = intval($_POST['key_id'] ?? 0);
if (!$key_id) { echo json_encode(['success'=>false]); exit; }
$conn->prepare("UPDATE machine_api_keys SET status='inactive' WHERE key_id=?")->execute([$key_id]);
echo json_encode(['success'=>true]);
