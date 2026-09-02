<?php
require_login();
// ══════════════════════════════════════════════════════════════════
// BEARING CSV — Upload & perbandingan multi-sensor CSV
// Fix: DB di unit DB, tampil 858/859, selector sensor, anomali garis
// ══════════════════════════════════════════════════════════════════

if (!defined('BEARING_API_URL_CSV')) define('BEARING_API_URL_CSV','http://localhost:5050');

function bearing_csv_get(string $path): ?array {
    $raw = @file_get_contents(BEARING_API_URL_CSV . $path);
    return $raw !== false ? json_decode($raw, true) : null;
}

$health     = bearing_csv_get('/health');
$api_online = ($health !== null);

global $conn;
$_unit_id_php  = intval($_SESSION['selected_unit_id']  ?? 0);
$_plant_id_php = intval($_SESSION['selected_plant_id'] ?? 0);
$_unit_db_name = '';
if ($_unit_id_php) {
    try {
        $__stmt = $conn->prepare("SELECT database_name FROM units WHERE unit_id=?");
        $__stmt->execute([$_unit_id_php]);
        $_unit_db_name = $__stmt->fetchColumn() ?: '';
    } catch(Throwable $e){}
}

$batas = max(0.1,(float)($_GET['batas'] ?? 5.0));

// ── Ambil data 858 & 859 dari unit DB ──────────────────────────
$bearing_data = []; // ['858'=>[['date'=>'...','value'=>...]], '859'=>[...]]
if ($_unit_id_php) {
    try {
        $unit_pdo = get_unit_db($_unit_id_php, $conn);
        if ($unit_pdo) {
            // bearing_aktual biasanya ada: tagno, tgl_data, avg_nilai
            foreach (['858','859'] as $tag) {
                $rows = $unit_pdo->prepare(
                    "SELECT DATE_FORMAT(tgl_data,'%Y-%m-%d') as d, avg_nilai as v
                     FROM bearing_aktual WHERE tagno=? ORDER BY tgl_data"
                );
                $rows->execute([$tag]);
                $pts = $rows->fetchAll(PDO::FETCH_ASSOC);
                if ($pts) $bearing_data[$tag] = $pts;
            }
        }
    } catch(Throwable $e){}
}

