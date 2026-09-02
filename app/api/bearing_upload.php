<?php
// ── API: Upload CSV Bearing ──────────────────────────────────────────────
// File disimpan ke: public/uploads/bearing/unit_{unit_id}/{filename}
// Metadata disimpan ke DB UNIT (db_pacitan_2, db_paiton_1, dll) — BUKAN pln_web
// URL: ?api=bearing-upload
require_login();
header('Content-Type: application/json');

// ── Koneksi ke DB unit dari session ──────────────────────────────────────
$unit_id  = intval($_SESSION['selected_unit_id']  ?? 0);
$plant_id = intval($_SESSION['selected_plant_id'] ?? 0);

if (!$unit_id) {
    echo json_encode(['success'=>false,'error'=>'Pilih unit terlebih dahulu','uploaded'=>[],'errors'=>[],'files'=>[]]);
    exit;
}

global $conn;
$unit_conn = get_unit_db($unit_id, $conn);
if (!$unit_conn) {
    echo json_encode(['success'=>false,'error'=>'Tidak bisa koneksi ke database unit','uploaded'=>[],'errors'=>[],'files'=>[]]);
    exit;
}

// ── Direktori upload per unit ────────────────────────────────────────────
$_root      = realpath(__DIR__ . '/../../') ?: dirname(dirname(__DIR__));
$_root      = rtrim(str_replace('\\', '/', $_root), '/');
$UPLOAD_DIR = $_root . '/public/uploads/bearing/unit_' . $unit_id . '/';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);
if (!is_dir($UPLOAD_DIR)) {
    echo json_encode(['success'=>false,'error'=>"Tidak bisa buat direktori: $UPLOAD_DIR",'uploaded'=>[],'errors'=>[],'files'=>[]]);
    exit;
}

$uploaded = [];
$errors   = [];
$seen     = [];

// Kumpulkan semua file dari semua input
$all_files_raw = [];
foreach ($_FILES as $slot => $fdata) {
    if (is_array($fdata['name'])) {
        for ($i = 0; $i < count($fdata['name']); $i++) {
            if (isset($fdata['error'][$i]) && $fdata['error'][$i] === UPLOAD_ERR_OK)
                $all_files_raw[] = ['name'=>$fdata['name'][$i], 'tmp'=>$fdata['tmp_name'][$i]];
        }
    } else {
        if ($fdata['error'] === UPLOAD_ERR_OK)
            $all_files_raw[] = ['name'=>$fdata['name'], 'tmp'=>$fdata['tmp_name']];
    }
}

foreach ($all_files_raw as $f) {
    $orig = basename($f['name']);
    if (strtolower(pathinfo($orig, PATHINFO_EXTENSION)) !== 'csv') { $errors[]="$orig bukan CSV"; continue; }
    if (in_array($orig, $seen)) continue;
    $seen[] = $orig;
    $dest = $UPLOAD_DIR . $orig;
    if (move_uploaded_file($f['tmp'], $dest)) {
        $uploaded[] = $orig;
        _save_meta_unit($unit_conn, $orig, $dest, $unit_id, $plant_id);
    } else { $errors[] = "Gagal simpan $orig"; }
}

// Daftar semua file untuk unit ini
$all_files = _list_files($unit_id, $_root);

echo json_encode([
    'success'  => !empty($uploaded) && empty($errors),
    'uploaded' => $uploaded,
    'errors'   => $errors,
    'files'    => $all_files,
    'unit_id'  => $unit_id,
]);

function _list_files(int $uid, string $root): array {
    $result=[]; $seen=[];
    foreach (["$root/public/uploads/bearing/unit_$uid/","$root/public/uploads/bearing/shared/","$root/app/jupyter/notebooks/bearing/data/"] as $dir) {
        $dir = rtrim(str_replace('\\','/',$dir),'/').'/';
        if (!is_dir($dir)) continue;
        foreach (glob($dir.'*.csv')?:[] as $f) {
            $n=basename($f); if(in_array($n,$seen))continue; $seen[]=$n;
            $result[]=['name'=>$n,'source'=>str_contains($f,'uploads')?'upload':'data'];
        }
    }
    usort($result,fn($a,$b)=>strcmp($a['name'],$b['name']));
    return $result;
}

function _save_meta_unit(PDO $uc, string $fn, string $fp, int $unit_id = 0, int $plant_id = 0): void {
    try {
        $sz  = file_exists($fp)?filesize($fp):0;
        $fh  = fopen($fp,'r'); $first=fgets($fh); fclose($fh);
        $nc  = max(1,substr_count(trim($first),',')+1);
        $ci  = $nc>=3?1:0;
        $rows=0; $dmin=$dmax=null;
        $fh=fopen($fp,'r');
        while(($ln=fgets($fh))!==false){
            $ln=trim($ln); if(!$ln)continue;
            $p=explode(',',$ln); if(count($p)<=$ci)continue;
            $ts=trim($p[$ci]," \t\r\n\"'"); $d=substr($ts,0,10);
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)){
                if(!$dmin||$d<$dmin)$dmin=$d;
                if(!$dmax||$d>$dmax)$dmax=$d;
            }
            $rows++;
        }
        fclose($fh);
        $abs=str_replace('\\','/',realpath($fp)?:$fp);
        $uid = $unit_id ?: null;
        $pid = $plant_id ?: null;
        $st=$uc->prepare("INSERT INTO bearing_csv_files (filename,filepath,file_size,row_count,date_min,date_max,unit_id,plant_id,uploaded_by)
            VALUES(:fn,:fp,:sz,:nr,:dmin,:dmax,:uid,:pid,:by)
            ON DUPLICATE KEY UPDATE filepath=VALUES(filepath),file_size=VALUES(file_size),
            row_count=VALUES(row_count),date_min=VALUES(date_min),date_max=VALUES(date_max),
            unit_id=VALUES(unit_id),plant_id=VALUES(plant_id),
            uploaded_at=NOW(),uploaded_by=VALUES(uploaded_by)");
        $st->execute([':fn'=>$fn,':fp'=>$abs,':sz'=>$sz,':nr'=>$rows,':dmin'=>$dmin,':dmax'=>$dmax,
                      ':uid'=>$uid,':pid'=>$pid,':by'=>$_SESSION['username']??'web']);
    } catch(Throwable $e){ error_log("[upload] $fn: ".$e->getMessage()); }
}
