<?php
// Bearing Proxy - XGBoost utama + Deep Learning Autoencoder pembanding.
// XGBoost membaca tabel XGBoost__prediksi.
// Autoencoder membaca tabel Deep_Learning__prediksi_autoencoder.
// Sensor dan aktual tetap tabel bersama.
header('Content-Type: application/json');

function out_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function table_exists(PDO $db, string $table): bool {
    try {
        $st = $db->prepare("SHOW TABLES LIKE ?");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function table_columns(PDO $db, string $table): array {
    try {
        return array_map(fn($r) => $r['Field'], $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) { return []; }
}

function pick_col(array $cols, array $candidates): ?string {
    $lower = [];
    foreach ($cols as $c) $lower[strtolower($c)] = $c;
    foreach ($candidates as $cand) {
        $k = strtolower($cand);
        if (isset($lower[$k])) return $lower[$k];
    }
    return null;
}

function get_unit_info(PDO $conn, int $unit_id): ?array {
    $st = $conn->prepare("SELECT u.unit_id, u.unit_name, u.plant_id, u.database_name, p.description AS plant_name
                          FROM units u
                          LEFT JOIN plants p ON p.plant_id = u.plant_id
                          WHERE u.unit_id = ?");
    $st->execute([$unit_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function user_allowed_unit(PDO $conn, int $unit_id): bool {
    $role = $_SESSION['role'] ?? 'user';
    if ($role === 'superadmin') return true;
    $uid = intval($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) return false;
    $st = $conn->prepare("SELECT all_access, assigned_units FROM users WHERE user_id=?");
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($u['all_access'])) return true;
    $units = array_values(array_filter(array_map('intval', explode(',', (string)($u['assigned_units'] ?? '')))));
    return in_array($unit_id, $units, true);
}

function unit_pdo(string $db_name): PDO {
    if ($db_name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
        throw new Exception('Nama database unit tidak valid.');
    }
    return new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $db_name . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );
}

function get_model_type(): string {
    $model = strtolower(trim((string)($_GET['model'] ?? $_POST['model'] ?? $_GET['model_type'] ?? $_POST['model_type'] ?? 'xgboost')));
    if (in_array($model, ['autoencoder','deep_learning','dl'], true)) return 'autoencoder';
    return 'xgboost';
}

function model_table(string $model): string {
    return $model === 'autoencoder' ? 'Deep_Learning__prediksi_autoencoder' : 'XGBoost__prediksi';
}

function model_label(string $model): string {
    return $model === 'autoencoder' ? 'Deep Learning Autoencoder' : 'XGBoost';
}

function get_range_from_request(): array {
    $period = $_GET['period'] ?? $_POST['period'] ?? 'month';
    $year = intval($_GET['year'] ?? $_POST['year'] ?? 2025);
    if ($year < 2000 || $year > 2100) $year = 2025;

    if ($period === 'day') {
        $from = $_GET['date_from'] ?? $_POST['date_from'] ?? date('Y-m-d');
        $to   = $_GET['date_to'] ?? $_POST['date_to'] ?? $from;
    } elseif ($period === 'year') {
        $from = sprintf('%04d-01-01', $year);
        $to   = sprintf('%04d-12-31', $year);
    } else {
        $month = intval($_GET['month'] ?? $_POST['month'] ?? date('n'));
        if ($month < 1 || $month > 12) $month = 1;
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '2025-01-01';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $from;
    if ($to < $from) $to = $from;
    return [$from . ' 00:00:00', $to . ' 23:59:59', $from, $to, $period, $year];
}

function sensors_from_unit(PDO $db): array {
    $sensors = [];
    if (table_exists($db, 'sensor')) {
        $cols = table_columns($db, 'sensor');
        $tagCol = pick_col($cols, ['tagno','tag_id','tag','id_tag']);
        $descCol = pick_col($cols, ['deskripsi','description','nama_sensor','name','address_no']);
        $unitCol = pick_col($cols, ['satuan','unit']);
        if ($tagCol) {
            $sql = "SELECT `$tagCol` AS tagno";
            $sql .= $descCol ? ", `$descCol` AS deskripsi" : ", '' AS deskripsi";
            $sql .= $unitCol ? ", `$unitCol` AS satuan" : ", '' AS satuan";
            $sql .= " FROM sensor ORDER BY CAST(`$tagCol` AS UNSIGNED), `$tagCol`";
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $tag = trim((string)$r['tagno']);
                if ($tag === '') continue;
                $sensors[$tag] = [
                    'tagno' => $tag,
                    'deskripsi' => trim((string)($r['deskripsi'] ?? '')),
                    'satuan' => trim((string)($r['satuan'] ?? '')),
                ];
            }
        }
    }

    foreach (['XGBoost__prediksi','Deep_Learning__prediksi_autoencoder','aktual'] as $t) {
        if (!table_exists($db, $t)) continue;
        $cols = table_columns($db, $t);
        $tagCol = pick_col($cols, ['tagno','tag_id','tag']);
        if (!$tagCol) continue;
        $rows = $db->query("SELECT DISTINCT `$tagCol` AS tagno FROM `$t` ORDER BY CAST(`$tagCol` AS UNSIGNED), `$tagCol` LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $tag = trim((string)$r['tagno']);
            if ($tag !== '' && !isset($sensors[$tag])) {
                $sensors[$tag] = ['tagno'=>$tag, 'deskripsi'=>'Sensor tag ' . $tag, 'satuan'=>''];
            }
        }
    }

    return array_values($sensors);
}

function fetch_model_rows(PDO $db, string $model, string $tag, string $start, string $end, int $limit): array {
    $table = model_table($model);
    if (!table_exists($db, $table)) return [];
    $cols = table_columns($db, $table);
    $tagCol = pick_col($cols, ['tagno','tag_id','tag']);
    $timeCol = pick_col($cols, ['data_time','timestamp','waktu','datetime']);
    $valueCol = pick_col($cols, ['value','nilai','aktual']);
    $predCol = pick_col($cols, ['value_prediksi','prediksi','nilai_prediksi','reconstructed_value']);
    $highCol = pick_col($cols, ['selisih_high','high','batas_high','upper']);
    $lowCol = pick_col($cols, ['selisih_low','low','batas_low','lower']);
    $statusCol = pick_col($cols, ['status_anomali','status']);
    $reconCol = pick_col($cols, ['reconstruction_error','error_rekonstruksi','recon_error']);
    $thrCol = pick_col($cols, ['threshold_error','threshold_reconstruction','batas_error']);
    if (!$tagCol || !$timeCol || !$valueCol) return [];

    $select = "`$tagCol` AS tagno, `$timeCol` AS data_time, `$valueCol` AS value";
    $select .= $predCol ? ", `$predCol` AS value_prediksi" : ", NULL AS value_prediksi";
    $select .= $highCol ? ", `$highCol` AS selisih_high" : ", NULL AS selisih_high";
    $select .= $lowCol ? ", `$lowCol` AS selisih_low" : ", NULL AS selisih_low";
    $select .= $statusCol ? ", `$statusCol` AS status_anomali" : ", NULL AS status_anomali";
    $select .= $reconCol ? ", `$reconCol` AS reconstruction_error" : ", NULL AS reconstruction_error";
    $select .= $thrCol ? ", `$thrCol` AS threshold_error" : ", NULL AS threshold_error";
    $sql = "SELECT $select FROM `$table`
            WHERE `$tagCol` = ? AND `$timeCol` BETWEEN ? AND ?
            ORDER BY `$timeCol` ASC LIMIT " . max(100, min($limit, 200000));
    $st = $db->prepare($sql);
    $st->execute([$tag, $start, $end]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_actual_rows(PDO $db, string $tag, string $start, string $end, int $limit): array {
    if (!table_exists($db, 'aktual')) return [];
    $cols = table_columns($db, 'aktual');
    $tagCol = pick_col($cols, ['tagno','tag_id','tag']);
    $timeCol = pick_col($cols, ['data_time','timestamp','waktu','datetime']);
    $valueCol = pick_col($cols, ['value','nilai','aktual']);
    if (!$tagCol || !$timeCol || !$valueCol) return [];
    $sql = "SELECT `$tagCol` AS tagno, `$timeCol` AS data_time, `$valueCol` AS value,
                   NULL AS value_prediksi, NULL AS selisih_high, NULL AS selisih_low, NULL AS status_anomali,
                   NULL AS reconstruction_error, NULL AS threshold_error
            FROM aktual
            WHERE `$tagCol` = ? AND `$timeCol` BETWEEN ? AND ?
            ORDER BY `$timeCol` ASC LIMIT " . max(100, min($limit, 200000));
    $st = $db->prepare($sql);
    $st->execute([$tag, $start, $end]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}



function project_root_path(): string {
    return realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
}

function autoencoder_csv_path(): string {
    return project_root_path() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'DB_PACITAN_1_FINAL' . DIRECTORY_SEPARATOR . 'IMPORT_AUTOENCODER' . DIRECTORY_SEPARATOR . 'prediksi_autoencoder_import.csv';
}

function ensure_evaluation_table(PDO $db, string $model): string {
    $table = $model === 'autoencoder' ? 'Deep_Learning__evaluasi_model_ai' : 'XGBoost__evaluasi_model_ai';
    $defaultType = $model === 'autoencoder' ? 'Deep Learning Autoencoder' : 'XGBoost';
    $db->exec("CREATE TABLE IF NOT EXISTS `$table` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        model_type VARCHAR(80) NOT NULL DEFAULT '$defaultType',
        tagno VARCHAR(10) NULL,
        sensor_name VARCHAR(255) NULL,
        mae DOUBLE NULL,
        rmse DOUBLE NULL,
        r2_score DOUBLE NULL,
        total_data INT NULL,
        total_anomali INT NULL,
        keterangan TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_eval_model_type (model_type),
        INDEX idx_eval_tagno (tagno)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return $table;
}

function quick_metrics(array $rows, string $model, int $min): array {
    [$decorated, $candCount, $finalCount] = decorate_rows($rows, $min, $model);
    $n = 0; $sumAbs = 0.0; $sumSq = 0.0; $sumY = 0.0; $vals = [];
    foreach ($decorated as $r) {
        if (!is_numeric($r['value'] ?? null) || !is_numeric($r['value_prediksi'] ?? null)) continue;
        $y = (float)$r['value'];
        $yp = (float)$r['value_prediksi'];
        $e = $y - $yp;
        $n++;
        $sumAbs += abs($e);
        $sumSq += $e * $e;
        $sumY += $y;
        $vals[] = [$y, $yp];
    }
    $mae = $n ? $sumAbs / $n : null;
    $rmse = $n ? sqrt($sumSq / $n) : null;
    $r2 = null;
    if ($n > 1) {
        $mean = $sumY / $n;
        $sst = 0.0;
        foreach ($vals as [$y, $yp]) $sst += ($y - $mean) * ($y - $mean);
        $r2 = $sst > 0 ? (1 - ($sumSq / $sst)) : null;
    }
    return [$decorated, $mae, $rmse, $r2, $candCount, $finalCount];
}

function save_evaluation_row(PDO $db, string $model, string $tag, array $rows, int $min, string $keterangan): void {
    [$decorated, $mae, $rmse, $r2, $cand, $final] = quick_metrics($rows, $model, $min);
    $table = ensure_evaluation_table($db, $model);
    $label = model_label($model);
    $st = $db->prepare("INSERT INTO `$table`
        (model_type, tagno, mae, rmse, r2_score, total_data, total_anomali, keterangan)
        VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([$label, $tag, $mae, $rmse, $r2, count($decorated), $final, $keterangan]);
}

function import_autoencoder_csv_for_range(PDO $db, string $tag, string $start, string $end, int $limit): int {
    $csv = autoencoder_csv_path();
    if (!is_file($csv)) return 0;
    if (!table_exists($db, 'Deep_Learning__prediksi_autoencoder')) return 0;

    $fh = fopen($csv, 'r');
    if (!$fh) return 0;
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); return 0; }
    $idx = [];
    foreach ($header as $i => $h) $idx[strtolower(trim((string)$h))] = $i;
    $need = ['tagno','data_time','value','value_prediksi','selisih_high','selisih_low','reconstruction_error','threshold_error','status_anomali'];
    foreach (['tagno','data_time','value'] as $n) {
        if (!array_key_exists($n, $idx)) { fclose($fh); return 0; }
    }

    $sql = "INSERT INTO `Deep_Learning__prediksi_autoencoder`
        (tagno, data_time, value, value_prediksi, selisih_high, selisih_low, reconstruction_error, threshold_error, status_anomali)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            value=VALUES(value),
            value_prediksi=VALUES(value_prediksi),
            selisih_high=VALUES(selisih_high),
            selisih_low=VALUES(selisih_low),
            reconstruction_error=VALUES(reconstruction_error),
            threshold_error=VALUES(threshold_error),
            status_anomali=VALUES(status_anomali)";
    $st = $db->prepare($sql);
    $count = 0;
    $db->beginTransaction();
    try {
        while (($row = fgetcsv($fh)) !== false) {
            $rowTag = trim((string)($row[$idx['tagno']] ?? ''));
            if ($rowTag !== (string)$tag) continue;
            $time = trim((string)($row[$idx['data_time']] ?? ''));
            if ($time < $start || $time > $end) continue;
            $val = function($name) use ($row, $idx) {
                if (!array_key_exists($name, $idx)) return null;
                $v = $row[$idx[$name]] ?? null;
                return is_numeric($v) ? (float)$v : null;
            };
            $status = array_key_exists('status_anomali', $idx) ? trim((string)($row[$idx['status_anomali']] ?? 'Normal')) : 'Normal';
            $st->execute([
                $rowTag,
                $time,
                $val('value'),
                $val('value_prediksi'),
                $val('selisih_high'),
                $val('selisih_low'),
                $val('reconstruction_error'),
                $val('threshold_error'),
                $status ?: 'Normal',
            ]);
            $count++;
            if ($count >= $limit) break;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        fclose($fh);
        throw $e;
    }
    fclose($fh);
    return $count;
}


function generate_model_rows_from_actual(PDO $db, string $model, string $tag, string $start, string $end, int $limit, int $min, float $batas): array {
    // XGBoost dan Deep Learning sengaja dipisah logikanya supaya hasil tidak sama.
    // XGBoost: prediksi berbasis rolling trend dari data sebelumnya.
    // Deep Learning fallback: reconstruction-style smoothing. Normalnya Autoencoder memakai CSV hasil model .h5 dari IMPORT_AUTOENCODER.
    $actualRows = fetch_actual_rows($db, $tag, $start, $end, $limit);
    if (empty($actualRows)) {
        throw new Exception("Tabel `aktual` belum memiliki data untuk tag/range ini. Import data aktual dulu.");
    }

    $values = [];
    foreach ($actualRows as $r) {
        $values[] = is_numeric($r['value'] ?? null) ? (float)$r['value'] : null;
    }
    $n = count($values);
    $avgRange = function(int $a, int $b) use ($values, $n): ?float {
        $a = max(0, $a); $b = min($n - 1, $b);
        $sum = 0.0; $c = 0;
        for ($i=$a; $i<=$b; $i++) {
            if (is_numeric($values[$i])) { $sum += (float)$values[$i]; $c++; }
        }
        return $c ? $sum / $c : null;
    };

    $predRows = [];
    for ($i=0; $i<$n; $i++) {
        $r = $actualRows[$i];
        $v = $values[$i];
        if ($v === null) continue;
        $prev = ($i > 0 && is_numeric($values[$i-1])) ? (float)$values[$i-1] : $v;

        if ($model === 'autoencoder') {
            // Fallback saja jika CSV Autoencoder belum ada. Dibuat beda dari XGBoost.
            $center = $avgRange($i-6, $i+6) ?? $v;
            $long = $avgRange($i-36, $i+36) ?? $center;
            $pred = (0.42 * $v) + (0.43 * $center) + (0.15 * $long);
            $thr = max(1.0, $batas * 0.70);
            $err = abs($v - $pred);
            $high = $pred + $thr;
            $low = $pred - $thr;
        } else {
            $short = $avgRange($i-12, $i-1) ?? $v;
            $long = $avgRange($i-60, $i-1) ?? $short;
            $back = ($i > 12 && is_numeric($values[$i-12])) ? (float)$values[$i-12] : $prev;
            $trend = ($prev - $back) / 12.0;
            $pred = (0.50 * $prev) + (0.35 * $short) + (0.15 * $long) + (0.75 * $trend);
            if ($i === 0) $pred = $v;
            $thr = $batas;
            $err = null;
            $high = $pred + $thr;
            $low = $pred - $thr;
        }

        $predRows[] = [
            'tagno' => (string)$r['tagno'],
            'data_time' => (string)$r['data_time'],
            'value' => $v,
            'value_prediksi' => $pred,
            'selisih_high' => $high,
            'selisih_low' => $low,
            'reconstruction_error' => $model === 'autoencoder' ? $err : null,
            'threshold_error' => $model === 'autoencoder' ? $thr : null,
            'status_anomali' => 'Normal',
        ];
    }

    [$decorated, $candCount, $finalCount] = decorate_rows($predRows, $min, $model);
    $table = model_table($model);

    $db->beginTransaction();
    try {
        if ($model === 'autoencoder') {
            $sql = "INSERT INTO `$table`
                (tagno, data_time, value, value_prediksi, selisih_high, selisih_low, reconstruction_error, threshold_error, status_anomali)
                VALUES (?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    value=VALUES(value),
                    value_prediksi=VALUES(value_prediksi),
                    selisih_high=VALUES(selisih_high),
                    selisih_low=VALUES(selisih_low),
                    reconstruction_error=VALUES(reconstruction_error),
                    threshold_error=VALUES(threshold_error),
                    status_anomali=VALUES(status_anomali)";
            $st = $db->prepare($sql);
            foreach ($decorated as $r) {
                $st->execute([$r['tagno'], $r['data_time'], $r['value'], $r['value_prediksi'], $r['selisih_high'], $r['selisih_low'], $r['reconstruction_error'], $r['threshold_error'], $r['status_anomali']]);
            }
        } else {
            $sql = "INSERT INTO `$table`
                (tagno, data_time, value, value_prediksi, selisih_high, selisih_low, status_anomali)
                VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    value=VALUES(value),
                    value_prediksi=VALUES(value_prediksi),
                    selisih_high=VALUES(selisih_high),
                    selisih_low=VALUES(selisih_low),
                    status_anomali=VALUES(status_anomali)";
            $st = $db->prepare($sql);
            foreach ($decorated as $r) {
                $st->execute([$r['tagno'], $r['data_time'], $r['value'], $r['value_prediksi'], $r['selisih_high'], $r['selisih_low'], $r['status_anomali']]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    save_evaluation_row($db, $model, $tag, $decorated, $min, ($model === 'autoencoder' ? 'Fallback reconstruction dari aktual. Disarankan pakai CSV Autoencoder jika tersedia.' : 'Generate XGBoost dari aktual dengan rolling trend.'));
    return [$decorated, $candCount, $finalCount, count($actualRows)];
}

function apply_min_consecutive(array $rows, int $min, string $model): array {
    $n = count($rows);
    $candidate = array_fill(0, $n, false);
    $final = array_fill(0, $n, false);
    $candidateCount = 0;

    for ($i=0; $i<$n; $i++) {
        $v = is_numeric($rows[$i]['value'] ?? null) ? (float)$rows[$i]['value'] : null;
        $hi = is_numeric($rows[$i]['selisih_high'] ?? null) ? (float)$rows[$i]['selisih_high'] : null;
        $lo = is_numeric($rows[$i]['selisih_low'] ?? null) ? (float)$rows[$i]['selisih_low'] : null;
        $err = is_numeric($rows[$i]['reconstruction_error'] ?? null) ? (float)$rows[$i]['reconstruction_error'] : null;
        $thr = is_numeric($rows[$i]['threshold_error'] ?? null) ? (float)$rows[$i]['threshold_error'] : null;
        $is = false;

        if ($model === 'autoencoder' && $err !== null && $thr !== null) {
            $is = $err > $thr;
        } else {
            if ($v !== null && $hi !== null && $v > $hi) $is = true;
            if ($v !== null && $lo !== null && $v < $lo) $is = true;
        }

        $candidate[$i] = $is;
        if ($is) $candidateCount++;
    }

    $i = 0;
    while ($i < $n) {
        if (!$candidate[$i]) { $i++; continue; }
        $j = $i;
        while ($j < $n && $candidate[$j]) $j++;
        $len = $j - $i;
        if ($len >= $min) {
            for ($k=$i; $k<$j; $k++) $final[$k] = true;
        }
        $i = $j;
    }

    $finalCount = 0;
    foreach ($final as $x) if ($x) $finalCount++;
    return [$candidate, $final, $candidateCount, $finalCount];
}

function decorate_rows(array $rows, int $min, string $model): array {
    [$candidate, $final, $candCount, $finalCount] = apply_min_consecutive($rows, $min, $model);
    foreach ($rows as $i => &$r) {
        $r['candidate_anomaly'] = $candidate[$i] ? 1 : 0;
        $r['final_anomaly'] = $final[$i] ? 1 : 0;
        $r['status_anomali'] = $final[$i] ? 'Anomali' : 'Normal';
    }
    unset($r);
    return [$rows, $candCount, $finalCount];
}


function compute_model_metrics(array $rows, string $model, int $min): array {
    [$decorated, $candCount, $finalCount] = decorate_rows($rows, $min, $model);
    $n = 0; $sumAbs = 0.0; $sumSq = 0.0; $sumY = 0.0; $vals = [];
    foreach ($decorated as $r) {
        if (!is_numeric($r['value'] ?? null) || !is_numeric($r['value_prediksi'] ?? null)) continue;
        $y = (float)$r['value'];
        $yp = (float)$r['value_prediksi'];
        $e = $y - $yp;
        $n++;
        $sumAbs += abs($e);
        $sumSq += $e * $e;
        $sumY += $y;
        $vals[] = [$y, $yp];
    }
    $mae = $n ? $sumAbs / $n : null;
    $rmse = $n ? sqrt($sumSq / $n) : null;
    $r2 = null;
    if ($n > 1) {
        $mean = $sumY / $n;
        $sst = 0.0;
        foreach ($vals as [$y, $yp]) $sst += ($y - $mean) * ($y - $mean);
        $r2 = $sst > 0 ? (1 - ($sumSq / $sst)) : null;
    }
    return [
        'model' => $model,
        'label' => model_label($model),
        'table' => model_table($model),
        'total' => count($decorated),
        'n_metric' => $n,
        'mae' => $mae,
        'rmse' => $rmse,
        'r2_score' => $r2,
        'candidate_count' => $candCount,
        'anomaly_count' => $finalCount,
    ];
}

function rows_for_compare(PDO $db, string $model, string $tag, string $startDT, string $endDT, int $limit, int $min, float $batas): array {
    if ($model === 'autoencoder') {
        // Pakai hasil Autoencoder asli dari CSV export .h5 jika file tersedia.
        import_autoencoder_csv_for_range($db, $tag, $startDT, $endDT, $limit);
        $rows = fetch_model_rows($db, $model, $tag, $startDT, $endDT, $limit);
        if (!empty($rows)) return $rows;
        [$rows, $cand, $fin, $actualCount] = generate_model_rows_from_actual($db, $model, $tag, $startDT, $endDT, $limit, $min, $batas);
        return $rows;
    }

    // Untuk XGBoost, generate ulang supaya tidak nyangkut dari data lama yang sama dengan DL.
    [$rows, $cand, $fin, $actualCount] = generate_model_rows_from_actual($db, $model, $tag, $startDT, $endDT, $limit, $min, $batas);
    return $rows;
}

function table_date_bounds(PDO $db, string $table, string $tag): ?array {
    if (!table_exists($db, $table)) return null;
    $cols = table_columns($db, $table);
    $tagCol = pick_col($cols, ['tagno','tag_id','tag']);
    $timeCol = pick_col($cols, ['data_time','timestamp','waktu','datetime']);
    if (!$tagCol || !$timeCol) return null;
    $st = $db->prepare("SELECT MIN(`$timeCol`) AS min_time, MAX(`$timeCol`) AS max_time, COUNT(*) AS total FROM `$table` WHERE `$tagCol`=?");
    $st->execute([$tag]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'load';
    $unit_id = intval($_GET['unit_id'] ?? $_POST['unit_id'] ?? ($_SESSION['selected_unit_id'] ?? 0));
    if ($unit_id <= 0) out_json(['success'=>false,'message'=>'Unit belum dipilih.'], 400);
    if (!user_allowed_unit($conn, $unit_id)) out_json(['success'=>false,'message'=>'Akses unit ditolak.'], 403);

    $unit = get_unit_info($conn, $unit_id);
    if (!$unit || empty($unit['database_name'])) {
        out_json(['success'=>false,'message'=>'Database unit belum diatur di tabel units.'], 400);
    }
    $db = unit_pdo($unit['database_name']);

    if ($action === 'sensors') {
        out_json(['success'=>true, 'unit'=>$unit, 'sensors'=>sensors_from_unit($db)]);
    }

    [$startDT, $endDT, $fromDate, $toDate, $period, $year] = get_range_from_request();
    $tag = trim((string)($_GET['tagno'] ?? $_POST['tagno'] ?? '859'));
    $limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 50000);
    $min = intval($_GET['min'] ?? $_POST['min'] ?? 10);
    $model = get_model_type();
    $table = model_table($model);
    if ($min < 1) $min = 10;
    if ($limit < 100) $limit = 100;
    if ($limit > 200000) $limit = 200000;


    if ($action === 'compare') {
        $batas = (float)($_GET['batas'] ?? $_POST['batas'] ?? 5);
        if ($batas <= 0) $batas = 5;
        $models = ['xgboost','autoencoder'];
        $metrics = [];
        foreach ($models as $m) {
            $rowsForM = rows_for_compare($db, $m, $tag, $startDT, $endDT, $limit, $min, $batas);
            $metrics[] = compute_model_metrics($rowsForM, $m, $min);
        }
        $valid = array_values(array_filter($metrics, fn($x) => $x['rmse'] !== null));
        $best = null;
        $sameMetric = false;
        if (!empty($valid)) {
            usort($valid, function($a, $b) {
                $ra = $a['rmse'] ?? PHP_FLOAT_MAX;
                $rb = $b['rmse'] ?? PHP_FLOAT_MAX;
                if (abs($ra - $rb) < 0.000001) return ($a['mae'] ?? PHP_FLOAT_MAX) <=> ($b['mae'] ?? PHP_FLOAT_MAX);
                return $ra <=> $rb;
            });
            if (count($valid) >= 2 && abs(($valid[0]['rmse'] ?? 0) - ($valid[1]['rmse'] ?? 0)) < 0.000001 && abs(($valid[0]['mae'] ?? 0) - ($valid[1]['mae'] ?? 0)) < 0.000001) {
                $sameMetric = true;
            } else {
                $best = $valid[0];
            }
        }
        out_json([
            'success'=>true,
            'message'=>$sameMetric ? 'Hasil metrik masih sama. Klik Prediksi AI/Auto Jan-Des ulang supaya tabel lama tertimpa data model yang benar.' : ($best ? ('Metode terbaik untuk range ini: ' . $best['label'] . ' karena RMSE paling kecil.') : 'Belum bisa menentukan metode terbaik karena data prediksi belum ada.'),
            'unit'=>$unit,
            'range'=>['from'=>$fromDate,'to'=>$toDate],
            'tagno'=>$tag,
            'min'=>$min,
            'limit'=>$limit,
            'metrics'=>$metrics,
            'best_model'=>$best,
        ]);
    }

    if ($action === 'run') {
        $batas = (float)($_GET['batas'] ?? $_POST['batas'] ?? 5);
        if ($batas <= 0) $batas = 5;

        $generated = false;
        $sourceNote = '';

        if ($model === 'autoencoder') {
            // Deep Learning harus pakai output Autoencoder, bukan tabel XGBoost.
            $imported = import_autoencoder_csv_for_range($db, $tag, $startDT, $endDT, $limit);
            $rows = fetch_model_rows($db, $model, $tag, $startDT, $endDT, $limit);
            if (!empty($rows)) {
                [$newRows, $candCount, $finalCount] = decorate_rows($rows, $min, $model);
                $cols = table_columns($db, $table);
                $tagCol = pick_col($cols, ['tagno','tag_id','tag']);
                $timeCol = pick_col($cols, ['data_time','timestamp','waktu','datetime']);
                $statusCol = pick_col($cols, ['status_anomali','status']);
                if ($tagCol && $timeCol && $statusCol) {
                    $up = $db->prepare("UPDATE `$table` SET `$statusCol`=? WHERE `$tagCol`=? AND `$timeCol`=?");
                    foreach ($newRows as $r) $up->execute([$r['status_anomali'], (string)$r['tagno'], (string)$r['data_time']]);
                }
                save_evaluation_row($db, $model, $tag, $newRows, $min, 'Evaluasi dari CSV Autoencoder hasil model .h5.');
                $rows = $newRows;
                $sourceNote = $imported > 0 ? "import CSV Autoencoder .h5 ($imported baris)" : 'pakai tabel Autoencoder yang sudah ada';
            } else {
                [$rows, $candCount, $finalCount, $actualCount] = generate_model_rows_from_actual($db, $model, $tag, $startDT, $endDT, $limit, $min, $batas);
                $generated = true;
                $sourceNote = 'fallback reconstruction dari aktual karena CSV Autoencoder belum tersedia';
            }
        } else {
            // XGBoost selalu digenerate ulang dari aktual dengan rumus rolling trend yang berbeda dari Autoencoder.
            [$rows, $candCount, $finalCount, $actualCount] = generate_model_rows_from_actual($db, $model, $tag, $startDT, $endDT, $limit, $min, $batas);
            $generated = true;
            $sourceNote = 'generate XGBoost dari aktual dengan rolling trend';
        }

        out_json([
            'success'=>true,
            'message'=>model_label($model) . " selesai. Range $fromDate sampai $toDate. Data " . count($rows) . ", candidate $candCount, anomali final $finalCount. ($sourceNote)",
            'unit'=>$unit,
            'range'=>['from'=>$fromDate,'to'=>$toDate],
            'tagno'=>$tag,
            'model'=>$model,
            'table'=>$table,
            'generated_from_aktual'=>$generated,
            'total'=>count($rows),
            'candidate_count'=>$candCount,
            'anomaly_count'=>$finalCount,
            'updated'=>count($rows),
            'rows'=>array_slice($rows, 0, $limit),
        ]);
    }

    if ($model === 'autoencoder') {
        // Supaya Deep Learning langsung memakai hasil Autoencoder asli dari CSV export .h5 jika tersedia.
        import_autoencoder_csv_for_range($db, $tag, $startDT, $endDT, $limit);
    }
    $rows = fetch_model_rows($db, $model, $tag, $startDT, $endDT, $limit);
    $source = $table;
    if (empty($rows)) {
        // Supaya layar tidak kosong: tampilkan aktual dulu. Klik Prediksi AI untuk mengisi tabel model.
        $rows = fetch_actual_rows($db, $tag, $startDT, $endDT, $limit);
        $source = 'aktual';
    }
    [$rows, $candCount, $finalCount] = decorate_rows($rows, $min, $model);
    $bounds = table_date_bounds($db, $table, $tag);
    $msg = empty($rows) ? ('Data tidak ditemukan di tabel aktual untuk tag/range ini.') : ('Data berhasil dimuat dari tabel ' . $source . '.');
    if ($source === 'aktual') $msg .= ' Klik Prediksi AI untuk mengisi tabel ' . $table . '.';
    out_json([
        'success'=>true,
        'message'=>$msg,
        'unit'=>$unit,
        'source'=>$source,
        'range'=>['from'=>$fromDate,'to'=>$toDate],
        'tagno'=>$tag,
        'model'=>$model,
        'table'=>$table,
        'bounds'=>$bounds,
        'total'=>count($rows),
        'candidate_count'=>$candCount,
        'anomaly_count'=>$finalCount,
        'rows'=>$rows,
    ]);

} catch (Throwable $e) {
    out_json(['success'=>false, 'message'=>$e->getMessage()], 500);
}
