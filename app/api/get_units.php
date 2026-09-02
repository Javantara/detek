<?php
// Dipanggil lewat: index.php?api=get-units&plant_id=...
// Fix: unit untuk admin/user difilter dari assigned_units, dan plant tetap muncul kalau unit memang ada di database.
header('Content-Type: application/json');

$plant_id = intval($_GET['plant_id'] ?? 0);
if ($plant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid plant ID', 'units' => []]);
    exit;
}

try {
    $role = $_SESSION['role'] ?? 'user';
    $user_id = intval($_SESSION['user_id'] ?? 0);
    $allowed_units = [];
    $all_access = false;

    if ($role !== 'superadmin' && $user_id > 0) {
        $st = $conn->prepare("SELECT all_access, assigned_units FROM users WHERE user_id = ?");
        $st->execute([$user_id]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $all_access = !empty($u['all_access']);
        if (!$all_access) {
            $allowed_units = array_values(array_filter(array_map('intval', explode(',', (string)($u['assigned_units'] ?? '')))));
            if (empty($allowed_units)) {
                echo json_encode(['success' => true, 'units' => []]);
                exit;
            }
        }
    } else {
        $all_access = true;
    }

    $sql = "SELECT unit_id, unit_name, plant_id, database_name
            FROM units
            WHERE plant_id = ? AND (status = 1 OR status = '1' OR status = 'active')";
    $params = [$plant_id];

    if (!$all_access && !empty($allowed_units)) {
        $placeholders = implode(',', array_fill(0, count($allowed_units), '?'));
        $sql .= " AND unit_id IN ($placeholders)";
        $params = array_merge($params, $allowed_units);
    }

    $sql .= " ORDER BY unit_name ASC, unit_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'units' => $units]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'units' => []]);
}
