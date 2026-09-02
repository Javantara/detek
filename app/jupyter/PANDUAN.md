# 📘 PANDUAN LENGKAP — Jupyter Parameter Monitoring PLN

Dokumen ini panduan step-by-step dari nol sampai Jupyter bisa jalan dan terhubung ke MySQL.

---

## 📁 Struktur Folder

```
pln_web/
└── app/
    └── jupyter/                  ← Semua file Jupyter di sini
        ├── requirements.txt      ← Daftar library Python yang dibutuhkan
        ├── PANDUAN.md            ← File ini
        ├── config/
        │   ├── .env.example      ← Template konfigurasi
        │   └── .env              ← Buat sendiri (jangan di-commit ke git)
        ├── notebooks/
        │   ├── 01_setup_koneksi.ipynb        ← Cek koneksi DB
        │   ├── 02_analisis_parameter.ipynb   ← Grafik & analisis
        │   ├── 03_import_csv.ipynb           ← Import CSV/Excel
        │   ├── 04_kirim_data_ke_web.ipynb    ← Push data ke DB/API
        │   └── 05_parameter_monitoring.ipynb ← Monitoring lengkap + ML
        ├── scripts/
        │   └── db_connector.py   ← Helper koneksi DB (dipakai semua notebook)
        └── data/                 ← Taruh file CSV/Excel di sini
```

---

## 🔧 LANGKAH 1 — Install Python

### Windows
1. Download **Python 3.11** dari https://www.python.org/downloads/
2. Saat install, **centang "Add Python to PATH"** (penting!)
3. Klik Install Now
4. Buka **Command Prompt**, test:
   ```
   python --version
   ```
   Harusnya muncul `Python 3.11.x`

---

## ⚙️ LANGKAH 2 — Install Library yang Dibutuhkan

Buka **Command Prompt**, masuk ke folder `app/jupyter/`, lalu jalankan:

```bash
cd path\ke\pln_web\app\jupyter

pip install -r requirements.txt
```

Proses ini akan menginstall semua library sekaligus (pandas, plotly, scikit-learn, dll).
Tunggu sampai selesai (~2-5 menit tergantung koneksi internet).

---

## ⚙️ LANGKAH 3 — Buat File .env

1. Buka folder `app/jupyter/config/`
2. Salin file `.env.example` → namakan `.env`
3. Edit `.env` sesuai setup MySQL kamu:

```env
MYSQL_HOST=localhost
MYSQL_PORT=3307
MYSQL_USER=root
MYSQL_PASS=
MYSQL_DB=pln_web
PLN_API_URL=http://localhost/pln_web/public/
PLN_API_KEY=
```

> **Catatan `MYSQL_HOST`:**
> - Kalau MySQL ada di komputer yang sama → pakai `localhost`
> - Kalau MySQL ada di server lain → pakai IP server tersebut (misal `192.168.1.10`)

---

## 🚀 LANGKAH 4 — Jalankan Jupyter

Buka **Command Prompt**, masuk ke folder `app/jupyter/`, lalu jalankan:

```bash
cd path\ke\pln_web\app\jupyter

jupyter lab
```

Browser akan otomatis terbuka menampilkan **Jupyter Lab**.
Kalau tidak terbuka otomatis, buka manual di browser: **http://localhost:8888**

> **Catatan:** Jangan tutup jendela Command Prompt selama Jupyter dipakai.
> Untuk menghentikan Jupyter, tekan `Ctrl+C` di Command Prompt.

---

## 📓 LANGKAH 5 — Jalankan Notebook Pertama

1. Di sidebar Jupyter, buka folder `notebooks/`
2. Buka `01_setup_koneksi.ipynb`
3. Klik **Run All** (tombol ▶▶ di toolbar) atau jalankan tiap cell satu-satu dengan **Shift+Enter**
4. Kalau muncul ✅ di setiap cell → koneksi berhasil

### Kalau gagal koneksi ke MySQL:
```
❌ Gagal: ...Connection refused...
```
Cek:
- MySQL sudah jalan? (buka phpMyAdmin, kalau bisa akses berarti MySQL jalan)
- Port benar? Default XAMPP = 3306, kalau pakai port custom cek di XAMPP → Config
- Pastikan `MYSQL_HOST=localhost` di file `.env`

---

## 📊 LANGKAH 6 — Analisis & Monitoring Parameter

Buka `05_parameter_monitoring.ipynb` untuk fitur lengkap:

