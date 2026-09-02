"""
bearing_api.py
==============
Flask micro-service untuk analisis anomali bearing.
Semua kalkulasi ML ada di sini (bukan di PHP).

Model yang didukung:
    - xgboost  (default, lebih akurat untuk data non-linear)
    - linear   (Linear Regression, fallback/perbandingan)

Endpoint:
    POST /analyze          — Jalankan analisis, simpan model & log ke MySQL
    POST /train            — Training model baru saja (tanpa predict)
    POST /predict          — Predict dengan model yang sudah ada (by model_id)
    GET  /models           — Daftar model tersimpan
    GET  /model/<id>       — Detail model + log anomali
    DELETE /model/<id>     — Hapus model
    GET  /csv-info         — Info semua CSV tersedia (row count, date range)
    POST /upload-csv       — Upload & proses CSV, simpan metadata ke DB

Jalankan:
    cd pln_web/app/jupyter/scripts
    python bearing_api.py
    # atau gunakan port lain:
    PORT=5001 python bearing_api.py
"""

import os
import sys
import json
import pickle
import base64
import traceback
from pathlib import Path
from datetime import datetime, date

import numpy as np
import pandas as pd
from flask import Flask, request, jsonify
from flask_cors import CORS
from sqlalchemy import create_engine, text
from dotenv import load_dotenv
from sklearn.linear_model import LinearRegression
from sklearn.metrics import r2_score, mean_absolute_error
from sklearn.preprocessing import StandardScaler

try:
    import xgboost as xgb
    XGBOOST_AVAILABLE = True
except ImportError:
    XGBOOST_AVAILABLE = False
    print("[WARN] XGBoost tidak terinstall. Jalankan: pip install xgboost>=2.0.0")

# ── Config ──────────────────────────────────────────────────────────────
_THIS_DIR    = Path(__file__).resolve().parent
_JUPYTER_DIR = _THIS_DIR.parent
_ENV_PATH    = _JUPYTER_DIR / 'config' / '.env'

load_dotenv(dotenv_path=_ENV_PATH)
load_dotenv()

MYSQL_HOST  = os.getenv('MYSQL_HOST', 'localhost')
MYSQL_PORT  = int(os.getenv('MYSQL_PORT', 3307))
MYSQL_USER  = os.getenv('MYSQL_USER', 'root')
MYSQL_PASS  = os.getenv('MYSQL_PASS', '')
MYSQL_DB    = os.getenv('MYSQL_DB', 'pln_web')

DATA_DIR    = (_JUPYTER_DIR / 'notebooks' / 'bearing' / 'data').resolve()
UPLOAD_DIR  = Path(os.getenv('UPLOAD_DIR', str(_JUPYTER_DIR.parent.parent / 'public' / 'uploads' / 'bearing')))
UPLOAD_DIR.mkdir(parents=True, exist_ok=True)
PORT        = int(os.getenv('PORT', 5050))

app = Flask(__name__)
CORS(app)  # izinkan request dari PHP

# ── DB Engine ────────────────────────────────────────────────────────────
def get_engine(db_name: str = None):
    """
    Koneksi ke database.
    db_name=None → pakai MYSQL_DB (pln_web)
    db_name='db_pacitan_2' → koneksi ke database unit spesifik
    """
    target = db_name or MYSQL_DB
    url = f"mysql+pymysql://{MYSQL_USER}:{MYSQL_PASS}@{MYSQL_HOST}:{MYSQL_PORT}/{target}?charset=utf8mb4"
    return create_engine(url, pool_pre_ping=True)

def get_unit_engine(unit_db: str):
    """Koneksi ke database unit (db_pacitan_2, db_paiton_1, dll)."""""
    return get_engine(unit_db)

# ── Helpers ──────────────────────────────────────────────────────────────

def list_csv_files(unit_id: str = '') -> dict:
    """Kembalikan dict {filename: filepath} dari direktori CSV sesuai unit."""
    result = {}
    # Kumpulkan semua direktori kandidat
    candidate_dirs = [DATA_DIR, UPLOAD_DIR]
    # Tambah subfolder unit jika unit_id diberikan
    if unit_id:
        unit_folder = UPLOAD_DIR / f'unit_{unit_id}'
        unit_folder.mkdir(parents=True, exist_ok=True)
        candidate_dirs.insert(0, unit_folder)  # prioritaskan folder unit
    # Tambah direktori relatif dari lokasi script (untuk berbagai setup XAMPP/Linux)
    for extra in [
        _THIS_DIR.parent.parent.parent / 'public' / 'uploads' / 'bearing',  # app/jupyter/scripts/../../.. 
        _THIS_DIR.parent.parent / 'public' / 'uploads' / 'bearing',
        Path(__file__).parent.parent.parent.parent / 'public' / 'uploads' / 'bearing',
    ]:
        candidate_dirs.append(extra)
    
    # Scan semua subfolder unit_* di UPLOAD_DIR
    try:
        for _sub in sorted(UPLOAD_DIR.glob('unit_*')):
            if _sub.is_dir() and _sub not in candidate_dirs:
                candidate_dirs.append(_sub)
    except Exception:
        pass

    seen_dirs = set()
    for d in candidate_dirs:
        try:
            d = d.resolve()
        except Exception:
            continue
        if d in seen_dirs or not d.exists():
            continue
        seen_dirs.add(d)
        for f in sorted(d.glob('*.csv')):
            if f.name not in result:
                result[f.name] = str(f)
    return result


