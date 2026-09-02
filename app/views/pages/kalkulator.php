<?php require_login(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalkulator - PLN Dashboard</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        /* ── Kalkulator Wrapper ─────────────────────────────── */
        .calc-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 32px;
            flex-wrap: wrap;
        }

        /* ── Kalkulator Utama ──────────────────────────────── */
        .calculator {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px;
            width: 340px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .calc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .calc-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .calc-mode-tabs {
            display: flex;
            gap: 4px;
            background: var(--bg-secondary);
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .calc-mode-tab {
            flex: 1;
            padding: 6px 0;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            border-radius: 7px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s;
            border: none;
            background: transparent;
        }

        .calc-mode-tab.active {
            background: var(--accent-cyan);
            color: #fff;
        }

        /* ── Display ──────────────────────────────────────── */
        .calc-display {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 16px;
            min-height: 96px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-end;
            overflow: hidden;
        }

        .calc-expression {
            font-size: 13px;
            color: var(--text-secondary);
            min-height: 20px;
            word-break: break-all;
            text-align: right;
        }

        .calc-result {
            font-size: 38px;
            font-weight: 700;
            color: var(--text-primary);
            word-break: break-all;
            text-align: right;
            line-height: 1.1;
            transition: color 0.2s;
        }

        .calc-result.error {
            color: #ff6b7a;
            font-size: 22px;
        }

        /* ── Buttons ──────────────────────────────────────── */
        .calc-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .calc-btn {
            border: none;
            border-radius: 12px;
            height: 60px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            position: relative;
            overflow: hidden;
        }

        .calc-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: white;
            opacity: 0;
            transition: opacity 0.1s;
            border-radius: inherit;
        }

        .calc-btn:active::after { opacity: 0.12; }
        .calc-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .calc-btn:active { transform: translateY(0); }

        /* Number buttons */
        .btn-num {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Operator buttons */
        .btn-op {
            background: rgba(0, 217, 255, 0.15);
            color: var(--accent-cyan);
            border: 1px solid rgba(0, 217, 255, 0.3);
        }
        .btn-op:hover { background: rgba(0, 217, 255, 0.25); }

        /* Equal button */
        .btn-eq {
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            color: white;
            grid-column: span 1;
        }

        /* Clear / Function buttons */
        .btn-fn {
            background: rgba(255, 107, 122, 0.15);
            color: #ff6b7a;
            border: 1px solid rgba(255, 107, 122, 0.3);
            font-size: 14px;
        }
        .btn-fn:hover { background: rgba(255, 107, 122, 0.25); }

        /* Span 2 wide */
        .span2 { grid-column: span 2; }

        /* ── Riwayat Panel ─────────────────────────────────── */
        .history-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px;
            width: 280px;
            max-height: 530px;
            display: flex;
            flex-direction: column;
        }

        .history-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-clear-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .history-clear-btn:hover { background: rgba(255,107,122,0.15); color: #ff6b7a; }

        .history-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .history-list::-webkit-scrollbar { width: 4px; }
        .history-list::-webkit-scrollbar-thumb { background: var(--accent-cyan); border-radius: 2px; }

        .history-item {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .history-item:hover { border-color: var(--accent-cyan); background: var(--hover-bg); }

        .history-expr { font-size: 12px; color: var(--text-secondary); }
        .history-val  { font-size: 18px; font-weight: 700; color: var(--text-primary); }

        .history-empty {
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
            padding: 40px 0;
            opacity: 0.5;
        }

        /* ── Sci mode extra buttons ───────────────────────── */
        .calc-grid-sci {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        .btn-sci {
            background: rgba(130, 80, 255, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(130, 80, 255, 0.25);
            border-radius: 12px;
            height: 44px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-sci:hover { background: rgba(130, 80, 255, 0.25); transform: translateY(-1px); }
        .btn-sci:active { transform: translateY(0); }

        @media (max-width: 768px) {
            .history-panel { display: none; }
            .calculator { width: 100%; max-width: 380px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">
            <h1 class="page-title" style="margin-bottom:24px">🧮 Kalkulator</h1>

            <div class="calc-wrapper">

                <!-- ── KALKULATOR UTAMA ── -->
                <div class="calculator">
                    <div class="calc-header">
                        <span class="calc-title">Kalkulator</span>
                        <span style="font-size:20px">⚡</span>
                    </div>

                    <!-- Mode Tabs -->
                    <div class="calc-mode-tabs">
                        <button class="calc-mode-tab active" onclick="setMode('std', this)">Standar</button>
                        <button class="calc-mode-tab" onclick="setMode('sci', this)">Ilmiah</button>
                        <button class="calc-mode-tab" onclick="setMode('conv', this)">Konversi</button>
                    </div>

                    <!-- Display -->
                    <div class="calc-display">
                        <div class="calc-expression" id="expression"></div>
                        <div class="calc-result" id="result">0</div>
                    </div>

                    <!-- Scientific Extra Row (hidden by default) -->
                    <div id="sci-pad" style="display:none">
                        <div class="calc-grid-sci">
                            <button class="btn-sci" onclick="sciFunc('sin')">sin</button>
                            <button class="btn-sci" onclick="sciFunc('cos')">cos</button>
                            <button class="btn-sci" onclick="sciFunc('tan')">tan</button>
                            <button class="btn-sci" onclick="sciFunc('log')">log</button>
                            <button class="btn-sci" onclick="sciFunc('ln')">ln</button>
                            <button class="btn-sci" onclick="sciFunc('sqrt')">√</button>
                            <button class="btn-sci" onclick="sciFunc('pow2')">x²</button>
                            <button class="btn-sci" onclick="sciFunc('inv')">1/x</button>
                        </div>
                    </div>

                    <!-- Conversion Panel (hidden by default) -->
                    <div id="conv-pad" style="display:none;margin-bottom:10px">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                            <div>
                                <label style="font-size:12px;color:var(--text-secondary);display:block;margin-bottom:4px">Dari</label>
                                <select id="convFrom" class="form-control" style="font-size:13px" onchange="doConvert()">
                                    <option value="km">km</option>
                                    <option value="m">m</option>
                                    <option value="cm">cm</option>
                                    <option value="mm">mm</option>
                                    <option value="mile">mile</option>
                                    <option value="ft">ft</option>
                                    <option value="kg">kg</option>
                                    <option value="g">g</option>
                                    <option value="lb">lb</option>
                                    <option value="kw">kW</option>
                                    <option value="mw">MW</option>
                                    <option value="hp">HP</option>
                                    <option value="celsius">°C</option>
                                    <option value="fahrenheit">°F</option>
                                    <option value="kelvin">K</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;color:var(--text-secondary);display:block;margin-bottom:4px">Ke</label>
                                <select id="convTo" class="form-control" style="font-size:13px" onchange="doConvert()">
                                    <option value="m">m</option>
                                    <option value="km">km</option>
                                    <option value="cm">cm</option>
                                    <option value="mm">mm</option>
                                    <option value="mile">mile</option>
                                    <option value="ft">ft</option>
                                    <option value="kg">kg</option>
                                    <option value="g">g</option>
                                    <option value="lb">lb</option>
                                    <option value="kw">kW</option>
                                    <option value="mw">MW</option>
                                    <option value="hp">HP</option>
                                    <option value="celsius">°C</option>
                                    <option value="fahrenheit">°F</option>
                                    <option value="kelvin">K</option>
                                </select>
                            </div>
                        </div>
                        <div id="conv-result" style="background:var(--bg-secondary);border-radius:10px;padding:12px;font-size:15px;color:var(--accent-cyan);text-align:center;min-height:44px">
                            Masukkan angka lalu tekan =
                        </div>
                    </div>

                    <!-- Main Keypad -->
                    <div class="calc-grid" id="main-pad">
                        <!-- Row 1 -->
                        <button class="calc-btn btn-fn" onclick="clearAll()">AC</button>
                        <button class="calc-btn btn-fn" onclick="toggleSign()">+/-</button>
                        <button class="calc-btn btn-fn" onclick="percent()">%</button>
                        <button class="calc-btn btn-op" onclick="inputOp('/')">÷</button>
                        <!-- Row 2 -->
                        <button class="calc-btn btn-num" onclick="inputNum('7')">7</button>
                        <button class="calc-btn btn-num" onclick="inputNum('8')">8</button>
                        <button class="calc-btn btn-num" onclick="inputNum('9')">9</button>
                        <button class="calc-btn btn-op" onclick="inputOp('*')">×</button>
                        <!-- Row 3 -->
                        <button class="calc-btn btn-num" onclick="inputNum('4')">4</button>
                        <button class="calc-btn btn-num" onclick="inputNum('5')">5</button>
                        <button class="calc-btn btn-num" onclick="inputNum('6')">6</button>
                        <button class="calc-btn btn-op" onclick="inputOp('-')">−</button>
                        <!-- Row 4 -->
                        <button class="calc-btn btn-num" onclick="inputNum('1')">1</button>
                        <button class="calc-btn btn-num" onclick="inputNum('2')">2</button>
                        <button class="calc-btn btn-num" onclick="inputNum('3')">3</button>
                        <button class="calc-btn btn-op" onclick="inputOp('+')">+</button>
                        <!-- Row 5 -->
                        <button class="calc-btn btn-num span2" onclick="inputNum('0')">0</button>
                        <button class="calc-btn btn-num" onclick="inputDot()">.</button>
                        <button class="calc-btn btn-eq" onclick="calculate()">=</button>
                    </div>

                    <!-- Backspace below -->
                    <div style="margin-top:10px">
                        <button class="calc-btn btn-fn" style="width:100%;font-size:14px" onclick="backspace()">
                            ⌫ Hapus
                        </button>
                    </div>
                </div>

                <!-- ── RIWAYAT ── -->
                <div class="history-panel">
                    <div class="history-title">
                        Riwayat
                        <button class="history-clear-btn" onclick="clearHistory()">Hapus Semua</button>
                    </div>
                    <div class="history-list" id="historyList">
                        <div class="history-empty" id="historyEmpty">
                            <div style="font-size:32px;margin-bottom:8px">📋</div>
                            Belum ada riwayat
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// ── State ─────────────────────────────────────────────────────
let current     = '0';
let expression  = '';
let operator    = null;
let prevValue   = null;
let justCalc    = false;
let calcHistory = [];
let currentMode = 'std';

const resultEl    = document.getElementById('result');
const expressionEl= document.getElementById('expression');

// ── Display ──────────────────────────────────────────────────
function updateDisplay(val, expr = '') {
    // Format angka
    let display = val;
    if (!isNaN(parseFloat(val)) && isFinite(val)) {
        let n = parseFloat(val);
        // Max 10 digit signifikan
        if (Math.abs(n) >= 1e10 || (Math.abs(n) < 1e-6 && n !== 0)) {
            display = n.toExponential(4);
        } else {
            display = parseFloat(n.toPrecision(10)).toString();
        }
    }
    resultEl.textContent  = display;
    expressionEl.textContent = expr;
    resultEl.className = 'calc-result' + (val === 'Error' ? ' error' : '');

    // Shrink font for long numbers
    const len = display.length;
    resultEl.style.fontSize = len > 14 ? '20px' : len > 10 ? '28px' : '38px';
}

// ── Input handlers ────────────────────────────────────────────
function inputNum(n) {
    if (justCalc) { current = n; justCalc = false; }
    else current = (current === '0' && n !== '.') ? n : current + n;
    updateDisplay(current, expression);
}

function inputDot() {
    if (justCalc) { current = '0.'; justCalc = false; }
    else if (!current.includes('.')) current += '.';
    updateDisplay(current, expression);
}

function inputOp(op) {
    const opSymbols = { '+': '+', '-': '−', '*': '×', '/': '÷' };
    justCalc = false;
    if (operator && !justCalc && prevValue !== null) {
        calculate(true);
        operator  = op;
        expression = resultEl.textContent + ' ' + opSymbols[op];
        current   = '0';
        prevValue = parseFloat(resultEl.textContent.replace(/,/g, ''));
    } else {
        prevValue  = parseFloat(current);
        operator   = op;
        expression = current + ' ' + opSymbols[op];
        current    = '0';
    }
    updateDisplay(current, expression);
}

function calculate(internal = false) {
    if (operator === null || prevValue === null) return;
    const cur = parseFloat(current);
    let res;
    const opSymbols = { '+': '+', '-': '−', '*': '×', '/': '÷' };

    try {
        switch (operator) {
            case '+': res = prevValue + cur; break;
            case '-': res = prevValue - cur; break;
            case '*': res = prevValue * cur; break;
            case '/':
                if (cur === 0) { updateDisplay('Error', 'Bagi dengan 0'); return; }
                res = prevValue / cur;
                break;
        }
    } catch (e) { updateDisplay('Error', ''); return; }

    const fullExpr = expression + ' ' + current;
    if (!internal) {
        addHistory(fullExpr, res);
        expression = fullExpr + ' =';
    }
    current   = String(res);
    prevValue = null;
    operator  = null;
    justCalc  = true;
    updateDisplay(res, internal ? expression : fullExpr + ' =');

    // Konversi otomatis
    if (currentMode === 'conv') doConvert(res);
}

function clearAll() {
    current = '0'; expression = ''; operator = null; prevValue = null; justCalc = false;
    updateDisplay('0', '');
    document.getElementById('conv-result').textContent = 'Masukkan angka lalu tekan =';
}

function backspace() {
    if (justCalc || current.length <= 1) { current = '0'; }
    else current = current.slice(0, -1);
    updateDisplay(current, expression);
}

function toggleSign() {
    current = String(parseFloat(current) * -1);
    updateDisplay(current, expression);
}

function percent() {
    current = String(parseFloat(current) / 100);
    updateDisplay(current, expression);
}

// ── Scientific ────────────────────────────────────────────────
function sciFunc(fn) {
    const n = parseFloat(current);
    let res, expr;
    try {
        switch (fn) {
            case 'sin':  res = Math.sin(n * Math.PI / 180); expr = `sin(${n}°)`; break;
            case 'cos':  res = Math.cos(n * Math.PI / 180); expr = `cos(${n}°)`; break;
            case 'tan':  res = Math.tan(n * Math.PI / 180); expr = `tan(${n}°)`; break;
            case 'log':  res = Math.log10(n); expr = `log(${n})`; break;
            case 'ln':   res = Math.log(n);   expr = `ln(${n})`;  break;
            case 'sqrt': res = Math.sqrt(n);  expr = `√(${n})`;   break;
            case 'pow2': res = Math.pow(n, 2);expr = `${n}²`;     break;
            case 'inv':  if (n === 0) { updateDisplay('Error', '1/0'); return; }
                         res = 1 / n; expr = `1/${n}`;  break;
        }
        addHistory(expr, res);
        current = String(res);
        justCalc = true;
        updateDisplay(res, expr + ' =');
    } catch (e) { updateDisplay('Error', ''); }
}

// ── Conversion ────────────────────────────────────────────────
// Semua dikonversi ke base unit, lalu ke target
const convBase = {
    // Panjang → meter
    km: 1000, m: 1, cm: 0.01, mm: 0.001, mile: 1609.34, ft: 0.3048,
    // Massa → gram
    kg: 1000, g: 1, lb: 453.592,
    // Daya → watt
    kw: 1000, mw: 1e6, hp: 745.7,
    // Suhu khusus
    celsius: 'temp', fahrenheit: 'temp', kelvin: 'temp'
};

function doConvert(val) {
    const from = document.getElementById('convFrom').value;
    const to   = document.getElementById('convTo').value;
    const n    = val !== undefined ? parseFloat(val) : parseFloat(current);
    if (isNaN(n)) return;

    let result;
    // Suhu
    if (convBase[from] === 'temp' || convBase[to] === 'temp') {
        let celsius;
        if (from === 'celsius')    celsius = n;
        else if (from === 'fahrenheit') celsius = (n - 32) * 5/9;
        else                       celsius = n - 273.15;

        if (to === 'celsius')      result = celsius;
        else if (to === 'fahrenheit') result = celsius * 9/5 + 32;
        else                       result = celsius + 273.15;
    } else {
        // Cek apakah sama kategori (panjang/massa/daya)
        const lengthUnits = ['km','m','cm','mm','mile','ft'];
        const massUnits   = ['kg','g','lb'];
        const powerUnits  = ['kw','mw','hp'];
        const inSame = (a, b) => {
            for (const g of [lengthUnits, massUnits, powerUnits]) if (g.includes(a) && g.includes(b)) return true;
            return false;
        };
        if (!inSame(from, to)) {
            document.getElementById('conv-result').textContent = '⚠️ Kategori berbeda';
            return;
        }
        result = n * convBase[from] / convBase[to];
    }

    const fmt = (v) => parseFloat(v.toPrecision(8)).toLocaleString('id-ID', {maximumFractionDigits:6});
    document.getElementById('conv-result').innerHTML =
        `<span style="color:var(--text-secondary)">${n} ${from}</span> = <strong style="color:var(--accent-cyan);font-size:18px">${fmt(result)}</strong> ${to}`;
}

// ── History ───────────────────────────────────────────────────
function addHistory(expr, val) {
    calcHistory.unshift({ expr, val });
    if (calcHistory.length > 50) calcHistory.pop();
    renderHistory();
}

function renderHistory() {
    const list  = document.getElementById('historyList');
    const empty = document.getElementById('historyEmpty');
    list.querySelectorAll('.history-item').forEach(e => e.remove());

    if (calcHistory.length === 0) {
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';
    calcHistory.forEach((item, i) => {
        const div = document.createElement('div');
        div.className = 'history-item';
        div.innerHTML = `<div class="history-expr">${item.expr}</div>
                         <div class="history-val">${parseFloat(item.val.toPrecision(10))}</div>`;
        div.onclick = () => {
            current = String(item.val);
            justCalc = true;
            updateDisplay(item.val, item.expr + ' =');
        };
        list.appendChild(div);
    });
}

function clearHistory() {
    calcHistory = [];
    renderHistory();
}

// ── Mode Switch ───────────────────────────────────────────────
function setMode(mode, btn) {
    currentMode = mode;
    document.querySelectorAll('.calc-mode-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sci-pad').style.display  = mode === 'sci'  ? 'block' : 'none';
    document.getElementById('conv-pad').style.display = mode === 'conv' ? 'block' : 'none';
    clearAll();
}

// ── Keyboard Support ──────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key >= '0' && e.key <= '9')   inputNum(e.key);
    else if (e.key === '.')             inputDot();
    else if (e.key === '+')             inputOp('+');
    else if (e.key === '-')             inputOp('-');
    else if (e.key === '*')             inputOp('*');
    else if (e.key === '/')             { e.preventDefault(); inputOp('/'); }
    else if (e.key === 'Enter' || e.key === '=') calculate();
    else if (e.key === 'Backspace')     backspace();
    else if (e.key === 'Escape')        clearAll();
    else if (e.key === '%')             percent();
});
</script>
<script>
</script>
</body>
</html>
