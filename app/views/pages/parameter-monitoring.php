<?php
require_login();
$role     = $_SESSION['role'];
$plant_id = $_SESSION['selected_plant_id'] ?? null;
$unit_id  = $_SESSION['selected_unit_id']  ?? null;

// Superadmin bisa pilih plant/unit manapun
if ($role === 'superadmin') {
    $plant_id = intval($_GET['plant_id'] ?? $_SESSION['selected_plant_id'] ?? 0);
    $unit_id  = intval($_GET['unit_id']  ?? $_SESSION['selected_unit_id']  ?? 0);
    $all_plants = $conn->query("SELECT * FROM plants WHERE status=1 ORDER BY description")->fetchAll();
    $all_units  = $plant_id ? $conn->query("SELECT * FROM units WHERE plant_id=$plant_id AND status=1 ORDER BY unit_name")->fetchAll() : [];
} else {
    // Admin/user: pakai plant & unit session mereka
    $all_plants = [];
    $all_units  = [];
}

// Ambil addresses untuk plant+unit ini
$addresses = [];
if ($plant_id && $unit_id) {
    $stmt = $conn->prepare("SELECT a.*, p.description as plant_name, u.unit_name FROM pm_address a JOIN plants p ON a.plant_id=p.plant_id JOIN units u ON a.unit_id=u.unit_id WHERE a.plant_id=? AND a.unit_id=? ORDER BY a.tag_id");
    $stmt->execute([$plant_id, $unit_id]);
    $addresses = $stmt->fetchAll();
}

