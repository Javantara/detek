<?php
// IMPORT DATA AKTUAL TANPA PYTHON - FIX BACA CSV HEADER / TANPA HEADER
// Bisa baca langsung dari ZIP data_2025_pisah.zip

ini_set('memory_limit', '1024M');
set_time_limit(0);

$dbHost = '127.0.0.1';
$dbPort = 3307;
$dbUser = 'root';
$dbPass = '';
$dbName = 'db_pacitan_1';
$table  = 'aktual';
$defaultSource = 'D:\\Magang\\deteksi_anomali\\data_2025_pisah.zip';
$source = $argv[1] ?? $defaultSource;
$baseDir = __DIR__;
$tmpDir = $baseDir . DIRECTORY_SEPARATOR . '__tmp_import_aktual';

function line($s = '') { echo $s . PHP_EOL; }
function fail($s) { line('ERROR: ' . $s); exit(1); }
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}
function csvFiles($dir) {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'csv') $out[] = $file->getPathname();
    }
    sort($out);
    return $out;
}
function detectDelimiter($line) {
    $candidates = ["," => substr_count($line, ','), ";" => substr_count($line, ';'), "\t" => substr_count($line, "\t"), "|" => substr_count($line, '|')];
    arsort($candidates);
    $best = array_key_first($candidates);
    return ($candidates[$best] > 0) ? $best : ',';
}
function norm($s) {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
    return trim($s, '_');
}
function parseNumber($v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    // Jangan terima tanggal sebagai angka
    if (preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $v)) return null;
    if (preg_match('/\d{1,2}[-\/]\d{1,2}[-\/]\d{4}/', $v)) return null;
    $v = str_replace(['"', "'", ' '], '', $v);
    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
        if (strrpos($v, ',') > strrpos($v, '.')) $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else if (strpos($v, ',') !== false) {
        $v = str_replace(',', '.', $v);
    }
    return is_numeric($v) ? (float)$v : null;
}
function parseDateTimeStr($v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    $v = preg_replace('/\.\d+$/', '', $v);

    // format umum: 2025-07-01 00:00:00 / 2025/07/01 00:00
    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $v, $m)) {
        $hh = $m[4] ?? '00'; $ii = $m[5] ?? '00'; $ss = $m[6] ?? '00';
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[1], $m[2], $m[3], $hh, $ii, $ss);
    }

    // format lokal: 01/07/2025 00:00:00 / 01-07-2025
    if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $v, $m)) {
        $hh = $m[4] ?? '00'; $ii = $m[5] ?? '00'; $ss = $m[6] ?? '00';
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[3], $m[2], $m[1], $hh, $ii, $ss);
    }
    return null;
}
function tagFromName($path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    if (preg_match('/^(\d+)/', $name, $m)) return $m[1];
    return null;
}
function rowLooksLikeHeader($row) {
    $joined = norm(implode('_', $row));
    $headerWords = ['data_time','datetime','date_time','timestamp','tanggal','waktu','time','date','nilai','value','actual','aktual'];
    foreach ($headerWords as $w) {
        if (strpos($joined, $w) !== false) return true;
    }
    return false;
}
function chooseFromHeader($header) {
    $timeIdx = null;
    $valueIdx = null;
    $timeKeys = ['data_time','datetime','date_time','timestamp','tanggal','waktu','time','date'];
    $valueKeys = ['nilai','value','actual','aktual','average','avg','mean'];
    foreach ($timeKeys as $key) {
        foreach ($header as $i => $h) {
            $x = norm($h);
            if ($x === $key || strpos($x, $key) !== false) { $timeIdx = $i; break 2; }
        }
    }
    foreach ($valueKeys as $key) {
        foreach ($header as $i => $h) {
            if ($i === $timeIdx) continue;
            $x = norm($h);
            if ($x === $key || strpos($x, $key) !== false) { $valueIdx = $i; break 2; }
        }
    }
    return [$timeIdx, $valueIdx];
}
function chooseFromDataRow($row, $tagno) {
    $timeIdx = null;
    $valueIdx = null;

    // Cari kolom tanggal dari isi baris pertama
    foreach ($row as $i => $v) {
        if (parseDateTimeStr($v) !== null) { $timeIdx = $i; break; }
    }

    // Kalau formatnya: tagno, data_time, nilai
    if ($timeIdx !== null) {
        foreach ($row as $i => $v) {
            if ($i === $timeIdx) continue;
            $num = parseNumber($v);
            if ($num === null) continue;
            // skip kolom tagno kalau nilainya sama dengan tag dari nama file
            if ((string)(int)$num === (string)$tagno && $i < $timeIdx) continue;
            $valueIdx = $i;
            break;
        }
    }

    // Fallback umum
    if ($timeIdx === null && count($row) >= 2) $timeIdx = 0;
    if ($valueIdx === null) {
        for ($i = 0; $i < count($row); $i++) {
            if ($i === $timeIdx) continue;
            if (parseNumber($row[$i]) !== null) { $valueIdx = $i; break; }
        }
    }
    return [$timeIdx, $valueIdx];
}
function labelCol($row, $idx) {
    if ($idx === null) return 'TIDAK_KETEMU';
    return isset($row[$idx]) ? $row[$idx] : (string)$idx;
}

