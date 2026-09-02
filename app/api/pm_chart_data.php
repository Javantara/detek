<?php
// ============================================================
// API: Chart data - dengan forecast/ghost projection
// ============================================================
require_login();

$tag_id    = intval($_GET['tag_id'] ?? 0);
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');
$unit_id   = intval($_SESSION['selected_unit_id'] ?? 0);

if (!$tag_id) { echo json_encode(['success'=>false,'message'=>'tag_id required']); exit; }

$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) { echo json_encode(['success'=>false,'message'=>'Database unit tidak tersedia']); exit; }

// Ambil info address dari tag_master
$addr = $unit_conn->prepare("SELECT * FROM tag_master WHERE tag_id = ?");
$addr->execute([$tag_id]);
$address = $addr->fetch();
if (!$address) { echo json_encode(['success'=>false,'message'=>'Tag tidak ditemukan']); exit; }

// ── Cari batas data real ──────────────────────────────────────
$lastReal = $unit_conn->prepare("SELECT MAX(DATE(timestamp)) FROM tag_data WHERE tag_id=?");
$lastReal->execute([$tag_id]);
$last_real_date = $lastReal->fetchColumn(); // null kalau belum ada data

// ── Ambil data real (dari date_from s/d date_to atau s/d last_real_date) ──
$cnt = $unit_conn->prepare("SELECT COUNT(*) FROM tag_data WHERE tag_id=? AND DATE(timestamp) BETWEEN ? AND ?");
$cnt->execute([$tag_id, $date_from, $date_to]);
$total = intval($cnt->fetchColumn());

$need_forecast = false; // apakah perlu proyeksi
$forecast_start = null;

// Kalau tanggal_to lebih besar dari last_real_date, kita perlu forecast
if ($last_real_date && $date_to > $last_real_date) {
    $need_forecast = true;
    $forecast_start = date('Y-m-d', strtotime($last_real_date . ' +1 day'));
}

// Query data real
if ($total > 10000 || ($total > 2000 && $last_real_date)) {
    $sql = "
        SELECT DATE_FORMAT(timestamp,'%Y-%m-%d %H:00:00') as ts, AVG(value) as val
        FROM tag_data
        WHERE tag_id=? AND DATE(timestamp) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(timestamp,'%Y-%m-%d %H:00:00')
        ORDER BY ts ASC
    ";
    $aggregated = true;
} else {
    $sql = "
        SELECT timestamp as ts, value as val
        FROM tag_data
        WHERE tag_id=? AND DATE(timestamp) BETWEEN ? AND ?
        ORDER BY ts ASC
        LIMIT 5000
    ";
    $aggregated = $total > 2000;
}

$real_to = ($need_forecast && $last_real_date) ? $last_real_date : $date_to;

$stmt = $unit_conn->prepare($sql);
$stmt->execute([$tag_id, $date_from, $real_to]);
$rows = $stmt->fetchAll();

$real_labels = array_column($rows, 'ts');
$real_values = array_map('floatval', array_column($rows, 'val'));

// ── Buat proyeksi ghost (1 hari ke depan dari last_real_date) ──
$ghost_labels = [];
$ghost_values = [];

if ($need_forecast && !empty($real_values)) {
    // Ambil data historis 30 hari terakhir untuk referensi pola
    $hist = $unit_conn->prepare("
        SELECT DATE_FORMAT(timestamp,'%Y-%m-%d %H:00:00') as ts, AVG(value) as val
        FROM tag_data
        WHERE tag_id=? AND timestamp >= DATE_SUB(?, INTERVAL 30 DAY)
        GROUP BY DATE_FORMAT(timestamp,'%Y-%m-%d %H:00:00')
        ORDER BY ts ASC
    ");
    $hist->execute([$tag_id, $last_real_date . ' 23:59:59']);
    $hist_rows = $hist->fetchAll();
    $hist_vals = array_map('floatval', array_column($hist_rows, 'val'));

    // Statistik historis
    $hist_avg = count($hist_vals) > 0 ? array_sum($hist_vals) / count($hist_vals) : end($real_values);
    $hist_std = 0;
    if (count($hist_vals) > 1) {
        $variance = array_sum(array_map(fn($v) => pow($v - $hist_avg, 2), $hist_vals)) / count($hist_vals);
        $hist_std = sqrt($variance);
    }

    // Buat titik proyeksi per jam dari forecast_start s/d date_to
    $fc_start = new DateTime($forecast_start . ' 00:00:00');
    $fc_end   = new DateTime($date_to . ' 23:00:00');
    $interval = new DateInterval('PT1H');
    $period   = new DatePeriod($fc_start, $interval, $fc_end);

    // Pola jam dari data historis (per jam dalam sehari)
    $hourly_pattern = array_fill(0, 24, $hist_avg);
    if (!empty($hist_rows)) {
        $hour_sums = array_fill(0, 24, 0);
        $hour_cnts = array_fill(0, 24, 0);
        foreach ($hist_rows as $hr) {
            $h = intval(substr($hr['ts'], 11, 2));
            $hour_sums[$h] += floatval($hr['val']);
            $hour_cnts[$h]++;
        }
        for ($h = 0; $h < 24; $h++) {
            if ($hour_cnts[$h] > 0) $hourly_pattern[$h] = $hour_sums[$h] / $hour_cnts[$h];
        }
    }

    foreach ($period as $dt) {
        $ts_str = $dt->format('Y-m-d H:00:00');
        $h      = intval($dt->format('H'));
        // Nilai dari pola historis dengan sedikit variasi random ringan
        $proj_val = $hourly_pattern[$h];
        $ghost_labels[] = $ts_str;
        $ghost_values[] = round($proj_val, 6);
    }
}

echo json_encode([
    'success'        => true,
    'address'        => $address,
    'labels'         => $real_labels,
    'values'         => $real_values,
    'ghost_labels'   => $ghost_labels,
    'ghost_values'   => $ghost_values,
    'total'          => $total,
    'aggregated'     => $aggregated ?? false,
    'last_real_date' => $last_real_date,
    'need_forecast'  => $need_forecast,
]);
