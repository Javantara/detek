"""
scripts/db_connector.py
=======================
Koneksi ke MySQL PLN — dipakai semua notebook.

Cara pakai di notebook:
    from db_connector import get_main_db, get_unit_db, list_units, list_tags
"""

import os
import re
from pathlib import Path
import pandas as pd
import pymysql
from sqlalchemy import create_engine, text
from dotenv import load_dotenv

# ── Auto-detect path .env (Windows lokal maupun Docker) ──────
_THIS_DIR   = Path(__file__).resolve().parent          # .../scripts
_JUPYTER_DIR = _THIS_DIR.parent                        # .../jupyter
_ENV_PATH   = _JUPYTER_DIR / 'config' / '.env'

load_dotenv(dotenv_path=_ENV_PATH)
load_dotenv()   # fallback

HOST = os.getenv('MYSQL_HOST', 'localhost')
PORT = int(os.getenv('MYSQL_PORT', 3307))
USER = os.getenv('MYSQL_USER', 'root')
PASS = os.getenv('MYSQL_PASS', '')
DB   = os.getenv('MYSQL_DB',   'pln_web')


def _engine(db_name: str):
    """Buat SQLAlchemy engine ke database tertentu."""
    url = f"mysql+pymysql://{USER}:{PASS}@{HOST}:{PORT}/{db_name}?charset=utf8mb4"
    return create_engine(url, pool_pre_ping=True)


def get_main_db():
    """Engine ke database utama pln_web."""
    return _engine(DB)


def get_unit_db(unit_id: int = None, db_name: str = None):
    """
    Engine ke database unit terpisah.

    Pakai salah satu:
        conn = get_unit_db(unit_id=1)
        conn = get_unit_db(db_name='db_pacitan_2')
    """
    if db_name:
        return _engine(db_name)

    if unit_id:
        main = get_main_db()
        with main.connect() as c:
            row = c.execute(
                text("SELECT database_name FROM units WHERE unit_id = :id"),
                {"id": unit_id}
            ).fetchone()
        if not row or not row[0]:
            raise ValueError(f"Unit ID {unit_id} tidak punya database_name di tabel units")
        return _engine(row[0])

    raise ValueError("Harus isi salah satu: unit_id atau db_name")


def list_units() -> pd.DataFrame:
    """Ambil semua unit aktif beserta nama database-nya."""
    engine = get_main_db()
    return pd.read_sql("""
        SELECT u.unit_id, u.unit_name, u.database_name,
               p.description as plant_name
        FROM units u
        JOIN plants p ON u.plant_id = p.plant_id
        WHERE u.status = 1
        ORDER BY p.description, u.unit_name
    """, engine)


def list_tags(unit_id: int = None, db_name: str = None) -> pd.DataFrame:
    """Ambil semua sensor dari tabel sensor di database unit."""
    engine = get_unit_db(unit_id=unit_id, db_name=db_name)
    return pd.read_sql("""
        SELECT s.tagno, s.deskripsi, s.unit, s.plant,
               (SELECT COUNT(*) FROM aktual a WHERE a.tagno = s.tagno) as total_data,
               (SELECT MAX(data_time) FROM aktual a WHERE a.tagno = s.tagno) as last_update
        FROM sensor s
        ORDER BY s.tagno
    """, engine)


def get_tag_data(
    tag_id: int,
    date_from: str,
    date_to: str,
    unit_id: int = None,
    db_name: str = None,
    resample: str = None
) -> pd.DataFrame:
    """
    Ambil data time-series satu tag dari tabel aktual.

    Returns DataFrame dengan kolom: timestamp, value
    """
    engine = get_unit_db(unit_id=unit_id, db_name=db_name)
    df = pd.read_sql(
        """
        SELECT data_time AS timestamp, nilai AS value
        FROM aktual
        WHERE tagno = %(tag_id)s
          AND DATE(data_time) BETWEEN %(f)s AND %(t)s
        ORDER BY data_time ASC
        """,
        engine,
        params={"tag_id": str(tag_id), "f": date_from, "t": date_to}
    )
    df['timestamp'] = pd.to_datetime(df['timestamp'])
    df = df.set_index('timestamp')

    if resample:
        df = df['value'].resample(resample).mean().dropna().reset_index()
        df.columns = ['timestamp', 'value']
        return df

    return df.reset_index()


def push_data_to_db(
    tag_id: int,
    df: pd.DataFrame,
    unit_id: int = None,
    db_name: str = None,
    mode: str = 'append'
):
    """
    Simpan DataFrame ke aktual di database unit.

    df harus punya kolom: timestamp, value
    mode 'replace' akan hapus data lama tag ini dulu.
    """
    engine = get_unit_db(unit_id=unit_id, db_name=db_name)
    df = df.copy()
    df['tagno'] = str(tag_id)
    df['timestamp'] = pd.to_datetime(df['timestamp'])
    df = df[['tagno', 'timestamp', 'value']].dropna()

    with engine.begin() as conn:
        if mode == 'replace':
            conn.execute(text("DELETE FROM aktual WHERE tagno = :tid"), {"tid": tag_id})

        rows = df.to_dict(orient='records')
        for i in range(0, len(rows), 500):
            batch = rows[i:i+500]
            conn.execute(
                text("INSERT IGNORE INTO aktual (tagno, data_time, nilai) VALUES (:tagno, :timestamp, :value)"),
                batch
            )

    print(f"✅ {len(df)} baris berhasil disimpan ke tag_id={tag_id}")
