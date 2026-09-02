@echo off
setlocal EnableExtensions EnableDelayedExpansion
TITLE IMPORT AKTUAL JAN-DESEMBER 2025 - PLN WEB
cd /d "%~dp0"
echo ============================================================
echo IMPORT DATA AKTUAL JAN - DESEMBER 2025 TANPA PYTHON
echo Target database : db_pacitan_1
echo Target tabel    : aktual
echo ============================================================
echo.
set "DATA_ZIP=D:\Magang\deteksi_anomali\data_2025_pisah.zip"
if not exist "%DATA_ZIP%" (
    echo File data belum ketemu di:
    echo %DATA_ZIP%
    echo.
    set /p DATA_ZIP=Paste path ZIP data_2025_pisah.zip: 
)
if not exist "%DATA_ZIP%" (
    echo ERROR: File ZIP tetap tidak ketemu.
    pause
    exit /b 1
)
C:\xampp\php\php.exe database\DB_PACITAN_1_FINAL\03_IMPORT_AKTUAL_DARI_ZIP.php "%DATA_ZIP%"
echo.
echo SELESAI. Cek: SELECT COUNT(*) FROM aktual;
pause