// ── Ambil anomali 858 & 859 dari bearing_anomaly_log ────────────
$anomaly_dates = []; // ['858'=>['2025-01-05','2025-02-10'], '859'=>[...]]
if ($_unit_id_php) {
    try {
        $unit_pdo = $unit_pdo ?? get_unit_db($_unit_id_php, $conn);
        if ($unit_pdo) {
            foreach (['858','859'] as $tag) {
                // Cek tabel bearing_prediksi untuk kolom anomali
                $rows = $unit_pdo->prepare(
                    "SELECT DATE_FORMAT(tgl_data,'%Y-%m-%d') as d
                     FROM bearing_prediksi WHERE tagno=? AND is_anomali=1 ORDER BY tgl_data"
                );
                $rows->execute([$tag]);
                $dates = $rows->fetchAll(PDO::FETCH_COLUMN);
                if ($dates) $anomaly_dates[$tag] = $dates;
            }
        }
    } catch(Throwable $e){}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Upload CSV Sensor — PLN</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
/* Flatpickr Dark Theme — sesuai tema web */
.flatpickr-calendar{background:var(--bg-card)!important;border:2px solid var(--accent-cyan)!important;border-radius:16px!important;box-shadow:0 8px 32px rgba(0,0,0,.4)!important;padding:10px!important;width:310px!important}
.flatpickr-months{background:rgba(0,217,255,.08)!important;border-radius:10px!important;padding:4px!important;margin-bottom:8px!important;display:flex!important;align-items:center!important;gap:4px!important;height:auto!important}
.flatpickr-months .flatpickr-month{background:transparent!important;height:36px!important;flex:1!important}
.flatpickr-prev-month,.flatpickr-next-month{position:static!important;width:34px!important;height:34px!important;padding:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;background:var(--bg-secondary)!important;border:1.5px solid var(--border-color)!important;border-radius:8px!important;color:var(--accent-cyan)!important;cursor:pointer!important;flex-shrink:0!important;top:auto!important}
.flatpickr-prev-month:hover,.flatpickr-next-month:hover{background:rgba(0,217,255,.15)!important;border-color:var(--accent-cyan)!important}
.flatpickr-prev-month svg,.flatpickr-next-month svg{width:14px!important;height:14px!important;fill:var(--accent-cyan)!important}
.flatpickr-current-month{display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;font-size:14px!important;font-weight:700!important;padding:0!important;width:100%!important;left:auto!important;position:static!important}
.flatpickr-current-month .flatpickr-monthDropdown-months{font-size:14px!important;font-weight:700!important;color:var(--text-primary)!important;background:var(--bg-secondary)!important;border:1px solid var(--border-color)!important;border-radius:8px!important;padding:4px 8px!important}
.flatpickr-current-month input.cur-year{font-size:14px!important;font-weight:700!important;color:var(--text-primary)!important;background:var(--bg-secondary)!important;border:1px solid var(--border-color)!important;border-radius:8px!important;padding:4px 6px!important;width:60px!important;text-align:center!important}
.flatpickr-weekdays{background:transparent!important;margin-bottom:4px!important}
span.flatpickr-weekday{color:var(--accent-cyan)!important;font-size:11px!important;font-weight:700!important;text-transform:uppercase!important;background:transparent!important}
.flatpickr-days{border:none!important}.dayContainer{padding:0!important}
.flatpickr-day{color:var(--text-primary)!important;border-radius:8px!important;font-size:13px!important;height:38px!important;line-height:38px!important;border:1px solid transparent!important;margin:1px!important}
.flatpickr-day:hover{background:rgba(0,217,255,.12)!important;color:var(--accent-cyan)!important}
.flatpickr-day.selected{background:linear-gradient(135deg,#00d9ff,#0066ff)!important;color:#0a1628!important;font-weight:700!important}
.flatpickr-day.today{border-color:var(--accent-cyan)!important}
.flatpickr-day.flatpickr-disabled{color:rgba(148,163,184,.3)!important}
</style>
<script>if(window.ChartAnnotation){Chart.register(window.ChartAnnotation);window.ChartAnnotation=Chart.registry.plugins.get('annotation');}</script>
<style>
.csv-wrap{display:flex;flex-direction:column;gap:20px}
.page-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:22px 24px}
.btn-primary{padding:9px 22px;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-blue));border:none;border-radius:10px;color:#0a1628;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:opacity .2s}
.btn-primary:hover{opacity:.88}
.btn-sec{padding:8px 16px;background:var(--bg-secondary);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-secondary);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .2s}
.btn-sec:hover{border-color:var(--accent-cyan);color:var(--accent-cyan)}
#csv-dropzone{border:2px dashed var(--border-color);border-radius:12px;padding:36px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s}
#csv-dropzone:hover{border-color:var(--accent-cyan);background:rgba(0,217,255,.03)}
.at-wrap{max-height:340px;overflow-y:auto;margin-top:10px}
.at{width:100%;border-collapse:collapse;font-size:12px}
.at th{padding:8px 12px;text-align:left;background:var(--bg-secondary);color:var(--text-secondary);font-size:10px;text-transform:uppercase;letter-spacing:.07em;position:sticky;top:0}
.at td{padding:8px 12px;border-bottom:1px solid var(--border-color);color:var(--text-primary)}
.at tr:hover td{background:var(--hover-bg)}
.leg{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px}
.leg-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary)}
.leg-line{width:22px;height:3px;border-radius:2px}
.leg-vline{width:3px;height:16px;border-radius:1px;display:inline-block}
.leg-dot{width:10px;height:10px;border-radius:50%}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{border:3px solid rgba(99,179,237,.2);border-top-color:#63b3ed;border-radius:50%;animation:spin 1s linear infinite}
.api-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.api-badge.online{background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.3)}
.api-badge.offline{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)}
/* Sensor selector */
.sensor-check{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.sensor-pill{display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid var(--border-color);color:var(--text-secondary);background:var(--bg-secondary);transition:all .15s;user-select:none}
.sensor-pill.on{background:rgba(0,217,255,.12);border-color:var(--accent-cyan);color:var(--accent-cyan)}
.sensor-pill .sp-dot{width:10px;height:10px;border-radius:50%}
</style>
</head>
<body>
<div class="layout">
<?php include VIEWS . 'shared/sidebar.php'; ?>
<div class="main-content">
<?php include VIEWS . 'shared/header.php'; ?>
<div class="content">

<!-- Page header -->
<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div>
        <h1 class="page-title" style="margin-bottom:4px">
            <i class="bi bi-file-earmark-bar-graph" style="color:var(--accent-cyan)"></i> Upload CSV Sensor
        </h1>
        <p style="font-size:12px;color:var(--text-secondary);margin:0">
            Upload CSV sensor lain · bandingkan dengan Bearing 858 &amp; 859 · anomali ditampilkan sebagai garis vertikal merah
        </p>
    </div>
    <span class="api-badge <?= $api_online?'online':'offline' ?>">
        <i class="bi bi-circle-fill" style="font-size:7px"></i>
        <?= $api_online?'Python API Online':'Python API Offline' ?>
    </span>
</div>

<div class="csv-wrap">

<!-- Info -->
<div class="page-card" style="padding:14px 20px;display:flex;align-items:flex-start;gap:12px">
    <i class="bi bi-info-circle" style="color:var(--accent-cyan);font-size:18px;flex-shrink:0;margin-top:2px"></i>
    <div style="font-size:13px;color:var(--text-secondary);line-height:1.7">
        Upload CSV sensor lain → grafik digabung bersama data <strong>Bearing 858</strong> &amp; <strong>Bearing 859</strong>.
        Bisa upload <strong>banyak file sekaligus</strong>. Anomali ditandai dengan <strong style="color:#ef4444">garis vertikal merah</strong>.<br>
        Format: <code>tanggal (YYYY-MM-DD)</code> + kolom nilai numerik &nbsp;·&nbsp; Format IDF2B (tagno, datetime, nilai) juga didukung.
    </div>
</div>

<!-- Upload area -->
<div class="page-card">
    <div style="font-size:12px;font-weight:700;color:var(--accent-cyan);text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px">
        <i class="bi bi-upload"></i> Upload File CSV
    </div>
    <div id="csv-dropzone"
         onclick="document.getElementById('csv-input').click()"
         ondragover="event.preventDefault();this.style.borderColor='var(--accent-cyan)'"
         ondragleave="this.style.borderColor=''"
         ondrop="_csvDropHandler(event)">
        <input type="file" id="csv-input" accept=".csv" multiple style="display:none" onchange="_csvFilesSelected(this.files)">
        <i class="bi bi-file-earmark-bar-graph" style="font-size:44px;color:var(--text-secondary)"></i>
        <div style="margin-top:12px;font-size:15px;font-weight:700">Klik atau seret file CSV ke sini</div>
        <div style="font-size:12px;color:var(--text-secondary);margin-top:6px">Format: tanggal (YYYY-MM-DD) + nilai numerik · bisa banyak file sekaligus</div>
    </div>
    <div id="csv-files-list" style="margin-top:14px"></div>
    <div id="csv-config-area" style="display:none;margin-top:14px">
        <div style="font-size:11px;font-weight:700;color:var(--text-secondary);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px">Konfigurasi Kolom CSV</div>
        <div id="csv-per-file-config" style="display:flex;flex-direction:column;gap:10px"></div>
        <div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <button onclick="_renderCsvChart()" class="btn-primary"><i class="bi bi-bar-chart-line"></i> Tampilkan Grafik</button>
            <button onclick="_csvReset()" class="btn-sec"><i class="bi bi-x-circle"></i> Hapus Semua &amp; Reset</button>
        </div>
    </div>
</div>

<!-- Date range filter + toleransi siang/malam -->
<div id="date-filter-wrap" style="display:none">
    <div class="page-card" style="padding:14px 20px">
        <div style="font-size:12px;font-weight:700;color:var(--accent-cyan);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">
            <i class="bi bi-calendar-range"></i> Filter Tanggal &amp; Toleransi
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <div>
                <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px">DARI</label>
                <input type="text" id="csv-date-start" placeholder="YYYY-MM-DD" autocomplete="off"
                       style="padding:7px 12px;background:var(--input-bg);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;width:140px">
            </div>
            <div>
                <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px">SAMPAI</label>
                <input type="text" id="csv-date-end" placeholder="YYYY-MM-DD" autocomplete="off"
                       style="padding:7px 12px;background:var(--input-bg);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;width:140px">
            </div>
            <div>
                <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px">TOLERANSI SIANG (°C) <span style="color:#f59e0b">☀</span></label>
                <input type="number" id="csv-batas-siang" value="5" min="0.5" max="50" step="0.5"
                       oninput="_updateAutoMalam()"
                       style="padding:7px 12px;background:var(--input-bg);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;width:110px">
            </div>
            <div>
                <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px">TOLERANSI MALAM (°C) <span style="color:#6366f1">🌙</span></label>
                <input type="number" id="csv-batas-malam" value="3" min="0.5" max="50" step="0.5"
                       style="padding:7px 12px;background:var(--input-bg);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;width:110px">
            </div>
            <button onclick="_applyDateFilter()" class="btn-primary" style="padding:8px 18px;font-size:12px">
                <i class="bi bi-funnel"></i> Terapkan
            </button>
            <button onclick="_clearDateFilter()" class="btn-sec" style="padding:7px 14px;font-size:12px">
                <i class="bi bi-x-circle"></i> Reset
            </button>
        </div>
        <div style="margin-top:8px;font-size:11px;color:var(--text-secondary)">
            <i class="bi bi-info-circle"></i> Siang = 06:00–18:00 · Malam = 18:00–06:00 · Anomali juga terdeteksi jika ≥10 titik 5-menit dalam 1 jam melewati toleransi berturut-turut
        </div>
    </div>
</div>

<!-- Sensor selector (shown after chart data is ready) -->
<div id="sensor-selector-wrap" style="display:none">
    <div class="page-card" style="padding:14px 20px">
        <div style="font-size:12px;font-weight:700;color:var(--accent-cyan);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">
            <i class="bi bi-toggles"></i> Pilih Sensor yang Ditampilkan
        </div>
        <div id="sensor-pills" class="sensor-check"></div>
        <div style="margin-top:10px;display:flex;gap:8px">
            <button onclick="_selectAllSensors(true)"  class="btn-sec" style="font-size:11px;padding:4px 12px"><i class="bi bi-check-all"></i> Pilih Semua</button>
            <button onclick="_selectAllSensors(false)" class="btn-sec" style="font-size:11px;padding:4px 12px"><i class="bi bi-x"></i> Hapus Semua</button>
        </div>
    </div>
</div>

<!-- Combined chart -->
<div id="csv-chart-wrap" style="display:none">
    <div class="page-card">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px">
            <div style="font-size:13px;font-weight:700;color:var(--text-primary)">
                <i class="bi bi-bar-chart-line" style="color:var(--accent-cyan)"></i> Grafik Gabungan Sensor
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button onclick="_saveCsvToDb()" id="btn-csv-save-db" style="padding:6px 14px;background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.4);border-radius:8px;color:#60a5fa;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px">
                    <i class="bi bi-cloud-arrow-up"></i> Simpan ke DB
                </button>
                <button onclick="_exportXLSX()" style="padding:6px 14px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);border-radius:8px;color:#22c55e;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px">
                    <i class="bi bi-file-earmark-spreadsheet"></i> XLSX
                </button>
                <button onclick="_exportPDF()" style="padding:6px 14px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);border-radius:8px;color:#ef4444;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
            </div>
        </div>
        <!-- Legend -->
        <div id="csv-legend" class="leg"></div>
        <!-- Stats -->
        <div id="csv-stats" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px"></div>
        <!-- Main chart -->
        <div id="wrap-csv-main" style="position:relative;height:380px;background:var(--bg-card);border-radius:10px;padding:10px;border:1px solid var(--border-color)">
            <canvas id="chart-csv-main"></canvas>
        </div>
        <!-- Deviation chart -->
        <div style="font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.06em;margin:10px 0 4px">Deviasi Harian</div>
        <div id="wrap-csv-dev" style="position:relative;height:140px;background:var(--bg-card);border-radius:10px;padding:8px;border:1px solid var(--border-color)">
            <canvas id="chart-csv-dev"></canvas>
        </div>
        <!-- Anomaly timeline -->
        <div style="font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.06em;margin:12px 0 4px">
            <i class="bi bi-calendar-x" style="color:#ef4444"></i> Garis Waktu Anomali
        </div>
        <div id="wrap-csv-anom" style="position:relative;height:48px;width:100%;background:var(--bg-secondary);border-radius:8px;border:1px solid var(--border-color);overflow:hidden">
            <canvas id="chart-csv-anom"></canvas>
        </div>
        <!-- Per-sensor anomaly tables -->
        <div id="csv-table-wrap" style="margin-top:16px"></div>
    </div>
</div>

</div><!-- .csv-wrap -->
</div><!-- .content -->
</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
var BATAS   = <?= $batas ?>;
const UNIT_DB = '<?= addslashes($_unit_db_name) ?>';
const UNIT_ID = <?= (int)$_unit_id_php ?>;

// ── Nama sensor: hardcoded fallback + DB override ────────────────────
var SENSOR_NAMES = {"85":"IDF B DE Vib-Y","89":"IDF B Coil V Temp 2","90":"IDF B Coil W Temp 2","336":"IDF B Current","858":"IDF B Coil W Temp 1","859":"IDF B DE Brg Temp 1","877":"PAF A Inlet Air Temp","1094":"IDF A DE Brg Temp 1","1101":"IDF B NDE Vib-Y","1106":"IDF B Middle Brg Temp 2","1107":"IDF B Coil V Temp 1","1357":"IDF B Motor DE Brg Temp","1358":"IDF B DE Brg Temp 2","1577":"Unit Load (MW)","1628":"IDF A Middle Brg Temp 1","1868":"IDF B NDE Brg Temp 1"};
function getSensorName(tag){ var t=String(tag); return (SENSOR_NAMES[t]&&SENSOR_NAMES[t].singkatan)||SENSOR_NAMES[t]||('Tag '+t); }

// Load from DB (overrides hardcoded)
fetch('<?= BASE_URL ?>?api=bearing-proxy&action=sensor_names')
    .then(function(r){return r.json();})
    .then(function(data){
        if(data.success&&data.tags){
            Object.keys(data.tags).forEach(function(id){
                var t=data.tags[id];
                SENSOR_NAMES[id]=t.singkatan||t.deskripsi;
            });
        }
    }).catch(function(){});  // silent fail — fallback to hardcoded

// ── Auto toleransi siang/malam (rasio 0.6) ────────────────────────────
var _batasSiang = <?= $batas ?>, _batasMalam = <?= round($batas * 0.6, 1) ?>;
function _updateAutoMalam() {
    var sEl = document.getElementById('csv-batas-siang');
    var mEl = document.getElementById('csv-batas-malam');
    if (!sEl || !mEl) return;
    _batasSiang = parseFloat(sEl.value) || 5;
    _batasMalam = Math.round(_batasSiang * 0.6 * 10) / 10;
    mEl.value = _batasMalam;
    mEl.parentElement.querySelector('label').innerHTML = 'TOLERANSI MALAM (°C) 🌙 <span style="color:#94a3b8;font-weight:400;font-size:10px">(auto: ' + _batasSiang + '×0.6)</span>';
}

// ── SVG overlay: garis merah di atas Chart.js canvas ─────────────────
function _drawAnomalyOverlay(canvasId, labels, anomaliArr) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var chartInst = Chart.getChart(canvasId);
    if (!chartInst || !chartInst.chartArea) return;
    var ca = chartInst.chartArea;
    var nTotal = labels.length;
    if (nTotal < 2) return;
    var wrapper = canvas.parentElement;
    wrapper.querySelectorAll('.anom-svg-overlay').forEach(function(el){ el.remove(); });
    var step = (ca.right - ca.left) / (nTotal - 1);
    var lines = '';
    labels.forEach(function(l, i) {
        if (!(anomaliArr||[])[i]) return;
        var x = Math.round(ca.left + i * step);
        lines += '<line x1="'+x+'" y1="'+Math.round(ca.top)+'" x2="'+x+'" y2="'+Math.round(ca.bottom)+'" stroke="rgba(239,68,68,.8)" stroke-width="1.5"/>';
    });
    if (!lines) return;
    var svgEl = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svgEl.classList.add('anom-svg-overlay');
    svgEl.style.cssText = 'position:absolute;top:0;left:0;pointer-events:none;z-index:5';
    svgEl.setAttribute('width', canvas.clientWidth || wrapper.clientWidth);
    svgEl.setAttribute('height', canvas.clientHeight || wrapper.clientHeight);
    svgEl.innerHTML = lines;
    wrapper.appendChild(svgEl);
}

