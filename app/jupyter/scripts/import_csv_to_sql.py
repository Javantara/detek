"""
Import data aktual sensor dari file ZIP/folder CSV ke database unit PLN.

Cara pakai dari folder project pln_web:
  set PLN_DB_NAME=db_pacitan_1
  set PLN_IMPORT_TAGS=
  python app/jupyter/scripts/import_csv_to_sql.py "D:\\Magang\\deteksi_anomali\\data_2025_pisah.zip"

Target tabel:
  db_pacitan_1.sensor
  db_pacitan_1.aktual
"""
import csv
import os
import re
import sys
import zipfile
from datetime import datetime
from pathlib import Path

try:
    import pymysql
except Exception:
    print("ERROR: pymysql belum ada.")
    print("Jalankan dulu: python -m pip install pymysql")
    sys.exit(1)

DB_HOST = os.getenv("PLN_DB_HOST", "localhost")
DB_PORT = int(os.getenv("PLN_DB_PORT", "3307"))
DB_NAME = os.getenv("PLN_DB_NAME", "db_pacitan_1")
DB_USER = os.getenv("PLN_DB_USER", "root")
DB_PASS = os.getenv("PLN_DB_PASS", "")
PLANT_NAME = os.getenv("PLN_PLANT_NAME", "Pacitan")
CHUNK_SIZE = int(os.getenv("PLN_IMPORT_CHUNK", "5000"))
START_DATE_ENV = os.getenv("PLN_START_DATE", "").strip()
END_DATE_ENV = os.getenv("PLN_END_DATE", "").strip()


SENSOR_DESC = {
    "858": "Bearing temperature Y1",
    "859": "Bearing temperature Y2 / sensor utama anomali",
    "877": "Ambient / suhu ruangan",
    "1577": "Load / beban",
}


def tag_from_name(name: str) -> str:
    base = os.path.basename(name)
    m = re.match(r"(\d+)", base)
    return m.group(1) if m else "UNKNOWN"


def parse_datetime(value: str):
    value = str(value).strip().strip('"\'')
    if not value:
        return None
    formats = [
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%d %H:%M",
        "%Y/%m/%d %H:%M:%S",
        "%Y/%m/%d %H:%M",
        "%d/%m/%Y %H:%M:%S",
        "%d/%m/%Y %H:%M",
        "%m/%d/%Y %H:%M:%S",
        "%m/%d/%Y %H:%M",
    ]
    for fmt in formats:
        try:
            return datetime.strptime(value, fmt).strftime("%Y-%m-%d %H:%M:%S")
        except ValueError:
            pass
    return None


def in_date_range(dt_str: str) -> bool:
    if not dt_str:
        return False
    if START_DATE_ENV and dt_str < START_DATE_ENV:
        return False
    if END_DATE_ENV and dt_str > END_DATE_ENV:
        return False
    return True


def parse_float(value: str):
    value = str(value).strip().strip('"\'')
    if not value:
        return None
    value = value.replace(",", ".")
    try:
        return float(value)
    except ValueError:
        return None


def detect_row(row, fallback_tag: str):
    """Terima format lama:
    1) tagno, data_time, nilai
    2) data_time, nilai
    3) data_time, tagno, nilai
    """
    row = [str(x).strip() for x in row]
    if len(row) < 2:
        return None

    # Format: tagno, data_time, nilai
    if len(row) >= 3 and re.fullmatch(r"\d+", row[0] or ""):
        dt = parse_datetime(row[1])
        val = parse_float(row[2])
        if dt is not None and val is not None:
            return row[0], dt, val

    # Format: data_time, nilai
    dt = parse_datetime(row[0])
    val = parse_float(row[1]) if len(row) >= 2 else None
    if dt is not None and val is not None:
        return fallback_tag, dt, val

    # Format: data_time, tagno, nilai
    if len(row) >= 3:
        dt = parse_datetime(row[0])
        val = parse_float(row[2])
        if dt is not None and val is not None:
            tag = row[1] if re.fullmatch(r"\d+", row[1] or "") else fallback_tag
            return tag, dt, val

    return None


def iter_csv_files(source: str):
    source_path = Path(source)
    if source_path.is_file() and zipfile.is_zipfile(source_path):
        zf = zipfile.ZipFile(source_path)
        files = [n for n in zf.namelist() if n.lower().endswith(".csv")]
        for name in files:
            yield name, zf.open(name, "r"), True
        zf.close()
    elif source_path.is_dir():
        for path in source_path.rglob("*.csv"):
            yield str(path), open(path, "rb"), False
    elif source_path.is_file() and source_path.suffix.lower() == ".csv":
        yield str(source_path), open(source_path, "rb"), False
    else:
        raise FileNotFoundError(f"File/folder tidak ditemukan atau bukan CSV/ZIP: {source}")


