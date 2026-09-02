#!/usr/bin/env python3
"""
Script untuk menerapkan patch ke bearing-anomali.php
Jalankan di server: python3 apply_patch.py /path/to/bearing-anomali.php
"""
import sys, re, os

if len(sys.argv) < 2:
    print("Usage: python3 apply_patch.py /path/to/bearing-anomali.php")
    sys.exit(1)

php_path = sys.argv[1]
if not os.path.exists(php_path):
    print(f"File tidak ditemukan: {php_path}")
    sys.exit(1)

with open(php_path, 'r', encoding='utf-8') as f:
    content = f.read()

backup_path = php_path + '.bak_' + __import__('datetime').datetime.now().strftime('%Y%m%d_%H%M%S')
with open(backup_path, 'w', encoding='utf-8') as f:
    f.write(content)
print(f"Backup disimpan: {backup_path}")

# ── Patch 1: Inject window._csvModelY1/_csvModelY2 setelah var DREF ─────────
inject_after = 'var DREF=<?= (!empty($ref_data[\'dates\'])) ? json_encode([\'dates\'=>$ref_data[\'dates\'],\'values\'=>$ref_data[\'values\']]) : \'null\' ?>;'
inject_code = "\nwindow._csvModelY1 = <?php echo json_encode($model_b1 ?? null); ?>;\nwindow._csvModelY2 = <?php echo json_encode($model_b2 ?? null); ?>;"

if inject_after in content and 'window._csvModelY1' not in content:
    content = content.replace(inject_after, inject_after + inject_code)
    print("✅ Patch 1: Inject model Y1/Y2 berhasil")
else:
    print("⚠️  Patch 1: Sudah ada atau titik inject tidak ditemukan")

# ── Patch 2: Fix var(--card-bg) → var(--bg-card) & var(--border) → var(--border-color) di chart area CSV ─
# Hanya di dalam panel-csv_compare
panel_start = '<div class="ba-panel" id="panel-csv_compare">'
panel_end_marker = '<!-- PANEL MODELS -->'
if panel_start in content and panel_end_marker in content:
    idx_s = content.index(panel_start)
    idx_e = content.index(panel_end_marker)
    panel_section = content[idx_s:idx_e]
    panel_fixed = panel_section.replace('var(--card-bg)', 'var(--bg-card)').replace(
        'var(--border)', 'var(--border-color)')
    # Tambah id pada wrapper chart utama jika belum ada
    panel_fixed = panel_fixed.replace(
        '<canvas id="chart-csv-compare-main"></canvas>',
        '<canvas id="chart-csv-compare-main" style="display:block;width:100%"></canvas>'
    )
    content = content[:idx_s] + panel_fixed + content[idx_e:]
    print("✅ Patch 2: Fix CSS vars & canvas style di panel CSV compare")
else:
    print("⚠️  Patch 2: Panel csv_compare tidak ditemukan")

# ── Patch 3: Ganti fungsi _buildCsvChart ────────────────────────────────────
patch_js_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'bearing_csv_chart_patch.js')
if not os.path.exists(patch_js_path):
    print(f"⚠️  Patch 3: File patch JS tidak ditemukan: {patch_js_path}")
else:
    with open(patch_js_path, 'r', encoding='utf-8') as f:
        new_func = f.read().strip()

    # Cari awal fungsi _buildCsvChart
    func_marker = 'function _buildCsvChart(allData) {'
    if func_marker not in content:
        print("⚠️  Patch 3: Fungsi _buildCsvChart tidak ditemukan")
    else:
        start_idx = content.index(func_marker)
        # Cari penutup fungsi dengan bracket counting
        depth = 0
        i = start_idx
        in_str = False
        str_char = ''
        while i < len(content):
            ch = content[i]
            if in_str:
                if ch == str_char and content[i-1] != '\\':
                    in_str = False
            elif ch in ('"', "'", '`'):
                in_str = True; str_char = ch
            elif ch == '{':
                depth += 1
            elif ch == '}':
                depth -= 1
                if depth == 0:
                    end_idx = i + 1
                    break
            i += 1
        
        content = content[:start_idx] + new_func + '\n' + content[end_idx:]
        print(f"✅ Patch 3: Fungsi _buildCsvChart diganti ({end_idx - start_idx} chars → {len(new_func)} chars)")

# ── Simpan hasil ─────────────────────────────────────────────────────────────
with open(php_path, 'w', encoding='utf-8') as f:
    f.write(content)
print(f"\n✅ Patch selesai! File disimpan: {php_path}")
print(f"Backup ada di: {backup_path}")
