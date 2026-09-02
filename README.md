# deteksi anomali — Bearing Anomali ✅ VERSI BERSIH

Sistem deteksi anomali suhu bearing berbasis XGBoost/Linear Regression.

## Urutan Menjalankan SQL (PENTING)

Jalankan **satu per satu** di phpMyAdmin → pilih DB unit (db_pacitan_1 dll.) → tab SQL → paste → Go.

### Step 1 — Setup tabel dasar
```
FINAL_FIX_JALANKAN_DI_DB_UNIT.sql
```
Membuat tabel `bearing_aktual`, `bearing_prediksi`, `bearing_sensor`.
Aman dijalankan ulang (semua `IF NOT EXISTS`).

### Step 2 — Partisi + arsip otomatis
```
FIX_BEARING_PARTITION_v2.sql
```
Partisi per bulan, tabel arsip data >12 bulan, VIEW gabungan, event scheduler tiap tgl 25.
> **Fix error #1109** sudah diterapkan — query verifikasi pakai subquery wrapper.

### Step 3 — Bersihkan tabel sisa (disarankan)
```
BERSIHKAN_TABEL_SISA.sql
```
Hapus: `bearing_aktual_old`, `bearing_aktual_backup_partition`,
`bearing_prediksi_old`, `bearing_prediksi_backup_partition`.

### Schema DB utama (login/roles/plant/unit)
```
database.sql
```
Jalankan SEKALI di database `pln_web` (bukan database unit).

---

## Tabel DB Unit (3 Tabel Utama + Arsip)

| Tabel | Isi |
|---|---|
| `bearing_aktual` | Data aktual: tagno, datetime, value — 12 bulan terakhir |
| `bearing_prediksi` | Hasil prediksi ML: tagno, datetime, value_prediksi, anomali |
| `bearing_sensor` | Master sensor: tagno, deskripsi, unit, plant |
| `bearing_aktual_arsip` | Arsip aktual >12 bulan (terkompresi) |
| `bearing_prediksi_arsip` | Arsip prediksi >12 bulan (terkompresi) |
| `v_bearing_aktual_all` | VIEW: aktual + arsip |
| `v_bearing_prediksi_all` | VIEW: prediksi + arsip |

---

## Struktur File Penting

```
pln_web/
├── START_BEARING_API.bat           ← Jalankan Python API (Windows)
├── start_bearing_api.sh            ← Jalankan Python API (Linux/Mac)
├── FIX_BEARING_ANOMALI_RUN_THIS.sql ← SQL setup DB — jalankan SEKALI di phpMyAdmin
├── database.sql                    ← Schema DB utama (pln_web)
│
├── app/
│   ├── jupyter/
│   │   ├── config/.env             ← Konfigurasi koneksi MySQL
│   │   ├── requirements.txt        ← Dependensi Python
│   │   └── scripts/
│   │       ├── bearing_api.py      ← Python API (Flask, port 5050)
│   │       └── db_connector.py     ← Helper koneksi DB untuk notebook
│   │
│   ├── api/
│   │   ├── bearing_proxy.php       ← Jembatan PHP → Python API
│   │   └── bearing_upload.php      ← Upload file CSV bearing
│   │
│   └── views/pages/
│       ├── bearing-anomali.php     ← Halaman utama analisis
│       └── bearing-view.php        ← Halaman monitor realtime
│
└── public/
    └── index.php                   ← Entry point aplikasi
```

## Cara Setup

### 1. Jalankan SQL di phpMyAdmin
Buka `FIX_BEARING_ANOMALI_RUN_THIS.sql` → jalankan di **setiap database unit**
(db_pacitan_1, db_paiton_1, dst).

### 2. Konfigurasi .env
Edit `app/jupyter/config/.env`:
```
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_USER=root
MYSQL_PASS=
MYSQL_DB=pln_web
```

### 3. Jalankan Python API
```
START_BEARING_API.bat   (Windows)
./start_bearing_api.sh  (Linux)
```
Tunggu hingga muncul: `Running on http://0.0.0.0:5050`

### 4. Upload CSV & Analisis
- Buka browser → `http://localhost/pln_web/public/`
- Menu **Bearing** → upload CSV sensor (877=ambient, 858=bearing1, 859=bearing2)
- Klik **Analisis**

## Struktur Tabel DB Unit

| Tabel | Isi |
|---|---|
| `bearing_models` | Model tersimpan (XGBoost/Linear) |
| `bearing_sensor` | Master sensor: tagno, deskripsi, unit, plant, equipment_number |
| `bearing_aktual` | Data aktual: tagno, datetime, date_rec, value, equipment_number |
| `bearing_prediksi` | Hasil prediksi: tagno, datetime, value_prediksi, selisih, high, low, anomali |
| `bearing_anomaly_log` | Log anomali harian |
| `bearing_csv_files` | Metadata file CSV yang diupload |

## Model File
Model disimpan di `app/jupyter/models/` format `.pkl` (joblib):
- `bearing_y1_model_1.pkl` → Bearing 1 (tag 858)
- `bearing_y2_model_1.pkl` → Bearing 2 (tag 859)

Prediksi bisa dijalankan ulang tanpa training (pakai model tersimpan).
