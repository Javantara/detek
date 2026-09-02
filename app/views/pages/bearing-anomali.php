<?php
require_role(['superadmin','admin','user']);
if (!isset($conn)) require_once APP . 'config/database.php';

$role = $_SESSION['role'] ?? 'user';
$user_id = intval($_SESSION['user_id'] ?? 0);
$selected_unit_id = intval($_SESSION['selected_unit_id'] ?? 0);

function ids_from_csv($raw): array {
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$raw)))));
}

$unit_options = [];
try {
    if ($role === 'superadmin') {
        $q = $conn->query("SELECT u.unit_id, u.unit_name, u.database_name, p.description AS plant_name
                           FROM units u JOIN plants p ON p.plant_id=u.plant_id
                           WHERE (u.status=1 OR u.status='active') AND u.database_name IS NOT NULL AND u.database_name <> ''
                           ORDER BY p.description, u.unit_name");
        $unit_options = $q->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $conn->prepare("SELECT all_access, assigned_units FROM users WHERE user_id=?");
        $st->execute([$user_id]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!empty($u['all_access'])) {
            $q = $conn->query("SELECT u.unit_id, u.unit_name, u.database_name, p.description AS plant_name
                               FROM units u JOIN plants p ON p.plant_id=u.plant_id
                               WHERE (u.status=1 OR u.status='active') AND u.database_name IS NOT NULL AND u.database_name <> ''
                               ORDER BY p.description, u.unit_name");
            $unit_options = $q->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $ids = ids_from_csv($u['assigned_units'] ?? '');
            if ($selected_unit_id && !in_array($selected_unit_id, $ids, true)) $ids[] = $selected_unit_id;
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $q = $conn->prepare("SELECT u.unit_id, u.unit_name, u.database_name, p.description AS plant_name
                                     FROM units u JOIN plants p ON p.plant_id=u.plant_id
                                     WHERE u.unit_id IN ($ph) AND (u.status=1 OR u.status='active')
                                     ORDER BY p.description, u.unit_name");
                $q->execute($ids);
                $unit_options = $q->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Throwable $e) { $unit_options = []; }

if ($selected_unit_id <= 0 && !empty($unit_options)) $selected_unit_id = (int)$unit_options[0]['unit_id'];
$active_unit = null;
foreach ($unit_options as $u) {
    if ((int)$u['unit_id'] === $selected_unit_id) { $active_unit = $u; break; }
}
if (!$active_unit && !empty($unit_options)) $active_unit = $unit_options[0];

$bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$bulan_short = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deteksi Anomali - PLN</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
<style>
    .anom-wrap{display:flex;flex-direction:column;gap:18px;max-width:100%}
    .panelx{background:var(--bg-card);border:1px solid var(--border-color);border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
    .head-title{display:flex;gap:12px;align-items:center;font-size:30px;font-weight:900;margin:0 0 8px;color:var(--text-primary)}
    .head-title i{color:var(--accent-cyan)}
    .muted{color:var(--text-secondary)}.small{font-size:13px}.tiny{font-size:12px}
    .filter-title{font-size:14px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:8px}.filter-title i{color:var(--accent-cyan)}
    .filter-main-grid{display:grid;grid-template-columns:minmax(220px,1.25fr) minmax(360px,1.8fr) minmax(170px,.85fr) minmax(110px,.5fr) minmax(120px,.55fr);gap:12px;align-items:end}
    .filter-period-grid{display:grid;grid-template-columns:minmax(190px,.75fr) minmax(210px,.8fr) minmax(210px,.8fr) minmax(150px,.65fr);gap:12px;align-items:end;margin-top:14px;max-width:920px}
    .fieldx label{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:7px;white-space:nowrap}
    .inputx,.selectx{width:100%;height:50px;background:var(--input-bg);border:1.5px solid var(--border-color);border-radius:13px;color:var(--text-primary);padding:0 14px;font-size:15px;font-weight:700;outline:none}.inputx:focus,.selectx:focus{border-color:var(--accent-cyan);box-shadow:0 0 0 3px rgba(0,217,255,.08)}
    .period-detail-inline{display:contents}
    .period-detail-inline:empty{display:none}
    .sensor-box{margin-top:14px;padding:14px 16px;border-radius:14px;background:rgba(0,217,255,.06);border:1px solid rgba(0,217,255,.18);display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.sensor-box b{color:var(--text-primary)}
    .badge-ok{display:inline-flex;align-items:center;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:900;background:rgba(33,208,122,.15);color:#7ef0ae;border:1px solid rgba(33,208,122,.3)}
    .filter-actions{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:14px;flex-wrap:wrap}.run-info{font-size:13px;color:var(--text-secondary)}.run-info b{color:#ffde79}.btn-row{display:flex;gap:10px;flex-wrap:wrap}.btnx{height:46px;border:none;border-radius:13px;padding:0 20px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-size:15px}.btn-primaryx{background:var(--accent-cyan);color:#061220}.btn-secondaryx{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary)}.btnx:disabled{opacity:.55;cursor:not-allowed}
    .notice{border-radius:14px;padding:14px 16px;border:1px solid rgba(255,211,77,.25);background:rgba(255,211,77,.10);color:#ffde79}.notice.ok{border-color:rgba(33,208,122,.25);background:rgba(33,208,122,.10);color:#7ef0ae}.notice.err{border-color:rgba(255,91,110,.25);background:rgba(255,91,110,.10);color:#ff9aa6}
    .progress-box{display:none;border-radius:14px;padding:14px 16px;border:1px solid rgba(0,217,255,.2);background:rgba(0,217,255,.06);max-height:170px;overflow:auto}.progress-line{font-size:13px;color:var(--text-secondary);padding:4px 0}.progress-line b{color:var(--text-primary)}
    .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi{background:var(--bg-card);border:1px solid var(--border-color);border-radius:17px;padding:17px}.kpi-label{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:var(--text-secondary)}.kpi-value{font-size:28px;font-weight:900;color:var(--text-primary);margin-top:10px}.kpi-caption{font-size:13px;color:var(--text-secondary);margin-top:8px}.status-normal{color:#21d07a!important}.status-anomali{color:#ff4d5d!important}
    .chart-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px}.chart-head h3{margin:0;font-size:20px}.chips{display:flex;gap:9px;flex-wrap:wrap}.chip{height:38px;border-radius:999px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);display:inline-flex;align-items:center;gap:8px;padding:0 14px;font-size:13px;font-weight:900;cursor:pointer}.chip .dot{width:11px;height:11px;border-radius:50%}.chip.off{opacity:.35}.c-actual{background:#12d9ff}.c-pred{background:#ffd35c}.c-high{background:#ff6474}.c-low{background:#26d07c}.c-anom{background:#ff2d45}
    .plotly-box{height:590px;min-height:420px;position:relative}.plot-help{display:flex;gap:14px;flex-wrap:wrap;align-items:center;color:var(--text-secondary);font-size:12px;margin-top:10px}.plot-help b{color:var(--text-primary)}
    .table-wrap{max-height:260px;overflow:auto}.tablex{width:100%;border-collapse:collapse;min-width:780px}.tablex th,.tablex td{padding:11px 12px;border-bottom:1px solid var(--border-color);font-size:13px;text-align:left}.tablex th{position:sticky;top:0;background:var(--bg-secondary);color:var(--text-secondary);text-transform:uppercase;font-size:11px;letter-spacing:.08em}
    @media(max-width:1300px){.filter-main-grid{grid-template-columns:repeat(3,1fr)}.filter-period-grid{grid-template-columns:repeat(3,1fr);max-width:100%}.kpi-grid{grid-template-columns:repeat(2,1fr)}}

    .compare-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:12px}.compare-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:15px;padding:15px}.compare-card.best{border-color:#21d07a;background:rgba(33,208,122,.08)}.compare-title{font-weight:900;color:var(--text-primary);margin-bottom:8px}.metric-line{display:flex;justify-content:space-between;gap:10px;font-size:13px;padding:4px 0;color:var(--text-secondary)}.metric-line b{color:var(--text-primary)}.best-note{font-weight:900;color:#7ef0ae;margin-top:8px;font-size:13px}@media(max-width:900px){.compare-grid{grid-template-columns:1fr}}
    @media(max-width:760px){.filter-main-grid,.filter-period-grid,.kpi-grid{grid-template-columns:1fr}.filter-actions{align-items:stretch}.btn-row,.btnx{width:100%;justify-content:center}.head-title{font-size:24px}.plotly-box{height:430px}}
</style>
</head>
<body>
<div class="layout">
<?php include VIEWS . 'shared/sidebar.php'; ?>
<div class="main-content">
<?php include VIEWS . 'shared/header.php'; ?>
<div class="content anom-wrap">

    <div class="panelx">
        <h1 class="head-title"><i class="bi bi-cpu"></i>Deteksi Anomali</h1>
        <div class="muted">Monitoring kondisi sensor berdasarkan data aktual, prediksi XGBoost, dan batas low/high.</div>
        <div class="small muted" style="margin-top:10px">Unit aktif: <b><?= htmlspecialchars($active_unit['plant_name'] ?? '-') ?></b> - <b><?= htmlspecialchars($active_unit['unit_name'] ?? '-') ?></b> | DB unit: <b><?= htmlspecialchars($active_unit['database_name'] ?? '-') ?></b></div>
    </div>

    <div class="panelx">
        <div class="filter-title"><i class="bi bi-sliders"></i> Filter Trend</div>
        <div class="filter-main-grid">
            <div class="fieldx"><label>Unit</label><select id="unitId" class="selectx">
                <?php if (empty($unit_options)): ?><option value="">Belum ada unit</option><?php endif; ?>
                <?php foreach ($unit_options as $u): ?>
                    <option value="<?= (int)$u['unit_id'] ?>" data-db="<?= htmlspecialchars($u['database_name'] ?? '') ?>" data-plant="<?= htmlspecialchars($u['plant_name'] ?? '') ?>" data-unit="<?= htmlspecialchars($u['unit_name'] ?? '') ?>" <?= ((int)$u['unit_id']===(int)($active_unit['unit_id']??0))?'selected':'' ?>><?= htmlspecialchars(($u['plant_name'] ?? '-') . ' - ' . ($u['unit_name'] ?? '-')) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="fieldx"><label>Sensor</label><select id="tagno" class="selectx"><option value="">Memuat sensor...</option></select></div>
            <div class="fieldx"><label>Model AI</label><select id="modelType" class="selectx"><option value="xgboost">XGBoost (Utama)</option><option value="autoencoder">Deep Learning Autoencoder</option></select></div>
            <div class="fieldx"><label>Min</label><input id="minConsecutive" class="inputx" type="number" min="1" value="10"></div>
            <div class="fieldx"><label>Batas ±</label><input id="batas" class="inputx" type="number" step="0.1" value="5" disabled title="Batas diambil dari tabel prediksi: selisih_low dan selisih_high"></div>
        </div>

        <div class="filter-period-grid">
            <div class="fieldx"><label>Periode</label><select id="period" class="selectx"><option value="day">Per Hari</option><option value="month" selected>Per Bulan</option><option value="year">Jan - Des</option></select></div>
            <div id="periodDetail" class="period-detail-inline"></div>
            <div class="fieldx"><label>Maks Data</label><input id="limitRows" class="inputx" type="number" min="1000" max="200000" step="1000" value="120000"></div>
        </div>

        <div class="sensor-box">
            <div><b id="sensorTitle">Sensor terpilih</b><div class="muted small" id="sensorDesc">Pilih sensor untuk melihat keterangan dari database.</div></div>
            <span class="badge-ok" id="modelBadge">XGBoost</span>
        </div>

        <div class="filter-actions">
            <div class="run-info"><b>Run range:</b> <span id="runRangeText">-</span></div>
            <div class="btn-row">
                <button class="btnx btn-primaryx" id="btnPredict"><i class="bi bi-lightning-charge-fill"></i> Prediksi AI</button>
                <button class="btnx btn-secondaryx" id="btnRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                <button class="btnx btn-secondaryx" id="btnCompare"><i class="bi bi-bar-chart-line"></i> Bandingkan Metode</button>
                <button class="btnx btn-secondaryx" id="btnAuto"><i class="bi bi-calendar2-range"></i> Auto Jan-Des</button>
            </div>
        </div>
    </div>

    <div id="alertBox" class="notice">Memuat data...</div>
    <div id="progressBox" class="progress-box"></div>

    <div class="panelx" id="comparePanel" style="display:none">
        <div class="chart-head">
            <div>
                <h3>Perbandingan Metode</h3>
                <div class="muted small">Metode paling bagus dilihat dari RMSE dan MAE paling kecil pada sensor/range yang dipilih.</div>
            </div>
        </div>
        <div id="compareResult" class="compare-grid"></div>
    </div>

    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">Aktual Terbaru</div><div id="nilaiAktual" class="kpi-value">-</div><div id="tglAktual" class="kpi-caption">-</div></div>
        <div class="kpi"><div class="kpi-label">Prediksi</div><div id="nilaiPrediksi" class="kpi-value">-</div><div class="kpi-caption" id="predCaption">XGBoost | XGBoost__prediksi</div></div>
        <div class="kpi"><div class="kpi-label">Batas Low - High</div><div id="rangePrediksi" class="kpi-value">-</div><div class="kpi-caption">Dari selisih_low dan selisih_high</div></div>
        <div class="kpi"><div class="kpi-label">Status</div><div id="statusAnomali" class="kpi-value">-</div><div id="tglPrediksi" class="kpi-caption">-</div></div>
    </div>

    <div class="panelx">
        <div class="chart-head">
            <div><h3>Trend Sensor</h3><div class="muted small">Grafik interaktif: scroll untuk zoom, drag untuk geser, atau pakai range slider di bawah grafik.</div></div>
            <div class="chips">
                <button class="chip" data-trace="0"><span class="dot c-actual"></span>Aktual <i class="bi bi-eye"></i></button>
                <button class="chip" data-trace="1"><span class="dot c-pred"></span>Prediksi <i class="bi bi-eye"></i></button>
                <button class="chip" data-trace="2"><span class="dot c-high"></span>High <i class="bi bi-eye"></i></button>
                <button class="chip" data-trace="3"><span class="dot c-low"></span>Low <i class="bi bi-eye"></i></button>
                <button class="chip" data-trace="4"><span class="dot c-anom"></span>Titik Anomali <i class="bi bi-eye"></i></button>
            </div>
        </div>
        <div id="trendPlot" class="plotly-box"></div>
        <div class="plot-help">
            <span><b>Zoom:</b> scroll mouse / trackpad</span>
            <span><b>Geser:</b> drag kiri-kanan saat sudah zoom</span>
            <span><b>Range:</b> gunakan tombol 1D, 7D, 1M, All atau slider bawah grafik</span>
        </div>
    </div>

    <div class="panelx">
        <div class="chart-head"><h3>Riwayat Hasil Prediksi</h3><div class="muted small">Menampilkan data terakhir sesuai filter.</div></div>
        <div class="table-wrap"><table class="tablex"><thead><tr><th>Waktu</th><th>Aktual</th><th>Prediksi</th><th>Low</th><th>High</th><th>Status</th></tr></thead><tbody id="historyBody"><tr><td colspan="6" class="muted">Belum ada data.</td></tr></tbody></table></div>
    </div>
</div>
</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const MONTHS = <?= json_encode($bulan, JSON_UNESCAPED_UNICODE) ?>;
const MONTHS_SHORT = <?= json_encode($bulan_short, JSON_UNESCAPED_UNICODE) ?>;
let sensors = [];
let fullRows = [];
let traceVisible = [true,true,true,true,true];

const $ = id => document.getElementById(id);
function fmt(v, d=2){ if(v===null || v===undefined || v==='') return '-'; const n = Number(v); return Number.isFinite(n) ? n.toFixed(d) : '-'; }
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
function setAlert(msg, type='warn') { $('alertBox').className = 'notice ' + (type==='ok'?'ok':type==='err'?'err':''); $('alertBox').textContent = msg; }
function logProgress(html){ $('progressBox').style.display='block'; $('progressBox').insertAdjacentHTML('beforeend', `<div class="progress-line">${html}</div>`); $('progressBox').scrollTop = $('progressBox').scrollHeight; }
function clearProgress(){ $('progressBox').innerHTML=''; $('progressBox').style.display='none'; }
function selectedUnit(){ return $('unitId').value; }
function pad2(n){ return String(n).padStart(2,'0'); }
function currentModel(){ return $('modelType').value || 'xgboost'; }
function currentModelLabel(){ return currentModel()==='autoencoder' ? 'Deep Learning Autoencoder' : 'XGBoost'; }

function defaultYear(){ return '2025'; }
function monthName(n){ return MONTHS[Number(n)] || '-'; }
function parseMonthInput(v){
    const m = String(v || `${defaultYear()}-02`).match(/^(\d{4})-(\d{2})$/);
    if (!m) return {year: defaultYear(), month: '2'};
    return {year: m[1], month: String(Number(m[2]))};
}
function yearFromCurrentFilter(){
    const period = $('period').value;
    if (period === 'day') return String(($('dateFrom')?.value || `${defaultYear()}-01-01`).slice(0,4));
    if (period === 'month') return parseMonthInput($('monthInput')?.value).year;
    return $('yearInput')?.value || defaultYear();
}

function renderPeriodDetail(){
    const period = $('period').value;
    let html = '';
    const box = $('periodDetail');

    // Layout dibuat seperti versi awal:
    // Periode dipilih di dropdown utama, lalu field bawah berubah sesuai pilihan.
    // Per Hari  = Dari Tanggal + Sampai Tanggal
    // Per Bulan = Bulan
    // Jan-Des   = Tahun
    if (period === 'day') {
        html = `<div class="fieldx"><label>Dari Tanggal</label><input id="dateFrom" class="inputx" type="date" value="2025-02-01"></div>
                <div class="fieldx"><label>Sampai Tanggal</label><input id="dateTo" class="inputx" type="date" value="2025-02-28"></div>`;
    } else if (period === 'month') {
        html = `<div class="fieldx"><label>Bulan</label><input id="monthInput" class="inputx" type="month" value="2025-02"></div>`;
    } else {
        html = `<div class="fieldx"><label>Tahun</label><input id="yearInput" class="inputx" type="number" min="2020" max="2100" value="2025"></div>`;
    }
    box.innerHTML = html;
    const df = $('dateFrom'), dt = $('dateTo'), mi = $('monthInput'), yi = $('yearInput');
    if (df) df.addEventListener('change', refreshTrend);
    if (dt) dt.addEventListener('change', refreshTrend);
    if (mi) mi.addEventListener('change', refreshTrend);
    if (yi) yi.addEventListener('change', refreshTrend);
}

function getRangeParams(){
    const period = $('period').value;
    const params = new URLSearchParams();
    params.set('period', period);
    if (period === 'day') {
        const from = $('dateFrom')?.value || '2025-02-01';
        const to = $('dateTo')?.value || from;
        params.set('date_from', from);
        params.set('date_to', to);
        params.set('year', from.slice(0,4));
    } else if (period === 'month') {
        const parsed = parseMonthInput($('monthInput')?.value || '2025-02');
        params.set('year', parsed.year);
        params.set('month', parsed.month);
    } else {
        params.set('year', $('yearInput')?.value || defaultYear());
    }
    return params;
}

function getRangeText(){
    const period = $('period').value;
    if (period === 'day') return `${$('dateFrom')?.value || '-'} sampai ${$('dateTo')?.value || '-'}`;
    if (period === 'month') {
        const parsed = parseMonthInput($('monthInput')?.value || '2025-02');
        return `${monthName(parsed.month)} ${parsed.year}`;
    }
    return `Januari sampai Desember ${$('yearInput')?.value || defaultYear()}`;
}

async function api(action, extra = {}){
    const params = getRangeParams();
    params.set('api','bearing-proxy');
    params.set('action',action);
    params.set('unit_id', selectedUnit());
    params.set('tagno', $('tagno').value || '859');
    params.set('limit', $('limitRows').value || '50000');
    params.set('min', $('minConsecutive').value || '10');
    params.set('batas', $('batas').value || '5');
    params.set('model', $('modelType').value || 'xgboost');
    Object.entries(extra).forEach(([k,v]) => params.set(k,v));
    const r = await fetch(BASE_URL + '?' + params.toString(), {cache:'no-store'});
    const j = await r.json();
    if (!j.success && action !== 'load') throw new Error(j.message || 'Gagal memuat data');
    return j;
}

async function loadSensors(){
    $('tagno').innerHTML = '<option value="">Memuat sensor...</option>';
    const params = new URLSearchParams({api:'bearing-proxy', action:'sensors', unit_id:selectedUnit()});
    try {
        const res = await fetch(BASE_URL + '?' + params.toString(), {cache:'no-store'});
        const data = await res.json();
        sensors = data.sensors || [];
        $('tagno').innerHTML = '';
        if (!sensors.length) {
            $('tagno').innerHTML = '<option value="">Sensor tidak ditemukan</option>';
            return;
        }
        sensors.forEach(s => {
            const label = `${s.tagno} - ${s.deskripsi || 'Sensor'}`;
            const o = new Option(label, s.tagno);
            o.dataset.desc = s.deskripsi || '';
            o.dataset.satuan = s.satuan || '';
            $('tagno').appendChild(o);
        });
        const preferred = ['859','858'];
        for (const p of preferred) {
            if ([...$('tagno').options].some(o => o.value === p)) { $('tagno').value = p; break; }
        }
        updateSensorInfo();
    } catch(e) {
        $('tagno').innerHTML = '<option value="">Gagal memuat sensor</option>';
        setAlert('Gagal memuat sensor: ' + e.message, 'err');
    }
}

function updateSensorInfo(){
    const opt = $('tagno').selectedOptions[0];
    if (!opt) return;
    const model = currentModel();
    const label = currentModelLabel();
    const table = model === 'autoencoder' ? 'Deep_Learning__prediksi_autoencoder' : 'XGBoost__prediksi';
    $('sensorTitle').textContent = opt.textContent || 'Sensor terpilih';
    $('sensorDesc').textContent = `Model: ${label} | Data dari tabel ${table} unit aktif | Final anomaly min ${$('minConsecutive').value || 10} data berturut-turut.`;
    const badge = $('modelBadge');
    if (badge) badge.textContent = label;
    const predCaption = $('predCaption');
    if (predCaption) predCaption.textContent = `${label} | tabel ${table}`;
}

function prepareRows(rows){
    return (rows || []).map(r => ({
        time: r.data_time,
        label: String(r.data_time || '').replace('T',' '),
        actual: Number(r.value),
        pred: r.value_prediksi === null ? null : Number(r.value_prediksi),
        high: r.selisih_high === null ? null : Number(r.selisih_high),
        low: r.selisih_low === null ? null : Number(r.selisih_low),
        anomaly: Number(r.final_anomaly || 0) === 1 || String(r.status_anomali || '').toLowerCase() === 'anomali',
        candidate: Number(r.candidate_anomaly || 0) === 1,
        status: r.status_anomali || 'Normal',
        reconstruction_error: r.reconstruction_error === null ? null : Number(r.reconstruction_error),
        threshold_error: r.threshold_error === null ? null : Number(r.threshold_error)
    })).filter(r => Number.isFinite(r.actual));
}

function updateKpi(rows){
    const last = rows[rows.length - 1];
    if (!last) {
        $('nilaiAktual').textContent='-'; $('nilaiPrediksi').textContent='-'; $('rangePrediksi').textContent='-'; $('statusAnomali').textContent='-'; $('tglAktual').textContent='-'; $('tglPrediksi').textContent='-';
        return;
    }
    $('nilaiAktual').textContent = fmt(last.actual);
    $('tglAktual').textContent = `${$('tagno').value} • ${last.label}`;
    $('nilaiPrediksi').textContent = fmt(last.pred);
    $('rangePrediksi').textContent = `${fmt(last.low)} - ${fmt(last.high)}`;
    $('statusAnomali').textContent = last.anomaly ? 'Anomali' : 'Normal';
    $('statusAnomali').className = 'kpi-value ' + (last.anomaly ? 'status-anomali' : 'status-normal');
    $('tglPrediksi').textContent = `Waktu: ${last.label}`;
}

function renderPlot(){
    const rows = fullRows;
    const x = rows.map(r => r.label);
    const anomalyX = rows.filter(r => r.anomaly).map(r => r.label);
    const anomalyY = rows.filter(r => r.anomaly).map(r => r.actual);
    const anomalyText = rows.filter(r => r.anomaly).map(r => {
        let reason = 'Masuk status Anomali';
        if (currentModel() === 'autoencoder' && r.reconstruction_error !== null && r.threshold_error !== null) {
            reason = `Reconstruction error ${fmt(r.reconstruction_error,4)} > threshold ${fmt(r.threshold_error,4)}`;
        } else {
            if (r.high !== null && r.actual > r.high) reason = `Aktual ${fmt(r.actual)} > High ${fmt(r.high)}`;
            if (r.low !== null && r.actual < r.low) reason = `Aktual ${fmt(r.actual)} < Low ${fmt(r.low)}`;
        }
        return `${reason}<br>Model: ${currentModelLabel()}<br>Final anomaly: minimal ${$('minConsecutive').value || 10} data berturut-turut`;
    });

    const traces = [
        {x, y: rows.map(r=>r.actual), type:'scatter', mode:'lines', name:'Aktual', line:{color:'#12d9ff', width:3}, visible: traceVisible[0] ? true : 'legendonly'},
        {x, y: rows.map(r=>r.pred), type:'scatter', mode:'lines', name:'Prediksi', line:{color:'#ffd35c', width:3}, visible: traceVisible[1] ? true : 'legendonly'},
        {x, y: rows.map(r=>r.high), type:'scatter', mode:'lines', name:'High', line:{color:'#ff6474', width:2, dash:'dash'}, visible: traceVisible[2] ? true : 'legendonly'},
        {x, y: rows.map(r=>r.low), type:'scatter', mode:'lines', name:'Low', line:{color:'#26d07c', width:2, dash:'dash'}, visible: traceVisible[3] ? true : 'legendonly'},
        {x: anomalyX, y: anomalyY, type:'scatter', mode:'markers', name:'Titik Anomali', marker:{color:'#ff2d45', size:9, line:{color:'#ffffff', width:1}}, text: anomalyText, hovertemplate:'%{x}<br>Aktual: %{y:.2f}<br>%{text}<extra></extra>', visible: traceVisible[4] ? true : 'legendonly'}
    ];

    const layout = {
        margin:{l:58,r:26,t:18,b:62},
        paper_bgcolor:'rgba(0,0,0,0)',
        plot_bgcolor:'rgba(9,18,34,0.35)',
        font:{color:'#9fb0ca', family:'inherit'},
        hovermode:'x unified',
        dragmode:'pan',
        legend:{orientation:'h', y:1.08, x:0, bgcolor:'rgba(0,0,0,0)'},
        xaxis:{
            title:'Timestamp',
            showgrid:true,
            gridcolor:'rgba(148,163,184,.15)',
            zeroline:false,
            rangeslider:{visible:true, thickness:0.13, bgcolor:'rgba(15,30,50,.65)', bordercolor:'rgba(0,217,255,.16)', borderwidth:1},
            rangeselector:{
                x:0,
                y:1.16,
                buttons:[
                    {count:1, label:'1D', step:'day', stepmode:'backward'},
                    {count:7, label:'7D', step:'day', stepmode:'backward'},
                    {count:1, label:'1M', step:'month', stepmode:'backward'},
                    {step:'all', label:'All'}
                ],
                bgcolor:'rgba(18,34,53,.95)',
                activecolor:'#12d9ff',
                bordercolor:'rgba(0,217,255,.22)',
                font:{color:'#e8f0ff'}
            }
        },
        yaxis:{title:'Value', showgrid:true, gridcolor:'rgba(148,163,184,.15)', zeroline:false}
    };
    const config = {responsive:true, scrollZoom:true, displaylogo:false, modeBarButtonsToRemove:['lasso2d','select2d']};
    Plotly.react('trendPlot', traces, layout, config);
    document.querySelectorAll('.chip').forEach((chip, idx) => chip.classList.toggle('off', !traceVisible[idx]));
}

function renderHistory(rows){
    const tbody = $('historyBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="muted">Data tidak tersedia untuk filter ini.</td></tr>'; return; }
    const recent = rows.slice(-80).reverse();
    tbody.innerHTML = recent.map(r => `<tr><td>${esc(r.label)}</td><td>${fmt(r.actual)}</td><td>${fmt(r.pred)}</td><td>${fmt(r.low)}</td><td>${fmt(r.high)}</td><td style="font-weight:800;color:${r.anomaly?'#ff4d5d':'#21d07a'}">${r.anomaly?'Anomali':'Normal'}</td></tr>`).join('');
}

async function refreshTrend(){
    updateSensorInfo();
    $('runRangeText').textContent = getRangeText();
    setAlert('Memuat data...', 'warn');
    try {
        const data = await api('load');
        fullRows = prepareRows(data.rows || []);
        if (!fullRows.length) {
            setAlert(data.message || 'Data tidak ditemukan untuk filter ini.', 'err');
        } else {
            setAlert(`${data.message} Range ${data.range.from} sampai ${data.range.to}. Model ${currentModelLabel()}. Total ${data.total}, candidate ${data.candidate_count}, anomali final ${data.anomaly_count}.`, 'ok');
        }
        updateKpi(fullRows);
        renderPlot();
        renderHistory(fullRows);
    } catch(e) {
        setAlert('Gagal memuat data: ' + e.message, 'err');
    }
}


function fmtMetric(v, d=4){
    if(v===null || v===undefined || v==='') return '-';
    const n = Number(v);
    return Number.isFinite(n) ? n.toFixed(d) : '-';
}

async function compareMethods(){
    updateSensorInfo();
    $('btnCompare').disabled = true;
    setAlert('Membandingkan XGBoost dan Deep Learning Autoencoder...', 'warn');
    try {
        const data = await api('compare');
        const best = data.best_model ? data.best_model.model : '';
        const cards = (data.metrics || []).map(m => {
            const isBest = m.model === best;
            return `<div class="compare-card ${isBest?'best':''}">
                <div class="compare-title">${esc(m.label)} ${isBest?'🏆':''}</div>
                <div class="metric-line"><span>Tabel</span><b>${esc(m.table)}</b></div>
                <div class="metric-line"><span>Total data</span><b>${Number(m.total || 0).toLocaleString('id-ID')}</b></div>
                <div class="metric-line"><span>MAE</span><b>${fmtMetric(m.mae)}</b></div>
                <div class="metric-line"><span>RMSE</span><b>${fmtMetric(m.rmse)}</b></div>
                <div class="metric-line"><span>R²</span><b>${fmtMetric(m.r2_score)}</b></div>
                <div class="metric-line"><span>Anomali final</span><b>${Number(m.anomaly_count || 0).toLocaleString('id-ID')}</b></div>
                ${isBest ? '<div class="best-note">Metode terbaik untuk filter ini</div>' : ''}
            </div>`;
        }).join('');
        $('compareResult').innerHTML = cards || '<div class="muted">Data perbandingan belum tersedia.</div>';
        $('comparePanel').style.display = 'block';
        setAlert(data.message || 'Perbandingan selesai.', 'ok');
    } catch(e) {
        setAlert('Gagal membandingkan metode: ' + e.message, 'err');
    } finally {
        $('btnCompare').disabled = false;
    }
}

async function runPredict(){
    clearProgress();
    $('btnPredict').disabled = true;
    setAlert(`Menjalankan ${currentModelLabel()}. Run: ${getRangeText()}. Update status_anomali di tabel ${currentModel()==='autoencoder'?'Deep_Learning__prediksi_autoencoder':'XGBoost__prediksi'}.`, 'warn');
    try {
        const data = await api('run');
        logProgress(`<b>Selesai ${esc(currentModelLabel())}</b> ${esc(data.range.from)} sampai ${esc(data.range.to)} | Data ${data.total} | candidate ${data.candidate_count} | anomali final ${data.anomaly_count}`);
        setAlert(data.message, 'ok');
        await refreshTrend();
    } catch(e) {
        setAlert('Gagal Prediksi AI: ' + e.message, 'err');
    } finally {
        $('btnPredict').disabled = false;
    }
}

async function autoJanDes(){
    clearProgress();
    const oldPeriod = $('period').value;
    const oldMonth = $('monthInput')?.value || '';
    const oldYear = $('yearInput')?.value || '';
    const oldFrom = $('dateFrom')?.value || '';
    const oldTo = $('dateTo')?.value || '';
    const year = yearFromCurrentFilter();
    $('btnAuto').disabled = true;
    $('btnPredict').disabled = true;
    $('progressBox').style.display='block';
    setAlert(`Auto Jan-Des ${year} berjalan per bulan dengan ${currentModelLabel()}. Trend akan balik ke filter yang sedang dipilih setelah selesai.`, 'warn');
    try {
        for (let m=1; m<=12; m++) {
            logProgress(`Sedang run <b>${MONTHS[m]} ${year}</b> dengan ${esc(currentModelLabel())}...`);
            const data = await api('run', {period:'month', year:year, month:String(m)});
            logProgress(`Selesai <b>${MONTHS[m]}</b> | Data ${data.total} | candidate ${data.candidate_count} | anomali final ${data.anomaly_count}`);
        }
        setAlert(`Auto Jan-Des ${year} selesai. Grafik tetap mengikuti filter aktif: ${getRangeText()}.`, 'ok');
    } catch(e) {
        logProgress(`<span style="color:#ff9aa6">Gagal: ${esc(e.message)}</span>`);
        setAlert('Auto Jan-Des gagal: ' + e.message, 'err');
    } finally {
        $('period').value = oldPeriod;
        renderPeriodDetail();
        if ($('monthInput') && oldMonth) $('monthInput').value = oldMonth;
        if ($('yearInput') && oldYear) $('yearInput').value = oldYear;
        if ($('dateFrom') && oldFrom) $('dateFrom').value = oldFrom;
        if ($('dateTo') && oldTo) $('dateTo').value = oldTo;
        $('btnAuto').disabled = false;
        $('btnPredict').disabled = false;
        await refreshTrend();
    }
}

$('unitId').addEventListener('change', async () => { await loadSensors(); await refreshTrend(); });
$('tagno').addEventListener('change', refreshTrend);
$('modelType').addEventListener('change', refreshTrend);
$('period').addEventListener('change', () => { renderPeriodDetail(); refreshTrend(); });
$('minConsecutive').addEventListener('change', refreshTrend);
$('limitRows').addEventListener('change', refreshTrend);
$('btnPredict').addEventListener('click', runPredict);
$('btnRefresh').addEventListener('click', refreshTrend);
$('btnCompare').addEventListener('click', compareMethods);
$('btnAuto').addEventListener('click', autoJanDes);

document.querySelectorAll('.chip').forEach(chip => chip.addEventListener('click', () => {
    const idx = Number(chip.dataset.trace);
    traceVisible[idx] = !traceVisible[idx];
    renderPlot();
}));

window.addEventListener('resize', () => { if ($('trendPlot')) Plotly.Plots.resize('trendPlot'); });

renderPeriodDetail();
loadSensors().then(refreshTrend);
</script>
</body>
</html>
