<?php
require_login();
$role     = $_SESSION['role'];
$plant_id = intval($_SESSION['selected_plant_id'] ?? 0);
$unit_id  = intval($_SESSION['selected_unit_id']  ?? 0);

if (!$plant_id || !$unit_id) redirect('select-plant');

// Info plant & unit
$plant = $conn->prepare("SELECT description FROM plants WHERE plant_id=?");
$plant->execute([$plant_id]);
$plant_name = $plant->fetchColumn();

$unit_row = $conn->prepare("SELECT unit_name, database_name FROM units WHERE unit_id=?");
$unit_row->execute([$unit_id]);
$unit_data = $unit_row->fetch();
$unit_name = $unit_data['unit_name'] ?? '';
$db_name   = $unit_data['database_name'] ?? null;

// Koneksi ke database unit terpisah
$unit_conn = get_unit_db($unit_id, $conn);
// Refresh db_name setelah get_unit_db (bisa baru di-generate)
$unit_row2 = $conn->prepare("SELECT database_name FROM units WHERE unit_id=?");
$unit_row2->execute([$unit_id]);
$db_name = $unit_row2->fetchColumn() ?: 'belum ada';

$addresses = [];
$total_tags = 0;
if ($unit_conn) {
    $stmt = $unit_conn->query("
        SELECT m.*,
               (SELECT COUNT(*) FROM tag_data d WHERE d.tag_id = m.tag_id) as data_count,
               (SELECT MAX(timestamp) FROM tag_data d WHERE d.tag_id = m.tag_id) as last_update
        FROM tag_master m
        ORDER BY m.tag_id ASC
    ");
    $addresses  = $stmt->fetchAll();
    $total_tags = count($addresses);
}

$can_manage = in_array($role, ['superadmin','admin']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parameter Monitoring - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260225">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <style>
        .flatpickr-calendar { background:#1a2a3f !important; border:1.5px solid rgba(0,217,255,.25) !important; border-radius:12px !important; box-shadow:0 12px 40px rgba(0,0,0,.5) !important; font-family:inherit !important; }
        .flatpickr-day { color:#c8d6e5 !important; border-radius:8px !important; }
        .flatpickr-day:hover,.flatpickr-day.inRange { background:rgba(0,217,255,.15) !important; border-color:transparent !important; color:#fff !important; }
        .flatpickr-day.selected,.flatpickr-day.startRange,.flatpickr-day.endRange { background:linear-gradient(135deg,#00d9ff,#0066ff) !important; border-color:transparent !important; color:#0a1628 !important; font-weight:700 !important; }
        .flatpickr-months .flatpickr-month,.flatpickr-weekdays,span.flatpickr-weekday { background:transparent !important; color:#00d9ff !important; }
        .flatpickr-current-month input.cur-year,.flatpickr-current-month .flatpickr-monthDropdown-months { color:#fff !important; background:transparent !important; }
        .flatpickr-prev-month svg,.flatpickr-next-month svg { fill:#00d9ff !important; }
        .flatpickr-day.today { border-color:#00d9ff !important; }
        .flatpickr-day.flatpickr-disabled { color:#3a4a5a !important; }
        .date-btn { display:flex;align-items:center;gap:7px;padding:7px 13px;background:var(--bg-secondary);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;cursor:pointer;transition:border-color .2s;white-space:nowrap;min-width:130px; }
        .date-btn:hover { border-color:var(--accent-cyan); }
        .date-btn i { color:var(--accent-cyan); }
    </style>
    <style>
        .pm-tabs { display:flex; gap:0; border-bottom:2px solid var(--border-color); margin-bottom:24px; }
        .pm-tab  { padding:11px 22px; border:none; background:none; cursor:pointer; color:var(--text-secondary); font-size:14px; font-weight:600; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; display:flex; align-items:center; gap:7px; }
        .pm-tab:hover  { color:var(--text-primary); }
        .pm-tab.active { color:var(--accent-cyan); border-bottom-color:var(--accent-cyan); }
        .pm-panel { display:none; } .pm-panel.active { display:block; }
        .tag-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:12px; margin-bottom:20px; }
        .tag-card { background:var(--bg-secondary); border:2px solid var(--border-color); border-radius:12px; padding:16px 18px; cursor:pointer; transition:all .2s; }
        .tag-card:hover  { border-color:var(--accent-cyan); transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,217,255,.12); }
        .tag-card.active { border-color:var(--accent-cyan); background:rgba(0,217,255,.06); }
        .tag-id-badge { background:linear-gradient(135deg,#00d9ff,#0066ff); color:#0a1628; border-radius:8px; padding:3px 10px; font-size:12px; font-weight:800; display:inline-block; margin-bottom:8px; }
        .chart-wrap { position:relative; height:400px; }
        .stat-pills { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
        .stat-pill  { background:rgba(0,217,255,.1); color:var(--accent-cyan); border-radius:20px; padding:5px 14px; font-size:12px; font-weight:600; display:flex; align-items:center; gap:5px; }
        /* Upload zone */
        .upload-zone { border:2px dashed var(--border-color); border-radius:12px; padding:28px; text-align:center; cursor:pointer; transition:all .2s; }
        .upload-zone:hover, .upload-zone.drag { border-color:var(--accent-cyan); background:rgba(0,217,255,.04); }
        /* DB badge */
        .db-badge { background:rgba(0,217,255,.12); border:1px solid rgba(0,217,255,.3); color:var(--accent-cyan); border-radius:8px; padding:4px 12px; font-size:12px; font-family:monospace; display:inline-flex; align-items:center; gap:6px; }
        /* Steps */
        .step-flow { display:flex; align-items:center; gap:0; margin-bottom:20px; }
        .step-box { flex:1; background:var(--bg-secondary); border:2px solid var(--border-color); border-radius:12px; padding:14px 16px; text-align:center; }
        .step-box.active-step { border-color:var(--accent-cyan); background:rgba(0,217,255,.05); }
        .step-arrow { color:var(--text-secondary); font-size:20px; padding:0 12px; flex-shrink:0; }
        .step-num { background:var(--accent-cyan); color:#0a1628; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; margin-bottom:6px; }
        /* Modal */
        .pm-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); backdrop-filter:blur(5px); z-index:99999; align-items:center; justify-content:center; padding:20px; }
        .pm-modal.open { display:flex; }
        .pm-modal-box { background:var(--bg-card); border:1px solid var(--border-color); border-radius:16px; padding:28px 32px; width:100%; max-width:500px; }
        .edit-table { width:100%; border-collapse:collapse; }
        .edit-table th { background:var(--bg-secondary); padding:10px 14px; text-align:left; font-size:12px; color:var(--text-secondary); font-weight:600; }
        .edit-table td { padding:12px 14px; border-bottom:1px solid var(--border-color); font-size:13px; vertical-align:middle; }
        .satuan-badge { background:rgba(0,217,255,.12); color:var(--accent-cyan); border-radius:6px; padding:2px 8px; font-size:11px; font-weight:600; }
        #uploadProgress { height:5px; background:var(--accent-cyan); width:0%; border-radius:3px; transition:width .3s; }
        #uploadProgressWrap, #excelProgressWrap { background:var(--border-color); border-radius:3px; margin-top:10px; overflow:hidden; display:none; }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">

            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                <div>
                    <h1 class="page-title" style="margin-bottom:4px">
                        <i class="bi bi-activity" style="color:var(--accent-cyan);margin-right:8px"></i>Parameter Monitoring
                    </h1>
                    <p style="color:var(--text-secondary);font-size:13px;margin:0 0 6px">
                        <i class="bi bi-geo-alt-fill" style="color:var(--accent-cyan)"></i>
                        <?= htmlspecialchars($plant_name) ?> — <?= htmlspecialchars($unit_name) ?>
                    </p>
                    <span class="db-badge">
                        <i class="bi bi-database-fill"></i> Database: <strong><?= htmlspecialchars($db_name) ?></strong>
                        &nbsp;·&nbsp; <?= $total_tags ?> tag terdaftar
                    </span>
                </div>
            </div>

            <?= flash() ?>

            <!-- TABS -->
            <div class="pm-tabs">
                <button class="pm-tab active" id="tabBtn-tampilkan" onclick="switchTab('tampilkan')">
                    <i class="bi bi-graph-up"></i> Tampilkan Data
                </button>
                <?php if ($can_manage): ?>
                <button class="pm-tab" id="tabBtn-tambah" onclick="switchTab('tambah')">
                    <i class="bi bi-upload"></i> Upload Data
                </button>
                <button class="pm-tab" id="tabBtn-edit" onclick="switchTab('edit')">
                    <i class="bi bi-pencil-square"></i> Kelola Address
                </button>
                <?php if ($role === 'superadmin'): ?>
                <button class="pm-tab" id="tabBtn-mesin" onclick="switchTab('mesin')">
                    <i class="bi bi-cpu"></i> Koneksi Mesin
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- ══════════════════════════════ TAB: TAMPILKAN ══════════════════════════════ -->
            <div class="pm-panel active" id="panel-tampilkan">
                <?php if (empty($addresses)): ?>
                <div class="card" style="text-align:center;padding:60px">
                    <i class="bi bi-inbox" style="font-size:52px;color:var(--text-secondary);opacity:.4;display:block;margin-bottom:14px"></i>
                    <p style="font-size:16px;font-weight:600;margin-bottom:6px">Belum ada tag terdaftar</p>
                    <p style="font-size:13px;color:var(--text-secondary)">Upload file Excel (.xlsx) berisi daftar address/tag sensor terlebih dahulu</p>
                    <?php if ($can_manage): ?>
                    <button onclick="switchTab('tambah')" class="btn btn-primary" style="margin-top:16px">
                        <i class="bi bi-file-earmark-excel"></i> Upload Excel Address
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>

                <div class="card" style="margin-bottom:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                        <h3 style="font-size:14px;margin:0;color:var(--text-secondary);font-weight:600">
                            <i class="bi bi-tags" style="color:var(--accent-cyan);margin-right:6px"></i>
                            PILIH PARAMETER (<span id="tagVisibleCount"><?= count($addresses) ?></span> tersedia)
                        </h3>
                    </div>

                    <!-- Search & Sort Bar -->
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
                        <!-- Search -->
                        <div style="position:relative;flex:1;min-width:200px">
                            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:13px;pointer-events:none"></i>
                            <input type="text" id="tagSearchInput" placeholder="Cari nama parameter, tag ID, atau address..."
                                   oninput="filterAndSortTags()"
                                   style="width:100%;padding:9px 12px 9px 36px;background:var(--bg-secondary);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;outline:none;box-sizing:border-box;transition:border-color .2s"
                                   onfocus="this.style.borderColor='var(--accent-cyan)'"
                                   onblur="this.style.borderColor='var(--border-color)'">
                        </div>

                        <!-- Sort Dropdown -->
                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                            <span style="font-size:12px;color:var(--text-secondary);white-space:nowrap"><i class="bi bi-sort-down"></i> Urutkan:</span>
                            <select id="tagSortSelect" onchange="filterAndSortTags()"
                                    style="padding:9px 12px;background:var(--bg-secondary);border:1.5px solid var(--border-color);border-radius:10px;color:var(--text-primary);font-size:13px;outline:none;cursor:pointer;transition:border-color .2s"
                                    onfocus="this.style.borderColor='var(--accent-cyan)'"
                                    onblur="this.style.borderColor='var(--border-color)'">
                                <option value="tag_asc">Tag Terkecil → Terbesar</option>
                                <option value="tag_desc">Tag Terbesar → Terkecil</option>
                                <option value="name_asc">Nama A → Z</option>
                                <option value="name_desc">Nama Z → A</option>
                                <option value="data_asc">Data Terkecil → Terbesar</option>
                                <option value="data_desc">Data Terbesar → Terkecil</option>
                            </select>
                        </div>

                        <!-- Reset button -->
                        <button onclick="resetTagFilter()" title="Reset filter" id="tagResetBtn"
                                style="display:none;padding:9px 12px;background:rgba(255,107,122,.12);border:1.5px solid rgba(255,107,122,.3);border-radius:10px;color:#ff6b7a;font-size:13px;cursor:pointer;transition:all .2s;white-space:nowrap"
                                onmouseover="this.style.background='rgba(255,107,122,.22)'"
                                onmouseout="this.style.background='rgba(255,107,122,.12)'">
                            <i class="bi bi-x-lg"></i> Reset
                        </button>
                    </div>

                    <!-- No result message -->
                    <div id="tagNoResult" style="display:none;text-align:center;padding:40px 20px;color:var(--text-secondary)">
                        <i class="bi bi-search" style="font-size:40px;display:block;margin-bottom:10px;opacity:.3"></i>
                        <p style="font-size:14px;font-weight:600;margin-bottom:4px">Tidak ada parameter yang cocok</p>
                        <p style="font-size:12px;opacity:.7">Coba kata kunci lain atau <a href="#" onclick="resetTagFilter();return false" style="color:var(--accent-cyan)">reset filter</a></p>
                    </div>

                    <div class="tag-grid" id="tagGrid">
                        <?php foreach ($addresses as $addr): ?>
                        <div class="tag-card"
                             onclick="selectTag(<?= $addr['tag_id'] ?>, '<?= htmlspecialchars($addr['deskripsi'],ENT_QUOTES) ?>', '<?= htmlspecialchars($addr['satuan'],ENT_QUOTES) ?>', '<?= htmlspecialchars($addr['address_no'],ENT_QUOTES) ?>', this)"
                             id="tagcard-<?= $addr['tag_id'] ?>"
                             data-tag-id="<?= $addr['tag_id'] ?>"
                             data-name="<?= htmlspecialchars(strtolower($addr['deskripsi']),ENT_QUOTES) ?>"
                             data-address="<?= htmlspecialchars(strtolower($addr['address_no']),ENT_QUOTES) ?>"
                             data-count="<?= intval($addr['data_count']) ?>">
                            <div class="tag-id-badge">Tag #<?= $addr['tag_id'] ?></div>
                            <div style="font-size:13px;font-weight:700;margin-bottom:4px;line-height:1.4">
                                <?= htmlspecialchars($addr['deskripsi']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-secondary);word-break:break-all;margin-bottom:8px">
                                <?= htmlspecialchars($addr['address_no']) ?>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--text-secondary)">
                                <span><i class="bi bi-database"></i> <?= number_format($addr['data_count']) ?> data</span>
                                <span class="satuan-badge"><?= htmlspecialchars($addr['satuan']) ?></span>
                            </div>
                            <?php if ($addr['last_update']): ?>
                            <div style="font-size:10px;color:var(--text-secondary);margin-top:5px">
                                <i class="bi bi-clock"></i> Update: <?= date('d/m/Y H:i', strtotime($addr['last_update'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card" id="chartPanel" style="display:none">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
                        <div>
                            <h2 id="chartTitle" style="font-size:16px;margin:0;font-weight:700"></h2>
                            <p id="chartSub" style="font-size:11px;color:var(--text-secondary);margin:4px 0 0"></p>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <button class="date-btn" id="dateFromBtn">
                                <i class="bi bi-calendar3"></i>
                                <span id="dateFromLabel">Dari Tanggal</span>
                            </button>
                            <input type="hidden" id="dateFrom">
                            <span style="color:var(--text-secondary);font-size:13px">s/d</span>
                            <button class="date-btn" id="dateToBtn">
                                <i class="bi bi-calendar3"></i>
                                <span id="dateToLabel">Sampai Tanggal</span>
                            </button>
                            <input type="hidden" id="dateTo">
                            <button onclick="loadChart()" class="btn btn-primary" style="padding:8px 16px;font-size:13px">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <div style="position:relative;display:inline-block" id="exportDropdownWrap">
                                <button onclick="toggleExportMenu()" class="btn btn-secondary" style="padding:8px 13px;font-size:13px" title="Unduh">
                                    <i class="bi bi-download"></i>
                                </button>
                                <div id="exportMenu" style="display:none;position:absolute;right:0;top:calc(100% + 6px);background:var(--bg-card);border:1.5px solid var(--border-color);border-radius:10px;min-width:150px;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,.4);overflow:hidden">
                                    <button onclick="exportCSV();toggleExportMenu()" style="width:100%;padding:10px 16px;background:none;border:none;color:var(--text-primary);font-size:13px;text-align:left;cursor:pointer;display:flex;align-items:center;gap:9px;transition:background .15s" onmouseover="this.style.background='rgba(0,217,255,.08)'" onmouseout="this.style.background='none'">
                                        <i class="bi bi-filetype-csv" style="color:#5dd87e"></i> Export CSV
                                    </button>
                                    <button onclick="exportPNG();toggleExportMenu()" style="width:100%;padding:10px 16px;background:none;border:none;color:var(--text-primary);font-size:13px;text-align:left;cursor:pointer;display:flex;align-items:center;gap:9px;transition:background .15s" onmouseover="this.style.background='rgba(0,217,255,.08)'" onmouseout="this.style.background='none'">
                                        <i class="bi bi-file-image" style="color:var(--accent-cyan)"></i> Unduh Gambar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-pills" id="statPills"></div>
                    <div class="chart-wrap">
                        <canvas id="pmChart"></canvas>
                        <div id="chartLoading" style="display:none;position:absolute;inset:0;align-items:center;justify-content:center;background:var(--bg-card);border-radius:8px;flex-direction:column;gap:10px">
                            <div style="width:36px;height:36px;border:3px solid var(--border-color);border-top-color:var(--accent-cyan);border-radius:50%;animation:spin .8s linear infinite"></div>
                            <span style="color:var(--text-secondary);font-size:13px">Memuat data...</span>
                        </div>
                        <div id="chartEmpty" style="display:none;position:absolute;inset:0;align-items:center;justify-content:center">
                            <div style="text-align:center;color:var(--text-secondary)">
                                <i class="bi bi-bar-chart-line" style="font-size:44px;display:block;margin-bottom:10px;opacity:.3"></i>
                                Tidak ada data pada rentang ini
                            </div>
                        </div>
                    </div>
                    <div id="aggNote" style="font-size:11px;color:var(--text-secondary);margin-top:8px;text-align:right"></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ══════════════════════════════ TAB: UPLOAD DATA ══════════════════════════════ -->
            <?php if ($can_manage): ?>
            <div class="pm-panel" id="panel-tambah">

                <!-- Alur kerja step -->
                <div class="step-flow">
                    <div class="step-box <?= empty($addresses) ? 'active-step' : '' ?>">
                        <div class="step-num">1</div>
                        <div style="font-size:12px;font-weight:700;color:var(--text-primary)">Upload Excel Address</div>
                        <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Daftarkan semua tag/sensor dari file .xlsx</div>
                    </div>
                    <div class="step-arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="step-box <?= !empty($addresses) ? 'active-step' : '' ?>">
                        <div class="step-num">2</div>
                        <div style="font-size:12px;font-weight:700;color:var(--text-primary)">Upload Data CSV</div>
                        <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Tag otomatis terdeteksi dari nama file CSV</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

                    <!-- ══ PANEL KIRI: Upload Excel ══ -->
                    <div class="card">
                        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <i class="bi bi-file-earmark-excel" style="color:#1d7a44"></i> Upload Address Excel
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">
                            Upload file <strong>.xlsx</strong> berisi daftar address/tag untuk <strong><?= htmlspecialchars($unit_name) ?></strong>.
                        </p>
                        <div style="background:rgba(0,217,255,.05);border:1px solid rgba(0,217,255,.2);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px">
                            <strong style="color:var(--accent-cyan)">Format kolom Excel:</strong><br>
                            <code>address_no</code> &nbsp;|&nbsp; <code>tag_id</code> &nbsp;|&nbsp; <code>deskripsi</code> &nbsp;|&nbsp; <code>satuan</code>
                        </div>

                        <div class="form-group">
                            <label>Mode Upload Excel</label>
                            <div style="display:flex;gap:20px">
                                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;text-transform:none;letter-spacing:normal;font-weight:normal;font-size:13px">
                                    <input type="radio" name="excelMode" value="append" checked style="width:15px;height:15px"> Tambahkan / Update
                                </label>
                                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;text-transform:none;letter-spacing:normal;font-weight:normal;font-size:13px">
                                    <input type="radio" name="excelMode" value="replace" style="width:15px;height:15px"> Ganti Semua (hapus dulu)
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>File Excel (.xlsx)</label>
                            <div class="upload-zone" id="excelZone" onclick="document.getElementById('excelFileInput').click()">
                                <i class="bi bi-file-earmark-excel" style="font-size:38px;color:#1d7a44;display:block;margin-bottom:8px"></i>
                                <div id="excelZoneText" style="font-size:13px;color:var(--text-secondary)">
                                    Klik atau drag & drop file .xlsx<br>
                                    <small style="opacity:.6">Kolom: address_no, tag_id, deskripsi, satuan</small>
                                </div>
                            </div>
                            <input type="file" id="excelFileInput" accept=".xlsx,.xls" style="display:none" onchange="onExcelChosen(this)">
                            <div id="excelProgressWrap"><div id="excelProgress" style="height:5px;background:var(--accent-cyan);width:0%;border-radius:3px;transition:width .3s"></div></div>
                        </div>

                        <div id="msgExcel"></div>
                        <button onclick="submitExcel()" class="btn btn-primary" style="width:100%;margin-top:4px;background:#1d7a44;border-color:#1d7a44">
                            <i class="bi bi-file-earmark-arrow-up" style="margin-right:6px"></i>Upload & Daftarkan Tag
                        </button>

                        <?php if ($total_tags > 0): ?>
                        <div style="margin-top:12px;padding:10px 14px;background:rgba(93,216,126,.08);border:1px solid rgba(93,216,126,.25);border-radius:8px;font-size:12px;color:#5dd87e">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong><?= $total_tags ?></strong> tag sudah terdaftar di database <code><?= htmlspecialchars($db_name) ?></code>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ══ PANEL KANAN: Upload CSV ══ -->
                    <div class="card">
                        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <i class="bi bi-cloud-upload" style="color:var(--accent-cyan)"></i> Upload Data CSV
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">
                            Upload file CSV data sensor. <strong>Tag otomatis terdeteksi</strong> dari nama file.
                        </p>
                        <div style="background:rgba(0,217,255,.05);border:1px solid rgba(0,217,255,.2);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px">
                            <strong style="color:var(--accent-cyan)">Format nama file:</strong> <code>[tag_id]-[keterangan].csv</code><br>
                            Contoh: <code>85-idf2b-2026a.csv</code> → Tag #85 otomatis terdeteksi<br><br>
                            <strong style="color:var(--accent-cyan)">Format isi CSV:</strong><br>
                            <code>tag_id, timestamp, value</code>
                        </div>

                        <?php if (empty($addresses)): ?>
                        <div style="text-align:center;padding:30px;color:var(--text-secondary);font-size:13px">
                            <i class="bi bi-exclamation-triangle" style="display:block;font-size:28px;margin-bottom:8px;opacity:.5;color:#f59e0b"></i>
                            Upload Excel address terlebih dahulu (Langkah 1)<br>sebelum bisa upload data CSV.
                        </div>
                        <?php else: ?>

                        <div class="form-group">
                            <label>Mode Upload CSV</label>
                            <div style="display:flex;gap:20px">
                                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;text-transform:none;letter-spacing:normal;font-weight:normal;font-size:13px">
                                    <input type="radio" name="uploadMode" value="append" checked style="width:15px;height:15px"> Tambahkan (append)
                                </label>
                                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;text-transform:none;letter-spacing:normal;font-weight:normal;font-size:13px">
                                    <input type="radio" name="uploadMode" value="replace" style="width:15px;height:15px"> Ganti semua (replace)
                                </label>
                            </div>
                        </div>

                        <!-- Deteksi otomatis -->
                        <div id="autoDetectBox" style="display:none;background:rgba(0,217,255,.06);border:1px solid rgba(0,217,255,.3);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px">
                            <i class="bi bi-lightning-fill" style="color:var(--accent-cyan)"></i>
                            <strong>Tag terdeteksi: </strong>
                            <span id="autoDetectTag" style="color:var(--accent-cyan);font-weight:700"></span>
                            <span id="autoDetectDesc" style="color:var(--text-secondary)"></span>
                        </div>
                        <div id="autoDetectError" style="display:none;background:rgba(255,107,122,.08);border:1px solid rgba(255,107,122,.3);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:#ff6b7a">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span id="autoDetectErrMsg"></span>
                        </div>

                        <div class="form-group">
                            <label>File CSV</label>
                            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFileInput').click()">
                                <i class="bi bi-file-earmark-spreadsheet" id="uploadIcon" style="font-size:36px;color:var(--accent-cyan);display:block;margin-bottom:8px"></i>
                                <div id="uploadZoneText" style="font-size:13px;color:var(--text-secondary)">
                                    Klik atau drag & drop file CSV<br>
                                    <small style="opacity:.6">Format nama: [tag_id]-[nama]-[tahun].csv</small>
                                </div>
                            </div>
                            <input type="file" id="csvFileInput" accept=".csv" style="display:none" onchange="onFileChosen(this)">
                            <div id="uploadProgressWrap"><div id="uploadProgress"></div></div>
                        </div>
                        <div id="msgUpload"></div>
                        <button onclick="submitUpload()" class="btn btn-primary" style="width:100%;margin-top:4px">
                            <i class="bi bi-upload" style="margin-right:6px"></i>Upload & Simpan Data
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════ TAB: KELOLA ADDRESS ══════════════════════════════ -->
            <div class="pm-panel" id="panel-edit">
                <div class="card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
                        <div>
                            <h3 style="margin:0">Daftar Address Terdaftar</h3>
                            <p style="font-size:12px;color:var(--text-secondary);margin:4px 0 0">
                                Database: <code><?= htmlspecialchars($db_name) ?></code> · <?= $total_tags ?> tag
                            </p>
                        </div>
                        <button onclick="switchTab('tambah')" class="btn btn-primary" style="padding:8px 14px;font-size:13px">
                            <i class="bi bi-file-earmark-excel"></i> Upload Excel Baru
                        </button>
                    </div>
                    <?php if (empty($addresses)): ?>
                    <p style="text-align:center;padding:40px;color:var(--text-secondary)">Belum ada address. Upload Excel terlebih dahulu.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="edit-table" id="editTable">
                            <thead>
                                <tr>
                                    <th>Tag ID</th>
                                    <th>Deskripsi</th>
                                    <th>Address No</th>
                                    <th>Satuan</th>
                                    <th>Data</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($addresses as $a): ?>
                                <tr id="erow-<?= $a['tag_id'] ?>">
                                    <td><strong style="color:var(--accent-cyan)">#<?= $a['tag_id'] ?></strong></td>
                                    <td style="max-width:220px"><?= htmlspecialchars($a['deskripsi']) ?></td>
                                    <td style="font-size:11px;color:var(--text-secondary);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($a['address_no']) ?>"><?= htmlspecialchars($a['address_no']) ?></td>
                                    <td><span class="satuan-badge"><?= htmlspecialchars($a['satuan']) ?></span></td>
                                    <td style="font-size:12px"><?= number_format($a['data_count']) ?></td>
                                    <td>
                                        <div style="display:flex;gap:5px">
                                            <button onclick='openEditAddr(<?= json_encode($a) ?>)' class="btn btn-sm btn-secondary" title="Edit address">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button onclick="openDeleteRange(<?= $a['tag_id'] ?>, '<?= htmlspecialchars($a['deskripsi'],ENT_QUOTES) ?>')" class="btn btn-sm btn-secondary" style="color:#f59e0b" title="Hapus range data">
                                                <i class="bi bi-calendar-x"></i>
                                            </button>
                                            <button onclick="deleteAddress(<?= $a['tag_id'] ?>, '<?= htmlspecialchars($a['deskripsi'],ENT_QUOTES) ?>')" class="btn btn-sm btn-secondary" style="color:#ff6b7a" title="Hapus address & semua data">
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
            
            <!-- ══════════════════════════════ TAB: KONEKSI MESIN ══════════════════════════════ -->
            <?php if ($role === 'superadmin'): ?>
            <div class="pm-panel" id="panel-mesin">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

                    <!-- Info Endpoint -->
                    <div class="card">
                        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <i class="bi bi-plug" style="color:var(--accent-cyan)"></i> Endpoint Ingest Data
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:20px">
                            Kirim data dari mesin/DCS/OPC-UA ke endpoint ini menggunakan HTTP POST
                        </p>

                        <div class="form-group">
                            <label style="font-size:11px">URL Endpoint</label>
                            <div style="background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;font-family:monospace;font-size:12px;word-break:break-all;color:var(--accent-cyan)">
                                POST <?= rtrim(BASE_URL,'?') ?>?api=pm-machine-ingest
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size:11px">Header yang diperlukan</label>
                            <div style="background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;font-family:monospace;font-size:12px;color:#5dd87e">
                                Content-Type: application/json<br>
                                X-API-Key: <em style="color:#f59e0b">[api_key dari bawah]</em>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size:11px">Contoh Body JSON</label>
                            <div style="background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;font-family:monospace;font-size:11px;white-space:pre;overflow-x:auto;color:#c9d1d9">{
  "data": [
    {
      "tag_id": 85,
      "timestamp": "2026-02-27 08:05:00",
      "value": 0.8210
    },
    {
      "tag_id": 85,
      "timestamp": "2026-02-27 08:10:00",
      "value": 0.8340
    }
  ]
}</div>
                        </div>

                        <div class="form-group">
                            <label style="font-size:11px">Contoh cURL</label>
                            <div style="background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;font-family:monospace;font-size:11px;white-space:pre-wrap;color:#c9d1d9">curl -X POST "<?= rtrim(BASE_URL,'?') ?>?api=pm-machine-ingest" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: API_KEY_DISINI" \
  -d '{"data":[{"tag_id":85,"timestamp":"2026-02-27 08:05:00","value":0.821}]}'</div>
                        </div>

                        <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:12px 16px;font-size:12px;color:#f59e0b">
                            <i class="bi bi-info-circle" style="margin-right:6px"></i>
                            Data yang masuk akan langsung menggantikan proyeksi bayangan di grafik sesuai tanggal/jam yang dikirim.
                        </div>
                    </div>

                    <!-- Kelola API Key -->
                    <div class="card">
                        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <i class="bi bi-key" style="color:var(--accent-cyan)"></i> Kelola API Key
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:20px">
                            API key untuk unit <strong><?= htmlspecialchars($unit_name) ?></strong>.
                            Simpan key ini dengan aman — hanya tampil sekali saat dibuat.
                        </p>

                        <div id="apiKeyList" style="margin-bottom:16px">
                            <div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:13px">
                                <i class="bi bi-arrow-repeat" style="animation:spin .8s linear infinite;display:inline-block"></i>
                                Memuat...
                            </div>
                        </div>

                        <button onclick="generateApiKey()" class="btn btn-primary" style="width:100%">
                            <i class="bi bi-plus-circle" style="margin-right:6px"></i>Generate API Key Baru
                        </button>
                        <div id="msgApiKey" style="margin-top:10px"></div>

                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-color)">
                            <h4 style="font-size:13px;margin-bottom:12px;color:var(--text-secondary)">Log Koneksi Terakhir</h4>
                            <div id="machineLogList">
                                <p style="font-size:12px;color:var(--text-secondary);text-align:center">Belum ada log</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status real-time -->
                <div class="card" style="margin-top:0">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <h3 style="margin:0;display:flex;align-items:center;gap:8px">
                            <i class="bi bi-activity" style="color:#5dd87e"></i> Status Data Hari Ini
                        </h3>
                        <button onclick="loadMachineStatus()" class="btn btn-secondary" style="padding:6px 12px;font-size:12px">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div id="machineStatusGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
                        <p style="color:var(--text-secondary);font-size:13px">Klik Refresh untuk melihat status</p>
                    </div>
                </div>
            </div>
            <?php endif; // superadmin ?>
<?php endif; // can_manage ?>

        </div>
    </div>
</div>

<!-- ═══ MODAL: Edit Address ═══ -->
<div class="pm-modal" id="modalEditAddr">
    <div class="pm-modal-box">
        <h3 style="margin-bottom:18px;font-size:16px">Edit Address</h3>
        <input type="hidden" id="editTagId">
        <div class="form-group">
            <label>Tag ID <small style="opacity:.5">(tidak bisa diubah)</small></label>
            <input type="text" id="editTagIdShow" class="form-control" readonly style="opacity:.5;cursor:not-allowed">
        </div>
        <div class="form-group">
            <label>Address No *</label>
            <input type="text" id="editAddrNo" class="form-control">
        </div>
        <div class="form-group">
            <label>Deskripsi *</label>
            <input type="text" id="editAddrDesk" class="form-control">
        </div>
        <div class="form-group">
            <label>Satuan</label>
            <input type="text" id="editAddrSat" class="form-control">
        </div>
        <div id="msgEditAddr" style="margin-bottom:10px"></div>
        <div style="display:flex;gap:10px">
            <button onclick="submitEditAddr()" class="btn btn-primary" style="flex:1">Simpan Perubahan</button>
            <button onclick="closeModal('modalEditAddr')" class="btn btn-secondary" style="padding:12px 18px">Batal</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL: Hapus Range Data ═══ -->
<div class="pm-modal" id="modalDeleteRange">
    <div class="pm-modal-box" style="max-width:420px">
        <h3 style="margin-bottom:6px;font-size:16px">Hapus Data Berdasarkan Rentang</h3>
        <p id="deleteRangeDesc" style="font-size:12px;color:var(--text-secondary);margin-bottom:20px"></p>
        <input type="hidden" id="deleteRangeTagId">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group" style="margin:0">
                <label>Dari Tanggal *</label>
                <input type="date" id="delFrom" class="form-control">
            </div>
            <div class="form-group" style="margin:0">
                <label>Sampai Tanggal *</label>
                <input type="date" id="delTo" class="form-control">
            </div>
        </div>
        <div id="msgDeleteRange" style="margin:12px 0"></div>
        <div style="display:flex;gap:10px;margin-top:4px">
            <button onclick="submitDeleteRange()" class="btn btn-primary" style="flex:1;background:#ef4444;border-color:#ef4444">
                <i class="bi bi-trash3" style="margin-right:6px"></i>Hapus Data
            </button>
            <button onclick="closeModal('modalDeleteRange')" class="btn btn-secondary" style="padding:12px 18px">Batal</button>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const UNIT_ID    = <?= $unit_id ?>;
const PLANT_ID   = <?= $plant_id ?>;
const PLANT_NAME = '<?= addslashes($plant_name) ?>';
const UNIT_NAME  = '<?= addslashes($unit_name) ?>';
// Daftar tag yang terdaftar (untuk deteksi otomatis)
const TAGS       = <?= json_encode(array_column($addresses, null, 'tag_id')) ?>;

let pmChart      = null;
let currentTag   = null;
let currentData  = { labels:[], values:[] };
let selectedFile = null;
let selectedExcel= null;

// ── Tab ──────────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.pm-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-'+tab)?.classList.add('active');
    document.getElementById('tabBtn-'+tab)?.classList.add('active');
}

// ── Chart ─────────────────────────────────────────────────────
function selectTag(tagId, desk, satuan, addrNo, el) {
    document.querySelectorAll('.tag-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    currentTag = { id:tagId, desk, satuan, addrNo };
    document.getElementById('chartPanel').style.display = 'block';
    document.getElementById('chartTitle').textContent = desk;
    document.getElementById('chartSub').textContent   = `Tag #${tagId} · ${addrNo} · Satuan: ${satuan}`;
    const now = new Date();
    const y = now.getFullYear(), m = String(now.getMonth()+1).padStart(2,'0'), d = String(now.getDate()).padStart(2,'0');
    const fromVal = `${y}-${m}-01`;
    const toVal   = `${y}-${m}-${d}`;
    document.getElementById('dateFrom').value = fromVal;
    document.getElementById('dateTo').value   = toVal;
    // Sync flatpickr jika sudah ada
    if (typeof fpFrom !== 'undefined') { fpFrom.setDate(fromVal, false); document.getElementById('dateFromLabel').textContent = formatTanggal(fromVal); }
    if (typeof fpTo   !== 'undefined') { fpTo.setDate(toVal,   false); document.getElementById('dateToLabel').textContent   = formatTanggal(toVal); }
    loadChart();
    document.getElementById('chartPanel').scrollIntoView({ behavior:'smooth', block:'start' });
}

function loadChart() {
    if (!currentTag) return;
    const from = document.getElementById('dateFrom').value;
    const to   = document.getElementById('dateTo').value;
    if (!from || !to) { alert('Pilih rentang tanggal'); return; }
    setChartState('loading');
    fetch(`${BASE_URL}?api=pm-chart-data&tag_id=${currentTag.id}&from=${from}&to=${to}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || (!data.values.length && !data.ghost_values?.length)) {
                setChartState('empty'); clearStats(); return;
            }
            currentData = { labels: data.labels, values: data.values };
            renderStats(data.values, currentTag.satuan, data.total);
            renderChart(data.labels, data.values, data.ghost_labels || [], data.ghost_values || [], data.last_real_date);

            // Keterangan bawah grafik
            let noteText = '';
            if (data.aggregated) noteText += `⚡ Data diringkas per jam (${data.total.toLocaleString()} titik asli)`;
            if (data.need_forecast && data.last_real_date) {
                noteText += (noteText ? '  ·  ' : '') +
                    `👻 Proyeksi bayangan mulai ${formatDateNote(data.last_real_date)} (belum ada data baru)`;
            }
            document.getElementById('aggNote').textContent = noteText;
            setChartState('chart');
        })
        .catch(() => setChartState('empty'));
}

function formatDateNote(d) {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}

function setChartState(state) {
    document.getElementById('chartLoading').style.display = state === 'loading' ? 'flex' : 'none';
    document.getElementById('chartEmpty').style.display   = state === 'empty'   ? 'flex' : 'none';
    document.getElementById('pmChart').style.display      = state === 'chart'   ? 'block' : 'none';
}
function clearStats() { document.getElementById('statPills').innerHTML = ''; }

function renderStats(values, satuan, total) {
    const min=Math.min(...values), max=Math.max(...values);
    const avg=values.reduce((a,b)=>a+b,0)/values.length;
    const last=values[values.length-1];
    document.getElementById('statPills').innerHTML = `
        <span class="stat-pill"><i class="bi bi-arrow-down-circle"></i> Min: <b>${min.toFixed(4)}</b> ${satuan}</span>
        <span class="stat-pill"><i class="bi bi-arrow-up-circle"></i> Max: <b>${max.toFixed(4)}</b> ${satuan}</span>
        <span class="stat-pill"><i class="bi bi-dash-circle"></i> Rata-rata: <b>${avg.toFixed(4)}</b> ${satuan}</span>
        <span class="stat-pill"><i class="bi bi-clock-history"></i> Terakhir: <b>${last.toFixed(4)}</b> ${satuan}</span>
        <span class="stat-pill"><i class="bi bi-database"></i> <b>${values.length.toLocaleString()}</b> titik</span>
    `;
}

function renderChart(labels, values, ghostLabels, ghostValues, lastRealDate) {
    if (pmChart) pmChart.destroy();
    const isDark = !document.body.classList.contains('light-theme');
    const grid   = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.06)';
    const tick   = isDark ? '#8892a4' : '#6b7280';
    const ctx = document.getElementById('pmChart').getContext('2d');

    // Gabungkan semua label untuk sumbu X
    const allLabels = [...labels, ...ghostLabels];

    // Dataset data real
    const realData = labels.map((t, i) => ({ x: t, y: values[i] }));

    // Dataset ghost: null di area real, mulai dari titik terakhir real
    let ghostData = [];
    if (ghostLabels.length > 0) {
        // Titik sambung: ambil nilai terakhir data real sebagai awal ghost
        const joinVal = values.length > 0 ? values[values.length - 1] : null;
        const joinTs  = labels.length > 0 ? labels[labels.length - 1] : null;

        // Ghost dataset mulai dari titik terakhir real
        if (joinTs && joinVal !== null) {
            ghostData.push({ x: joinTs, y: joinVal }); // sambung tanpa gap
        }
        ghostLabels.forEach((t, i) => ghostData.push({ x: t, y: ghostValues[i] }));
    }

    const datasets = [
        {
            label: `${currentTag.desk} (${currentTag.satuan})`,
            data: realData,
            borderColor: '#00d9ff',
            backgroundColor: 'rgba(0,217,255,.07)',
            borderWidth: 1.8,
            pointRadius: realData.length > 400 ? 0 : 2.5,
            pointHoverRadius: 5,
            fill: true,
            tension: 0.15,
            order: 1,
        }
    ];

    if (ghostData.length > 0) {
        datasets.push({
            label: 'Proyeksi (bayangan historis)',
            data: ghostData,
            borderColor: 'rgba(0,217,255,0.25)',
            backgroundColor: 'rgba(0,217,255,0.03)',
            borderWidth: 1.5,
            borderDash: [6, 4],
            pointRadius: 0,
            pointHoverRadius: 3,
            fill: true,
            tension: 0.3,
            order: 2,
        });
    }

    pmChart = new Chart(ctx, {
        type: 'line',
        data: { datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode:'index', intersect:false },
            plugins: {
                legend: {
                    display: ghostData.length > 0,
                    position: 'top',
                    align: 'end',
                    labels: {
                        color: tick,
                        font: { size: 11 },
                        boxWidth: 20,
                        padding: 12,
                        usePointStyle: true,
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const isGhost = ctx.dataset.label.includes('Proyeksi');
                            const val = ctx.parsed.y?.toFixed(4);
                            return isGhost
                                ? ` 👻 ${val} ${currentTag.satuan} (proyeksi)`
                                : ` ${val} ${currentTag.satuan}`;
                        }
                    }
                },
                annotation: lastRealDate ? {
                    annotations: {
                        cutoffLine: {
                            type: 'line',
                            xMin: lastRealDate + ' 23:59:59',
                            xMax: lastRealDate + ' 23:59:59',
                            borderColor: 'rgba(255,180,50,0.5)',
                            borderWidth: 1.5,
                            borderDash: [5, 3],
                            label: {
                                content: 'Data terakhir',
                                display: true,
                                position: 'start',
                                color: 'rgba(255,180,50,0.8)',
                                font: { size: 10 },
                                backgroundColor: 'transparent',
                                padding: 4
                            }
                        }
                    }
                } : {}
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        tooltipFormat: 'dd/MM/yyyy HH:mm',
                        displayFormats: { minute:'HH:mm', hour:'dd/MM HH:mm', day:'dd/MM/yy', month:'MMM yy' }
                    },
                    ticks: { color:tick, maxTicksLimit:10, font:{size:11} },
                    grid: { color:grid }
                },
                y: {
                    ticks: { color:tick, font:{size:11}, callback: v => `${v} ${currentTag.satuan}` },
                    grid: { color:grid }
                }
            }
        }
    });
}

function exportCSV() {
    if (!currentData.labels.length) return;
    const from = document.getElementById('dateFrom').value;
    const to   = document.getElementById('dateTo').value;
    const now  = new Date().toLocaleString('id-ID');
    // Header keterangan
    let csv = `# Parameter Monitoring - PLN\n`;
    csv    += `# Plant,${PLANT_NAME}\n`;
    csv    += `# Unit,${UNIT_NAME}\n`;
    csv    += `# Tag ID,${currentTag.id}\n`;
    csv    += `# Parameter,${currentTag.desk}\n`;
    csv    += `# Address,${currentTag.addrNo}\n`;
    csv    += `# Satuan,${currentTag.satuan}\n`;
    csv    += `# Periode,${from} s/d ${to}\n`;
    csv    += `# Jumlah Data,${currentData.labels.length} titik\n`;
    csv    += `# Diekspor,${now}\n`;
    csv    += `#\n`;
    csv    += `tag_id,timestamp,value,satuan\n`;
    currentData.labels.forEach((t,i) => {
        csv += `${currentTag.id},${t},${currentData.values[i]},${currentTag.satuan}\n`;
    });
    const a = document.createElement('a');
    a.href     = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = `tag_${currentTag.id}_${(currentTag.desk||'').replace(/\s+/g,'_')}_${from}_${to}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function exportPNG() {
    if (!pmChart) return;
    const srcCanvas = document.getElementById('pmChart');
    const from = document.getElementById('dateFrom').value;
    const to   = document.getElementById('dateTo').value;
    const now  = new Date().toLocaleString('id-ID');

    // Tinggi banner info di atas grafik
    const bannerH = 110;
    const tmp = document.createElement('canvas');
    tmp.width  = srcCanvas.width;
    tmp.height = srcCanvas.height + bannerH;
    const ctx = tmp.getContext('2d');

    // Background
    ctx.fillStyle = '#0d1b2e';
    ctx.fillRect(0, 0, tmp.width, tmp.height);

    // Banner area
    ctx.fillStyle = '#111f33';
    ctx.fillRect(0, 0, tmp.width, bannerH);
    // Garis bawah banner
    ctx.strokeStyle = 'rgba(0,217,255,0.3)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(0, bannerH); ctx.lineTo(tmp.width, bannerH); ctx.stroke();

    // Teks banner
    ctx.fillStyle = '#00d9ff';
    ctx.font = 'bold 14px sans-serif';
    ctx.fillText(`${currentTag.desk}`, 20, 26);

    ctx.fillStyle = '#8892a4';
    ctx.font = '11px sans-serif';
    ctx.fillText(`Tag #${currentTag.id}  ·  ${currentTag.addrNo}  ·  Satuan: ${currentTag.satuan}`, 20, 46);

    ctx.fillStyle = '#c8d6e5';
    ctx.font = '11px sans-serif';
    ctx.fillText(`Plant: ${PLANT_NAME}   Unit: ${UNIT_NAME}`, 20, 64);
    ctx.fillText(`Periode: ${from} s/d ${to}   ·   ${currentData.labels.length} titik data`, 20, 80);

    ctx.fillStyle = '#4a5a6a';
    ctx.font = '10px sans-serif';
    ctx.fillText(`Diekspor: ${now}`, 20, 98);

    // Gambar chart di bawah banner
    ctx.drawImage(srcCanvas, 0, bannerH);

    const url = tmp.toDataURL('image/png');
    if (!url || url === 'data:,') { alert('Gagal mengambil gambar grafik'); return; }
    const a = document.createElement('a');
    a.href     = url;
    a.download = `tag_${currentTag.id}_${(currentTag.desk||'').replace(/\s+/g,'_')}_${from}_${to}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ── Flatpickr Date Pickers ────────────────────────────────────
function formatTanggal(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}

const fpFrom = flatpickr('#dateFromBtn', {
    locale: 'id',
    dateFormat: 'Y-m-d',
    disableMobile: true,
    onChange: (sel, str) => {
        document.getElementById('dateFrom').value = str;
        document.getElementById('dateFromLabel').textContent = formatTanggal(str) || 'Dari Tanggal';
        if (str && fpTo.selectedDates[0] && new Date(str) > fpTo.selectedDates[0]) {
            fpTo.setDate(str);
        }
    }
});

const fpTo = flatpickr('#dateToBtn', {
    locale: 'id',
    dateFormat: 'Y-m-d',
    disableMobile: true,
    onChange: (sel, str) => {
        document.getElementById('dateTo').value = str;
        document.getElementById('dateToLabel').textContent = formatTanggal(str) || 'Sampai Tanggal';
    }
});

function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
// Tutup dropdown jika klik di luar
document.addEventListener('click', e => {
    const wrap = document.getElementById('exportDropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('exportMenu').style.display = 'none';
    }
});

// ── Upload Excel ──────────────────────────────────────────────
const excelZone = document.getElementById('excelZone');
if (excelZone) {
    excelZone.addEventListener('dragover', e => { e.preventDefault(); excelZone.classList.add('drag'); });
    excelZone.addEventListener('dragleave', () => excelZone.classList.remove('drag'));
    excelZone.addEventListener('drop', e => {
        e.preventDefault(); excelZone.classList.remove('drag');
        const f = e.dataTransfer.files[0];
        if (f && (f.name.endsWith('.xlsx') || f.name.endsWith('.xls'))) { selectedExcel = f; updateExcelText(f); }
        else alert('Hanya file .xlsx atau .xls');
    });
}

function onExcelChosen(input) {
    if (input.files[0]) { selectedExcel = input.files[0]; updateExcelText(selectedExcel); }
}
function updateExcelText(f) {
    document.getElementById('excelZoneText').innerHTML =
        `<strong style="color:#1d7a44">${f.name}</strong><br><small style="opacity:.6">${(f.size/1024).toFixed(1)} KB</small>`;
}

function submitExcel() {
    const msg = document.getElementById('msgExcel');
    if (!selectedExcel) { alert('Pilih file Excel terlebih dahulu'); return; }

    const mode = document.querySelector('input[name="excelMode"]:checked').value;
    const fd = new FormData();
    fd.append('excel_file', selectedExcel);
    fd.append('mode', mode);

    msg.innerHTML = '<p style="color:var(--accent-cyan);font-size:13px"><i class="bi bi-arrow-repeat"></i> Memproses Excel...</p>';
    document.getElementById('excelProgressWrap').style.display = 'block';
    document.getElementById('excelProgress').style.width = '60%';

    fetch(`${BASE_URL}?api=pm-upload-excel`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            document.getElementById('excelProgress').style.width = '100%';
            if (res.success) {
                msg.innerHTML = `<div class="alert alert-success" style="padding:10px;margin-top:8px">
                    ✅ ${res.message}<br>
                    <small>Sekarang Anda bisa upload data CSV di panel kanan.</small>
                </div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                msg.innerHTML = `<div class="alert alert-error" style="padding:10px;margin-top:8px">❌ ${res.message}</div>`;
            }
            setTimeout(() => {
                document.getElementById('excelProgress').style.width = '0%';
                document.getElementById('excelProgressWrap').style.display = 'none';
            }, 2000);
        })
        .catch(err => {
            msg.innerHTML = `<div class="alert alert-error" style="padding:10px;margin-top:8px">❌ Error: ${err.message}</div>`;
        });
}

// ── Upload CSV ─────────────────────────────────────────────────
const zone = document.getElementById('uploadZone');
if (zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('drag');
        const f = e.dataTransfer.files[0];
        if (f?.name.endsWith('.csv')) { selectedFile = f; updateZoneText(f); detectTagFromFile(f.name); }
        else alert('Hanya file .csv');
    });
}

function onFileChosen(input) {
    if (input.files[0]) {
        selectedFile = input.files[0];
        updateZoneText(selectedFile);
        detectTagFromFile(selectedFile.name);
    }
}

function updateZoneText(f) {
    document.getElementById('uploadZoneText').innerHTML =
        `<strong style="color:var(--accent-cyan)">${f.name}</strong><br><small style="opacity:.6">${(f.size/1024).toFixed(1)} KB</small>`;
}

// Otomatis deteksi tag dari nama file: [tag_id]-nama.csv
function detectTagFromFile(filename) {
    const autoBox  = document.getElementById('autoDetectBox');
    const errBox   = document.getElementById('autoDetectError');
    const tagSpan  = document.getElementById('autoDetectTag');
    const descSpan = document.getElementById('autoDetectDesc');
    const errMsg   = document.getElementById('autoDetectErrMsg');

    autoBox.style.display = 'none';
    errBox.style.display  = 'none';

    const match = filename.match(/^(\d+)[-_]/);
    if (!match) {
        errBox.style.display = 'block';
        errMsg.textContent = `Nama file "${filename}" tidak mengandung tag_id di awal. Format: [tag_id]-nama.csv`;
        return;
    }

    const tagId = parseInt(match[1]);
    const tagInfo = TAGS[tagId];

    if (!tagInfo) {
        errBox.style.display = 'block';
        errMsg.textContent = `Tag ID #${tagId} tidak terdaftar di unit ini. Upload Excel address terlebih dahulu.`;
        return;
    }

    autoBox.style.display = 'block';
    tagSpan.textContent   = `#${tagId} — ${tagInfo.deskripsi}`;
    descSpan.textContent  = ` (${tagInfo.satuan})`;
}

function submitUpload() {
    const mode  = document.querySelector('input[name="uploadMode"]:checked')?.value ?? 'append';
    const msg   = document.getElementById('msgUpload');

    if (!selectedFile) { alert('Pilih file CSV'); return; }

    // Deteksi tag dari nama file
    const match = selectedFile.name.match(/^(\d+)[-_]/);
    if (!match) {
        msg.innerHTML = '<div class="alert alert-error" style="padding:10px;margin-top:8px">❌ Nama file harus dimulai dengan tag_id. Contoh: 85-idf2b.csv</div>';
        return;
    }

    const fd = new FormData();
    fd.append('mode', mode);
    fd.append('csv_file', selectedFile);
    // tag_id juga dikirim (server akan auto-detect juga dari nama file, ini double check)
    fd.append('tag_id', match[1]);

    msg.innerHTML = '<p style="color:var(--accent-cyan);font-size:13px">Mengupload & memproses...</p>';
    document.getElementById('uploadProgressWrap').style.display = 'block';
    document.getElementById('uploadProgress').style.width = '40%';

    fetch(`${BASE_URL}?api=pm-upload`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            document.getElementById('uploadProgress').style.width = '100%';
            if (res.success) {
                msg.innerHTML = `<div class="alert alert-success" style="padding:10px;margin-top:8px">✅ ${res.message}</div>`;
                selectedFile = null;
                document.getElementById('uploadZoneText').innerHTML = 'Klik atau drag & drop file CSV';
                document.getElementById('csvFileInput').value = '';
                document.getElementById('autoDetectBox').style.display = 'none';
            } else {
                msg.innerHTML = `<div class="alert alert-error" style="padding:10px;margin-top:8px">❌ ${res.message}</div>`;
            }
            setTimeout(() => {
                document.getElementById('uploadProgress').style.width = '0%';
                document.getElementById('uploadProgressWrap').style.display = 'none';
            }, 1500);
        });
}

// ── Edit Address ──────────────────────────────────────────────
function openEditAddr(addr) {
    document.getElementById('editTagId').value    = addr.tag_id;
    document.getElementById('editTagIdShow').value = `#${addr.tag_id}`;
    document.getElementById('editAddrNo').value    = addr.address_no;
    document.getElementById('editAddrDesk').value  = addr.deskripsi;
    document.getElementById('editAddrSat').value   = addr.satuan;
    document.getElementById('msgEditAddr').innerHTML = '';
    openModal('modalEditAddr');
}
function submitEditAddr() {
    const tag = document.getElementById('editTagId').value;
    const body = new URLSearchParams({
        tag_id:     tag,
        address_no: document.getElementById('editAddrNo').value,
        deskripsi:  document.getElementById('editAddrDesk').value,
        satuan:     document.getElementById('editAddrSat').value,
    });
    fetch(`${BASE_URL}?api=pm-edit-address`, { method:'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else document.getElementById('msgEditAddr').innerHTML = `<p style="color:#ff6b7a;font-size:13px">${res.message}</p>`;
        });
}

// ── Delete Range ──────────────────────────────────────────────
function openDeleteRange(tagId, desc) {
    document.getElementById('deleteRangeTagId').value    = tagId;
    document.getElementById('deleteRangeDesc').textContent = `Tag #${tagId}: ${desc}`;
    document.getElementById('msgDeleteRange').innerHTML = '';
    openModal('modalDeleteRange');
}
function submitDeleteRange() {
    const body = new URLSearchParams({
        tag_id:    document.getElementById('deleteRangeTagId').value,
        date_from: document.getElementById('delFrom').value,
        date_to:   document.getElementById('delTo').value,
    });
    if (!document.getElementById('delFrom').value || !document.getElementById('delTo').value) {
        document.getElementById('msgDeleteRange').innerHTML = '<p style="color:#ff6b7a;font-size:13px">Pilih rentang tanggal!</p>';
        return;
    }
    fetch(`${BASE_URL}?api=pm-delete-data`, { method:'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('msgDeleteRange').innerHTML = `<p style="color:#5dd87e;font-size:13px">✅ ${res.deleted} data berhasil dihapus</p>`;
                setTimeout(() => { closeModal('modalDeleteRange'); location.reload(); }, 1500);
            } else {
                document.getElementById('msgDeleteRange').innerHTML = `<p style="color:#ff6b7a;font-size:13px">${res.message}</p>`;
            }
        });
}

// ── Delete Address ────────────────────────────────────────────
function deleteAddress(tagId, desc) {
    if (!confirm(`Hapus address "${desc}" beserta SEMUA datanya?\n\nIni tidak bisa dibatalkan!`)) return;
    const body = new URLSearchParams({ tag_id: tagId });
    fetch(`${BASE_URL}?api=pm-delete-address`, { method:'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.success) document.getElementById(`erow-${tagId}`)?.remove();
            else alert('Gagal menghapus');
        });
}

// ── Search & Sort Tags ────────────────────────────────────────
function filterAndSortTags() {
    const query  = (document.getElementById('tagSearchInput')?.value || '').toLowerCase().trim();
    const sort   = document.getElementById('tagSortSelect')?.value || 'tag_asc';
    const grid   = document.getElementById('tagGrid');
    const cards  = Array.from(grid.querySelectorAll('.tag-card'));
    const resetBtn = document.getElementById('tagResetBtn');

    // Show/hide reset button
    if (resetBtn) resetBtn.style.display = (query || sort !== 'tag_asc') ? 'inline-flex' : 'none';

    // Filter
    let visible = [];
    cards.forEach(card => {
        const name    = card.dataset.name    || '';
        const address = card.dataset.address || '';
        const tagId   = card.dataset.tagId   || '';
        const matches = !query ||
            name.includes(query) ||
            address.includes(query) ||
            tagId.includes(query);
        card.style.display = matches ? '' : 'none';
        if (matches) visible.push(card);
    });

    // Sort visible cards
    visible.sort((a, b) => {
        switch (sort) {
            case 'tag_asc':   return parseInt(a.dataset.tagId)  - parseInt(b.dataset.tagId);
            case 'tag_desc':  return parseInt(b.dataset.tagId)  - parseInt(a.dataset.tagId);
            case 'name_asc':  return a.dataset.name.localeCompare(b.dataset.name);
            case 'name_desc': return b.dataset.name.localeCompare(a.dataset.name);
            case 'data_asc':  return parseInt(a.dataset.count)  - parseInt(b.dataset.count);
            case 'data_desc': return parseInt(b.dataset.count)  - parseInt(a.dataset.count);
            default: return 0;
        }
    });

    // Re-append in sorted order (hidden ones stay hidden)
    visible.forEach(card => grid.appendChild(card));
    // Also move hidden cards to end (consistent DOM order)
    cards.filter(c => c.style.display === 'none').forEach(card => grid.appendChild(card));

    // Update count and no-result message
    const countEl = document.getElementById('tagVisibleCount');
    if (countEl) countEl.textContent = visible.length;

    const noResult = document.getElementById('tagNoResult');
    if (noResult) noResult.style.display = visible.length === 0 ? 'block' : 'none';
}

function resetTagFilter() {
    const input = document.getElementById('tagSearchInput');
    const sel   = document.getElementById('tagSortSelect');
    if (input) input.value = '';
    if (sel)   sel.value = 'tag_asc';
    filterAndSortTags();
}

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.pm-modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Machine API ───────────────────────────────────────────────
function loadMachineStatus() {
    fetch(`${BASE_URL}?api=pm-machine-status`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;

            // API key list
            const keyDiv = document.getElementById('apiKeyList');
            if (keyDiv) {
                if (!res.has_api_key) {
                    keyDiv.innerHTML = '<p style="font-size:13px;color:var(--text-secondary);text-align:center">Belum ada API key aktif</p>';
                } else {
                    keyDiv.innerHTML = `
                        <div style="background:var(--bg-primary);border:1px solid rgba(93,216,126,.3);border-radius:8px;padding:12px 14px;font-size:12px">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                <span style="color:#5dd87e;font-weight:600"><i class="bi bi-check-circle-fill"></i> API Key Aktif</span>
                                <button onclick="revokeApiKey(${res.api_key_id})" style="background:none;border:none;color:#ff6b7a;cursor:pointer;font-size:12px">
                                    <i class="bi bi-x-circle"></i> Cabut
                                </button>
                            </div>
                            <div style="color:var(--text-secondary)">Key ID: <strong>#${res.api_key_id}</strong></div>
                            <div style="color:var(--text-secondary);font-size:11px;margin-top:4px">Key tersimpan aman di database. Generate baru untuk melihat nilainya.</div>
                        </div>`;
                }
            }

            // Logs
            const logDiv = document.getElementById('machineLogList');
            if (logDiv && res.recent_logs?.length) {
                logDiv.innerHTML = res.recent_logs.map(l => `
                    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border-color);font-size:12px">
                        <span style="color:var(--text-secondary)">${l.created_at}</span>
                        <span style="color:#5dd87e">+${l.inserted}</span>
                        <span style="color:var(--text-secondary)">${l.ip}</span>
                    </div>`).join('');
            }

            // Status grid
            const grid = document.getElementById('machineStatusGrid');
            if (grid) {
                if (!res.today_stats?.length) {
                    grid.innerHTML = '<p style="color:var(--text-secondary);font-size:13px">Tidak ada data masuk hari ini</p>';
                } else {
                    grid.innerHTML = res.today_stats.map(s => `
                        <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;padding:12px 14px">
                            <div style="font-size:11px;color:var(--text-secondary);margin-bottom:4px">Tag #${s.tag_id}</div>
                            <div style="font-size:18px;font-weight:700;color:#5dd87e">${parseInt(s.total_today).toLocaleString()}</div>
                            <div style="font-size:10px;color:var(--text-secondary)">data hari ini</div>
                            <div style="font-size:10px;color:var(--accent-cyan);margin-top:4px">
                                <i class="bi bi-clock"></i> ${s.last_ts}
                            </div>
                        </div>`).join('');
                }
            }
        });
}

function generateApiKey() {
    if (!confirm('Generate API key baru untuk unit ini?\n\nJika ada key aktif sebelumnya, key lama akan dinonaktifkan.')) return;

    const msg = document.getElementById('msgApiKey');
    msg.innerHTML = '<p style="font-size:13px;color:var(--accent-cyan)">Generating...</p>';

    fetch(`${BASE_URL}?api=pm-generate-api-key`, { method:'POST', body: new URLSearchParams({ unit_id: UNIT_ID, plant_id: PLANT_ID }) })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.innerHTML = `
                    <div style="background:rgba(93,216,126,.1);border:1px solid rgba(93,216,126,.3);border-radius:10px;padding:14px 16px">
                        <div style="font-size:13px;font-weight:600;color:#5dd87e;margin-bottom:8px">✅ API Key Baru:</div>
                        <div style="font-family:monospace;font-size:13px;background:var(--bg-primary);padding:10px;border-radius:6px;word-break:break-all;color:#f59e0b">${res.api_key}</div>
                        <div style="font-size:11px;color:#ff6b7a;margin-top:8px">⚠️ Salin key ini sekarang! Tidak bisa dilihat lagi setelah halaman di-refresh.</div>
                    </div>`;
                loadMachineStatus();
            } else {
                msg.innerHTML = `<p style="color:#ff6b7a;font-size:13px">${res.message}</p>`;
            }
        });
}

function revokeApiKey(keyId) {
    if (!confirm('Cabut API key ini? Koneksi mesin akan terputus.')) return;
    fetch(`${BASE_URL}?api=pm-revoke-api-key`, { method:'POST', body: new URLSearchParams({ key_id: keyId }) })
        .then(r => r.json())
        .then(res => {
            if (res.success) { loadMachineStatus(); document.getElementById('msgApiKey').innerHTML = '<p style="color:#5dd87e;font-size:13px">API key berhasil dicabut</p>'; }
        });
}

// Auto load machine status ketika tab mesin dibuka
const origSwitchTab = switchTab;
window.switchTab = function(tab) {
    origSwitchTab(tab);
    if (tab === 'mesin') loadMachineStatus();
};
</script>
</body>
</html>
