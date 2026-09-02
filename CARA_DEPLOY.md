# Cara Deploy Patch PLN Web Fix

## File yang berubah:
1. `app/views/pages/bearing-view.php`  → Fix Monitor Bearing (baca dari DB via /predict-range)
2. `app/views/pages/bearing-csv.php`   → Fix anomali Upload CSV (deteksi 10-per-jam + harian)
3. `public/assets/js/main.js`          → Tambah SPA cache untuk pindah tab tanpa loading

## Langkah deploy:
1. **Backup dulu** file lama di server:
   - `cp app/views/pages/bearing-view.php app/views/pages/bearing-view.php.bak`
   - `cp app/views/pages/bearing-csv.php  app/views/pages/bearing-csv.php.bak`
   - `cp public/assets/js/main.js         public/assets/js/main.js.bak`

2. **Copy file baru** dari zip ini ke direktori pln_web di server:
   - Ganti `app/views/pages/bearing-view.php`
   - Ganti `app/views/pages/bearing-csv.php`
   - Ganti `public/assets/js/main.js`

3. **Jalankan SQL patch** di phpMyAdmin:
   - Buka phpMyAdmin
   - Pilih **DB unit** (mis. db_pacitan_1) → tab SQL
   - Copy-paste isi `sql/04_PATCH_BEARING_FIX.sql` → klik Go

4. **Test:**
   - Buka Monitor Bearing → pilih plant & unit → klik Tampilkan
   - Buka Upload CSV → upload file CSV IDF2B → anomali seharusnya muncul (garis merah)
   - Navigasi antar Menu Fitur (Kalkulator ↔ bearing ↔ Monitor Bearing) → seharusnya instan kali ke-2

## Ringkasan perbaikan:
- **Fix 1** (Upload CSV - tidak ada anomali): Anomali sekarang dideteksi client-side menggunakan aturan:
  - **10-per-jam**: jika ≥10 titik 5-menit dalam 1 jam melebihi toleransi → hari itu anomali
  - **Kelipatan**: 2 jam berurutan ≥20, 3 jam ≥30, dst.
  - **Fallback harian**: jika tidak ada raw data, gunakan deviasi dari 7-hari rolling avg
  - Toleransi bisa diubah lewat filter Siang/Malam

- **Fix 2** (Monitor Bearing - kosong): Sekarang membaca langsung dari tabel `bearing_prediksi`
  di DB unit via endpoint `/predict-range` (bukan `/predict` yang tidak ada).
  Juga meneruskan `unit_db` ke Python API agar query ke DB yang benar.

- **Fix 3** (SQL/DB): `04_PATCH_BEARING_FIX.sql` memastikan:
  - Tabel `bearing_models` ada di DB unit dengan schema yang benar
  - Kolom `is_anomali` dan `anomali` tersinkron
  - Kolom `avg_nilai`/`tgl_data` ada di `bearing_aktual`

- **Fix 4** (User role - hanya tanggal): Pengguna role "user" tidak melihat input toleransi
  di Monitor Bearing, hanya pilihan tanggal.

- **Fix 5** (10 anomali per jam + kelipatan): Implemented di `_detectAnomalies()` dalam bearing-csv.php

- **Fix 6** (Pindah tab tanpa loading lama): SPA cache di main.js menyimpan HTML halaman
  di memori. Kunjungan kedua ke halaman yang sama = instan (tanpa HTTP request ulang).
  Loading lama hanya terjadi saat pertama buka halaman atau klik "Tampilkan" data anomali.