def load_sensor_csv(filepaths: list) -> pd.Series:
    """
    Baca beberapa CSV sensor, gabungkan jadi satu Series (index=DatetimeIndex, value=float).
    Mendukung dua format:
      - 3 kolom: tag_id, timestamp, value  (format SCADA asli)
      - 2 kolom: timestamp, value
    """
    frames = []
    for path in filepaths:
        p = Path(path)
        if not p.exists():
            continue
        try:
            # Peek baris pertama untuk deteksi format
            with open(p, 'r', errors='replace') as fh:
                first = fh.readline().strip()
            ncols = len(first.split(','))

            if ncols >= 3:
                # Format 3-kolom: tag_id, timestamp, value
                df = pd.read_csv(p, header=None,
                                 names=['tag_id', 'ts', 'val'],
                                 usecols=['ts', 'val'])
            else:
                # Format 2-kolom: timestamp, value
                df = pd.read_csv(p, header=0,
                                 names=['ts', 'val'],
                                 usecols=[0, 1])

            df['ts']  = pd.to_datetime(df['ts'], errors='coerce')
            df['val'] = pd.to_numeric(df['val'], errors='coerce')
            df = df.dropna(subset=['ts', 'val'])
            df = df.set_index('ts')['val']
            frames.append(df)
        except Exception as e:
            print(f"[WARN] Gagal baca {path}: {e}")
    if not frames:
        return pd.Series(dtype=float)
    combined = pd.concat(frames)
    combined = combined[~combined.index.duplicated(keep='first')]
    combined = combined.sort_index()
    return combined


def _date_mask(s: pd.Series, d_start: str, d_end: str) -> pd.Series:
    """Filter Series by date range. Index harus DatetimeIndex."""
    idx = pd.to_datetime(s.index)
    s   = s.copy()
    s.index = idx
    lo = pd.Timestamp(d_start)
    hi = pd.Timestamp(d_end) + pd.Timedelta(days=1) - pd.Timedelta(seconds=1)
    return s[(s.index >= lo) & (s.index <= hi)]


def resample_daily(sx: pd.Series, sy: pd.Series, sl: pd.Series,
                   load_min: float, date_start: str, date_end: str) -> pd.DataFrame:
    """Resample ke harian untuk TRAINING — butuh x dan y keduanya."""
    sx_cut = _date_mask(sx, date_start, date_end)
    sy_cut = _date_mask(sy, date_start, date_end)
    if sx_cut.empty or sy_cut.empty:
        return pd.DataFrame(columns=['x', 'y'])
    # Resample ke harian dulu, baru filter load
    sx_d = sx_cut.resample('D').mean()
    sy_d = sy_cut.resample('D').mean()
    df = pd.DataFrame({'x': sx_d, 'y': sy_d}).dropna()
    if sl is not None and len(sl) > 0:
        sl_cut = _date_mask(sl, date_start, date_end)
        if len(sl_cut) > 0:
            sl_d = sl_cut.resample('D').mean()
            df = df.join(sl_d.rename('load'), how='left')
            df = df[df['load'].fillna(load_min) >= load_min].drop(columns=['load'])
    return df if not df.empty else pd.DataFrame(columns=['x', 'y'])


def resample_daily_pred(sx: pd.Series, sy: pd.Series, sl: pd.Series,
                        load_min: float, date_start: str, date_end: str) -> pd.DataFrame:
    """Resample ke harian untuk PREDIKSI.
    Tetap return data walau tidak ada Y aktual (pakai X saja, y=NaN).
    """
    sx_cut = _date_mask(sx, date_start, date_end)
    if sx_cut.empty:
        print(f"[WARN] Tidak ada data X di {date_start}→{date_end}")
        return pd.DataFrame(columns=['x', 'y'])
    sx_d = sx_cut.resample('D').mean().dropna()
    if sx_d.empty:
        return pd.DataFrame(columns=['x', 'y'])

    # Cek data Y aktual
    sy_d = pd.Series(dtype=float)
    if sy is not None and len(sy) > 0:
        sy_cut = _date_mask(sy, date_start, date_end)
        if len(sy_cut) > 0:
            sy_d = sy_cut.resample('D').mean()

    if len(sy_d) > 0:
        df = pd.DataFrame({'x': sx_d}).join(sy_d.rename('y'), how='left')
        # Filter load di level harian
        if sl is not None and len(sl) > 0:
            sl_cut = _date_mask(sl, date_start, date_end)
            if len(sl_cut) > 0:
                sl_d = sl_cut.resample('D').mean()
                df = df.join(sl_d.rename('load'), how='left')
                # Hari tanpa data load → tetap masuk (jangan drop)
                mask_load = df['load'].isna() | (df['load'] >= load_min)
                df = df[mask_load].drop(columns=['load'])
        df = df[df['x'].notna()]
        if not df.empty:
            n_aktual = int(df['y'].notna().sum())
            print(f"[INFO] Pred range {date_start}→{date_end}: {n_aktual} hari punya aktual dari {len(df)} hari")
            return df

    # X-only: tidak ada data Y di range ini
    result = pd.DataFrame({'x': sx_d, 'y': np.nan}, index=sx_d.index)
    print(f"[INFO] X-only prediction: {len(result)} hari ({date_start}→{date_end})")
    return result


