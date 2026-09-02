"""
Training Deep Learning Autoencoder untuk deteksi anomali bearing.

Output utama:
- autoencoder_pln.keras / autoencoder_pln.h5
- scaler_autoencoder_pln.pkl
- model_info_autoencoder.json
- metrics_autoencoder.csv
- prediksi_autoencoder_import.csv

CSV prediksi_autoencoder_import.csv di-import ke tabel prediksi_autoencoder pada database unit.
"""

from __future__ import annotations

import argparse
import json
import os
import glob
import zipfile
from pathlib import Path

import numpy as np
import pandas as pd
import joblib

from sklearn.preprocessing import StandardScaler
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

import tensorflow as tf
from tensorflow.keras import layers, models, callbacks

TAG_MAP = {
    858: "y1_bearing1",
    859: "y2_bearing2",
    877: "x_suhu_ruangan",
    1577: "load_1577",
}
FEATURE_COLS = ["x_suhu_ruangan", "y1_bearing1", "y2_bearing2", "load_1577"]
TARGET_TAGS = {858: "y1_bearing1", 859: "y2_bearing2"}


def extract_zip_files(data_dir: Path) -> None:
    for z in data_dir.rglob("*.zip"):
        out = z.with_suffix("")
        out.mkdir(parents=True, exist_ok=True)
        try:
            with zipfile.ZipFile(z, "r") as zip_ref:
                zip_ref.extractall(out)
            print(f"Ekstrak: {z} -> {out}")
        except Exception as exc:
            print(f"Lewati zip {z}: {exc}")


def detect_tag_from_path(path: str) -> int | None:
    name = Path(path).name.lower()
    for tag in TAG_MAP:
        if name.startswith(str(tag) + "-") or name.startswith(str(tag) + "_") or name.startswith(str(tag) + "."):
            return tag
    return None


def read_sensor_csv(path: str) -> pd.DataFrame | None:
    tag_from_name = detect_tag_from_path(path)
    try:
        df = pd.read_csv(path)
    except Exception:
        try:
            df = pd.read_csv(path, header=None)
        except Exception as exc:
            print(f"Gagal baca {path}: {exc}")
            return None

    lower_cols = {str(c).lower(): c for c in df.columns}
    tag_col = lower_cols.get("tag_id") or lower_cols.get("tagno") or lower_cols.get("tag")
    time_col = lower_cols.get("timestamp") or lower_cols.get("data_time") or lower_cols.get("waktu") or lower_cols.get("datetime")
    val_col = lower_cols.get("value") or lower_cols.get("nilai")

    if time_col is None or val_col is None:
        if df.shape[1] >= 3:
            df = df.iloc[:, :3].copy()
            df.columns = ["tag_id", "timestamp", "value"]
            tag_col, time_col, val_col = "tag_id", "timestamp", "value"
        elif df.shape[1] >= 2 and tag_from_name:
            df = df.iloc[:, :2].copy()
            df.columns = ["timestamp", "value"]
            df["tag_id"] = tag_from_name
            tag_col, time_col, val_col = "tag_id", "timestamp", "value"
        else:
            return None

    out = pd.DataFrame()
    if tag_col is not None:
        out["tag_id"] = pd.to_numeric(df[tag_col], errors="coerce")
    else:
        out["tag_id"] = tag_from_name
    if tag_from_name is not None:
        out["tag_id"] = out["tag_id"].fillna(tag_from_name)
    out["timestamp"] = pd.to_datetime(df[time_col], errors="coerce")
    out["value"] = pd.to_numeric(df[val_col], errors="coerce")
    out = out.dropna(subset=["tag_id", "timestamp", "value"])
    out["tag_id"] = out["tag_id"].astype(int)
    out = out[out["tag_id"].isin(TAG_MAP.keys())]
    return out if len(out) else None


def load_data(data_dir: str) -> pd.DataFrame:
    data_path = Path(data_dir)
    extract_zip_files(data_path)
    csv_files = [str(p) for p in data_path.rglob("*.csv")]
    if not csv_files:
        raise FileNotFoundError(f"Tidak ada CSV di {data_dir}")
    frames = []
    for f in csv_files:
        d = read_sensor_csv(f)
        if d is not None and len(d):
            frames.append(d)
    if not frames:
        raise RuntimeError("CSV ditemukan, tapi tidak ada data tag 858/859/877/1577 yang terbaca.")
    long_df = pd.concat(frames, ignore_index=True)
    long_df["sensor"] = long_df["tag_id"].map(TAG_MAP)
    wide = long_df.pivot_table(index="timestamp", columns="sensor", values="value", aggfunc="mean").reset_index()
    wide = wide.sort_values("timestamp")
    for c in FEATURE_COLS:
        if c not in wide.columns:
            raise RuntimeError(f"Kolom sensor {c} tidak ditemukan. Pastikan data tag terkait tersedia.")
    wide = wide.dropna(subset=FEATURE_COLS).copy()
    # Sama seperti notebook awal, load rendah bisa dibuang agar model belajar kondisi operasi.
    if "load_1577" in wide.columns:
        wide = wide[wide["load_1577"] > 100].copy()
    if len(wide) < 100:
        raise RuntimeError("Data terlalu sedikit setelah cleaning/filter load > 100.")
    return wide


