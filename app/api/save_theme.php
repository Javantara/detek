<?php
// Dipanggil lewat: POST index.php?api=save-theme
$data  = json_decode(file_get_contents('php://input'), true);
$theme = $data['theme'] ?? 'dark';

if (!in_array($theme, ['light', 'dark'])) $theme = 'dark';

$stmt = $conn->prepare("UPDATE users SET theme = ? WHERE user_id = ?");
$stmt->execute([$theme, $_SESSION['user_id']]);

echo json_encode(['success' => true, 'message' => 'Theme saved']);