// ── Pure SVG anomaly timeline ─────────────────────────────────────────
function _drawAnomalyTimeline(wrapperId, labels, anomaliArr) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    wrapper.style.cssText = 'position:relative;height:48px;width:100%;background:var(--bg-secondary);border-radius:8px;border:1px solid var(--border-color);overflow:hidden';
    var nTotal = labels.length;
    var nAnom = (anomaliArr||[]).filter(Boolean).length;
    if (nTotal < 1 || nAnom === 0) {
        wrapper.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:11px;gap:5px"><i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:13px"></i> Tidak ada anomali</div>';
        return;
    }
    var lines = '';
    labels.forEach(function(l, i) {
        if (!(anomaliArr||[])[i]) return;
        var pct = nTotal > 1 ? (i / (nTotal - 1)) * 100 : 0;
        lines += '<line x1="'+pct+'%" y1="0" x2="'+pct+'%" y2="100%" stroke="rgba(239,68,68,.9)" stroke-width="2" stroke-dasharray="4 3"/>';
    });
    wrapper.innerHTML = '<svg width="100%" height="48" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">'+lines+'</svg>'
        + '<div style="position:absolute;bottom:3px;right:8px;font-size:9px;font-weight:700;color:rgba(239,68,68,.8)">⚠ '+nAnom+' hari / '+nTotal+' hari</div>';
}