def build_autoencoder(input_dim: int) -> tf.keras.Model:
    inp = layers.Input(shape=(input_dim,))
    x = layers.Dense(16, activation="relu")(inp)
    x = layers.Dense(8, activation="relu")(x)
    encoded = layers.Dense(4, activation="relu", name="bottleneck")(x)
    x = layers.Dense(8, activation="relu")(encoded)
    x = layers.Dense(16, activation="relu")(x)
    out = layers.Dense(input_dim, activation="linear")(x)
    model = models.Model(inp, out, name="autoencoder_pln")
    model.compile(optimizer=tf.keras.optimizers.Adam(learning_rate=0.001), loss="mse", metrics=["mae"])
    return model


def apply_min_consecutive(group: pd.DataFrame, min_consecutive: int = 10) -> pd.Series:
    cand = group["candidate_anomaly"].astype(bool).to_numpy()
    final = np.zeros(len(cand), dtype=bool)
    i = 0
    while i < len(cand):
        if not cand[i]:
            i += 1
            continue
        j = i
        while j < len(cand) and cand[j]:
            j += 1
        if (j - i) >= min_consecutive:
            final[i:j] = True
        i = j
    return pd.Series(final, index=group.index)


def train_and_export(data_dir: str, out_dir: str, threshold_quantile: float = 0.95, min_consecutive: int = 10) -> None:
    out_path = Path(out_dir)
    out_path.mkdir(parents=True, exist_ok=True)
    df = load_data(data_dir)
    print("Data training:", df.shape, df["timestamp"].min(), "s/d", df["timestamp"].max())

    X = df[FEATURE_COLS].astype(float).values
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    X_train, X_test = train_test_split(X_scaled, test_size=0.2, shuffle=False)
    model = build_autoencoder(X_scaled.shape[1])
    es = callbacks.EarlyStopping(monitor="val_loss", patience=10, restore_best_weights=True)
    model.fit(X_train, X_train, validation_split=0.2, epochs=80, batch_size=128, callbacks=[es], verbose=1)

    reconstructed_scaled = model.predict(X_scaled, verbose=0)
    reconstructed = scaler.inverse_transform(reconstructed_scaled)
    recon_df = pd.DataFrame(reconstructed, columns=[f"pred_{c}" for c in FEATURE_COLS])
    full = pd.concat([df.reset_index(drop=True), recon_df], axis=1)

    metrics = []
    pred_rows = []
    thresholds = {}
    for tag, col in TARGET_TAGS.items():
        pred_col = f"pred_{col}"
        actual = full[col].astype(float).to_numpy()
        pred = full[pred_col].astype(float).to_numpy()
        abs_err = np.abs(actual - pred)
        thr = float(np.quantile(abs_err, threshold_quantile))
        thresholds[str(tag)] = thr

        mae = float(mean_absolute_error(actual, pred))
        rmse = float(np.sqrt(mean_squared_error(actual, pred)))
        r2 = float(r2_score(actual, pred))
        metrics.append({
            "nama_model": "Autoencoder PLN",
            "tipe_model": "Deep Learning Autoencoder",
            "tagno": tag,
            "mae": mae,
            "rmse": rmse,
            "r2_score": r2,
            "total_data": len(full),
            "threshold_error": thr,
            "threshold_quantile": threshold_quantile,
        })

        temp = pd.DataFrame({
            "tagno": tag,
            "data_time": full["timestamp"],
            "value": actual,
            "value_prediksi": pred,
            "selisih_high": pred + thr,
            "selisih_low": pred - thr,
            "reconstruction_error": abs_err,
            "threshold_error": thr,
        })
        temp["candidate_anomaly"] = temp["reconstruction_error"] > temp["threshold_error"]
        temp["final_anomaly"] = apply_min_consecutive(temp, min_consecutive=min_consecutive).values
        temp["status_anomali"] = np.where(temp["final_anomaly"], "Anomali", "Normal")
        pred_rows.append(temp[["tagno", "data_time", "value", "value_prediksi", "selisih_high", "selisih_low", "reconstruction_error", "threshold_error", "status_anomali"]])

    pred_out = pd.concat(pred_rows, ignore_index=True)
    pred_out = pred_out.sort_values(["tagno", "data_time"])

    metrics_df = pd.DataFrame(metrics)
    metrics_df.to_csv(out_path / "metrics_autoencoder.csv", index=False)
    pred_out.to_csv(out_path / "prediksi_autoencoder_import.csv", index=False)

    model.save(out_path / "autoencoder_pln.keras")
    model.save(out_path / "autoencoder_pln.h5")
    joblib.dump(scaler, out_path / "scaler_autoencoder_pln.pkl")

    info = {
        "model": "Deep Learning Autoencoder",
        "library": "TensorFlow Keras",
        "features": FEATURE_COLS,
        "target_tags": TARGET_TAGS,
        "threshold_quantile": threshold_quantile,
        "thresholds_by_tag": thresholds,
        "min_consecutive": min_consecutive,
        "data_start": str(df["timestamp"].min()),
        "data_end": str(df["timestamp"].max()),
        "total_rows": int(len(df)),
        "output_csv": "prediksi_autoencoder_import.csv",
    }
    (out_path / "model_info_autoencoder.json").write_text(json.dumps(info, indent=2, ensure_ascii=False), encoding="utf-8")

    print("Selesai. File output ada di:", out_path.resolve())
    print(metrics_df)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--data_dir", default="data", help="Folder data sensor/ZIP data_2025_pisah")
    parser.add_argument("--out_dir", default="models_autoencoder", help="Folder output")
    parser.add_argument("--threshold_quantile", type=float, default=0.95)
    parser.add_argument("--min_consecutive", type=int, default=10)
    args = parser.parse_args()
    train_and_export(args.data_dir, args.out_dir, args.threshold_quantile, args.min_consecutive)


if __name__ == "__main__":
    main()