$tab = $_GET['tab'] ?? 'tampilkan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parameter Monitoring - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        .pm-tabs { display:flex;gap:0;border-bottom:2px solid var(--border-color);margin-bottom:24px }
        .pm-tab  { padding:12px 24px;cursor:pointer;font-weight:600;font-size:14px;border:none;background:none;color:var(--text-secondary);border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s }
        .pm-tab.active { color:var(--accent-cyan);border-bottom-color:var(--accent-cyan) }
        .pm-tab:hover  { color:var(--text-primary) }
        .pm-pane { display:none }
        .pm-pane.active { display:block }
        .tag-card { background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;padding:16px 20px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:14px }
        .tag-card:hover { border-color:var(--accent-cyan);background:rgba(0,217,255,.06) }
        .tag-card.selected { border-color:var(--accent-cyan);background:rgba(0,217,255,.1) }
        .tag-badge { background:linear-gradient(135deg,var(--accent-cyan),var(--accent-blue));color:#0a1628;border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700;flex-shrink:0 }
        .chart-wrap { position:relative;height:380px;background:var(--bg-secondary);border-radius:12px;padding:16px;border:1px solid var(--border-color) }
        .stat-mini { background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;padding:14px 16px;text-align:center }
        .stat-mini .val { font-size:22px;font-weight:700;color:var(--accent-cyan) }
        .stat-mini .lbl { font-size:11px;color:var(--text-secondary);margin-top:3px }
        .upload-zone { border:2px dashed var(--border-color);border-radius:12px;padding:32px;text-align:center;transition:all .2s }
        .upload-zone.drag { border-color:var(--accent-cyan);background:rgba(0,217,255,.05) }
        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:16px }
        @media(max-width:640px){.form-row{grid-template-columns:1fr}}
        .badge-satuan { background:rgba(0,217,255,.15);color:var(--accent-cyan);border-radius:6px;padding:2px 8px;font-size:11px;font-weight:600 }
        #progressBar { height:6px;background:var(--accent-cyan);border-radius:3px;width:0%;transition:width .3s }
        #progressWrap { background:var(--border-color);border-radius:3px;overflow:hidden;margin-top:8px;display:none }
    </style>
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                <h1 class="page-title" style="margin:0">
                    <i class="bi bi-activity" style="color:var(--accent-cyan);margin-right:10px"></i>
                    Parameter Monitoring
                </h1>
                <?php if ($plant_id && $unit_id): ?>
                <span style="font-size:13px;color:var(--text-secondary)">
                    <i class="bi bi-geo-alt-fill" style="color:var(--accent-cyan)"></i>
                    <?php
                    $pname = $conn->prepare("SELECT description FROM plants WHERE plant_id=?"); $pname->execute([$plant_id]);
                    $uname = $conn->prepare("SELECT unit_name FROM units WHERE unit_id=?"); $uname->execute([$unit_id]);
                    echo htmlspecialchars($pname->fetchColumn() . ' — ' . $uname->fetchColumn());
                    ?>
                </span>
                <?php endif; ?>
            </div>

            <?= flash() ?>

            <?php if ($role === 'superadmin' && (!$plant_id || !$unit_id)): ?>
            <!-- Superadmin pilih plant/unit -->
            <div class="card" style="max-width:500px">
                <h2 class="card-title" style="margin-bottom:16px">Pilih Plant & Unit</h2>
                <form method="GET" action="">
                    <input type="hidden" name="page" value="parameter-monitoring">
                    <div class="form-group">
                        <label>Plant</label>
                        <select name="plant_id" class="form-control" onchange="this.form.submit()" required>
                            <option value="">-- Pilih Plant --</option>
                            <?php foreach ($all_plants as $p): ?>
                            <option value="<?= $p['plant_id'] ?>" <?= $plant_id==$p['plant_id']?'selected':'' ?>><?= htmlspecialchars($p['description']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($plant_id && !empty($all_units)): ?>
                    <div class="form-group">
                        <label>Unit</label>
                        <select name="unit_id" class="form-control" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($all_units as $u): ?>
                            <option value="<?= $u['unit_id'] ?>" <?= $unit_id==$u['unit_id']?'selected':'' ?>><?= htmlspecialchars($u['unit_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <?php endif; ?>
                </form>
            </div>
            <?php else: ?>

            <!-- TABS -->
            <div class="pm-tabs">
                <button class="pm-tab <?= $tab==='tampilkan'?'active':'' ?>" onclick="switchTab('tampilkan')">
                    <i class="bi bi-graph-up" style="margin-right:6px"></i>Tampilkan Data
                </button>
                <?php if (in_array($role,['superadmin','admin'])): ?>
                <button class="pm-tab <?= $tab==='tambah'?'active':'' ?>" onclick="switchTab('tambah')">
                    <i class="bi bi-plus-circle" style="margin-right:6px"></i>Tambah Data
                </button>
                <button class="pm-tab <?= $tab==='edit'?'active':'' ?>" onclick="switchTab('edit')">
                    <i class="bi bi-pencil-square" style="margin-right:6px"></i>Edit Data
                </button>
                <?php endif; ?>
            </div>

            <!-- ════ TAB: TAMPILKAN DATA ════ -->
            <div class="pm-pane <?= $tab==='tampilkan'?'active':'' ?>" id="tab-tampilkan">
                <?php if (empty($addresses)): ?>
                <div style="text-align:center;padding:60px;color:var(--text-secondary)">
                    <i class="bi bi-inbox" style="font-size:48px;display:block;margin-bottom:12px;opacity:.4"></i>
                    Belum ada address untuk plant/unit ini.<br>
                    <?php if(in_array($role,['superadmin','admin'])): ?>
                    <button onclick="switchTab('tambah')" class="btn btn-primary" style="margin-top:16px">Tambah Address</button>
                    <?php endif; ?>
                </div>
                <?php else: ?>

                <!-- Pilih tag -->
                <div style="margin-bottom:20px">
                    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">Pilih parameter untuk ditampilkan:</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px" id="tagList">
                        <?php foreach ($addresses as $addr): ?>
                        <div class="tag-card" onclick="selectTag(<?= $addr['address_id'] ?>, this)"
                             data-id="<?= $addr['address_id'] ?>">
                            <span class="tag-badge"><?= $addr['tag_id'] ?></span>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($addr['deskripsi']) ?></div>
                                <div style="font-size:11px;color:var(--text-secondary);margin-top:2px"><?= htmlspecialchars($addr['address_no']) ?></div>
                            </div>
                            <span class="badge-satuan"><?= htmlspecialchars($addr['satuan']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Panel grafik (awalnya hidden) -->
                <div id="chartPanel" style="display:none">
                    <!-- Filter -->
                    <div class="card" style="margin-bottom:16px">
                        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                                <label style="font-size:12px">Dari Tanggal</label>
                                <input type="date" id="dateFrom" class="form-control" style="padding:8px 12px">
                            </div>
                            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                                <label style="font-size:12px">Sampai Tanggal</label>
                                <input type="date" id="dateTo" class="form-control" style="padding:8px 12px">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label style="font-size:12px">Agregasi</label>
                                <select id="aggSelect" class="form-control" style="padding:8px 12px">
                                    <option value="raw">Raw (5 menit)</option>
                                    <option value="hour">Per Jam</option>
                                    <option value="day">Per Hari</option>
                                </select>
                            </div>
                            <button onclick="loadChart()" class="btn btn-primary" style="padding:9px 20px">
                                <i class="bi bi-search" style="margin-right:6px"></i>Tampilkan
                            </button>
                        </div>
                    </div>

                    <!-- Stat cards -->
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px" id="statsRow">
                        <div class="stat-mini"><div class="val" id="sAvg">—</div><div class="lbl">Rata-rata</div></div>
                        <div class="stat-mini"><div class="val" id="sMin">—</div><div class="lbl">Minimum</div></div>
                        <div class="stat-mini"><div class="val" id="sMax">—</div><div class="lbl">Maksimum</div></div>
                        <div class="stat-mini"><div class="val" id="sCount">—</div><div class="lbl">Jumlah Data</div></div>
                    </div>

                    <!-- Chart -->
                    <div class="chart-wrap">
                        <div id="chartTitle" style="font-weight:700;margin-bottom:12px;font-size:14px"></div>
                        <div style="position:relative;height:320px">
                            <canvas id="mainChart"></canvas>
                        </div>
                        <div id="chartEmpty" style="display:none;text-align:center;padding:60px;color:var(--text-secondary)">
                            <i class="bi bi-bar-chart-line" style="font-size:36px;opacity:.3;display:block;margin-bottom:8px"></i>
                            Tidak ada data untuk rentang tanggal ini
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ════ TAB: TAMBAH DATA ════ -->
            <?php if (in_array($role,['superadmin','admin'])): ?>
            <div class="pm-pane <?= $tab==='tambah'?'active':'' ?>" id="tab-tambah">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

                    <!-- Opsi 1: Tambah Address Baru -->
                    <div class="card">
                        <h3 style="margin-bottom:4px;display:flex;align-items:center;gap:8px">
                            <i class="bi bi-node-plus" style="color:var(--accent-cyan)"></i> Tambah Address Baru
                        </h3>
                        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px">Daftarkan tag/sensor baru untuk plant & unit ini</p>
                        <form id="formAddAddress">
                            <div class="form-group">
                                <label>Tag ID *</label>
                                <input type="number" name="tag_id" id="newTagId" class="form-control" placeholder="Contoh: 85" required min="1">
                            </div>
                            <div class="form-group">
                                <label>Address No *</label>
                                <input type="text" name="address_no" class="form-control" placeholder="Contoh: aOPC.AW2002.2KAI.AI131303.PNT" required>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi *</label>
                                <input type="text" name="deskripsi" class="form-control" placeholder="Contoh: IDF B DRIVED END BEARING..." required>
                            </div>
                            <div class="form-group">
                                <label>Satuan</label>
                                <input type="text" name="satuan" class="form-control" placeholder="Contoh: mm/s, °C, A, %">
                            </div>
                            <div id="addAddrMsg"></div>
                            <button type="button" onclick="submitAddAddress()" class="btn btn-primary" style="width:100%">
                                <i class="bi bi-plus-circle" style="margin-right:6px"></i>Daftarkan Address
                            </button>
                        </form>
                    </div>

                    <!-- Opsi 2: Upload CSV ke address yang ada -->
                    <div class="card">
                        <h3 style="margin-bottom:4px;display:flex;align-items:center;gap:8px">
                            <i class="bi bi-cloud-upload" style="color:var(--accent-cyan)"></i> Upload Data CSV
                        </h3>
                        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px">Upload file CSV ke address yang sudah terdaftar</p>
                        <div class="form-group">
                            <label>Pilih Address / Tag</label>
                            <select id="uploadAddressId" class="form-control">
                                <option value="">-- Pilih Address --</option>
                                <?php foreach ($addresses as $a): ?>
                                <option value="<?= $a['address_id'] ?>">[<?= $a['tag_id'] ?>] <?= htmlspecialchars($a['deskripsi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mode Upload</label>
                            <div style="display:flex;gap:12px">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;text-transform:none;letter-spacing:normal;font-weight:normal">
                                    <input type="radio" name="uploadMode" value="append" checked style="width:16px;height:16px"> Tambahkan (append)
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;text-transform:none;letter-spacing:normal;font-weight:normal">
                                    <input type="radio" name="uploadMode" value="replace" style="width:16px;height:16px"> Ganti semua (replace)
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>File CSV</label>
                            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
                                <i class="bi bi-file-earmark-spreadsheet" style="font-size:32px;color:var(--accent-cyan);display:block;margin-bottom:8px"></i>
                                <div id="uploadZoneText" style="font-size:13px;color:var(--text-secondary)">
                                    Klik atau drag & drop file CSV<br>
                                    <small>Format: tag_id, timestamp, value (per baris)</small>
                                </div>
                            </div>
                            <input type="file" id="csvFile" accept=".csv" style="display:none" onchange="onFileSelect(this)">
                            <div id="progressWrap"><div id="progressBar"></div></div>
                        </div>
                        <div id="uploadMsg"></div>
                        <button type="button" onclick="submitUpload()" class="btn btn-primary" style="width:100%">
                            <i class="bi bi-upload" style="margin-right:6px"></i>Upload & Simpan
                        </button>
                    </div>
                </div>
            </div>

            <!-- ════ TAB: EDIT DATA ════ -->
            <div class="pm-pane <?= $tab==='edit'?'active':'' ?>" id="tab-edit">
                <div class="card">
                    <h3 style="margin-bottom:16px">Daftar Address Terdaftar</h3>
                    <?php if (empty($addresses)): ?>
                    <p style="color:var(--text-secondary);text-align:center;padding:30px">Belum ada address.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table id="editTable">
                            <thead>
                                <tr>
                                    <th>Tag ID</th>
                                    <th>Address No</th>
                                    <th>Deskripsi</th>
                                    <th>Satuan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($addresses as $a): ?>
                                <tr id="row-<?= $a['address_id'] ?>">
                                    <td><strong><?= $a['tag_id'] ?></strong></td>
                                    <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($a['address_no']) ?>"><?= htmlspecialchars($a['address_no']) ?></td>
                                    <td style="max-width:220px"><?= htmlspecialchars($a['deskripsi']) ?></td>
                                    <td><span class="badge-satuan"><?= htmlspecialchars($a['satuan']) ?></span></td>
                                    <td>
                                        <div style="display:flex;gap:6px">
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" class="btn btn-sm btn-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button onclick="openDeleteDataModal(<?= $a['address_id'] ?>, '<?= htmlspecialchars($a['deskripsi']) ?>')" class="btn btn-sm btn-secondary" title="Hapus Range Data" style="color:#f59e0b">
                                                <i class="bi bi-calendar-x"></i>
                                            </button>
                                            <button onclick="deleteAddress(<?= $a['address_id'] ?>, '<?= htmlspecialchars($a['deskripsi']) ?>')" class="btn btn-sm btn-secondary" title="Hapus Address & Semua Data" style="color:#ff6b7a">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; // plant & unit exists ?>
        </div>
    </div>
</div>

<!-- Modal Edit Address -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center">
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:28px 32px;width:100%;max-width:480px;margin:20px">
        <div style="font-size:17px;font-weight:700;margin-bottom:20px">Edit Address</div>
        <input type="hidden" id="editAddrId">
        <div class="form-group">
            <label>Tag ID</label>
            <input type="number" id="editTagId" class="form-control" readonly style="opacity:.6">
        </div>
        <div class="form-group">
            <label>Address No *</label>
            <input type="text" id="editAddressNo" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Deskripsi *</label>
            <input type="text" id="editDeskripsi" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Satuan</label>
            <input type="text" id="editSatuan" class="form-control">
        </div>
        <div id="editMsg"></div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button onclick="submitEditAddress()" class="btn btn-primary" style="flex:1">Simpan</button>
            <button onclick="closeEditModal()" class="btn btn-secondary" style="padding:12px 20px">Batal</button>
        </div>
    </div>
</div>

<!-- Modal Hapus Range Data -->
<div id="deleteDataModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center">
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:28px 32px;width:100%;max-width:420px;margin:20px">
        <div style="font-size:17px;font-weight:700;margin-bottom:6px">Hapus Range Data</div>
        <div id="deleteDataDesc" style="font-size:13px;color:var(--text-secondary);margin-bottom:20px"></div>
        <input type="hidden" id="deleteAddrId">
        <div class="form-row">
            <div class="form-group" style="margin:0">
                <label>Dari Tanggal *</label>
                <input type="date" id="delDateFrom" class="form-control">
            </div>
            <div class="form-group" style="margin:0">
                <label>Sampai Tanggal *</label>
                <input type="date" id="delDateTo" class="form-control">
            </div>
        </div>
        <div id="deleteDataMsg" style="margin-top:12px"></div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button onclick="submitDeleteData()" class="btn btn-primary" style="flex:1;background:#ff6b7a;border-color:#ff6b7a">Hapus Data</button>
            <button onclick="document.getElementById('deleteDataModal').style.display='none'" class="btn btn-secondary" style="padding:12px 20px">Batal</button>
        </div>
    </div>
</div>

<script>
const BASE_URL       = '<?= BASE_URL ?>';
const PLANT_ID       = <?= intval($plant_id) ?>;
const UNIT_ID        = <?= intval($unit_id) ?>;
const CURRENT_ROLE   = '<?= $role ?>';
let   currentAddrId  = null;
let   mainChart      = null;

// ── Tab switching ─────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.pm-tab,.pm-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll(`[id="tab-${tab}"]`).forEach(el => el.classList.add('active'));
    document.querySelectorAll('.pm-tab').forEach(btn => {
        if (btn.textContent.trim().toLowerCase().includes(tab === 'tampilkan' ? 'tampilkan' : tab === 'tambah' ? 'tambah' : 'edit'))
            btn.classList.add('active');
    });
}
// Fix tab button active on click
document.querySelectorAll('.pm-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// ── Select Tag ────────────────────────────────────────────────
function selectTag(addrId, el) {
    document.querySelectorAll('.tag-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    currentAddrId = addrId;
    document.getElementById('chartPanel').style.display = 'block';
    // Default date: bulan ini
    const now = new Date();
    const y = now.getFullYear(), m = String(now.getMonth()+1).padStart(2,'0');
    document.getElementById('dateFrom').value = `${y}-${m}-01`;
    document.getElementById('dateTo').value   = `${y}-${m}-${String(now.getDate()).padStart(2,'0')}`;
    loadChart();
}

// ── Load Chart ────────────────────────────────────────────────
function loadChart() {
    if (!currentAddrId) return;
    const from = document.getElementById('dateFrom').value;
    const to   = document.getElementById('dateTo').value;
    const agg  = document.getElementById('aggSelect').value;

    if (!from || !to) { alert('Pilih rentang tanggal'); return; }

    document.getElementById('chartEmpty').style.display = 'none';

    fetch(`${BASE_URL}?api=pm&pm=get-chart-data&address_id=${currentAddrId}&date_from=${from}&date_to=${to}&agg=${agg}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { alert(res.message); return; }
            const addr = res.address;
            document.getElementById('chartTitle').textContent =
                `[${addr.tag_id}] ${addr.deskripsi} (${addr.satuan}) — ${addr.plant_name} ${addr.unit_name}`;

            if (res.data.length === 0) {
                document.getElementById('chartEmpty').style.display = 'block';
                if (mainChart) { mainChart.destroy(); mainChart = null; }
                return;
            }

            const labels = res.data.map(d => d.t);
            const values = res.data.map(d => parseFloat(d.v));

            if (mainChart) mainChart.destroy();
            const ctx = document.getElementById('mainChart').getContext('2d');
            mainChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: `${addr.deskripsi} (${addr.satuan})`,
                        data: values,
                        borderColor: '#00d9ff',
                        backgroundColor: 'rgba(0,217,255,0.07)',
                        borderWidth: 1.5,
                        pointRadius: values.length > 500 ? 0 : 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'category',
                            ticks: {
                                maxTicksLimit: 12,
                                color: '#8892a4',
                                font: { size: 11 },
                                callback: function(val, idx) {
                                    const lbl = this.getLabelForValue(val);
                                    return lbl ? lbl.substring(0, 16) : val;
                                }
                            },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        y: {
                            ticks: { color: '#8892a4', font: { size: 11 } },
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            title: { display: true, text: addr.satuan, color: '#8892a4', font: { size: 11 } }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: '#c9d1d9', font: { size: 12 } } },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.parsed.y.toFixed(4)} ${addr.satuan}`
                            }
                        }
                    }
                }
            });

            // Load stats
            fetch(`${BASE_URL}?api=pm&pm=get-stats&address_id=${currentAddrId}&date_from=${from}&date_to=${to}`)
                .then(r => r.json())
                .then(s => {
                    if (s.success && s.data) {
                        const d = s.data;
                        document.getElementById('sAvg').textContent   = d.avg   ? parseFloat(d.avg).toFixed(4)   : '—';
                        document.getElementById('sMin').textContent   = d.min   ? parseFloat(d.min).toFixed(4)   : '—';
                        document.getElementById('sMax').textContent   = d.max   ? parseFloat(d.max).toFixed(4)   : '—';
                        document.getElementById('sCount').textContent = d.total ? parseInt(d.total).toLocaleString() : '—';
                    }
                });
        })
        .catch(err => alert('Gagal memuat data: ' + err));
}