# ── Model Training ───────────────────────────────────────────────────────

def train_model_xgboost(daily: pd.DataFrame):
    """
    Fit XGBoost Regressor pada daily DataFrame.
    XGBoost menangkap hubungan non-linear suhu ruangan → suhu bearing.
    Returns: model, scaler, r2, mae, n
    """
    if len(daily) < 3:
        return None, None, 0.0, 0.0, 0
    if not XGBOOST_AVAILABLE:
        raise RuntimeError("XGBoost tidak terinstall. Jalankan: pip install xgboost>=2.0.0")

    X_raw = daily[['x']].values
    y     = daily['y'].values

    # Normalisasi fitur (lebih stabil untuk XGBoost)
    scaler = StandardScaler()
    X = scaler.fit_transform(X_raw)

    model = xgb.XGBRegressor(
        n_estimators     = 200,
        max_depth        = 4,
        learning_rate    = 0.05,
        subsample        = 0.8,
        colsample_bytree = 1.0,
        reg_alpha        = 0.1,
        reg_lambda       = 1.0,
        random_state     = 42,
        verbosity        = 0,
    )
    model.fit(X, y)

    y_pred = model.predict(X)
    r2  = float(r2_score(y, y_pred))
    mae = float(mean_absolute_error(y, y_pred))
    n   = len(daily)

    return model, scaler, r2, mae, n


def train_model_linear(daily: pd.DataFrame):
    """
    Fit LinearRegression sebagai pembanding/fallback.
    Returns: model, coef_a, coef_b, r2, mae, n
    """
    if len(daily) < 2:
        return None, 0.0, 0.0, 0.0, 0.0, 0

    X = daily[['x']].values
    y = daily['y'].values

    model = LinearRegression()
    model.fit(X, y)

    y_pred = model.predict(X)
    r2   = float(r2_score(y, y_pred))
    mae  = float(mean_absolute_error(y, y_pred))
    a    = float(model.coef_[0])
    b    = float(model.intercept_)
    n    = len(daily)

    return model, a, b, r2, mae, n


def serialize_model(model, scaler=None) -> str:
    """Serialisasi model ke base64 string untuk disimpan di DB."""
    obj = {'model': model, 'scaler': scaler}
    return base64.b64encode(pickle.dumps(obj)).decode('utf-8')


def deserialize_model(blob: str):
    """Deserialkan model dari base64 string."""
    obj = pickle.loads(base64.b64decode(blob.encode('utf-8')))
    return obj['model'], obj.get('scaler')


# ── Predict & Anomali ────────────────────────────────────────────────────

def predict_xgboost(model, scaler, daily_pred: pd.DataFrame) -> np.ndarray:
    """Prediksi dengan XGBoost model."""
    X_raw = daily_pred[['x']].values
    X = scaler.transform(X_raw) if scaler is not None else X_raw
    return model.predict(X)