line('===============================================');
line('IMPORT DATA AKTUAL TANPA PYTHON - FIX KOLOM');
line('Target DB : ' . $dbName . '.' . $table);
line('Sumber   : ' . $source);
line('===============================================');

if (!file_exists($source)) fail("file/folder sumber tidak ketemu: $source");

$dataDir = $source;
if (is_file($source) && strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'zip') {
    if (!class_exists('ZipArchive')) fail('PHP ZipArchive belum aktif. Pakai folder hasil extract atau aktifkan extension zip di PHP.');
    rrmdir($tmpDir);
    mkdir($tmpDir, 0777, true);
    $zip = new ZipArchive();
    if ($zip->open($source) !== true) fail('ZIP tidak bisa dibuka.');
    $zip->extractTo($tmpDir);
    $zip->close();
    $dataDir = $tmpDir;
    line('ZIP berhasil diextract sementara.');
}
if (!is_dir($dataDir)) fail('sumber bukan folder data yang valid.');

$files = csvFiles($dataDir);
if (!$files) fail('tidak ada file .csv yang ditemukan.');
line('Jumlah CSV ditemukan: ' . count($files));

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($mysqli->connect_errno) fail('koneksi MySQL gagal: ' . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

$stmt = $mysqli->prepare("INSERT INTO `$table` (tagno, data_time, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
if (!$stmt) fail('prepare SQL gagal: ' . $mysqli->error);

$total = 0;
$fileNo = 0;
foreach ($files as $file) {
    $fileNo++;
    $tagno = tagFromName($file);
    if (!$tagno) { line('SKIP: tagno tidak ketemu dari nama file ' . basename($file)); continue; }

    line("[$fileNo/" . count($files) . '] Import ' . basename($file) . " | tagno=$tagno");
    $fh = fopen($file, 'r');
    if (!$fh) { line('  SKIP: file tidak bisa dibuka'); continue; }
    $firstLine = fgets($fh);
    if ($firstLine === false) { fclose($fh); line('  SKIP: file kosong'); continue; }
    $delimiter = detectDelimiter($firstLine);
    rewind($fh);

    $firstRow = fgetcsv($fh, 0, $delimiter);
    if (!$firstRow || count($firstRow) < 2) { fclose($fh); line('  SKIP: kolom tidak valid'); continue; }

    $hasHeader = rowLooksLikeHeader($firstRow);
    if ($hasHeader) {
        [$timeIdx, $valueIdx] = chooseFromHeader($firstRow);
        // Kalau header aneh, intip baris data pertama untuk menentukan kolom
        if ($timeIdx === null || $valueIdx === null) {
            $pos = ftell($fh);
            $peek = fgetcsv($fh, 0, $delimiter);
            fseek($fh, $pos);
            if ($peek) [$timeIdx, $valueIdx] = chooseFromDataRow($peek, $tagno);
        }
        line('  Format: pakai header');
        line('  Kolom waktu: ' . labelCol($firstRow, $timeIdx) . ' | kolom nilai: ' . labelCol($firstRow, $valueIdx));
    } else {
        [$timeIdx, $valueIdx] = chooseFromDataRow($firstRow, $tagno);
        // karena tidak ada header, baris pertama juga harus diproses
        rewind($fh);
        line('  Format: TANPA HEADER');
        line('  Kolom waktu index: ' . $timeIdx . ' | kolom nilai index: ' . $valueIdx);
        line('  Contoh baris: ' . implode(' | ', array_slice($firstRow, 0, 5)));
    }

    if ($timeIdx === null || $valueIdx === null) {
        fclose($fh);
        line('  SKIP: kolom waktu/nilai tidak ketemu');
        continue;
    }

    $count = 0;
    $skip = 0;
    $mysqli->begin_transaction();
    try {
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!isset($row[$timeIdx]) || !isset($row[$valueIdx])) { $skip++; continue; }
            $dt = parseDateTimeStr($row[$timeIdx]);
            $nilai = parseNumber($row[$valueIdx]);
            if ($dt === null || $nilai === null) { $skip++; continue; }
            $stmt->bind_param('ssd', $tagno, $dt, $nilai);
            if (!$stmt->execute()) throw new Exception($stmt->error);
            $count++;
            if ($count % 2000 === 0) { $mysqli->commit(); $mysqli->begin_transaction(); }
        }
        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        fclose($fh);
        line('  ERROR: ' . $e->getMessage());
        continue;
    }
    fclose($fh);
    $total += $count;
    line("  OK: $count baris masuk/update | skip: $skip");
}
$stmt->close();

$res = $mysqli->query("SELECT COUNT(*) AS total FROM `$table`");
$row = $res ? $res->fetch_assoc() : ['total' => '?'];
line('===============================================');
line('SELESAI');
line('Total baris diproses sekarang: ' . $total);
line('Total isi tabel aktual sekarang: ' . $row['total']);
line('===============================================');
$mysqli->close();
if (is_dir($tmpDir)) rrmdir($tmpDir);
