// ════════════════════════════════════════════════════════════════════════════
// PATCH: _buildCsvChart — ganti type:'time' → type:'category' agar chart
// muncul tanpa perlu chartjs-adapter-date-fns. Juga tambah style konsisten
// dengan chart Bearing 858/859 (legenda, deviasi, band atas/bawah).
// 
// CARA TEMPEL: Cari blok "function _buildCsvChart(allData) {" s/d "}" penutup
// di bearing-anomali.php lalu ganti dengan isi file ini.
// ════════════════════════════════════════════════════════════════════════════

function _buildCsvChart(allData) {
    var wrap = document.getElementById('csv-compare-chart-wrap');
    wrap.style.display = 'block';
    if (_csvChartInst) { try { _csvChartInst.destroy(); } catch(e){} _csvChartInst = null; }
    if (_csvDevInst)   { try { _csvDevInst.destroy();   } catch(e){} _csvDevInst   = null; }

    // ── 1. Tagno dari nama file ──────────────────────────────────────────
    function _tagnofromName(name) {
        var m = name.match(/^(\d+)/);
        return m ? m[1] : name.replace(/[-_].*/,'').replace(/\.csv$/i,'');
    }

    // ── 2. Group per tagno, avg harian ───────────────────────────────────
    var groups = {};
    allData.forEach(function(entry) {
        if (!entry || !entry.pts || !entry.pts.length) return;
        var tagno = _tagnofromName(entry.fd.name);
        if (!groups[tagno]) groups[tagno] = { pts: [], label: tagno };
        groups[tagno].pts = groups[tagno].pts.concat(entry.pts);
    });
    var tagnos = Object.keys(groups).sort();
    tagnos.forEach(function(tagno, tidx) {
        groups[tagno].color = _SENSOR_COLORS[tidx % _SENSOR_COLORS.length];
        var byDate = {};
        groups[tagno].pts.forEach(function(p) {
            var dk = new Date(p.date).toISOString ? new Date(p.date).toISOString().slice(0,10)
                   : String(p.date).slice(0,10);
            if (!byDate[dk]) byDate[dk] = { sum:0, cnt:0 };
            byDate[dk].sum += p.value; byDate[dk].cnt++;
        });
        groups[tagno].pts = Object.keys(byDate).sort().map(function(dk){
            return { dateStr: dk, value: byDate[dk].sum / byDate[dk].cnt };
        });
    });

    // ── 3. Unified label array (union semua tanggal, sorted) ─────────────
    var dateSet = {};
    if (typeof D1 !== 'undefined' && D1 && D1.dates) D1.dates.forEach(function(d){ dateSet[d]=1; });
    if (typeof D2 !== 'undefined' && D2 && D2.dates) D2.dates.forEach(function(d){ dateSet[d]=1; });
    tagnos.forEach(function(t){ groups[t].pts.forEach(function(p){ dateSet[p.dateStr]=1; }); });
    var labels = Object.keys(dateSet).sort();

    if (!labels.length) {
        document.getElementById('csv-sensor-stats').innerHTML =
            '<div style="color:#f97316;font-size:13px"><i class="bi bi-exclamation-triangle"></i> Tidak ada data valid di file CSV.</div>';
        return;
    }

    // ── 4. Tick step ─────────────────────────────────────────────────────
    var tickStep = Math.max(1, Math.ceil(labels.length / 14));
    var isDark   = !document.body.classList.contains('light-theme');
    var gridCol  = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.07)';
    var tickCol  = isDark ? '#8892af' : '#6c757d';

    var xAxis = {
        type: 'category',
        labels: labels,
        grid: { color: gridCol },
        ticks: {
            color: tickCol,
            maxRotation: 30,
            callback: function(val, idx) { return idx % tickStep === 0 ? labels[idx] : ''; }
        }
    };
    var yAxis = {
        grid: { color: gridCol },
        ticks: { color: tickCol, callback: function(v){ return v + '°C'; } }
    };
    var tipBase = {
        backgroundColor: isDark ? '#1e293b' : '#fff',
        titleColor: isDark ? '#fff' : '#1a1d2e',
        bodyColor: tickCol,
        borderColor: isDark ? '#334155' : '#e2e8f0',
        borderWidth: 1, padding: 10,
        callbacks: {
            label: function(c) {
                return ' ' + c.dataset.label + ': ' +
                    (c.parsed.y != null ? c.parsed.y.toFixed(2) : '—') + ' °C';
            }
        }
    };

    // ── 5. Peta tanggal → indeks untuk D1, D2 ───────────────────────────
    var d1map = {}, d2map = {};
    if (typeof D1 !== 'undefined' && D1 && D1.dates)
        D1.dates.forEach(function(d,i){ d1map[d]=i; });
    if (typeof D2 !== 'undefined' && D2 && D2.dates)
        D2.dates.forEach(function(d,i){ d2map[d]=i; });

    var BATAS_VAL = typeof BATAS !== 'undefined' ? BATAS : 5;
    var modelY1   = window._csvModelY1 || null;  // diisi oleh PHP inline (lihat bawah)
    var modelY2   = window._csvModelY2 || null;

    function _estimate(val, model) {
        if (!model || model.coef_a == null || model.coef_b == null) return null;
        var a = parseFloat(model.coef_a), b = parseFloat(model.coef_b);
        return isNaN(a)||isNaN(b) ? null : a * val + b;
    }

    // ── 6. Build datasets ────────────────────────────────────────────────
    var datasets = [], devDatasets = [];

    // — Band Prediksi 858 (atas/bawah) —
    if (d1map && Object.keys(d1map).length) {
        var hi858 = labels.map(function(d){ var i=d1map[d]; return i!=null&&D1.prediksi[i]!=null ? D1.prediksi[i]+BATAS_VAL : null; });
        var lo858 = labels.map(function(d){ var i=d1map[d]; return i!=null&&D1.prediksi[i]!=null ? D1.prediksi[i]-BATAS_VAL : null; });
        datasets.push({ label:'Band Atas 858',  data:hi858, borderColor:'rgba(37,99,235,.18)',  borderWidth:1, borderDash:[4,3], backgroundColor:'rgba(37,99,235,.06)', fill:false, pointRadius:0, tension:.3, order:10 });
        datasets.push({ label:'Band Bawah 858', data:lo858, borderColor:'rgba(37,99,235,.18)',  borderWidth:1, borderDash:[4,3], fill:false, pointRadius:0, tension:.3, order:11 });
    }
    // — Band Prediksi 859 —
    if (d2map && Object.keys(d2map).length) {
        var hi859 = labels.map(function(d){ var i=d2map[d]; return i!=null&&D2.prediksi[i]!=null ? D2.prediksi[i]+BATAS_VAL : null; });
        var lo859 = labels.map(function(d){ var i=d2map[d]; return i!=null&&D2.prediksi[i]!=null ? D2.prediksi[i]-BATAS_VAL : null; });
        datasets.push({ label:'Band Atas 859',  data:hi859, borderColor:'rgba(124,58,237,.18)', borderWidth:1, borderDash:[4,3], backgroundColor:'rgba(124,58,237,.06)', fill:false, pointRadius:0, tension:.3, order:12 });
        datasets.push({ label:'Band Bawah 859', data:lo859, borderColor:'rgba(124,58,237,.18)', borderWidth:1, borderDash:[4,3], fill:false, pointRadius:0, tension:.3, order:13 });
    }
    // — Prediksi 858 & 859 (garis putus-putus) —
    if (Object.keys(d1map).length) {
        datasets.push({
            label:'Prediksi 858', data: labels.map(function(d){ var i=d1map[d]; return i!=null ? D1.prediksi[i] : null; }),
            borderColor:'rgba(37,99,235,.55)', borderWidth:1.8, borderDash:[6,4], pointRadius:0, tension:.3, fill:false, spanGaps:true, order:5
        });
    }
    if (Object.keys(d2map).length) {
        datasets.push({
            label:'Prediksi 859', data: labels.map(function(d){ var i=d2map[d]; return i!=null ? D2.prediksi[i] : null; }),
            borderColor:'rgba(124,58,237,.55)', borderWidth:1.8, borderDash:[6,4], pointRadius:0, tension:.3, fill:false, spanGaps:true, order:6
        });
    }
    // — Aktual 858 —
    if (Object.keys(d1map).length) {
        var actB1 = labels.map(function(d){ var i=d1map[d]; return (i!=null&&D1.aktual[i]!=null) ? D1.aktual[i] : null; });
        datasets.push({ label:'Aktual 858', data:actB1, borderColor:'#2563eb', borderWidth:2.5, pointRadius:0, tension:.3, fill:false, spanGaps:false, order:3 });
        // Anomali 858
        datasets.push({ label:'Anomali 858', type:'scatter',
            data: labels.map(function(d){ var i=d1map[d]; return (i!=null&&D1.aktual[i]!=null&&D1.anomali[i]) ? D1.aktual[i] : null; }),
            backgroundColor:'rgba(37,99,235,.85)', borderColor:'#fff', borderWidth:1.5, pointRadius:6, order:1 });
    }
    // — Aktual 859 —
    if (Object.keys(d2map).length) {
        var actB2 = labels.map(function(d){ var i=d2map[d]; return (i!=null&&D2.aktual[i]!=null) ? D2.aktual[i] : null; });
        datasets.push({ label:'Aktual 859', data:actB2, borderColor:'#7c3aed', borderWidth:2.5, pointRadius:0, tension:.3, fill:false, spanGaps:false, order:4 });
        datasets.push({ label:'Anomali 859', type:'scatter',
            data: labels.map(function(d){ var i=d2map[d]; return (i!=null&&D2.aktual[i]!=null&&D2.anomali[i]) ? D2.aktual[i] : null; }),
            backgroundColor:'rgba(124,58,237,.85)', borderColor:'#fff', borderWidth:1.5, pointRadius:6, order:2 });
    }

    // — Deviasi 858 & 859 untuk chart bawah —
    if (Object.keys(d1map).length) {
        devDatasets.push({
            label:'Deviasi 858', type:'bar',
            data: labels.map(function(d){ var i=d1map[d]; return i!=null&&D1.deviasi[i]!=null?D1.deviasi[i]:null; }),
            backgroundColor: labels.map(function(d){ var i=d1map[d]; return (i!=null&&D1.anomali[i]) ? 'rgba(37,99,235,.85)' : 'rgba(37,99,235,.35)'; }),
            borderWidth:0
        });
    }
    if (Object.keys(d2map).length) {
        devDatasets.push({
            label:'Deviasi 859', type:'bar',
            data: labels.map(function(d){ var i=d2map[d]; return i!=null&&D2.deviasi[i]!=null?D2.deviasi[i]:null; }),
            backgroundColor: labels.map(function(d){ var i=d2map[d]; return (i!=null&&D2.anomali[i]) ? 'rgba(124,58,237,.85)' : 'rgba(124,58,237,.35)'; }),
            borderWidth:0
        });
    }

    // — Dataset per tagno CSV baru —
    var statsHtml = '', tableHtml = '';
    tagnos.forEach(function(tagno) {
        var g = groups[tagno];
        var color = g.color;
        // Buat peta dateStr → value
        var ptMap = {};
        g.pts.forEach(function(p){ ptMap[p.dateStr] = p.value; });
        var vals = labels.map(function(d){ return ptMap[d] != null ? ptMap[d] : null; });

        // Aktual sensor baru
        datasets.push({
            label: 'Sensor ' + tagno, type:'line',
            data: vals,
            borderColor: color, borderWidth:2.5, pointRadius:0, tension:.3, fill:false,
            spanGaps:true, order:2
        });

        // Estimasi & anomali per titik
        var estVals = vals.map(function(v){
            if (v == null) return null;
            var e = _estimate(v, modelY1);
            if (e === null) e = _estimate(v, modelY2);
            return e;
        });
        var hasEst = estVals.some(function(v){ return v !== null; });

        if (hasEst) {
            // Band atas/bawah sensor baru
            datasets.push({
                label: tagno + ' Batas Atas', data: labels.map(function(d,i){ return estVals[i]!=null ? estVals[i]+BATAS_VAL : null; }),
                borderColor: color.replace(')',', .3)').replace('rgb','rgba'), borderWidth:1, borderDash:[3,3],
                backgroundColor: color.replace(')',', .06)').replace('rgb','rgba'), fill:false, pointRadius:0, tension:.3, order:14
            });
            datasets.push({
                label: tagno + ' Batas Bawah', data: labels.map(function(d,i){ return estVals[i]!=null ? estVals[i]-BATAS_VAL : null; }),
                borderColor: color.replace(')',', .3)').replace('rgb','rgba'), borderWidth:1, borderDash:[3,3],
                fill:false, pointRadius:0, tension:.3, order:15
            });
            // Estimasi garis
            datasets.push({
                label: tagno + ' Estimasi ML', data: estVals,
                borderColor: color, borderWidth:1.5, borderDash:[6,3], pointRadius:0, tension:.3,
                fill:false, spanGaps:true, order:7
            });

            var devPts = [], nAnom = 0;
            labels.forEach(function(d, i) {
                if (vals[i]==null||estVals[i]==null) { devPts.push(null); return; }
                var dev = parseFloat((vals[i]-estVals[i]).toFixed(3));
                devPts.push(dev);
                if (Math.abs(dev) > BATAS_VAL) nAnom++;
            });

            // Anomali scatter
            datasets.push({ label: tagno + ' Anomali', type:'scatter',
                data: labels.map(function(d,i){ return (vals[i]!=null&&estVals[i]!=null&&Math.abs(vals[i]-estVals[i])>BATAS_VAL) ? vals[i] : null; }),
                backgroundColor:'#ef4444', borderColor:'#fff', borderWidth:1.5, pointRadius:7, order:1 });

            // Deviasi bar
            devDatasets.push({
                label: 'Deviasi ' + tagno, type:'bar',
                data: devPts,
                backgroundColor: devPts.map(function(v){ return (v!=null&&Math.abs(v)>BATAS_VAL)?'rgba(239,68,68,.8)':color+'99'; }),
                borderWidth:0
            });

            // Stats card
            var validVals = g.pts.map(function(p){ return p.value; });
            var minV = Math.min.apply(null, validVals), maxV = Math.max.apply(null, validVals);
            var avgV = validVals.reduce(function(a,b){return a+b;},0) / validVals.length;
            var pct  = g.pts.length > 0 ? Math.round(nAnom/g.pts.length*1000)/10 : 0;
            var nFiles = allData.filter(function(e){ return e && _tagnofromName(e.fd.name)===tagno; }).length;
            statsHtml += '<div style="background:var(--bg-card);border:1.5px solid '+color.replace(')',', .4)').replace('rgb','rgba')+';border-radius:10px;padding:12px 16px;min-width:180px;flex:1">'
                + '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">'
                + '<div style="width:12px;height:12px;border-radius:50%;background:'+color+'"></div>'
                + '<div style="font-size:13px;font-weight:700;color:var(--text-primary)">Sensor '+tagno+'</div>'
                + (nFiles>1?'<span style="font-size:10px;color:var(--text-secondary)">('+nFiles+' file)</span>':'')
                + '</div>'
                + '<div style="font-size:11px;color:var(--text-secondary);line-height:2">'
                + '<div>'+g.pts.length+' hari data</div>'
                + '<div>Min: <b>'+minV.toFixed(2)+'</b> · Max: <b>'+maxV.toFixed(2)+'</b> · Avg: <b>'+avgV.toFixed(2)+'</b></div>'
                + '<div style="color:'+(pct>20?'#ef4444':'#22c55e')+';font-weight:700">'+nAnom+' anomali ('+pct+'%)</div>'
                + '</div></div>';

            // Tabel detail
            tableHtml += '<div style="margin-top:20px">';
            tableHtml += '<div style="font-size:12px;font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:8px">'
                + '<span style="width:12px;height:12px;background:'+color+';border-radius:50%;display:inline-block"></span>'
                + '<span style="color:'+color+'">Sensor '+tagno+'</span>';
            if (nFiles>1) tableHtml += '<span style="font-size:10px;color:var(--text-secondary)">('+nFiles+' file: '
                + allData.filter(function(e){ return e && _tagnofromName(e.fd.name)===tagno; }).map(function(e){return e.fd.name;}).join(', ')+')</span>';
            tableHtml += '</div>';
            tableHtml += '<div class="at-wrap" style="max-height:320px"><table class="at"><thead><tr>'
                + '<th>Tanggal</th><th style="text-align:right">Aktual</th>'
                + '<th style="text-align:right">Estimasi ML</th>'
                + '<th style="text-align:right;color:#f97316">Batas Atas</th>'
                + '<th style="text-align:right;color:#f97316">Batas Bawah</th>'
                + '<th style="text-align:right">Selisih</th>'
                + '<th style="text-align:center">Status</th>'
                + '</tr></thead><tbody>';
            labels.forEach(function(d, i) {
                if (vals[i] == null) return;
                var rv  = vals[i], est = estVals[i];
                var dev = est!=null ? parseFloat((rv-est).toFixed(3)) : null;
                var isa = dev!=null && Math.abs(dev)>BATAS_VAL;
                tableHtml += '<tr style="background:'+(isa?'rgba(239,68,68,.06)':'')+'">'
                    + '<td>'+d+'</td>'
                    + '<td style="text-align:right;font-family:monospace">'+rv.toFixed(3)+'</td>'
                    + '<td style="text-align:right;font-family:monospace">'+(est!=null?est.toFixed(3):'—')+'</td>'
                    + '<td style="text-align:right;font-family:monospace;color:#f97316">'+(est!=null?(est+BATAS_VAL).toFixed(3):'—')+'</td>'
                    + '<td style="text-align:right;font-family:monospace;color:#f97316">'+(est!=null?(est-BATAS_VAL).toFixed(3):'—')+'</td>'
                    + '<td style="text-align:right;font-family:monospace;color:'+(dev!=null?(isa?'#ef4444':'#22c55e'):'var(--text-secondary)')+'">'+(dev!=null?(dev>0?'+':'')+dev:'—')+'</td>'
                    + '<td style="text-align:center">'+(isa
                        ? '<span style="background:rgba(239,68,68,.15);color:#ef4444;border-radius:10px;padding:2px 8px;font-size:10px;font-weight:700">⚠ Anomali</span>'
                        : '<span style="background:rgba(34,197,94,.12);color:#22c55e;border-radius:10px;padding:2px 8px;font-size:10px;font-weight:700">✓ Normal</span>')
                    + '</td></tr>';
            });
            tableHtml += '</tbody></table></div></div>';
        } else {
            // Tidak ada model — tampilkan card tanpa estimasi
            var validVals2 = g.pts.map(function(p){ return p.value; });
            var minV2=Math.min.apply(null,validVals2), maxV2=Math.max.apply(null,validVals2), avgV2=validVals2.reduce(function(a,b){return a+b;},0)/validVals2.length;
            statsHtml += '<div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:10px;padding:12px 16px;min-width:180px;flex:1">'
                + '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">'
                + '<div style="width:12px;height:12px;border-radius:50%;background:'+color+'"></div>'
                + '<div style="font-size:13px;font-weight:700;color:var(--text-primary)">Sensor '+tagno+'</div></div>'
                + '<div style="font-size:11px;color:var(--text-secondary);line-height:2">'
                + '<div>'+g.pts.length+' hari data</div>'
                + '<div>Min: <b>'+minV2.toFixed(2)+'</b> · Max: <b>'+maxV2.toFixed(2)+'</b> · Avg: <b>'+avgV2.toFixed(2)+'</b></div>'
                + '<div style="color:#f97316;font-size:10px">Estimasi ML tidak tersedia — jalankan Analisis dulu</div>'
                + '</div></div>';
        }
    });

    // ── 7. Update stats & tabel ──────────────────────────────────────────
    document.getElementById('csv-sensor-stats').innerHTML =
        statsHtml || '<div style="color:#f97316;font-size:13px">Tidak ada data valid.</div>';
    document.getElementById('csv-sensor-table-wrap').innerHTML = tableHtml;

    // ── 8. Legenda atas chart ────────────────────────────────────────────
    var legHtml = '<div class="leg" style="margin-bottom:10px">';
    if (Object.keys(d1map).length) {
        legHtml += '<span class="leg-item"><span class="leg-line" style="background:#2563eb"></span>Aktual 858</span>';
        legHtml += '<span class="leg-item"><span class="leg-dash" style="border-color:rgba(37,99,235,.55)"></span>Prediksi 858</span>';
    }
    if (Object.keys(d2map).length) {
        legHtml += '<span class="leg-item"><span class="leg-line" style="background:#7c3aed"></span>Aktual 859</span>';
        legHtml += '<span class="leg-item"><span class="leg-dash" style="border-color:rgba(124,58,237,.55)"></span>Prediksi 859</span>';
    }
    tagnos.forEach(function(t) {
        var c = groups[t].color;
        legHtml += '<span class="leg-item"><span class="leg-line" style="background:'+c+'"></span>Sensor '+t+'</span>';
    });
    legHtml += '<span class="leg-item"><span class="leg-dot" style="background:#ef4444"></span>Anomali</span>';
    legHtml += '</div>';

    // Sisipkan legenda sebelum chart
    var mainWrap = document.getElementById('csv-main-chart-area');
    if (mainWrap) mainWrap.insertAdjacentHTML('beforebegin', legHtml);

    // ── 9. Render chart utama ─────────────────────────────────────────────
    var ctx1 = document.getElementById('chart-csv-compare-main');
    if (!ctx1) return;
    // Fix tinggi wrapper
    ctx1.parentElement.style.cssText = 'position:relative;height:360px;border-radius:10px;padding:8px;border:1px solid var(--border-color)';
    ctx1.style.cssText = 'display:block;width:100%;height:360px';

    _csvChartInst = new Chart(ctx1.getContext('2d'), {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 350 },
            interaction: { mode:'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: tipBase
            },
            scales: {
                x: xAxis,
                y: yAxis
            }
        }
    });

    // ── 10. Chart deviasi bawah ───────────────────────────────────────────
    if (devDatasets.length) {
        var ctx2 = document.getElementById('chart-csv-compare-dev');
        if (ctx2) {
            ctx2.parentElement.style.cssText = 'position:relative;height:150px;border-radius:10px;padding:8px;border:1px solid var(--border-color)';
            ctx2.style.cssText = 'display:block;width:100%;height:150px';
            var annoPlugin = window.ChartAnnotation || null;
            _csvDevInst = new Chart(ctx2.getContext('2d'), {
                type: 'bar',
                data: { labels: labels, datasets: devDatasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    animation: { duration: 250 },
                    interaction: { mode:'index', intersect: false },
                    plugins: {
                        legend: { position:'bottom', labels:{ font:{size:9}, boxWidth:12 } },
                        annotation: annoPlugin ? { annotations: {
                            lp: { type:'line', yMin:BATAS_VAL,  yMax:BATAS_VAL,  borderColor:'rgba(239,68,68,.5)', borderWidth:1.5, borderDash:[5,3] },
                            ln: { type:'line', yMin:-BATAS_VAL, yMax:-BATAS_VAL, borderColor:'rgba(239,68,68,.5)', borderWidth:1.5, borderDash:[5,3] },
                            lz: { type:'line', yMin:0,          yMax:0,          borderColor:'rgba(148,163,184,.35)', borderWidth:1 }
                        }} : {}
                    },
                    scales: {
                        x: xAxis,
                        y: {
                            grid: { color: gridCol },
                            ticks: { color: tickCol, callback: function(v){ return v+'°C'; } },
                            title: { display:true, text:'Deviasi (°C)', color: tickCol, font:{size:10} }
                        }
                    }
                }
            });
        }
    }
}
