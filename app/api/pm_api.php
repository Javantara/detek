<?php
// Parameter Monitoring API
require_login();

$action = $_GET['pm'] ?? '';

switch ($action) {

    // Ambil semua address untuk plant+unit
    case 'get-addresses':
        $plant_id = intval($_GET['plant_id'] ?? 0);
        $unit_id  = intval($_GET['unit_id']  ?? 0);
        $stmt = $conn->prepare("SELECT * FROM pm_address WHERE plant_id=? AND unit_id=? ORDER BY tag_id");
        $stmt->execute([$plant_id, $unit_id]);
        echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll()]);
        break;

    // Data grafik untuk satu address, filter by date range
    case 'get-chart-data':
        $address_id = intval($_GET['address_id'] ?? 0);
        $date_from  = $_GET['date_from'] ?? date('Y-m-01');
        $date_to    = $_GET['date_to']   ?? date('Y-m-d');
        $agg        = $_GET['agg'] ?? 'raw'; // raw, hour, day

        if ($agg === 'hour') {
            $sql = "SELECT DATE_FORMAT(recorded_at,'%Y-%m-%d %H:00:00') as t,
                           AVG(value) as v, MIN(value) as vmin, MAX(value) as vmax
                    FROM pm_data WHERE address_id=? AND recorded_at BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(recorded_at,'%Y-%m-%d %H:00:00')
                    ORDER BY t LIMIT 2000";
        } elseif ($agg === 'day') {
            $sql = "SELECT DATE_FORMAT(recorded_at,'%Y-%m-%d') as t,
                           AVG(value) as v, MIN(value) as vmin, MAX(value) as vmax
                    FROM pm_data WHERE address_id=? AND recorded_at BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(recorded_at,'%Y-%m-%d')
                    ORDER BY t LIMIT 2000";
        } else {
            $sql = "SELECT recorded_at as t, value as v
                    FROM pm_data WHERE address_id=? AND recorded_at BETWEEN ? AND ?
                    ORDER BY t LIMIT 5000";
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute([$address_id, $date_from.' 00:00:00', $date_to.' 23:59:59']);
        $rows = $stmt->fetchAll();
        // Ambil info address juga
        $addr = $conn->prepare("SELECT a.*, p.description as plant_name, u.unit_name FROM pm_address a JOIN plants p ON a.plant_id=p.plant_id JOIN units u ON a.unit_id=u.unit_id WHERE a.address_id=?");
        $addr->execute([$address_id]);
        echo json_encode(['success'=>true, 'data'=>$rows, 'address'=>$addr->fetch()]);
        break;

    // Statistik ringkas
    case 'get-stats':
        $address_id = intval($_GET['address_id'] ?? 0);
        $date_from  = $_GET['date_from'] ?? date('Y-m-01');
        $date_to    = $_GET['date_to']   ?? date('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) as total, AVG(value) as avg, MIN(value) as min, MAX(value) as max, STDDEV(value) as std FROM pm_data WHERE address_id=? AND recorded_at BETWEEN ? AND ?");
        $stmt->execute([$address_id, $date_from.' 00:00:00', $date_to.' 23:59:59']);
        echo json_encode(['success'=>true, 'data'=>$stmt->fetch()]);
        break;

    // Hapus data range
    case 'delete-data':
        require_role('superadmin');
        $address_id = intval($_POST['address_id'] ?? 0);
        $date_from  = $_POST['date_from'] ?? '';
        $date_to    = $_POST['date_to']   ?? '';
        if (!$address_id || !$date_from || !$date_to) {
            echo json_encode(['success'=>false,'message'=>'Parameter tidak lengkap']);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM pm_data WHERE address_id=? AND recorded_at BETWEEN ? AND ?");
        $stmt->execute([$address_id, $date_from.' 00:00:00', $date_to.' 23:59:59']);
        echo json_encode(['success'=>true,'deleted'=>$stmt->rowCount()]);
        break;

    // Hapus address beserta datanya
    case 'delete-address':
        require_role('superadmin');
        $address_id = intval($_POST['address_id'] ?? 0);
        if (!$address_id) { echo json_encode(['success'=>false]); break; }
        $conn->prepare("DELETE FROM pm_address WHERE address_id=?")->execute([$address_id]);
        echo json_encode(['success'=>true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Action tidak dikenal']);
}