def build_result(daily_pred: pd.DataFrame, model_type: str,
                 model, scaler, batas: float,
                 coef_a: float = 0.0, coef_b: float = 0.0) -> dict:
    """
    Bangun hasil prediksi + deteksi anomali.
    Mendukung model_type: 'xgboost' atau 'linear'.
    Returns dict for JSON response.
    """
    if daily_pred.empty:
        return {
            'dates': [], 'aktual': [], 'prediksi': [], 'deviasi': [],
            'anomali': [], 'n_anom': 0, 'n_total': 0, 'pct': 0.0, 'mae': 0.0,
            'model_type': model_type,
            'coef_a': round(coef_a, 4), 'coef_b': round(coef_b, 4),
        }

    dates    = [str(d.date()) for d in daily_pred.index]
    # aktual bisa NaN jika data Y tidak ada di range prediksi
    aktual   = [round(float(v), 2) if not pd.isna(v) else None
                for v in daily_pred['y'].tolist()]

    if model_type == 'xgboost' and model is not None:
        pred_arr = predict_xgboost(model, scaler, daily_pred)
        prediksi = [round(float(p), 2) for p in pred_arr]
    else:
        # Linear fallback: y = a*x + b
        prediksi = (coef_a * daily_pred['x'] + coef_b).round(2).tolist()

    # Deviasi dan anomali hanya dihitung jika ada data aktual
    deviasi  = [round(a_ - p_, 2) if a_ is not None else None for a_, p_ in zip(aktual, prediksi)]
    anomali  = [bool(abs(d_) > batas) if d_ is not None else False for d_ in deviasi]
    n_anom   = sum(anomali)
    n_total  = len(dates)
    mae_pred = round(float(np.mean(np.abs(deviasi))), 2) if deviasi else 0.0

    # Tambah nilai suhu ruangan (x) dan deteksi gap
    x_vals = daily_pred['x'].round(2).tolist() if 'x' in daily_pred.columns else []

    # Gap detection: cari hari yang tidak ada data dalam rentang tanggal
    gaps = []
    if len(dates) >= 2:
        all_days = pd.date_range(daily_pred.index[0], daily_pred.index[-1], freq='D')
        data_days = set(daily_pred.index.date)
        missing = [d for d in all_days if d.date() not in data_days]
        # Kelompokkan missing days yang berurutan
        if missing:
            group_start = missing[0]
            group_end   = missing[0]
            for d in missing[1:]:
                if (d - group_end).days == 1:
                    group_end = d
                else:
                    if (group_end - group_start).days >= 2:  # min 3 hari dianggap gap
                        gaps.append({'start': str(group_start.date()), 'end': str(group_end.date()),
                                     'days': (group_end - group_start).days + 1})
                    group_start = group_end = d
            if (group_end - group_start).days >= 2:
                gaps.append({'start': str(group_start.date()), 'end': str(group_end.date()),
                             'days': (group_end - group_start).days + 1})

    return {
        'dates':      dates,
        'aktual':     aktual,
        'prediksi':   prediksi,
        'deviasi':    deviasi,
        'anomali':    anomali,
        'x_values':   x_vals,
        'gaps':       gaps,
        'n_anom':     n_anom,
        'n_total':    n_total,
        'pct':        round(n_anom / n_total * 100, 1) if n_total else 0.0,
        'mae':        mae_pred,
        'model_type': model_type,
        'coef_a':     round(coef_a, 4),
        'coef_b':     round(coef_b, 4),
    }


# ── DB Save ──────────────────────────────────────────────────────────────

def save_model_to_db(engine, params: dict, bearing_label: str,
                     r2: float, mae: float, n: int,
                     model_type: str, model_blob: str,
                     coef_a: float = 0.0, coef_b: float = 0.0) -> int:
    """Simpan model ke tabel bearing_models dengan plant_id & unit_id. Returns model_id."""
    with engine.begin() as conn:
        result = conn.execute(text("""
            INSERT INTO bearing_models
                (model_name, bearing_label, files_x, files_y, files_load,
                 train_start, train_end, load_min, batas,
                 coef_a, coef_b, r2_score, mae_train, n_train,
                 model_type, model_blob,
                 plant_id, unit_id,
                 created_by, notes)
            VALUES
                (:model_name, :bearing_label, :files_x, :files_y, :files_load,
                 :train_start, :train_end, :load_min, :batas,
                 :coef_a, :coef_b, :r2_score, :mae_train, :n_train,
                 :model_type, :model_blob,
                 :plant_id, :unit_id,
                 :created_by, :notes)
        """), {
            'model_name':    params.get('model_name', f"Model {bearing_label} {datetime.now().strftime('%Y-%m-%d %H:%M')}"),
            'bearing_label': bearing_label,
            'files_x':       ','.join(params.get('files_x', [])),
            'files_y':       ','.join(params.get('files_y', [])),
            'files_load':    ','.join(params.get('files_load', [])),
            'train_start':   params['train_start'],
            'train_end':     params['train_end'],
            'load_min':      params.get('load_min', 100),
            'batas':         params.get('batas', 5),
            'coef_a':        coef_a,
            'coef_b':        coef_b,
            'r2_score':      r2,
            'mae_train':     mae,
            'n_train':       n,
            'model_type':    model_type,
            'model_blob':    model_blob,
            'plant_id':      params.get('plant_id', None),
            'unit_id':       params.get('unit_id',  None),
            'created_by':    params.get('created_by', 'web'),
            'notes':         params.get('notes', ''),
        })
        return result.lastrowid