// ── 858/859 data from PHP ────────────────────────────────────────
var BEARING_DATA = <?= json_encode($bearing_data) ?>;
var ANOMALY_DATES = <?= json_encode($anomaly_dates) ?>;
var SENSOR_COLORS = {
    '858': '#2563eb',
    '859': '#7c3aed',
    '_csv_0':'#22c55e','_csv_1':'#f59e0b','_csv_2':'#06b6d4',
    '_csv_3':'#ec4899','_csv_4':'#f97316','_csv_5':'#a3e635'
};

// ── State ────────────────────────────────────────────────────────
var _csvFiles = [];
var _allSensorData = {};  // key -> {label, pts:[{dateStr,value}], color, anomDates:[]}
var _visibleSensors = {}; // key -> bool
var _chartInst = null, _devInst = null, _anomInst = null;
var _lastCsvAllData = null;

// ── Preload bearing 858/859 ──────────────────────────────────────
function _preloadBearingData() {
    ['858','859'].forEach(function(tag){
        var pts = BEARING_DATA[tag];
        if(!pts||!pts.length) return;
        _allSensorData[tag] = {
            label: getSensorName(tag) + ' ('+tag+')',
            pts: pts.map(function(r){ return {dateStr:r.d, value:parseFloat(r.v)}; }),
            color: SENSOR_COLORS[tag],
            anomDates: ANOMALY_DATES[tag] || [],
            isBearing: true
        };
        _visibleSensors[tag] = true;
    });
}
_preloadBearingData();

// ── CSV file handling ────────────────────────────────────────────
function _csvDropHandler(e){
    e.preventDefault();
    document.getElementById('csv-dropzone').style.borderColor='';
    _csvFilesSelected(e.dataTransfer.files);
}

function _csvFilesSelected(fileList){
    for(var i=0;i<fileList.length;i++){
        var f=fileList[i];
        if(!f.name.toLowerCase().endsWith('.csv'))continue;
        if(!_csvFiles.find(function(x){return x.name===f.name;}))
            _csvFiles.push({file:f,name:f.name,label:f.name.replace(/\.csv$/i,''),cols:[],dateCol:null,valCol:null});
    }
    _csvReadHeaders();
}

function _csvReadHeaders(){
    var pending=_csvFiles.filter(function(f){return f.cols.length===0;});
    var done=0;
    if(!pending.length){_csvRenderConfig();return;}
    pending.forEach(function(fd){
        var r=new FileReader();
        r.onload=function(e){
            var lines=e.target.result.split('\n').slice(0,3).map(function(l){return l.trim();}).filter(Boolean);
            if(!lines.length){done++;if(done>=pending.length)_csvRenderConfig();return;}
            var row0=lines[0].split(',');
            var isIdf2b=row0.length===3&&!isNaN(row0[0])&&!isNaN(parseFloat(row0[2]));
            if(isIdf2b){fd.cols=['tagno','datetime','nilai'];fd.dateCol='datetime';fd.valCol='nilai';fd.isIdf2b=true;}
            else{
                fd.cols=row0.map(function(c){return c.trim().replace(/^"|"$/g,'');});
                fd.isIdf2b=false;
                fd.dateCol=fd.cols.find(function(c){return /date|time|tanggal|dt/i.test(c);})||fd.cols[0];
                fd.valCol=fd.cols.find(function(c){return !/date|time|tanggal|dt/i.test(c)&&c!==fd.dateCol;})||fd.cols[1];
            }
            done++;if(done>=pending.length)_csvRenderConfig();
        };
        r.readAsText(fd.file,'utf-8');
    });
}

function _csvRenderConfig(){
    if(!_csvFiles.length)return;
    document.getElementById('csv-config-area').style.display='block';
    var csvColorKeys=Object.keys(SENSOR_COLORS).filter(function(k){return k.startsWith('_csv_');});
    var el=document.getElementById('csv-per-file-config');
    el.innerHTML=_csvFiles.map(function(fd,idx){
        var col=SENSOR_COLORS['_csv_'+idx]||'#94a3b8';
        var dateSelected=fd.dateCol||fd.cols[0];
        var valSelected=fd.valCol||fd.cols[1];
        return '<div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">'
            +'<div style="width:14px;height:14px;border-radius:50%;background:'+col+';flex-shrink:0"></div>'
            +'<div style="font-size:12px;font-weight:600;flex:1;min-width:120px">'+fd.name+'</div>'
            +'<div style="display:flex;gap:8px;flex-wrap:wrap">'
            +'<div><label style="font-size:10px;color:var(--text-secondary)">KOLOM TANGGAL</label><br>'
            +'<select onchange="_csvFiles['+idx+'].dateCol=this.value" style="font-size:11px;padding:4px 8px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:6px;color:var(--text-primary)">'
            +fd.cols.map(function(c){return '<option value="'+c+'"'+(c===dateSelected?' selected':'')+'>'+c+'</option>';}).join('')+'</select></div>'
            +'<div><label style="font-size:10px;color:var(--text-secondary)">KOLOM NILAI</label><br>'
            +'<select onchange="_csvFiles['+idx+'].valCol=this.value" style="font-size:11px;padding:4px 8px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:6px;color:var(--text-primary)">'
            +fd.cols.map(function(c){return '<option value="'+c+'"'+(c===valSelected?' selected':'')+'>'+c+'</option>';}).join('')+'</select></div>'
            +'<div><label style="font-size:10px;color:var(--text-secondary)">LABEL</label><br>'
            +'<input value="'+fd.label+'" onchange="_csvFiles['+idx+'].label=this.value" style="font-size:11px;padding:4px 8px;width:110px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:6px;color:var(--text-primary)"></div>'
            +'<button onclick="_csvRemove('+idx+')" style="margin-top:12px;padding:2px 8px;font-size:11px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);border-radius:6px;color:#ef4444;cursor:pointer">✕</button>'
            +'</div></div>';
    }).join('');
    document.getElementById('csv-files-list').innerHTML=_csvFiles.length
        ?'<div style="font-size:11px;color:var(--text-secondary)">'+_csvFiles.length+' file: '+_csvFiles.map(function(f){return f.name;}).join(', ')+'</div>':'';
}

function _csvRemove(idx){_csvFiles.splice(idx,1);_csvRenderConfig();if(!_csvFiles.length)_csvReset();}

function _csvReset(){
    _csvFiles=[];
    // Remove CSV sensors from state (keep bearing 858/859)
    Object.keys(_allSensorData).forEach(function(k){
        if(!_allSensorData[k].isBearing){delete _allSensorData[k];delete _visibleSensors[k];}
    });
    document.getElementById('csv-config-area').style.display='none';
    document.getElementById('csv-per-file-config').innerHTML='';
    document.getElementById('csv-files-list').innerHTML='';
    document.getElementById('csv-input').value='';
    _redrawAll();
}

