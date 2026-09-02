import json
import os
import sys
import warnings

# Biar model XGBoost/joblib tidak berat karena kebanyakan thread di laptop/XAMPP.
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
        raise ValueError('Nilai input belum dikirim. Contoh: python predict_once.py 85')

    nilai = float(sys.argv[1])

    if not os.path.exists(MODEL_PATH):
        raise FileNotFoundError(f'File model tidak ditemukan: {MODEL_PATH}')

    model = joblib.load(MODEL_PATH)
    hasil = model.predict([[nilai]])

    print(json.dumps({
        'success': True,
        'model_path': MODEL_PATH,
        'nilai_aktual': nilai,
        'nilai_prediksi': float(hasil[0])
    }))
except Exception as exc:
    print(json.dumps({
        'success': False,
        'error': str(exc),
        'model_path': MODEL_PATH,
        'hint': 'Pastikan dependency sudah terinstall: python -m pip install joblib xgboost scikit-learn numpy'
    }))
    sys.exit(1)