def save_anomaly_log(engine, model_id: int, result: dict,
                     pred_start: str, pred_end: str,
                     bearing_label: str = '', batas: float = 5.0):
    """Simpan log anomali: tag, datetime, Actual, prediksi, prediksi_high, prediksi_low."""
    if not result['dates']:
        return
    rows = []
    for i, d in enumerate(result['dates']):
        pv = result['prediksi'][i]
        rows.append({
            'model_id':      model_id,
            'pred_start':    pred_start,
            'pred_end':      pred_end,
            'sensor_date':   d,
            'tag':           bearing_label,
            'value_actual':  result['aktual'][i],
            'value_pred':    pv,
            'prediksi_high': round(pv + batas, 2) if pv is not None else None,
            'prediksi_low':  round(pv - batas, 2) if pv is not None else None,
            'deviation':     result['deviasi'][i],
            'is_anomaly':    1 if result['anomali'][i] else 0,
        })
    with engine.begin() as conn:
        try:
            conn.execute(text("""
                INSERT INTO bearing_anomaly_log
                    (model_id, pred_start, pred_end, sensor_date, tag,
                     value_actual, value_pred, prediksi_high, prediksi_low,
                     deviation, is_anomaly)
                VALUES
                    (:model_id, :pred_start, :pred_end, :sensor_date, :tag,
                     :value_actual, :value_pred, :prediksi_high, :prediksi_low,
                     :deviation, :is_anomaly)
            """), rows)
        except Exception:
            # Fallback jika kolom baru belum ada
            rows2 = [{k: v for k, v in r.items() if k in (
                'model_id','pred_start','pred_end','sensor_date',
                'value_actual','value_pred','deviation','is_anomaly')} for r in rows]
            conn.execute(text("""
                INSERT INTO bearing_anomaly_log
                    (model_id, pred_start, pred_end, sensor_date,
                     value_actual, value_pred, deviation, is_anomaly)
                VALUES
                    (:model_id, :pred_start, :pred_end, :sensor_date,
                     :value_actual, :value_pred, :deviation, :is_anomaly)
            """), rows2)


# ── Routes ───────────────────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status':            'ok',
        'time':              datetime.now().isoformat(),
        'xgboost_available': XGBOOST_AVAILABLE,
    })


@app.route('/csv-info', methods=['GET'])
def csv_info():
    """Kembalikan info semua CSV (nama, ukuran, date range)."""
    unit_id = request.args.get('unit_id', '')
    unit_db = request.args.get('unit_db', '')
    files = list_csv_files(unit_id=unit_id)
    result = []
    for name, path in files.items():
        p = Path(path)
        info = {
            'name':      name,
            'path':      path,
            'size_kb':   round(p.stat().st_size / 1024, 1) if p.exists() else 0,
            'source':    'upload' if str(UPLOAD_DIR) in path else 'data',
            'date_min':  None,
            'date_max':  None,
            'row_count': 0,
        }
        try:
            with open(path, 'r', errors='replace') as fh2:
                _fl = fh2.readline().strip()
            _nc = len(_fl.split(','))
            _ci = 1 if _nc >= 3 else 0
            df = pd.read_csv(path, header=None, usecols=[_ci], names=['ts'])
            df['ts'] = pd.to_datetime(df['ts'], errors='coerce')
            df = df.dropna()
            if not df.empty:
                info['date_min']  = str(df['ts'].min().date())
                info['date_max']  = str(df['ts'].max().date())
                info['row_count'] = len(df)
        except Exception:
            pass
        result.append(info)
    return jsonify({'files': result})