// ── Parse CSV → daily avg + anomaly detection ─────────────────────
// Anomali per hari: nilai harian > toleransi (siang/malam)
// PLUS: rule 10-in-1-hour dari data 5-menit raw
function _parseCsv(text, fd) {
    var lines = text.split('\n').map(function(l){ return l.trim(); }).filter(Boolean);
    var dailyMap = {}; // dateStr -> {sum, cnt, rawPts:[{hour, val}]}

    function _addRaw(day, hour, val) {
        if (!dailyMap[day]) dailyMap[day] = {sum:0, cnt:0, rawPts:[]};
        dailyMap[day].sum += val;
        dailyMap[day].cnt++;
        dailyMap[day].rawPts.push({hour:hour, val:val});
    }

    if (fd.isIdf2b) {
        lines.forEach(function(line) {
            var parts = line.split(','); if (parts.length < 3) return;
            var val = parseFloat(parts[2]); if (isNaN(val)) return;
            var dt = parts[1].trim();
            var day = dt.substring(0, 10);
            var hour = parseInt(dt.substring(11, 13)) || 0;
            _addRaw(day, hour, val);
        });
    } else {
        var headers = lines[0].split(',').map(function(c){ return c.trim().replace(/^"|"$/g, ''); });
        var dcIdx = headers.indexOf(fd.dateCol); if (dcIdx < 0) dcIdx = 0;
        var vcIdx = headers.indexOf(fd.valCol);  if (vcIdx < 0) vcIdx = 1;
        for (var i = 1; i < lines.length; i++) {
            var cols = lines[i].split(',');
            if (cols.length <= Math.max(dcIdx, vcIdx)) continue;
            var dtStr = cols[dcIdx].trim().replace(/^"|"$/g, '');
            var val = parseFloat(cols[vcIdx].trim().replace(/^"|"$/g, '').replace(',', '.'));
            if (!isNaN(val)) {
                var day = dtStr.substring(0, 10);
                var hour = parseInt(dtStr.substring(11, 13)) || 0;
                _addRaw(day, hour, val);
            }
        }
    }

    var pts = [], anomDates = [];
    var bS = _batasSiang || 5, bM = _batasMalam || 3;

    // Compute daily mean for threshold reference (avg of all days)
    var allVals = Object.keys(dailyMap).map(function(d){ return dailyMap[d].sum / dailyMap[d].cnt; });
    var globalMean = allVals.length ? allVals.reduce(function(a,b){return a+b;},0) / allVals.length : 50;

    Object.keys(dailyMap).sort().forEach(function(day) {
        var dm = dailyMap[day];
        var avg = dm.sum / dm.cnt;
        pts.push({dateStr: day, value: avg});

        // ── Method 1: daily avg deviates from global mean beyond tolerance ──
        var isNight = false; // daily avg — use siang tolerance as default
        var batas = isNight ? bM : bS;
        var devFromMean = Math.abs(avg - globalMean);
        var method1Anom = devFromMean > batas;

        // ── Method 2: 10-in-1-hour rule on raw 5-minute data ──────────────
        // Group raw points by hour-slot, check if ≥10 points in any 60-min window exceed tolerance
        var method2Anom = false;
        if (dm.rawPts.length >= 10) {
            // Sort raw points by hour
            var sorted = dm.rawPts.slice().sort(function(a,b){ return a.hour - b.hour; });
            // Sliding window: for each starting point, count anomalies within 60 min (≈12 points at 5min intervals)
            // Since we only have hourly resolution from IDF2B format, use per-hour grouping
            var byHour = {};
            sorted.forEach(function(p) {
                if (!byHour[p.hour]) byHour[p.hour] = [];
                byHour[p.hour].push(p.val);
            });
            Object.keys(byHour).forEach(function(h) {
                var hVals = byHour[h];
                var hMean = hVals.reduce(function(a,b){return a+b;},0) / hVals.length;
                var hIsNight = (parseInt(h) < 6 || parseInt(h) >= 18);
                var hBatas = hIsNight ? bM : bS;
                // Count points exceeding tolerance from hour mean
                var anomCount = hVals.filter(function(v){ return Math.abs(v - hMean) > hBatas; }).length;
                // Also count per-kelipatan: every 10 anomalies triggers flag
                if (anomCount >= 10 || anomCount >= Math.ceil(hVals.length * 0.5)) {
                    method2Anom = true;
                }
            });
        }

        if (method1Anom || method2Anom) anomDates.push(day);
    });

    return {pts: pts, anomDates: anomDates};
}

// ── Compute anomalies for bearing 858/859 using same rule ─────────────
function _computeBearingAnomalies(pts) {
    if (!pts || pts.length < 2) return [];
    var bS = _batasSiang || 5, bM = _batasMalam || 3;
    var vals = pts.map(function(p){ return p.value; });
    var mean = vals.reduce(function(a,b){return a+b;},0) / vals.length;
    return pts.filter(function(p){ return Math.abs(p.value - mean) > bS; }).map(function(p){ return p.dateStr; });
}

// ── Render CSV chart ──────────────────────────────────────────────
function _renderCsvChart(){
    if(!_csvFiles.length){alert('Pilih minimal 1 file CSV terlebih dahulu.');return;}
    // Recompute bearing anomalies with current tolerance
    ['858','859'].forEach(function(tag){
        if(_allSensorData[tag]){
            _allSensorData[tag].anomDates = _computeBearingAnomalies(_allSensorData[tag].pts);
        }
    });
    var total=_csvFiles.length,done=0;
    _csvFiles.forEach(function(fd,idx){
        var r=new FileReader();
        r.onload=function(e){
            var key='csv_'+idx;
            var parsed=_parseCsv(e.target.result,fd);
            _allSensorData[key]={
                label:fd.label||fd.name,
                pts:parsed.pts,
                color:SENSOR_COLORS['_csv_'+idx]||'#94a3b8',
                anomDates:parsed.anomDates,
                isBearing:false,
                fd:fd
            };
            _visibleSensors[key]=true;
            done++;if(done>=total){_lastCsvAllData=true;_rebuildSelectorAndChart();}
        };
        r.readAsText(fd.file,'utf-8');
    });
}

// ── Load saved CSV from unit DB ──────────────────────────────────
async function _loadDbCsv(){
    try{
        var res=await fetch(BASE_URL+'?api=bearing-proxy&action=get_csv_sensor&unit_id='+encodeURIComponent(UNIT_ID)+'&unit_db='+encodeURIComponent(UNIT_DB));
        var data=await res.json();
        if(!data.success||!data.sensors||!data.sensors.length)return;
        var csvIdx=0;
        data.sensors.forEach(function(s){
            var key='db_'+s.tagno;
            var pts=(s.pts||[]).map(function(p){return{dateStr:p.date,value:parseFloat(p.value)};});
            _allSensorData[key]={
                label: getSensorName(s.tagno)+' ('+s.tagno+')',
                pts: pts,
                color: SENSOR_COLORS['_csv_'+csvIdx]||'#94a3b8',
                anomDates: _computeBearingAnomalies(pts),
                isBearing:false
            };
            _visibleSensors[key]=true;
            csvIdx++;
        });
        _rebuildSelectorAndChart();
    }catch(e){console.warn('loadDbCsv error:',e);}
}

// ── Sensor selector ──────────────────────────────────────────────
function _rebuildSelectorAndChart(){
    var pillsEl=document.getElementById('sensor-pills');
    pillsEl.innerHTML='';
    Object.keys(_allSensorData).forEach(function(key){
        var s=_allSensorData[key];
        var pill=document.createElement('div');
        pill.className='sensor-pill'+(_visibleSensors[key]?' on':'');
        pill.dataset.key=key;
        pill.innerHTML='<span class="sp-dot" style="background:'+s.color+'"></span>'+s.label;
        pill.onclick=function(){
            _visibleSensors[key]=!_visibleSensors[key];
            pill.classList.toggle('on');
            _buildChart();
        };
        pillsEl.appendChild(pill);
    });
    document.getElementById('sensor-selector-wrap').style.display='block';
    var dfw=document.getElementById('date-filter-wrap');
    if(dfw) dfw.style.display='block';
    _buildChart();
}

function _selectAllSensors(v){
    Object.keys(_allSensorData).forEach(function(k){_visibleSensors[k]=v;});
    document.querySelectorAll('.sensor-pill').forEach(function(p){p.classList.toggle('on',v);});
    _buildChart();
}