1. Set konfigurasi di Cell 2:
   ```python
   UNIT_ID       = 1             # sesuaikan dengan unit_id di database
   TAG_ID        = 85            # tag sensor yang mau dilihat
   DATE_FROM     = '2026-01-01'
   DATE_TO       = '2026-02-27'
   RESAMPLE      = '1h'          # agregasi: None=raw, '5min', '1h', '1D'
   BATAS_ATAS    = None          # opsional, misal: 150.0
   BATAS_BAWAH   = None          # opsional, misal: 50.0
   FORECAST_HARI = 7             # prediksi ML berapa hari ke depan
   ```
2. Klik **Run All** → semua tabel dan grafik muncul otomatis

### Fitur yang tersedia di `05_parameter_monitoring.ipynb`:

| Cell | Fungsi |
|------|--------|
| Cell 3 | 📋 Daftar semua tag/sensor yang tersedia |
| Cell 4 | 📥 Tabel data seperti Excel (dengan warna) |
| Cell 5 | 🔢 Statistik lengkap + ringkasan per bulan |
| Cell 6 | 📈 Grafik time-series interaktif + rolling average |
| Cell 7 | 🚨 Deteksi anomali + tabel alarm |
| Cell 8 | 📊 Histogram & Box Plot distribusi |
| Cell 9 | 🤖 Prediksi ML 7 hari ke depan |
| Cell 10 | 🔄 Komparasi multi-tag + korelasi |
| Cell 11 | 📥 Export ke CSV & Excel multi-sheet |

> **Mode Demo:** Kalau database belum terhubung, semua cell tetap bisa dijalankan
> menggunakan data dummy otomatis. Cocok untuk belajar dulu sebelum koneksi ke DB asli.

---

## 📥 LANGKAH 7 — Import Data CSV

Buka `03_import_csv.ipynb`:

1. Taruh file CSV di folder `data/` (misal `85-idf2b-2026.csv`)
2. Set path file di notebook:
   ```python
   CSV_FILE = 'data/85-idf2b-2026.csv'
   UNIT_ID  = 1
   ```
3. Run All → data masuk ke database
4. Cek di web PLN → grafik sudah update

---

## 📤 LANGKAH 8 — Kirim Data ke Web

Buka `04_kirim_data_ke_web.ipynb` untuk push data hasil analisis langsung ke database web PLN.

---

## ❓ FAQ

**Q: Muncul error "ModuleNotFoundError: No module named 'db_connector'"**
A: Pastikan cell pertama setiap notebook ada baris ini:
```python
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(os.getcwd()), 'scripts'))
```

**Q: "Access denied for user 'root'"**
A: Password MySQL salah. Cek file `.env`, pastikan `MYSQL_PASS` sesuai.

**Q: "Can't connect to MySQL server on 'localhost'"**
A: MySQL tidak jalan. Buka XAMPP Control Panel → Start MySQL.

**Q: Perubahan di notebook tidak tersimpan**
A: Simpan manual dengan `Ctrl+S` di Jupyter, atau aktifkan autosave.

**Q: Error "NumPy requires GCC >= 8.4" saat pip install**
A: Terjadi karena Python 3.13 tidak kompatibel dengan NumPy versi lama yang harus dikompilasi dari source.
Requirements.txt sudah diupdate untuk pakai versi terbaru yang punya pre-built wheel.
Cukup jalankan ulang:
```bash
pip install -r requirements.txt
```
Kalau masih error, coba install NumPy langsung dulu:
```bash
pip install numpy --upgrade
pip install -r requirements.txt
```

**Q: Mau install library Python tambahan**
A: Jalankan di Command Prompt:
```bash
pip install nama_library
```
Lalu tambahkan juga ke `requirements.txt` supaya mudah diinstall ulang nanti.

**Q: Jupyter tidak mau terbuka / port 8888 sudah dipakai**
A: Jalankan di port lain:
```bash
jupyter lab --port=8889
```

---

## 🔗 Hubungan Jupyter ↔ Web PLN

```
Web PLN (PHP)                   Jupyter (Python)
─────────────                   ────────────────
Dashboard & User  ←──MySQL──→   Analisis & Import
Parameter Monitor ←──MySQL──→   db_connector.py
Grafik (Chart.js) ←──MySQL──→   Grafik (Plotly)
                  ←──HTTP API──  04_kirim_data.ipynb
```

**Satu database, dua pintu masuk.** Data yang diimport lewat Jupyter langsung kelihatan
di web PLN, dan sebaliknya.
