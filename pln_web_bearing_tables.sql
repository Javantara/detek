-- ============================================================
-- Jalankan di database: pln_web
-- Tambah tabel bearing_aktual dan bearing_prediksi ke pln_web
-- ============================================================
USE pln_web;

CREATE TABLE IF NOT EXISTS `bearing_aktual` (
    `id`       INT AUTO_INCREMENT PRIMARY KEY,
    `tagno`    VARCHAR(20) NOT NULL,
    `datetime` DATETIME NOT NULL,
    `value`    DOUBLE,
    UNIQUE KEY `uq_bearing_aktual` (`tagno`, `datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bearing_prediksi` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `tagno`          VARCHAR(20) NOT NULL,
    `datetime`       DATETIME NOT NULL,
    `model_id`       INT DEFAULT NULL,
    `value_aktual`   DOUBLE,
    `value_prediksi` DOUBLE,
    `selisih`        DOUBLE,
    `high`           DOUBLE,
    `low`            DOUBLE,
    `anomali`        TINYINT(1) DEFAULT 0,
    UNIQUE KEY `uq_bearing_pred` (`tagno`, `datetime`, `model_id`),
    KEY `idx_model_id` (`model_id`),
    KEY `idx_tagno_dt` (`tagno`, `datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'Tabel bearing_aktual dan bearing_prediksi berhasil dibuat di pln_web' AS status;