@app.route('/analyze', methods=['POST'])
def analyze():
    """
    Analisis penuh: training + prediksi + simpan ke MySQL.
    Body JSON:
        files_x      : ["877-idf2b-2025a.csv", ...]
        files_y1     : ["858-idf2b-2025a.csv"] | []
        files_y2     : ["859-idf2b-2025a.csv"] | []
        files_ref    : ["336-idf2b-2025a.csv"] | []   (sensor pembanding)
        files_load   : ["1577-idf2b-2025a.csv"] | []
        train_start  : "2025-01-01"
        train_end    : "2025-06-30"
        pred_start   : "2025-01-01"
        pred_end     : "2025-12-31"
        batas        : 5.0
        load_min     : 100.0
        bearing      : "BOTH" | "B1" | "B2"
        model_type   : "xgboost" | "linear"   (default: xgboost)
        save_model   : true | false
        model_name   : "nama opsional"
        created_by   : "user123"
    """
    try:
        p = request.get_json(force=True) or {}

        unit_id_str = str(p.get('unit_id', '')) if p.get('unit_id') else ''
        csv_map = list_csv_files(unit_id=unit_id_str)
        def resolve(names):
            return [csv_map[n] for n in (names or []) if n in csv_map]

        paths_x    = resolve(p.get('files_x',    []))
        paths_y1   = resolve(p.get('files_y1',   []))
        paths_y2   = resolve(p.get('files_y2',   []))
        paths_load = resolve(p.get('files_load', []))
        paths_ref  = resolve(p.get('files_ref',  []))

        if not paths_x:
            return jsonify({'success': False, 'error': 'files_x kosong atau tidak ditemukan'}), 400
        if not paths_y1 and not paths_y2:
            return jsonify({'success': False, 'error': 'Butuh minimal files_y1 atau files_y2'}), 400

        train_start = p.get('train_start', '')
        train_end   = p.get('train_end',   '')
        pred_start  = p.get('pred_start',  train_start)
        pred_end    = p.get('pred_end',    train_end)
        batas       = float(p.get('batas',    5.0))
        load_min    = float(p.get('load_min', 100.0))
        bearing     = p.get('bearing', 'BOTH')
        save_model  = bool(p.get('save_model', True))
        model_type  = p.get('model_type', 'xgboost')

        # Fallback ke linear jika XGBoost tidak tersedia
        if model_type == 'xgboost' and not XGBOOST_AVAILABLE:
            model_type = 'linear'
            print("[WARN] XGBoost tidak tersedia, fallback ke Linear Regression")

        sx    = load_sensor_csv(paths_x)
        sy1   = load_sensor_csv(paths_y1) if paths_y1 else pd.Series(dtype=float)
        sy2   = load_sensor_csv(paths_y2) if paths_y2 else pd.Series(dtype=float)
        sl    = load_sensor_csv(paths_load) if paths_load else pd.Series(dtype=float)
        sr    = load_sensor_csv(paths_ref)  if paths_ref  else pd.Series(dtype=float)

        # Siapkan data ref (336) harian jika tersedia
        ref_daily = {}
        if len(sr) > 0:
            sr_cut = _date_mask(sr, pred_start, pred_end)
            if len(sr_cut) > 0:
                sr_daily = sr_cut.resample('D').mean().dropna()
                ref_daily = {
                    'dates':  [str(d.date()) for d in sr_daily.index],
                    'values': sr_daily.round(2).tolist(),
                }

        response = {
            'success':    True,
            'model_type': model_type,
            'b1': None, 'b2': None,
            'model_b1': None, 'model_b2': None,
            'ref': ref_daily if ref_daily else None,
        }

        unit_db = p.get('unit_db', '')  # nama db unit, misal 'db_pacitan_2'
        engine = get_unit_engine(unit_db) if (save_model and unit_db) else (get_engine() if save_model else None)

        def process_bearing(paths_y, bearing_label, files_y_key):
            sy_local    = load_sensor_csv(paths_y)
            daily_train = resample_daily(sx, sy_local, sl, load_min, train_start, train_end)

            if len(daily_train) < 2:
                return {'dates':[],'aktual':[],'prediksi':[],'deviasi':[],'anomali':[],
                        'n_anom':0,'n_total':0,'pct':0.0,'mae':0.0,'model_type':model_type,
                        'coef_a':0.0,'coef_b':0.0},                        {'r2':0.0,'mae':0.0,'n':0,'model_type':model_type,'coef_a':0.0,'coef_b':0.0}

            actual_type = model_type
            if model_type == 'xgboost':
                mdl, scaler, r2, mae, n = train_model_xgboost(daily_train)
                if mdl is None:  # fallback ke linear jika data kurang
                    actual_type = 'linear'
                    mdl, coef_a, coef_b, r2, mae, n = train_model_linear(daily_train)
                    scaler = None
                    blob = serialize_model(mdl) if mdl else ''
                else:
                    coef_a, coef_b = 0.0, 0.0
                    blob = serialize_model(mdl, scaler)
            else:
                actual_type = 'linear'
                mdl, coef_a, coef_b, r2, mae, n = train_model_linear(daily_train)
                scaler = None
                blob = serialize_model(mdl) if mdl else ''

            # Prediksi: tetap render walau tidak ada data Y aktual (x-only prediction)
            daily_pred = resample_daily_pred(sx, sy_local, sl, load_min, pred_start, pred_end)
            result     = build_result(daily_pred, actual_type, mdl, scaler, batas, coef_a, coef_b)

            model_info = {
                'r2': round(r2, 4), 'mae': round(mae, 2), 'n': n,
                'model_type': actual_type,
                'coef_a': round(coef_a, 4), 'coef_b': round(coef_b, 4),
            }

            if save_model and engine and mdl is not None:
                params_db = {**p, 'files_y': p.get(files_y_key, [])}
                mid = save_model_to_db(
                    engine, params_db, bearing_label,
                    r2, mae, n, actual_type, blob, coef_a, coef_b
                )
                save_anomaly_log(engine, mid, result, pred_start, pred_end, bearing_label, batas)
                model_info['model_id'] = mid

            return result, model_info

        # ── Bearing 1 (Y1) ──────────────────────────────────────
        if paths_y1 and bearing in ('BOTH', 'B1'):
            response['b1'], response['model_b1'] = process_bearing(paths_y1, 'Y1', 'files_y1')

        # ── Bearing 2 (Y2) ──────────────────────────────────────
        if paths_y2 and bearing in ('BOTH', 'B2'):
            response['b2'], response['model_b2'] = process_bearing(paths_y2, 'Y2', 'files_y2')

        return jsonify(response)

    except Exception as e:
        traceback.print_exc()
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/predict', methods=['POST'])
def predict():
    """
    Predict pakai model yang sudah tersimpan di DB (by model_id).
    Body JSON:
        model_id_b1 : 3        (opsional)
        model_id_b2 : 4        (opsional)
        pred_start  : "2026-01-01"
        pred_end    : "2026-03-31"
        batas       : 5.0      (override toleransi)
    """
    try:
        p      = request.get_json(force=True) or {}
        unit_db = p.get('unit_db', '')
        engine = get_unit_engine(unit_db) if unit_db else get_engine()

        def load_model_from_db(model_id):
            with engine.connect() as conn:
                row = conn.execute(
                    text("SELECT * FROM bearing_models WHERE model_id = :id"),
                    {'id': model_id}
                ).fetchone()
            if not row:
                raise ValueError(f"model_id {model_id} tidak ditemukan")
            return dict(row._mapping)

        unit_id_str = str(p.get('unit_id', '')) if p.get('unit_id') else ''
        csv_map = list_csv_files(unit_id=unit_id_str)
        def resolve(names_str):
            names = [n.strip() for n in (names_str or '').split(',') if n.strip()]
            return [csv_map[n] for n in names if n in csv_map]

        pred_start = p.get('pred_start')
        pred_end   = p.get('pred_end')
        response   = {'success': True, 'b1': None, 'b2': None}

        for key, label in [('model_id_b1', 'b1'), ('model_id_b2', 'b2')]:
            mid = p.get(key)
            if not mid:
                continue
            m          = load_model_from_db(int(mid))
            paths_x    = resolve(m['files_x'])
            paths_y    = resolve(m['files_y'])
            paths_load = resolve(m.get('files_load', ''))
            batas      = float(p.get('batas', m['batas']))
            load_min   = float(m['load_min'])
            mtype      = m.get('model_type', 'linear')

            sx = load_sensor_csv(paths_x)
            sy = load_sensor_csv(paths_y)
            sl = load_sensor_csv(paths_load)

            daily_pred = resample_daily(sx, sy, sl, load_min, pred_start, pred_end)

            # Load model dari blob jika XGBoost, atau gunakan koefisien linear
            mdl, scaler = None, None
            blob = m.get('model_blob', '')
            if blob and mtype == 'xgboost':
                try:
                    mdl, scaler = deserialize_model(blob)
                except Exception as e:
                    print(f"[WARN] Gagal load XGBoost model: {e}, fallback ke linear")
                    mtype = 'linear'

            result = build_result(
                daily_pred, mtype, mdl, scaler, batas,
                float(m.get('coef_a', 0)), float(m.get('coef_b', 0))
            )
            result['model'] = {
                'model_id': mid, 'model_type': mtype,
                'r2': m['r2_score'], 'n': m['n_train'],
                'coef_a': m.get('coef_a', 0), 'coef_b': m.get('coef_b', 0),
            }
            response[label] = result
            save_anomaly_log(engine, int(mid), result, pred_start, pred_end)

        return jsonify(response)

    except Exception as e:
        traceback.print_exc()
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/models', methods=['GET'])
def get_models():
    """Daftar semua model tersimpan."""
    try:
        unit_db = request.args.get('unit_db', '')
        engine = get_unit_engine(unit_db) if unit_db else get_engine()
        with engine.connect() as conn:
            rows = conn.execute(text("""
                SELECT m.model_id, m.model_name, m.bearing_label, m.model_type,
                       m.train_start, m.train_end, m.batas, m.load_min,
                       m.coef_a, m.coef_b, m.r2_score, m.mae_train, m.n_train,
                       m.created_at, m.created_by, m.notes,
                       COUNT(DISTINCT DATE(l.sensor_date)) AS total_logged_days,
                       SUM(l.is_anomaly) AS total_anomalies
                FROM bearing_models m
                LEFT JOIN bearing_anomaly_log l ON l.model_id = m.model_id
                WHERE m.is_active = 1
                GROUP BY m.model_id
                ORDER BY m.created_at DESC
            """)).fetchall()
        models = [dict(r._mapping) for r in rows]
        for m in models:
            for k, v in m.items():
                if isinstance(v, (date, datetime)):
                    m[k] = str(v)
        return jsonify({'success': True, 'models': models})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/model/<int:model_id>', methods=['GET'])
