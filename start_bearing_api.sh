#!/bin/bash
# PLN Web - Bearing API Starter (Linux/Mac)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/app/jupyter/scripts" && pwd)"

echo "============================================"
echo "  PLN Web - Bearing Anomali Python API"
echo "============================================"
echo ""

if [ ! -f "$SCRIPT_DIR/bearing_api.py" ]; then
    echo "[ERROR] bearing_api.py tidak ditemukan di: $SCRIPT_DIR"
    exit 1
fi

cd "$SCRIPT_DIR"
echo "Folder aktif: $(pwd)"
echo ""

# Cek dependensi
python3 -c "import flask" 2>/dev/null || {
    echo "[INSTALL] Menginstall dependensi..."
    pip3 install flask flask-cors sqlalchemy pymysql pandas numpy scikit-learn xgboost python-dotenv openpyxl
}

echo ""
echo "Menjalankan Bearing API di port 5050..."
echo "Tekan Ctrl+C untuk berhenti."
echo ""

python3 bearing_api.py