// ── Build unified chart ──────────────────────────────────────────
function _buildChart(){
    var wrap=document.getElementById('csv-chart-wrap');
    wrap.style.display='block';

    // Collect all visible dates (apply date filter)
    var dateSet={};
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        _allSensorData[key].pts.forEach(function(p){
            if(_dateStart && p.dateStr < _dateStart) return;
            if(_dateEnd   && p.dateStr > _dateEnd)   return;
            dateSet[p.dateStr]=1;
        });
        (_allSensorData[key].anomDates||[]).forEach(function(d){
            if(_dateStart && d < _dateStart) return;
            if(_dateEnd   && d > _dateEnd)   return;
            dateSet[d]=1;
        });
    });
    var labels=Object.keys(dateSet).sort();
    if(!labels.length){
        document.getElementById('csv-stats').innerHTML='<div style="color:#f97316;font-size:13px">Tidak ada data yang dipilih.</div>';
        return;
    }

    var tickStep=Math.max(1,Math.ceil(labels.length/14));
    var isDark=!document.body.classList.contains('light-theme');
    var gridCol=isDark?'rgba(255,255,255,.06)':'rgba(0,0,0,.07)';
    var tickCol=isDark?'#8892af':'#6c757d';
    var annoPlugin=window.ChartAnnotation||null;

    var xA={type:'category',labels:labels,grid:{color:gridCol},
        ticks:{color:tickCol,maxRotation:30,callback:function(val,idx){return idx%tickStep===0?labels[idx]:'';}}};
    var yA={grid:{color:gridCol},ticks:{color:tickCol,callback:function(v){return v+'°C';}}};
    var tip={backgroundColor:isDark?'#1e293b':'#fff',titleColor:isDark?'#fff':'#1a1d2e',bodyColor:tickCol,
        borderColor:isDark?'#334155':'#e2e8f0',borderWidth:1,padding:10,
        callbacks:{label:function(c){return ' '+c.dataset.label+': '+(c.parsed.y!=null?c.parsed.y.toFixed(2):'—')+'°C';}}};

    // Build per-sensor map (with date filter)
    var sensorMaps={};
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        var m={};
        _allSensorData[key].pts.forEach(function(p){
            if(_dateStart && p.dateStr < _dateStart) return;
            if(_dateEnd   && p.dateStr > _dateEnd)   return;
            m[p.dateStr]=p.value;
        });
        sensorMaps[key]=m;
    });

    // Collect all anomaly dates (from all visible sensors)
    var allAnomalies={};
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        (_allSensorData[key].anomDates||[]).forEach(function(d){allAnomalies[d]=1;});
    });

    // Annotations: vertical red lines for anomaly dates
    var anomAnnotations={};
    if(annoPlugin){
        Object.keys(allAnomalies).forEach(function(d,i){
            anomAnnotations['a'+i]={type:'line',scaleID:'x',value:d,
                borderColor:'rgba(239,68,68,.8)',borderWidth:2,borderDash:[0]};
        });
    }

    // Build datasets
    var datasets=[];
    var devDatasets=[];
    var statsHtml='',tableHtml='',legHtml='';
    var colorIdx=0;

    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        var s=_allSensorData[key];
        var vals=labels.map(function(d){return sensorMaps[key][d]!=null?sensorMaps[key][d]:null;});
        datasets.push({
            label:s.label,type:'line',data:vals,
            borderColor:s.color,borderWidth:2.5,pointRadius:0,tension:.3,
            fill:false,spanGaps:true,order:3+colorIdx
        });

        // Deviasi (solo: just value, no model estimate here)
        devDatasets.push({label:'Val '+s.label,type:'bar',data:vals,
            backgroundColor:s.color+'55',borderWidth:0});

        // Stats card
        var validVals=s.pts.map(function(p){return p.value;});
        if(validVals.length){
            var minV=Math.min.apply(null,validVals),maxV=Math.max.apply(null,validVals);
            var avgV=validVals.reduce(function(a,b){return a+b;},0)/validVals.length;
            var nAnom=(s.anomDates||[]).length;
            var pct=s.pts.length>0?Math.round(nAnom/s.pts.length*1000)/10:0;
            statsHtml+='<div style="background:var(--bg-secondary);border:1.5px solid '
                +s.color.replace(')',', .4)').replace('#','rgba(0,0,0,.0) !invalid! #')
                .replace(/rgba.*?!invalid!.*?#/,'rgba(').replace(')',',.4)')
                +';border-radius:10px;padding:12px 16px;min-width:170px;flex:1;border:1.5px solid '+s.color+'44">'
                +'<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">'
                +'<div style="width:12px;height:12px;border-radius:50%;background:'+s.color+'"></div>'
                +'<div style="font-size:13px;font-weight:700">'+s.label+'</div></div>'
                +'<div style="font-size:11px;color:var(--text-secondary);line-height:2">'
                +'<div>'+s.pts.length+' hari data</div>'
                +'<div>Min: <b>'+minV.toFixed(2)+'</b> · Max: <b>'+maxV.toFixed(2)+'</b> · Avg: <b>'+avgV.toFixed(2)+'</b></div>'
                +(nAnom>0?'<div style="color:#ef4444;font-weight:700">'+nAnom+' anomali ('+pct+'%)</div>':'<div style="color:#22c55e">0 anomali</div>')
                +'</div></div>';
        }

        legHtml+='<span class="leg-item"><span class="leg-line" style="background:'+s.color+'"></span>'+s.label+'</span>';
        colorIdx++;
    });
    if(annoPlugin&&Object.keys(allAnomalies).length){
        legHtml+='<span class="leg-item"><span class="leg-vline" style="background:#ef4444"></span>Anomali</span>';
    }

    document.getElementById('csv-legend').innerHTML=legHtml;
    document.getElementById('csv-stats').innerHTML=statsHtml||'<div style="color:var(--text-secondary);font-size:12px">Tidak ada data ditampilkan.</div>';

    // ── Main chart ───────────────────────────────────────────────
    var ctx1=document.getElementById('chart-csv-main');
    if(_chartInst){try{_chartInst.destroy();}catch(e){} _chartInst=null;}
    var w1=ctx1.parentElement;
    w1.style.cssText='position:relative;height:380px;background:var(--bg-card);border-radius:10px;padding:10px;border:1px solid var(--border-color)';
    ctx1.style.cssText='display:block;width:100%;height:360px';
    try{
        _chartInst=new Chart(ctx1.getContext('2d'),{
            type:'line',data:{labels:labels,datasets:datasets},
            options:{responsive:true,maintainAspectRatio:false,animation:{duration:350},
                interaction:{mode:'index',intersect:false},
                plugins:{legend:{display:false},tooltip:tip},
                scales:{x:xA,y:yA}}
        });
        // SVG overlay for anomaly lines
        requestAnimationFrame(function(){ requestAnimationFrame(function(){
            _drawAnomalyOverlay('chart-csv-main', labels,
                labels.map(function(d){ return !!allAnomalies[d]; }));
        }); });
    }catch(e){console.error('[csv main chart]',e);}

    // ── Dev chart ────────────────────────────────────────────────
    var ctx2=document.getElementById('chart-csv-dev');
    if(_devInst){try{_devInst.destroy();}catch(e){} _devInst=null;}
    var w2=ctx2.parentElement;
    w2.style.cssText='position:relative;height:140px;background:var(--bg-card);border-radius:10px;padding:8px;border:1px solid var(--border-color)';
    ctx2.style.cssText='display:block;width:100%;height:130px';
    try{
        _devInst=new Chart(ctx2.getContext('2d'),{
            type:'bar',data:{labels:labels,datasets:devDatasets},
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'bottom',labels:{font:{size:9},boxWidth:12}}},
                scales:{x:xA,y:{grid:{color:gridCol},ticks:{color:tickCol,callback:function(v){return v+'°C';}}}}}
        });
    }catch(e){console.error('[csv dev chart]',e);}

    // ── Pure SVG Anomaly Timeline ────────────────────────────────
    _drawAnomalyTimeline('wrap-csv-anom', labels,
        labels.map(function(d){ return !!allAnomalies[d]; }));

    // Tables
    var tHtml='';
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        var s=_allSensorData[key];
        tHtml+='<div style="margin-top:18px">';
        tHtml+='<div style="font-size:12px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:8px">'
            +'<span style="width:12px;height:12px;background:'+s.color+';border-radius:50%;display:inline-block"></span>'
            +'<span style="color:'+s.color+'">'+s.label+'</span></div>';
        tHtml+='<div class="at-wrap"><table class="at"><thead><tr><th>Tanggal</th><th style="text-align:right">Nilai</th><th style="text-align:center">Status</th></tr></thead><tbody>';
        s.pts.forEach(function(p){
            var isAno=(s.anomDates||[]).indexOf(p.dateStr)>=0;
            tHtml+='<tr style="background:'+(isAno?'rgba(239,68,68,.06)':'')+'">'
                +'<td>'+p.dateStr+'</td>'
                +'<td style="text-align:right;font-family:monospace">'+p.value.toFixed(3)+'</td>'
                +'<td style="text-align:center">'
                +(isAno?'<span style="background:rgba(239,68,68,.15);color:#ef4444;border-radius:10px;padding:2px 8px;font-size:10px;font-weight:700">⚠ Anomali</span>'
                       :'<span style="background:rgba(34,197,94,.12);color:#22c55e;border-radius:10px;padding:2px 8px;font-size:10px;font-weight:700">✓ Normal</span>')
                +'</td></tr>';
        });
        tHtml+='</tbody></table></div></div>';
    });
    document.getElementById('csv-table-wrap').innerHTML=tHtml;
}