def get_model(model_id):
    """Detail model + log anomali terakhir."""
    try:
        unit_db = request.args.get('unit_db', '')
        engine = get_unit_engine(unit_db) if unit_db else get_engine()
        with engine.connect() as conn:
            m = conn.execute(
                text("""SELECT model_id, model_name, bearing_label, model_type,
                               train_start, train_end, batas, load_min,
                               coef_a, coef_b, r2_score, mae_train, n_train,
                               created_at, created_by, notes
                        FROM bearing_models WHERE model_id = :id"""),
                {'id': model_id}
            ).fetchone()
            if not m:
                return jsonify({'success': False, 'error': 'Model tidak ditemukan'}), 404

            logs = conn.execute(text("""
                SELECT sensor_date, value_actual, value_pred, deviation, is_anomaly
                FROM bearing_anomaly_log
                WHERE model_id = :id
                ORDER BY sensor_date DESC
                LIMIT 500
            """), {'id': model_id}).fetchall()

        model_dict = dict(m._mapping)
        for k, v in model_dict.items():
            if isinstance(v, (date, datetime)):
                model_dict[k] = str(v)

        log_list = []
        for l in logs:
            row = dict(l._mapping)
            for k, v in row.items():
                if isinstance(v, (date, datetime)):
                    row[k] = str(v)
            log_list.append(row)

        return jsonify({'success': True, 'model': model_dict, 'logs': log_list})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/model/<int:model_id>', methods=['DELETE'])
