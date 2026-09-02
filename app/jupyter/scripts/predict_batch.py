import json
import os
import sys
import warnings

os.environ.setdefault('OMP_NUM_THREADS', '1')
os.environ.setdefault('OPENBLAS_NUM_THREADS', '1')
os.environ.setdefault('MKL_NUM_THREADS', '1')
warnings.filterwarnings('ignore')

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.abspath(os.path.join(BASE_DIR, '..', 'models', 'model_pln.h5'))

try:
    import joblib
except Exception as exc:
    print(json.dumps({
        'success': False,
        'error': f'joblib belum terinstall: {exc}. Jalankan: python -m pip install joblib xgboost scikit-learn numpy'
    }))
    sys.exit(1)

try:
    if len(sys.argv) < 2:
        raise ValueError('File input JSON belum dikirim. Contoh: python predict_batch.py input.json')

    input_path = sys.argv[1]
    with open(input_path, 'r', encoding='utf-8') as f:
        payload = json.load(f)

    values = payload.get('values', payload if isinstance(payload, list) else [])
    values = [float(v) for v in values]

    if not os.path.exists(MODEL_PATH):
        raise FileNotFoundError(f'File model tidak ditemukan: {MODEL_PATH}')

    model = joblib.load(MODEL_PATH)
    rows = [[v] for v in values]
    preds = model.predict(rows) if rows else []

    print(json.dumps({
        'success': True,
        'model_path': MODEL_PATH,
        'count': len(values),
        'predictions': [float(x) for x in preds]
    }))
except Exception as exc:
    print(json.dumps({
        'success': False,
        'error': str(exc),
        'model_path': MODEL_PATH,
        'hint': 'Pastikan dependency sudah terinstall: python -m pip install joblib xgboost scikit-learn numpy'
    }))
    sys.exit(1)