// ── Add Address ───────────────────────────────────────────────
function submitAddAddress() {
    const form = document.getElementById('formAddAddress');
    const data = new FormData(form);
    data.append('plant_id', PLANT_ID);
    data.append('unit_id', UNIT_ID);

    const msg = document.getElementById('addAddrMsg');
    msg.innerHTML = '<small style="color:var(--accent-cyan)">Menyimpan...</small>';

    fetch(`${BASE_URL}?api=pm-add-address`, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            msg.innerHTML = `<div class="alert alert-success" style="padding:10px;margin-top:10px">✅ Address berhasil ditambahkan! <a href="?page=parameter-monitoring" style="color:inherit">Refresh</a></div>`;
            form.reset();
        } else {
            msg.innerHTML = `<div class="alert alert-error" style="padding:10px;margin-top:10px">${res.message}</div>`;
        }
    });
}

// ── Upload CSV ────────────────────────────────────────────────
let selectedFile = null;

function onFileSelect(input) {
    if (input.files[0]) {
        selectedFile = input.files[0];
        document.getElementById('uploadZoneText').innerHTML =
            `<strong style="color:var(--accent-cyan)">${selectedFile.name}</strong><br><small>${(selectedFile.size/1024).toFixed(1)} KB</small>`;
    }
}