def delete_model(model_id):
    """Soft delete model."""
    try:
        unit_db = request.args.get('unit_db', '')
        engine = get_unit_engine(unit_db) if unit_db else get_engine()
        with engine.begin() as conn:
            conn.execute(
                text("UPDATE bearing_models SET is_active = 0 WHERE model_id = :id"),
                {'id': model_id}
            )
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/upload-csv', methods=['POST'])
def upload_csv():
    """
    Terima file CSV, simpan ke UPLOAD_DIR, update metadata ke DB.
    Form fields: f_x, f_y1, f_y2, f_load
    """
    uploaded = []
    errors   = []

    for slot in ['f_x', 'f_y1', 'f_y2', 'f_load']:
        f = request.files.get(slot)
        if not f or not f.filename:
            continue
        if not f.filename.lower().endswith('.csv'):
            errors.append(f"{f.filename} bukan CSV")
            continue
        dest = UPLOAD_DIR / f.filename
        try:
            f.save(str(dest))
            uploaded.append(f.filename)
            _upsert_csv_meta(f.filename, str(dest))
        except Exception as e:
            errors.append(f"Gagal simpan {f.filename}: {e}")

    all_files = [
        {'name': n, 'source': 'upload' if str(UPLOAD_DIR) in v else 'data'}
        for n, v in list_csv_files().items()
    ]

    return jsonify({
        'success':  len(errors) == 0,
        'uploaded': uploaded,
        'errors':   errors,
        'files':    all_files,
    })


def _upsert_csv_meta(filename: str, filepath: str):
    """Simpan/update metadata CSV ke DB — selalu absolute path."""
    try:
        engine = get_engine()
        p = Path(filepath).resolve()
        size = p.stat().st_size
        with open(str(p), 'r', errors='replace') as fh:
            first_line = fh.readline().strip()
        _ncols = len(first_line.split(',')) if first_line else 1
        _col_idx = 1 if _ncols >= 3 else 0
        df = pd.read_csv(str(p), header=None, usecols=[_col_idx], names=['ts'])
        df['ts'] = pd.to_datetime(df['ts'], errors='coerce')
        df = df.dropna()
        dmin  = str(df['ts'].min().date()) if not df.empty else None
        dmax  = str(df['ts'].max().date()) if not df.empty else None
        nrows = len(df)
        # Simpan path absolut dengan forward slash (Windows & Linux)
        abs_path = str(p).replace('\\', '/').replace('\\\\', '/')
        with engine.begin() as conn:
            conn.execute(text("""
                INSERT INTO bearing_csv_files
                    (filename, filepath, file_size, row_count, date_min, date_max)
                VALUES
                    (:fn, :fp, :sz, :nr, :dmin, :dmax)
                ON DUPLICATE KEY UPDATE
                    filepath=VALUES(filepath), file_size=VALUES(file_size),
                    row_count=VALUES(row_count), date_min=VALUES(date_min),
                    date_max=VALUES(date_max), uploaded_at=NOW()
            """), {'fn': filename, 'fp': abs_path, 'sz': size,
                   'nr': nrows, 'dmin': dmin, 'dmax': dmax})
        print(f"[INFO] CSV meta OK: {filename} | {nrows} baris | {dmin} – {dmax}")
    except Exception as e:
        print(f"[WARN] Gagal simpan metadata CSV {filename}: {e}")


# ── Main ─────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    print(f"Bearing API berjalan di http://localhost:{PORT}")
    print(f"   Model default : {'XGBoost' if XGBOOST_AVAILABLE else 'Linear Regression (XGBoost tidak terinstall)'}")
    print(f"   DATA_DIR      : {DATA_DIR}")
    print(f"   UPLOAD_DIR    : {UPLOAD_DIR}")
    print(f"   MySQL         : {MYSQL_HOST}:{MYSQL_PORT}/{MYSQL_DB}")
    app.run(host='0.0.0.0', port=PORT, debug=True)
