/**
 * ============================================================
 *  bearing_autoload_patch.js
 *  Auto-trigger analisis saat halaman bearing dibuka
 *
 *  CARA PASANG:
 *  Tambahkan tag berikut di bagian BAWAH file view bearing kamu
 *  (sebelum </body>), SETELAH script utama bearing dimuat:
 *
 *  <script src="/pln_web/public/js/bearing_autoload_patch.js"></script>
 *  ============================================================
 */

(function () {
    'use strict';

    // ── Tunggu halaman siap ──────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Coba langsung, atau tunggu sebentar biar semua JS halaman selesai load
        setTimeout(autoLoad, 500);
    }

    /**
     * Strategi auto-load:
     * 1. Cek apakah ada model tersimpan di DB (endpoint /models)
     *    → Kalau ada: pakai model terbaru → jalankan /predict otomatis
     *    → Kalau tidak ada: klik tombol Analisis otomatis
     * 2. Fallback: cari & klik tombol "Analisis" di halaman
     */
    function autoLoad() {
        console.log('[BearingPatch] Auto-load dimulai...');

        // Ambil parameter dari form (unit_db, date range, dll)
        var unitDb = getInputValue(['unit_db', 'unitDb', '[name="unit_db"]']) || '';

        fetch(buildUrl('?api=bearing-proxy&action=models', unitDb))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.models && data.models.length > 0) {
                    console.log('[BearingPatch] Ditemukan ' + data.models.length + ' model tersimpan');
                    autoPredict(data.models, unitDb);
                } else {
                    console.log('[BearingPatch] Belum ada model, klik tombol Analisis...');
                    clickAnalisisButton();
                }
            })
            .catch(function (err) {
                console.warn('[BearingPatch] Gagal cek model, fallback klik tombol:', err);
                clickAnalisisButton();
            });
    }

    /**
     * Jalankan prediksi otomatis dengan model terbaru
     */
    function autoPredict(models, unitDb) {
        // Cari model Y1 dan Y2 terbaru
        var modelY1 = null, modelY2 = null;
        models.forEach(function (m) {
            var lbl = (m.bearing_label || '').toUpperCase();
            if (lbl === 'Y1' && !modelY1) modelY1 = m;
            if (lbl === 'Y2' && !modelY2) modelY2 = m;
        });

        // Tanggal dari form, atau default ke bulan ini
        var predStart = getInputValue(['pred_start', 'tanggal_awal_pred', 'date_start']) || getTodayMinus(180);
        var predEnd   = getInputValue(['pred_end',   'tanggal_akhir_pred','date_end'])   || getToday();
        var batas     = parseFloat(getInputValue(['batas', 'toleransi']) || '5');

        var body = {
            pred_start:   predStart,
            pred_end:     predEnd,
            batas:        batas,
            unit_db:      unitDb,
        };
        if (modelY1) body.model_id_b1 = modelY1.model_id;
        if (modelY2) body.model_id_b2 = modelY2.model_id;

        if (!body.model_id_b1 && !body.model_id_b2) {
            console.warn('[BearingPatch] Tidak ada model Y1/Y2, fallback klik tombol');
            clickAnalisisButton();
            return;
        }

        console.log('[BearingPatch] Auto-predict dengan:', body);

        // ── Coba pakai fungsi internal halaman kalau ada ─────
        if (typeof window.runPredict === 'function') {
            window.runPredict(body);
            return;
        }
        if (typeof window.doAnalisis === 'function') {
            window.doAnalisis(body);
            return;
        }
        if (typeof window.renderBearingChart === 'function') {
            window.renderBearingChart(body);
            return;
        }

        // ── Fallback: panggil API langsung, render chart sendiri ─
        fetch('?api=bearing-proxy&action=predict', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                console.warn('[BearingPatch] Predict gagal:', data && data.error);
                // Tetap coba klik tombol sebagai fallback akhir
                clickAnalisisButton();
                return;
            }
            console.log('[BearingPatch] Predict berhasil, render chart...');
            renderAutoChart(data, modelY1, modelY2, batas);
        })
        .catch(function (err) {
            console.warn('[BearingPatch] Error predict:', err);
            clickAnalisisButton();
        });
    }

    /**
     * Render chart langsung kalau fungsi internal halaman tidak ditemukan.
     * Mencoba inject hasil ke elemen canvas yang sudah ada di halaman.
     */
    function renderAutoChart(data, modelY1, modelY2, batas) {
        // Coba trigger event custom supaya JS halaman bisa mendengarkan
        var event = new CustomEvent('bearingDataLoaded', {
            detail: { data: data, modelY1: modelY1, modelY2: modelY2, batas: batas }
        });
        document.dispatchEvent(event);

        // Coba set data ke variabel global yang mungkin dipakai halaman
        window._bearingAutoData = data;

        // Cari canvas chart yang ada
        var canvases = document.querySelectorAll('canvas[id*="chart"], canvas[id*="Chart"], canvas[id*="grafik"]');
        if (canvases.length === 0) {
            // Tidak ada canvas, coba klik tombol
            clickAnalisisButton();
            return;
        }

        // Render masing-masing bearing
        ['b1', 'b2'].forEach(function (key, idx) {
            var result = data[key];
            if (!result || !result.dates || result.dates.length === 0) return;
            var canvas = canvases[idx] || canvases[0];
            drawChart(canvas, result, key === 'b1' ? 'Bearing 858 (Y1)' : 'Bearing 859 (Y2)', batas);
        });

        // Update badge anomali jika ada
        updateAnomaliBadge(data);
    }

    /**
     * Gambar chart menggunakan Chart.js (harus sudah ter-load di halaman)
     */
    function drawChart(canvas, result, label, batas) {
        if (typeof Chart === 'undefined') {
            console.warn('[BearingPatch] Chart.js belum dimuat');
            return;
        }

        // Hancurkan chart lama kalau ada
        var existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        var dates   = result.dates.map(function (d) { return d.substring(0, 10); });
        var aktual  = result.aktual;
        var pred    = result.prediksi;
        var hiArr   = pred.map(function (p) { return p !== null ? +(p + batas).toFixed(2) : null; });
        var loArr   = pred.map(function (p) { return p !== null ? +(p - batas).toFixed(2) : null; });

        var isY1 = label.indexOf('858') >= 0 || label.indexOf('Y1') >= 0;
        var mainColor = isY1 ? '#38bdf8' : '#a78bfa';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Batas Atas',
                        data: hiArr,
                        borderColor: 'rgba(239,68,68,0.4)',
                        borderWidth: 1,
                        borderDash: [4, 3],
                        pointRadius: 0,
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: 'Batas Bawah',
                        data: loArr,
                        borderColor: 'rgba(239,68,68,0.4)',
                        borderWidth: 1,
                        borderDash: [4, 3],
                        pointRadius: 0,
                        fill: '-1',
                        backgroundColor: 'rgba(239,68,68,0.07)',
                        tension: 0.3,
                    },
                    {
                        label: 'Prediksi',
                        data: pred,
                        borderColor: '#f97316',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: 'Aktual ' + label,
                        data: aktual,
                        borderColor: mainColor,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        fill: false,
                        tension: 0.3,
                        spanGaps: false,
                    },
                    {
                        label: 'Anomali',
                        data: dates.map(function (d, i) {
                            if (aktual[i] !== null && result.anomali && result.anomali[i]) {
                                return aktual[i];
                            }
                            return null;
                        }),
                        type: 'scatter',
                        backgroundColor: '#ef4444',
                        borderColor: '#fff',
                        borderWidth: 1.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { size: 11 }, boxWidth: 20, usePointStyle: true },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.parsed.y;
                                return ctx.dataset.label + ': ' + (v !== null ? v.toFixed(2) + '°C' : '—');
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
                        grid: { color: 'rgba(255,255,255,0.05)' },
                    },
                    y: {
                        ticks: { callback: function (v) { return v + '°C'; } },
                        grid: { color: 'rgba(255,255,255,0.05)' },
                    },
                },
            },
        });

        console.log('[BearingPatch] Chart "' + label + '" berhasil dirender (' + dates.length + ' titik data)');
    }

    /**
     * Update badge jumlah anomali di tab
     */
    function updateAnomaliBadge(data) {
        ['b1', 'b2'].forEach(function (key, idx) {
            var result = data[key];
            if (!result) return;
            var n = result.n_anom || 0;
            // Cari badge elemen (format umum)
            var selectors = [
                '[data-bearing="' + (idx + 1) + '"] .badge',
                '.bearing-tab-' + (idx === 0 ? 'y1' : 'y2') + ' .badge',
                '.tab-bearing-' + (idx + 1) + ' .badge',
            ];
            selectors.forEach(function (sel) {
                try {
                    var el = document.querySelector(sel);
                    if (el) el.textContent = n;
                } catch (e) {}
            });
        });
    }

    // ── Klik tombol Analisis ─────────────────────────────────
    function clickAnalisisButton() {
        // Cari tombol berdasarkan teks / id / class umum
        var candidates = [
            document.querySelector('#btnAnalisis'),
            document.querySelector('#btn-analisis'),
            document.querySelector('[data-action="analisis"]'),
            document.querySelector('.btn-analisis'),
            findButtonByText('Analisis'),
            findButtonByText('Analyze'),
            findButtonByText('Run'),
        ];

        for (var i = 0; i < candidates.length; i++) {
            if (candidates[i]) {
                console.log('[BearingPatch] Klik tombol:', candidates[i]);
                candidates[i].click();
                return;
            }
        }
        console.warn('[BearingPatch] Tombol Analisis tidak ditemukan. ' +
            'Pastikan ID tombol adalah #btnAnalisis atau tambahkan atribut data-action="analisis"');
    }

    function findButtonByText(text) {
        var btns = document.querySelectorAll('button, .btn, [role="button"]');
        for (var i = 0; i < btns.length; i++) {
            if (btns[i].textContent.trim().toLowerCase().indexOf(text.toLowerCase()) >= 0) {
                return btns[i];
            }
        }
        return null;
    }

    // ── Helpers ──────────────────────────────────────────────
    function getInputValue(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var sel = selectors[i];
            var el = document.querySelector(
                '#' + sel + ', [name="' + sel + '"], ' + sel
            );
            if (el && el.value) return el.value;
        }
        return null;
    }

    function buildUrl(base, unitDb) {
        return unitDb ? base + '&unit_db=' + encodeURIComponent(unitDb) : base;
    }

    function getToday() {
        return new Date().toISOString().substring(0, 10);
    }

    function getTodayMinus(days) {
        var d = new Date();
        d.setDate(d.getDate() - days);
        return d.toISOString().substring(0, 10);
    }

})();