// Drag & drop
const zone = document.getElementById('uploadZone');
if (zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('drag');
        const f = e.dataTransfer.files[0];
        if (f && f.name.endsWith('.csv')) {
            selectedFile = f;
            document.getElementById('uploadZoneText').innerHTML =
                `<strong style="color:var(--accent-cyan)">${f.name}</strong><br><small>${(f.size/1024).toFixed(1)} KB</small>`;
        }
    });
}

function submitUpload() {
    const addrId = document.getElementById('uploadAddressId').value;
    const mode   = document.querySelector('input[name="uploadMode"]:checked').value;
    const msg    = document.getElementById('uploadMsg');

    if (!addrId) { alert('Pilih address terlebih dahulu'); return; }
    if (!selectedFile) { alert('Pilih file CSV terlebih dahulu'); return; }

    const data = new FormData();
    data.append('address_id', addrId);
    data.append('mode', mode);
    data.append('csv_file', selectedFile);

    document.getElementById('progressWrap').style.display = 'block';
    document.getElementById('progressBar').style.width = '30%';
    msg.innerHTML = '<small style="color:var(--accent-cyan)">Mengupload & memproses...</small>';

    fetch(`${BASE_URL}?api=pm-upload`, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            document.getElementById('progressBar').style.width = '100%';
            if (res.success) {
                msg.innerHTML = `<div class="alert alert-success" style="padding:10px;margin-top:10px">✅ ${res.message}</div>`;
                selectedFile = null;
                document.getElementById('uploadZoneText').innerHTML = 'Klik atau drag & drop file CSV<br><small>Format: tag_id, timestamp, value</small>';
            } else {
                msg.innerHTML = `<div class="alert alert-error" style="padding:10px;margin-top:10px">❌ ${res.message}</div>`;
            }
            setTimeout(() => { document.getElementById('progressBar').style.width = '0%'; document.getElementById('progressWrap').style.display = 'none'; }, 1500);
        })
        .catch(err => { msg.innerHTML = `<div class="alert alert-error" style="padding:10px;margin-top:10px">Error: ${err}</div>`; });
}