// ── Save to DB (unit DB) ──────────────────────────────────────────
function _saveCsvToDb(){
    var csvSensors=Object.keys(_allSensorData).filter(function(k){return!_allSensorData[k].isBearing;});
    if(!csvSensors.length){alert('Tidak ada CSV sensor untuk disimpan.');return;}
    var btn=document.getElementById('btn-csv-save-db');
    if(btn){btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Menyimpan...';}
    var payload=[];
    csvSensors.forEach(function(key){
        var s=_allSensorData[key];
        s.pts.forEach(function(p){
            payload.push({tagno:s.label,tanggal:p.dateStr,nilai:parseFloat(p.value.toFixed(4)),filename:key});
        });
    });
    fetch(BASE_URL+'?api=bearing-proxy&action=save_csv_sensor&unit_id='+encodeURIComponent(UNIT_ID)+'&unit_db='+encodeURIComponent(UNIT_DB),{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({rows:payload,unit_id:UNIT_ID,unit_db:UNIT_DB})
    }).then(function(r){return r.json();}).then(function(res){
        if(res.success){alert('✅ Tersimpan: '+(res.saved||payload.length)+' baris ke database unit.');
            if(btn){btn.innerHTML='<i class="bi bi-check-circle"></i> Tersimpan!';btn.style.color='#22c55e';}}
        else{alert('❌ Gagal: '+(res.error||'Unknown'));if(btn){btn.disabled=false;btn.innerHTML='<i class="bi bi-cloud-arrow-up"></i> Simpan ke DB';}}
    }).catch(function(e){alert('❌ Error: '+e.message);if(btn){btn.disabled=false;btn.innerHTML='<i class="bi bi-cloud-arrow-up"></i> Simpan ke DB';}});
}

// ── Export XLSX ───────────────────────────────────────────────────
function _exportXLSX(){
    if(typeof XLSX==='undefined'){
        var s=document.createElement('script');s.src='https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js';
        s.onload=function(){_doXLSX();};document.head.appendChild(s);
    }else{_doXLSX();}
}
function _doXLSX(){
    if(!Object.keys(_allSensorData).length){alert('Tidak ada data untuk diekspor.');return;}
    var wb=XLSX.utils.book_new();
    var hdrFill=['FF1E3A5F'];

    // Capture combined chart with anomaly lines drawn on canvas
    function _captureWithAnom(canvasId, anomArr, labels) {
        var cv=document.getElementById(canvasId);
        if(!cv||cv.width<10) return null;
        var ci=Chart.getChart(canvasId);
        if(ci&&ci.chartArea&&anomArr&&labels&&labels.length>1){
            var ca=ci.chartArea,step=(ca.right-ca.left)/(labels.length-1);
            var ctx=cv.getContext('2d');
            ctx.save(); ctx.strokeStyle='rgba(239,68,68,0.8)'; ctx.lineWidth=1.5;
            anomArr.forEach(function(a,i){ if(!a)return; var x=Math.round(ca.left+i*step); ctx.beginPath();ctx.moveTo(x,ca.top);ctx.lineTo(x,ca.bottom);ctx.stroke(); });
            ctx.restore();
        }
        return cv?cv.toDataURL('image/png').split(',')[1]:null;
    }

    var visLabels=[];
    var allAnomalies={};
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        _allSensorData[key].pts.forEach(function(p){ if(!visLabels.includes(p.dateStr)) visLabels.push(p.dateStr); });
        (_allSensorData[key].anomDates||[]).forEach(function(d){ allAnomalies[d]=1; });
    });
    visLabels.sort();
    var mainAnom=visLabels.map(function(d){return !!allAnomalies[d];});
    var mainImgB64=_captureWithAnom('chart-csv-main',mainAnom,visLabels);

    // Per-sensor sheets
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        var s=_allSensorData[key];
        var pts=s.pts.filter(function(p){
            if(_dateStart&&p.dateStr<_dateStart)return false;
            if(_dateEnd&&p.dateStr>_dateEnd)return false;
            return true;
        });
        var rows=[['Tanggal','Nilai','Status']];
        pts.forEach(function(p){
            rows.push([p.dateStr,parseFloat(p.value.toFixed(3)),
                (s.anomDates||[]).includes(p.dateStr)?'ANOMALI':'Normal']);
        });
        var ws=XLSX.utils.aoa_to_sheet(rows);
        ws['!cols']=[{wch:13},{wch:10},{wch:10}];
        // Style header row
        ['A1','B1','C1'].forEach(function(ref){
            if(ws[ref]) ws[ref].s={fill:{patternType:'solid',fgColor:{argb:'FF1E3A5F'}},font:{color:{argb:'FFFFFFFF'},bold:true}};
        });
        XLSX.utils.book_append_sheet(wb,ws,(s.label||key).slice(0,31));
    });

    // Summary sheet
    var sumRows=[['Sensor','Hari Data','Hari Anomali','% Anomali','Min','Max','Avg']];
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        var s=_allSensorData[key];
        var vals=s.pts.map(function(p){return p.value;});
        var nAnom=(s.anomDates||[]).length;
        var pct=s.pts.length?Math.round(nAnom/s.pts.length*1000)/10:0;
        sumRows.push([s.label||key,s.pts.length,nAnom,pct+'%',
            vals.length?Math.min.apply(null,vals).toFixed(3):'—',
            vals.length?Math.max.apply(null,vals).toFixed(3):'—',
            vals.length?(vals.reduce(function(a,b){return a+b;},0)/vals.length).toFixed(3):'—']);
    });
    var wsSummary=XLSX.utils.aoa_to_sheet(sumRows);
    wsSummary['!cols']=[{wch:30},{wch:12},{wch:14},{wch:12},{wch:10},{wch:10},{wch:10}];
    XLSX.utils.book_append_sheet(wb,wsSummary,'Ringkasan');

    XLSX.writeFile(wb,'sensor_csv_'+new Date().toISOString().slice(0,10).replace(/-/g,'')+'.xlsx');
    // Note: XLSX.js doesn't support image embedding — for charts with images use ExcelJS
    if(mainImgB64) alert('File XLSX berhasil dibuat. Catatan: gambar grafik hanya tersedia di ekspor PDF (keterbatasan format SheetJS).');
}

