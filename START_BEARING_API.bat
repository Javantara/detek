@echo off
title PLN Web - Bearing API
color 0A
echo ============================================
echo   PLN Web - Bearing Anomali Python API
echo ============================================
echo.

REM ── Deteksi lokasi otomatis (tidak perlu edit manual) ──────────────────
set SCRIPT_DIR=%~dp0app\jupyter\scripts

if not exist "%SCRIPT_DIR%\bearing_api.py" (
    echo [ERROR] bearing_api.py tidak ditemukan di:
    echo   %SCRIPT_DIR%
    echo.
    echo Pastikan folder pln_web sudah di-extract dengan benar.
    pause
    exit /b 1
)

cd /d "%SCRIPT_DIR%"
echo Folder aktif: %CD%
echo.

echo Memeriksa Python...
python --version
if %errorlevel% neq 0 (
    echo [ERROR] Python tidak ditemukan! Install Python 3.10+ dulu.
    pause
    exit /b 1
)

echo.
echo Memeriksa dependensi...
pip show flask >nul 2>&1
if %errorlevel% neq 0 (
    echo [INSTALL] Menginstall dependensi...
    pip install flask flask-cors sqlalchemy pymysql pandas numpy scikit-learn xgboost python-dotenv openpyxl
    if %errorlevel% neq 0 (
        echo [ERROR] Gagal install dependensi!
        pause
        exit /b 1
    )
)

echo.
echo ============================================
echo Menjalankan Bearing API di port 5050...
echo Jangan tutup window ini selama pakai aplikasi.
echo.
echo Setelah muncul "Running on http://0.0.0.0:5050":
echo   Buka browser: http://localhost/pln_web/public/
echo.
echo Tekan Ctrl+C untuk menghentikan API.
echo ============================================
echo.

python bearing_api.py

echo.
echo [API berhenti]
pause