def main():
    if len(sys.argv) < 2:
        print("Cara pakai:")
        print('  set PLN_DB_NAME=db_pacitan_1')
        print('  python app/jupyter/scripts/import_csv_to_sql.py "D:\\Magang\\deteksi_anomali\\data_2025_pisah.zip"')
        sys.exit(1)

    source = sys.argv[1]
    target_tags_env = os.getenv("PLN_IMPORT_TAGS", "")
    target_tags = {x.strip() for x in target_tags_env.split(",") if x.strip()}

    print("IMPORT DATA AKTUAL")
    print(f"Database : {DB_NAME}")
    print(f"Sumber   : {source}")
    if target_tags:
        print("Filter tag:", ", ".join(sorted(target_tags)))
    else:
        print("Filter tag: semua CSV")
    if START_DATE_ENV or END_DATE_ENV:
        print(f"Range tanggal: {START_DATE_ENV or 'awal'} sampai {END_DATE_ENV or 'akhir'}")
    else:
        print("Range tanggal: semua tanggal")
    print("-" * 60)

    conn = pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        charset="utf8mb4",
        autocommit=False,
    )
    cur = conn.cursor()

    # Pastikan tabel penting sudah ada. Kalau belum, berarti SQL 01 belum dijalankan.
    cur.execute("SHOW TABLES LIKE 'aktual'")
    if cur.fetchone() is None:
        print("ERROR: tabel aktual belum ada. Jalankan dulu 01_BUAT_ULANG_DB_PACITAN_1.sql di db_pacitan_1.")
        sys.exit(1)
    cur.execute("SHOW TABLES LIKE 'sensor'")
    if cur.fetchone() is None:
        print("ERROR: tabel sensor belum ada. Jalankan dulu 01_BUAT_ULANG_DB_PACITAN_1.sql dan 02_IMPORT_SENSOR.sql.")
        sys.exit(1)

    total_csv = 0
    total_rows = 0
    total_saved = 0

    try:
        for file_name, raw_handle, _is_zip in iter_csv_files(source):
            total_csv += 1
            tag_file = tag_from_name(file_name)
            if target_tags and tag_file not in target_tags:
                raw_handle.close()
                continue

            desc = SENSOR_DESC.get(tag_file, f"Sensor {tag_file}")
            cur.execute(
                """
                INSERT INTO sensor(tagno, deskripsi, unit, plant)
                VALUES(%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    deskripsi=COALESCE(NULLIF(deskripsi,''), VALUES(deskripsi)),
                    unit=COALESCE(NULLIF(unit,''), VALUES(unit)),
                    plant=COALESCE(NULLIF(plant,''), VALUES(plant))
                """,
                (tag_file, desc, "", PLANT_NAME),
            )

            text_lines = (line.decode("utf-8-sig", errors="ignore") for line in raw_handle)
            reader = csv.reader(text_lines)
            batch = []
            file_rows = 0
            file_saved = 0

            for row in reader:
                parsed = detect_row(row, tag_file)
                if parsed is None:
                    continue
                tagno, data_time, nilai = parsed
                if target_tags and tagno not in target_tags:
                    continue
                if not in_date_range(data_time):
                    continue
                batch.append((tagno, data_time, nilai))
                file_rows += 1
                if len(batch) >= CHUNK_SIZE:
                    cur.executemany(
                        """
                        INSERT INTO aktual(tagno, data_time, nilai)
                        VALUES(%s,%s,%s)
                        ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)
                        """,
                        batch,
                    )
                    conn.commit()
                    file_saved += len(batch)
                    batch.clear()

            if batch:
                cur.executemany(
                    """
                    INSERT INTO aktual(tagno, data_time, nilai)
                    VALUES(%s,%s,%s)
                    ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)
                    """,
                    batch,
                )
                conn.commit()
                file_saved += len(batch)

            raw_handle.close()
            total_rows += file_rows
            total_saved += file_saved
            print(f"OK {file_name} | tag file={tag_file} | baris terbaca={file_rows} | masuk/update={file_saved}")

    finally:
        cur.close()
        conn.close()

    print("=" * 60)
    print("SELESAI")
    print(f"CSV ditemukan     : {total_csv}")
    print(f"Baris terbaca     : {total_rows}")
    print(f"Baris masuk/update: {total_saved}")
    print("Cek di phpMyAdmin: SELECT COUNT(*) FROM aktual;")


if __name__ == "__main__":
    main()
