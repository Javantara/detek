<?php
// Dipanggil lewat: POST index.php?api=toggle-status
$data   = json_decode(file_get_contents('php://input'), true);
$type   = $data['type']   ?? '';
$id     = $data['id']     ?? 0;
$status = $data['status'] ?? 0;

if (!in_array($type, ['plant', 'unit']) || !is_numeric($id) || !in_array($status, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

if ($type === 'plant') {
    $stmt = $conn->prepare("UPDATE plants SET status = ? WHERE plant_id = ?");
} else {
    $stmt = $conn->prepare("UPDATE units SET status = ? WHERE unit_id = ?");
}
$stmt->execute([$status, $id]);

echo json_encode([
    'success' => true,
    'message' => ucfirst($type) . ' berhasil diubah menjadi ' . ($status ? 'aktif' : 'nonaktif')
]);
