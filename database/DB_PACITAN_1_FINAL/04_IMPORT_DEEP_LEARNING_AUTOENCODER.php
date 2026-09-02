<?php
// Import hasil Deep Learning Autoencoder TANPA PYTHON.
// Sumber: IMPORT_AUTOENCODER/prediksi_autoencoder_import.csv
// Target: db_pacitan_1.Deep_Learning__prediksi_autoencoder
ini_set('memory_limit','1024M');
set_time_limit(0);

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'db_pacitan_1';
$base = __DIR__;
$csv = $base . DIRECTORY_SEPARATOR . 'IMPORT_AUTOENCODER' . DIRECTORY_SEPARATOR . 'prediksi_autoencoder_import.csv';
$metrics = $base . DIRECTORY_SEPARATOR . 'IMPORT_AUTOENCODER' . DIRECTORY_SEPARATOR . 'metrics_autoencoder_y1_y2.csv';

if (!is_file($csv)) {
    echo "ERROR: prediksi_autoencoder_import.csv tidak ditemukan di IMPORT_AUTOENCODER\n";
    exit(1);
}
$port = 3307;
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>true]);
$pdo->exec("CREATE TABLE IF NOT EXISTS `Deep_Learning__prediksi_autoencoder` (
    tagno VARCHAR(10) NOT NULL,
    data_time DATETIME NOT NULL,
    value FLOAT NULL,
    value_prediksi FLOAT NULL,
    selisih_high FLOAT NULL,
    selisih_low FLOAT NULL,
    reconstruction_error DOUBLE NULL,
    threshold_error DOUBLE NULL,
    status_anomali VARCHAR(30) DEFAULT 'Normal',
    PRIMARY KEY(tagno, data_time),
    INDEX idx_auto_time(data_time),
    INDEX idx_auto_tag_time(tagno, data_time),
    INDEX idx_auto_status(status_anomali)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS `Deep_Learning__evaluasi_model_ai` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    model_type VARCHAR(80) NOT NULL DEFAULT 'Deep Learning Autoencoder',
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
    PRIMARY KEY(id), INDEX idx_eval_model_type(model_type), INDEX idx_eval_tagno(tagno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$fh = fopen($csv, 'r');
$header = fgetcsv($fh);
$idx = [];
foreach ($header as $i=>$h) $idx[strtolower(trim($h))] = $i;
$st = $pdo->prepare("INSERT INTO `Deep_Learning__prediksi_autoencoder`
(tagno,data_time,value,value_prediksi,selisih_high,selisih_low,reconstruction_error,threshold_error,status_anomali)
VALUES (?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE value=VALUES(value), value_prediksi=VALUES(value_prediksi), selisih_high=VALUES(selisih_high), selisih_low=VALUES(selisih_low), reconstruction_error=VALUES(reconstruction_error), threshold_error=VALUES(threshold_error), status_anomali=VALUES(status_anomali)");
$total=0; $batch=0;
$pdo->beginTransaction();
while (($row=fgetcsv($fh))!==false) {
    $get = function($name) use ($row,$idx) {
        if (!isset($idx[$name])) return null;
        $v = $row[$idx[$name]] ?? null;
        return is_numeric($v) ? (float)$v : null;
    };
    $tag = trim((string)($row[$idx['tagno']] ?? ''));
    $time = trim((string)($row[$idx['data_time']] ?? ''));
    if ($tag==='' || $time==='') continue;
    $status = isset($idx['status_anomali']) ? trim((string)($row[$idx['status_anomali']] ?? 'Normal')) : 'Normal';
    $st->execute([$tag,$time,$get('value'),$get('value_prediksi'),$get('selisih_high'),$get('selisih_low'),$get('reconstruction_error'),$get('threshold_error'),$status ?: 'Normal']);
    $total++; $batch++;
    if ($batch >= 5000) { $pdo->commit(); echo "Masuk/update: $total\n"; $pdo->beginTransaction(); $batch=0; }
}
$pdo->commit(); fclose($fh);

if (is_file($metrics)) {
    $fh=fopen($metrics,'r'); $header=fgetcsv($fh); $idx=[]; foreach($header as $i=>$h) $idx[strtolower(trim($h))]=$i;
    $ins=$pdo->prepare("INSERT INTO `Deep_Learning__evaluasi_model_ai` (model_type,tagno,mae,rmse,r2_score,keterangan) VALUES (?,?,?,?,?,?)");
    while(($row=fgetcsv($fh))!==false){
        $tag = $idx['tagno'] ?? ($idx['tag_id'] ?? null);
        $r2 = $idx['r2_score'] ?? ($idx['r2'] ?? null);
        $ins->execute([
            'Deep Learning Autoencoder',
            $tag!==null ? ($row[$tag] ?? null) : null,
            isset($idx['mae']) && is_numeric($row[$idx['mae']]) ? (float)$row[$idx['mae']] : null,
            isset($idx['rmse']) && is_numeric($row[$idx['rmse']]) ? (float)$row[$idx['rmse']] : null,
            $r2!==null && is_numeric($row[$r2]) ? (float)$row[$r2] : null,
            'Import dari CSV Autoencoder hasil model .h5'
        ]);
    }
    fclose($fh);
}

echo "===========================================\n";
echo "SELESAI IMPORT AUTOENCODER TANPA PYTHON\n";
echo "Total masuk/update: $total\n";
echo "Cek: SELECT COUNT(*) FROM Deep_Learning__prediksi_autoencoder;\n";
echo "===========================================\n";