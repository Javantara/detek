<?php
require_login();
$slugs = $_POST['slugs'] ?? [];
if (!is_array($slugs)) $slugs = [];
// Maks 8
$slugs = array_slice(array_values($slugs), 0, 8);
$json  = json_encode($slugs);
$conn->prepare("UPDATE users SET quick_access = ? WHERE user_id = ?")
     ->execute([$json, $_SESSION['user_id']]);
echo json_encode(['success' => true]);