// ── Export PDF ─────────────────────────────────────────────────────
function _exportPDF(){
    if(!Object.keys(_allSensorData).length){alert('Tidak ada data untuk diekspor.');return;}
    var now=new Date().toLocaleString('id-ID');

    // Draw anomaly lines onto canvas BEFORE capturing
    function _captureCanvasWithAnom(canvasId, anomArr, labels) {
        var cv = document.getElementById(canvasId);
        if (!cv || cv.width < 10) return null;
        var ci = Chart.getChart(canvasId);
        if (ci && ci.chartArea && anomArr && labels && labels.length > 1) {
            var ca = ci.chartArea;
            var step = (ca.right - ca.left) / (labels.length - 1);
            var ctx = cv.getContext('2d');
            ctx.save();
            ctx.strokeStyle = 'rgba(239,68,68,0.8)';
            ctx.lineWidth = 1.5;
            anomArr.forEach(function(a, i) {
                if (!a) return;
                var x = Math.round(ca.left + i * step);
                ctx.beginPath(); ctx.moveTo(x, ca.top); ctx.lineTo(x, ca.bottom); ctx.stroke();
            });
            ctx.restore();
        }
        return cv ? cv.toDataURL('image/png') : null;
    }

    var body = '<h1>Laporan Sensor CSV</h1><div class="sub">Digenerate '+now+'</div>';

    // Main combined chart
    var allAnomalies = {};
    var visLabels = [];
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        (_allSensorData[key].anomDates||[]).forEach(function(d){allAnomalies[d]=1;});
        _allSensorData[key].pts.forEach(function(p){ if(!visLabels.includes(p.dateStr)) visLabels.push(p.dateStr); });
    });
    visLabels.sort();
    var anomArr = visLabels.map(function(d){ return !!allAnomalies[d]; });
    var mainImg = _captureCanvasWithAnom('chart-csv-main', anomArr, visLabels);
    if(mainImg) body='<div style="margin-bottom:16px"><img src="'+mainImg+'" style="width:100%;max-height:280px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px"></div>'+body;

    // Per-sensor tables (SEPARATE, one table per sensor)
    body += '<div style="page-break-before:always"></div>';
    Object.keys(_allSensorData).forEach(function(key){
        if(!_visibleSensors[key])return;
        var s=_allSensorData[key];
        var nAnom=(s.anomDates||[]).length;
        var pct=s.pts.length?Math.round(nAnom/s.pts.length*1000)/10:0;
        body+='<div style="margin-top:20px;page-break-inside:avoid">';
        body+='<div style="background:'+s.color+'22;border-left:4px solid '+s.color+';padding:8px 12px;margin-bottom:8px;border-radius:0 6px 6px 0">'
            +'<div style="font-size:13px;font-weight:700;color:'+s.color+'">'+s.label+'</div>'
            +'<div style="font-size:10px;color:#475569">'+s.pts.length+' hari data · '
            +(nAnom>0?'<span style="color:#991b1b;font-weight:700">⚠ '+nAnom+' anomali ('+pct+'%)</span>':'<span style="color:#166534">✓ Tidak ada anomali</span>')
            +'</div></div>';
        body+='<table><thead><tr><th>Tanggal</th><th>Nilai</th><th>Status</th></tr></thead><tbody>';
        // Filter by date if active
        var pts = s.pts.filter(function(p){
            if(_dateStart && p.dateStr < _dateStart) return false;
            if(_dateEnd   && p.dateStr > _dateEnd)   return false;
            return true;
        });
        pts.forEach(function(p){
            var isa=(s.anomDates||[]).includes(p.dateStr);
            body+='<tr style="background:'+(isa?'#fef2f2':'')+'">'
                +'<td>'+p.dateStr+'</td>'
                +'<td style="text-align:right;font-family:monospace">'+p.value.toFixed(3)+'</td>'
                +'<td style="color:'+(isa?'#991b1b':'#166534')+';font-weight:700">'+(isa?'⚠ ANOMALI':'✓ Normal')+'</td></tr>';
        });
        body+='</tbody></table></div>';
        if(Object.keys(_allSensorData).indexOf(key) < Object.keys(_allSensorData).length-1)
            body+='<div style="page-break-after:always"></div>';
    });

    var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Laporan CSV</title>'
        +'<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:Segoe UI,Arial,sans-serif;color:#1a1a2e;background:#fff;padding:16px 22px;font-size:11px}'
        +'h1{font-size:16px;color:#1e3a5f;margin-bottom:3px;page-break-before:avoid}.sub{color:#64748b;font-size:10px;margin-bottom:14px}'
        +'table{width:100%;border-collapse:collapse;font-size:9px;margin-top:4px}'
        +'th{background:#1e3a5f;color:#fff;padding:4px 6px;text-align:left}'
        +'td{padding:3px 6px;border-bottom:1px solid #f1f5f9}'
        +'@media print{@page{margin:1.2cm}.page-break-before:always{page-break-before:always}}</style>'
        +'</head><body>'+body
        +'<script>setTimeout(function(){window.print();},500);<\/script></body></html>';
    var w=window.open('','_blank');if(w){w.document.write(html);w.document.close();}else{alert('Izinkan popup di browser.');}
}

// ── Date filter state ────────────────────────────────────────────
var _dateStart = null, _dateEnd = null;
var _batasSiang = 5, _batasMalam = 3;

function _applyDateFilter() {
    _dateStart = document.getElementById('csv-date-start').value || null;
    _dateEnd   = document.getElementById('csv-date-end').value   || null;
    _batasSiang = parseFloat(document.getElementById('csv-batas-siang').value) || 5;
    _batasMalam = parseFloat(document.getElementById('csv-batas-malam').value) || 3;
    // Recompute anomalies for all sensors with new tolerance
    Object.keys(_allSensorData).forEach(function(key){
        _allSensorData[key].anomDates = _computeBearingAnomalies(_allSensorData[key].pts);
    });
    _buildChart();
}

function _clearDateFilter() {
    _dateStart = null; _dateEnd = null;
    document.getElementById('csv-date-start').value = '';
    document.getElementById('csv-date-end').value   = '';
    _buildChart();
}

// ── Init: load saved sensors from unit DB, then trigger rebuild ──
document.addEventListener('DOMContentLoaded',function(){
    // Flatpickr for date filters
    ['csv-date-start','csv-date-end'].forEach(function(id){
        var el=document.getElementById(id);
        if(el && window.flatpickr) flatpickr(el,{dateFormat:'Y-m-d',allowInput:true,locale:{firstDayOfWeek:1}});
    });

    if(Object.keys(_allSensorData).length){
        _rebuildSelectorAndChart();
        document.getElementById('date-filter-wrap').style.display='block';
    }
    _loadDbCsv();
});
</script>
</body>
</html>