// ── Edit Address Modal ────────────────────────────────────────
function openEditModal(addr) {
    document.getElementById('editAddrId').value   = addr.address_id;
    document.getElementById('editTagId').value    = addr.tag_id;
    document.getElementById('editAddressNo').value = addr.address_no;
    document.getElementById('editDeskripsi').value = addr.deskripsi;
    document.getElementById('editSatuan').value   = addr.satuan;
    document.getElementById('editMsg').innerHTML  = '';
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

function submitEditAddress() {
    const id       = document.getElementById('editAddrId').value;
    const body     = new URLSearchParams({
        address_no: document.getElementById('editAddressNo').value,
        deskripsi:  document.getElementById('editDeskripsi').value,
        satuan:     document.getElementById('editSatuan').value,
    });
    fetch(`${BASE_URL}?api=pm-edit-address&address_id=${id}`, { method:'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) { location.reload(); }
            else { document.getElementById('editMsg').innerHTML = `<div style="color:#ff6b7a;font-size:13px;margin-top:8px">${res.message}</div>`; }
        });
}

// ── Delete Data Range ─────────────────────────────────────────
function openDeleteDataModal(addrId, desc) {
    document.getElementById('deleteAddrId').value  = addrId;
    document.getElementById('deleteDataDesc').textContent = `Address: ${desc}`;
    document.getElementById('deleteDataMsg').innerHTML = '';
    document.getElementById('deleteDataModal').style.display = 'flex';
}

function submitDeleteData() {
    const body = new URLSearchParams({
        address_id: document.getElementById('deleteAddrId').value,
        date_from:  document.getElementById('delDateFrom').value,
        date_to:    document.getElementById('delDateTo').value,
    });
    fetch(`${BASE_URL}?api=pm&pm=delete-data`, { method:'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('deleteDataMsg').innerHTML = `<div style="color:#5dd87e;font-size:13px">✅ ${res.deleted} data berhasil dihapus</div>`;
                setTimeout(() => document.getElementById('deleteDataModal').style.display = 'none', 1500);
            } else {
                document.getElementById('deleteDataMsg').innerHTML = `<div style="color:#ff6b7a;font-size:13px">${res.message}</div>`;
            }
        });
}

// ── Delete Address ────────────────────────────────────────────
function deleteAddress(addrId, desc) {
    if (!confirm(`Hapus address "${desc}" beserta SEMUA datanya? Ini tidak bisa dibatalkan!`)) return;
    fetch(`${BASE_URL}?api=pm&pm=delete-address`, {
        method: 'POST',
        body: new URLSearchParams({ address_id: addrId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById(`row-${addrId}`)?.remove();
        } else alert('Gagal menghapus');
    });
}

// Close modals on backdrop click
document.getElementById('editModal').addEventListener('click', e => { if(e.target===document.getElementById('editModal')) closeEditModal(); });
document.getElementById('deleteDataModal').addEventListener('click', e => { if(e.target===document.getElementById('deleteDataModal')) document.getElementById('deleteDataModal').style.display='none'; });
</script>
</body>
</html>
