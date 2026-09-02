-- ============================================================
-- 03_UNIT_DATABASE.sql
-- Database unit: db_pacitan_1 (atau db_unit_* lainnya)
-- Jalankan di: phpMyAdmin → pilih DB unit → tab SQL → paste → Go
-- Jalankan SEKALI saat setup awal. Aman dijalankan ulang
-- karena semua CREATE menggunakan IF NOT EXISTS.
-- ============================================================

-- Ganti nama database di bawah sesuai unit (db_pacitan_1, dll.)
-- USE db_pacitan_1;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ============================================================
-- 1. bearing_aktual
--    Data aktual suhu bearing dari sensor (upload CSV)
--    Dipartisi per bulan, otomatis diarsip setelah 12 bulan
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_aktual` (
    `aktual_id`       BIGINT       NOT NULL AUTO_INCREMENT,
    `tagno`           VARCHAR(50)  NOT NULL  COMMENT 'Tag sensor: 858 (B1), 859 (B2), 877 (ambient)',
    `datetime`        DATETIME     NOT NULL  COMMENT 'Waktu data',
    `date_rec`        DATE         NOT NULL  COMMENT 'Tanggal (untuk partisi & aggregate harian)',
    `value`           DOUBLE                COMMENT 'Nilai suhu (°C)',
    `equipment_num`   VARCHAR(100) DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`aktual_id`, `date_rec`),
    UNIQUE KEY `uq_tagno_datetime` (`tagno`, `datetime`, `date_rec`),
    KEY `idx_tagno_date` (`tagno`, `date_rec`),
    KEY `idx_date_rec`   (`date_rec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Data aktual bearing 12 bulan terakhir'
PARTITION BY RANGE (YEAR(`date_rec`) * 100 + MONTH(`date_rec`)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    PARTITION p202403 VALUES LESS THAN (202404),
    PARTITION p202404 VALUES LESS THAN (202405),
    PARTITION p202405 VALUES LESS THAN (202406),
    PARTITION p202406 VALUES LESS THAN (202407),
    PARTITION p202407 VALUES LESS THAN (202408),
    PARTITION p202408 VALUES LESS THAN (202409),
    PARTITION p202409 VALUES LESS THAN (202410),
    PARTITION p202410 VALUES LESS THAN (202411),
    PARTITION p202411 VALUES LESS THAN (202412),
    PARTITION p202412 VALUES LESS THAN (202501),
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    PARTITION p202503 VALUES LESS THAN (202504),
    PARTITION p202504 VALUES LESS THAN (202505),
    PARTITION p202505 VALUES LESS THAN (202506),
    PARTITION p202506 VALUES LESS THAN (202507),
    PARTITION p202507 VALUES LESS THAN (202508),
    PARTITION p202508 VALUES LESS THAN (202509),
    PARTITION p202509 VALUES LESS THAN (202510),
    PARTITION p202510 VALUES LESS THAN (202511),
    PARTITION p202511 VALUES LESS THAN (202512),
    PARTITION p202512 VALUES LESS THAN (202601),
    PARTITION p202601 VALUES LESS THAN (202602),
    PARTITION p202602 VALUES LESS THAN (202603),
    PARTITION p202603 VALUES LESS THAN (202604),
    PARTITION p202604 VALUES LESS THAN (202605),
    PARTITION p202605 VALUES LESS THAN (202606),
    PARTITION p202606 VALUES LESS THAN (202607),
    PARTITION p202607 VALUES LESS THAN (202608),
    PARTITION p202608 VALUES LESS THAN (202609),
    PARTITION p202609 VALUES LESS THAN (202610),
    PARTITION p202610 VALUES LESS THAN (202611),
    PARTITION p202611 VALUES LESS THAN (202612),
    PARTITION p202612 VALUES LESS THAN (202701),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);


-- ============================================================
-- 2. bearing_aktual_arsip
--    Data aktual > 12 bulan, dikompresi & diarsip otomatis
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_aktual_arsip` (
    `aktual_id`       BIGINT       NOT NULL,
    `tagno`           VARCHAR(50)  NOT NULL,
    `datetime`        DATETIME     NOT NULL,
    `date_rec`        DATE         NOT NULL,
    `value`           DOUBLE       NOT NULL,
    `equipment_num`   VARCHAR(100) DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `arsip_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`aktual_id`, `date_rec`),
    INDEX `idx_date_rec` (`date_rec`),
    INDEX `idx_tagno`    (`tagno`),
    INDEX `idx_arsip_at` (`arsip_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8
COMMENT='Arsip data aktual bearing > 12 bulan'
PARTITION BY RANGE (YEAR(`date_rec`) * 100 + MONTH(`date_rec`)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    PARTITION p202403 VALUES LESS THAN (202404),
    PARTITION p202404 VALUES LESS THAN (202405),
    PARTITION p202405 VALUES LESS THAN (202406),
    PARTITION p202406 VALUES LESS THAN (202407),
    PARTITION p202407 VALUES LESS THAN (202408),
    PARTITION p202408 VALUES LESS THAN (202409),
    PARTITION p202409 VALUES LESS THAN (202410),
    PARTITION p202410 VALUES LESS THAN (202411),
    PARTITION p202411 VALUES LESS THAN (202412),
    PARTITION p202412 VALUES LESS THAN (202501),
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    PARTITION p202503 VALUES LESS THAN (202504),
    PARTITION p202504 VALUES LESS THAN (202505),
    PARTITION p202505 VALUES LESS THAN (202506),
    PARTITION p202506 VALUES LESS THAN (202507),
    PARTITION p202507 VALUES LESS THAN (202508),
    PARTITION p202508 VALUES LESS THAN (202509),
    PARTITION p202509 VALUES LESS THAN (202510),
    PARTITION p202510 VALUES LESS THAN (202511),
    PARTITION p202511 VALUES LESS THAN (202512),
    PARTITION p202512 VALUES LESS THAN (202601),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);


-- ============================================================
-- 3. bearing_models
--    Model ML yang sudah dilatih (metadata + path file)
--    Diisi otomatis oleh Python API saat train
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_models` (
    `model_id`        INT          NOT NULL AUTO_INCREMENT,
    `tagno`           VARCHAR(50)  NOT NULL              COMMENT 'Tag target: 858 atau 859',
    `model_path`      VARCHAR(500) NOT NULL              COMMENT 'Path file model (.pkl)',
    `scaler_x_path`   VARCHAR(500) NOT NULL              COMMENT 'Path scaler input',
    `scaler_y_path`   VARCHAR(500) NOT NULL              COMMENT 'Path scaler output',
    `model_type`      VARCHAR(20)  NOT NULL DEFAULT 'gbr' COMMENT 'xgboost | linear | gbr | lstm',
    `target_col`      VARCHAR(50)  NOT NULL DEFAULT 'y1'  COMMENT 'y1 atau y2',
    `feature_cols`    TEXT                               COMMENT 'JSON array kolom fitur',
    `lookback`        INT          DEFAULT 10             COMMENT 'Window lookback (LSTM)',
    `mae`             DOUBLE       DEFAULT NULL           COMMENT 'Mean Absolute Error',
    `rmse`            DOUBLE       DEFAULT NULL           COMMENT 'Root Mean Square Error',
    `r2_score`        DOUBLE       DEFAULT NULL           COMMENT 'Koefisien R²',
    `coef_a`          DOUBLE       DEFAULT NULL           COMMENT 'Slope (linear)',
    `coef_b`          DOUBLE       DEFAULT NULL           COMMENT 'Intercept (linear)',
    `n_train`         INT          DEFAULT NULL           COMMENT 'Jumlah data training',
    `n_test`          INT          DEFAULT NULL           COMMENT 'Jumlah data test',
    `total_anomalies` INT          DEFAULT 0              COMMENT 'Jumlah anomali saat training',
    `bearing_label`   VARCHAR(20)  DEFAULT NULL           COMMENT 'Y1 atau Y2',
    `model_name`      VARCHAR(255) DEFAULT NULL           COMMENT 'Nama tampilan model',
    `train_start`     VARCHAR(20)  DEFAULT NULL           COMMENT 'Tanggal mulai training',
    `train_end`       VARCHAR(20)  DEFAULT NULL           COMMENT 'Tanggal akhir training',
    `source_file`     VARCHAR(255) DEFAULT NULL           COMMENT 'File CSV sumber training',
    `unit_id`         INT          DEFAULT NULL,
    `plant_id`        INT          DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`model_id`),
    KEY `idx_tagno`   (`tagno`),
    KEY `idx_unit_id` (`unit_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Model ML bearing yang tersimpan';


-- ============================================================
-- 4. bearing_prediksi
--    Hasil prediksi per hari dari model ML
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_prediksi` (
    `prediksi_id`    BIGINT       NOT NULL AUTO_INCREMENT,
    `model_id`       INT          DEFAULT NULL            COMMENT 'FK ke bearing_models',
    `tagno`          VARCHAR(50)  NOT NULL,
    `datetime`       DATETIME     NOT NULL,
    `date_rec`       DATE         NOT NULL,
    `value_aktual`   DOUBLE       DEFAULT NULL,
    `value_prediksi` DOUBLE       NOT NULL,
    `selisih`        DOUBLE       DEFAULT NULL,
    `high`           DOUBLE       DEFAULT NULL,
    `low`            DOUBLE       DEFAULT NULL,
    `anomali`        TINYINT(1)   DEFAULT 0,
    `equipment_num`  VARCHAR(100) DEFAULT NULL,
    `unit_id`        INT          DEFAULT NULL,
    `plant_id`       INT          DEFAULT NULL,
    `pred_interval`  INT          DEFAULT 1,
    `run_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`prediksi_id`, `date_rec`),
    UNIQUE KEY `uq_model_tagno_dt` (`model_id`, `tagno`, `datetime`, `date_rec`),
    KEY `idx_tagno_date` (`tagno`, `date_rec`),
    KEY `idx_model_id`   (`model_id`),
    KEY `idx_anomali`    (`anomali`),
    KEY `idx_date_rec`   (`date_rec`),
    CONSTRAINT `fk_prediksi_model`
        FOREIGN KEY (`model_id`) REFERENCES `bearing_models` (`model_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Hasil prediksi bearing 12 bulan terakhir'
PARTITION BY RANGE (YEAR(`date_rec`) * 100 + MONTH(`date_rec`)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    PARTITION p202403 VALUES LESS THAN (202404),
    PARTITION p202404 VALUES LESS THAN (202405),
    PARTITION p202405 VALUES LESS THAN (202406),
    PARTITION p202406 VALUES LESS THAN (202407),
    PARTITION p202407 VALUES LESS THAN (202408),
    PARTITION p202408 VALUES LESS THAN (202409),
    PARTITION p202409 VALUES LESS THAN (202410),
    PARTITION p202410 VALUES LESS THAN (202411),
    PARTITION p202411 VALUES LESS THAN (202412),
    PARTITION p202412 VALUES LESS THAN (202501),
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    PARTITION p202503 VALUES LESS THAN (202504),
    PARTITION p202504 VALUES LESS THAN (202505),
    PARTITION p202505 VALUES LESS THAN (202506),
    PARTITION p202506 VALUES LESS THAN (202507),
    PARTITION p202507 VALUES LESS THAN (202508),
    PARTITION p202508 VALUES LESS THAN (202509),
    PARTITION p202509 VALUES LESS THAN (202510),
    PARTITION p202510 VALUES LESS THAN (202511),
    PARTITION p202511 VALUES LESS THAN (202512),
    PARTITION p202512 VALUES LESS THAN (202601),
    PARTITION p202601 VALUES LESS THAN (202602),
    PARTITION p202602 VALUES LESS THAN (202603),
    PARTITION p202603 VALUES LESS THAN (202604),
    PARTITION p202604 VALUES LESS THAN (202605),
    PARTITION p202605 VALUES LESS THAN (202606),
    PARTITION p202606 VALUES LESS THAN (202607),
    PARTITION p202607 VALUES LESS THAN (202608),
    PARTITION p202608 VALUES LESS THAN (202609),
    PARTITION p202609 VALUES LESS THAN (202610),
    PARTITION p202610 VALUES LESS THAN (202611),
    PARTITION p202611 VALUES LESS THAN (202612),
    PARTITION p202612 VALUES LESS THAN (202701),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);


-- ============================================================
-- 5. bearing_prediksi_arsip
--    Arsip prediksi > 12 bulan
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_prediksi_arsip` (
    `prediksi_id`    BIGINT       NOT NULL,
    `model_id`       INT          DEFAULT NULL,
    `tagno`          VARCHAR(50)  NOT NULL,
    `datetime`       DATETIME     NOT NULL,
    `date_rec`       DATE         NOT NULL,
    `value_aktual`   DOUBLE       DEFAULT NULL,
    `value_prediksi` DOUBLE       NOT NULL,
    `selisih`        DOUBLE       DEFAULT NULL,
    `high`           DOUBLE       DEFAULT NULL,
    `low`            DOUBLE       DEFAULT NULL,
    `anomali`        TINYINT(1)   DEFAULT 0,
    `equipment_num`  VARCHAR(100) DEFAULT NULL,
    `unit_id`        INT          DEFAULT NULL,
    `plant_id`       INT          DEFAULT NULL,
    `pred_interval`  INT          DEFAULT 1,
    `run_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `arsip_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`prediksi_id`, `date_rec`),
    INDEX `idx_date_rec` (`date_rec`),
    INDEX `idx_tagno`    (`tagno`),
    INDEX `idx_anomali`  (`anomali`),
    INDEX `idx_model_id` (`model_id`),
    INDEX `idx_arsip_at` (`arsip_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8
COMMENT='Arsip prediksi bearing > 12 bulan'
PARTITION BY RANGE (YEAR(`date_rec`) * 100 + MONTH(`date_rec`)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    PARTITION p202403 VALUES LESS THAN (202404),
    PARTITION p202404 VALUES LESS THAN (202405),
    PARTITION p202405 VALUES LESS THAN (202406),
    PARTITION p202406 VALUES LESS THAN (202407),
    PARTITION p202407 VALUES LESS THAN (202408),
    PARTITION p202408 VALUES LESS THAN (202409),
    PARTITION p202409 VALUES LESS THAN (202410),
    PARTITION p202410 VALUES LESS THAN (202411),
    PARTITION p202411 VALUES LESS THAN (202412),
    PARTITION p202412 VALUES LESS THAN (202501),
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    PARTITION p202503 VALUES LESS THAN (202504),
    PARTITION p202504 VALUES LESS THAN (202505),
    PARTITION p202505 VALUES LESS THAN (202506),
    PARTITION p202506 VALUES LESS THAN (202507),
    PARTITION p202507 VALUES LESS THAN (202508),
    PARTITION p202508 VALUES LESS THAN (202509),
    PARTITION p202509 VALUES LESS THAN (202510),
    PARTITION p202510 VALUES LESS THAN (202511),
    PARTITION p202511 VALUES LESS THAN (202512),
    PARTITION p202512 VALUES LESS THAN (202601),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);


-- ============================================================
-- 6. bearing_sensor
--    Konfigurasi sensor (tagno → nama, posisi, tipe)
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_sensor` (
    `sensor_id`    INT          NOT NULL AUTO_INCREMENT,
    `tagno`        VARCHAR(50)  NOT NULL UNIQUE  COMMENT 'Tag sensor',
    `nama`         VARCHAR(100) DEFAULT NULL     COMMENT 'Nama deskriptif',
    `lokasi`       VARCHAR(100) DEFAULT NULL     COMMENT 'Lokasi fisik sensor',
    `satuan`       VARCHAR(20)  DEFAULT '°C',
    `tipe`         ENUM('bearing','ambient','ref','load') DEFAULT 'bearing',
    `bearing_pos`  ENUM('Y1','Y2') DEFAULT NULL  COMMENT 'Y1=Bearing 858, Y2=Bearing 859',
    `alarm_high`   DOUBLE       DEFAULT NULL,
    `alarm_low`    DOUBLE       DEFAULT NULL,
    `unit_id`      INT          DEFAULT NULL,
    `plant_id`     INT          DEFAULT NULL,
    `is_active`    TINYINT(1)   DEFAULT 1,
    `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sensor_id`),
    KEY `idx_tagno` (`tagno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Master konfigurasi sensor bearing';

INSERT IGNORE INTO `bearing_sensor` (`tagno`, `nama`, `lokasi`, `satuan`, `tipe`, `bearing_pos`) VALUES
('877',  'Suhu Ruangan Ambient',    'Ruang kontrol', '°C', 'ambient', NULL),
('858',  'Bearing 1 (Drive End)',   'Motor DE',      '°C', 'bearing', 'Y1'),
('859',  'Bearing 2 (Non-Drive)',   'Motor NDE',     '°C', 'bearing', 'Y2'),
('336',  'Sensor Pembanding',       'Area mesin',    '°C', 'ref',     NULL),
('1577', 'Beban Fan / Load',        '',              '%',  'load',    NULL);


-- ============================================================
-- 7. bearing_csv_sensor_data
--    Data sensor dari upload CSV di halaman "Upload CSV Sensor"
-- ============================================================
CREATE TABLE IF NOT EXISTS `bearing_csv_sensor_data` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `tagno`      VARCHAR(50)  NOT NULL,
    `tanggal`    DATE         NOT NULL,
    `nilai`      DOUBLE       NOT NULL,
    `filename`   VARCHAR(255) DEFAULT NULL,
    `unit_id`    INT          DEFAULT NULL,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tagno_tanggal` (`tagno`, `tanggal`),
    KEY `idx_tagno`    (`tagno`),
    KEY `idx_tanggal`  (`tanggal`),
    KEY `idx_unit_id`  (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Data sensor dari file CSV yang diupload pengguna';


-- ============================================================
-- 8. tag_master
--    Master data semua tag sensor
-- ============================================================
CREATE TABLE IF NOT EXISTS `tag_master` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `tagno`       VARCHAR(50)  NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `unit`        VARCHAR(20)  DEFAULT NULL,
    `plant_id`    INT          DEFAULT NULL,
    `unit_id`     INT          DEFAULT NULL,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tagno`   (`tagno`),
    KEY `idx_unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Master data tag sensor';

INSERT IGNORE INTO `tag_master` (`tagno`, `description`, `unit`) VALUES
('877',  'Ambient Temperature',      '°C'),
('858',  'Bearing Temperature 1',    '°C'),
('859',  'Bearing Temperature 2',    '°C'),
('336',  'Reference Sensor',         '°C'),
('1577', 'Fan Load',                 '%');


-- ============================================================
-- 9. tag_data
--    Data time-series semua tag sensor
--    Digunakan oleh fitur Parameter Monitoring
-- ============================================================
CREATE TABLE IF NOT EXISTS `tag_data` (
    `id`         BIGINT    NOT NULL AUTO_INCREMENT,
    `tagno`      VARCHAR(50) NOT NULL,
    `timestamp`  DATETIME  NOT NULL,
    `value`      DOUBLE    DEFAULT NULL,
    `quality`    TINYINT   DEFAULT 1  COMMENT '1=Good, 0=Bad',
    `created_at` DATETIME  DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tagno_timestamp` (`tagno`, `timestamp`),
    KEY `idx_tagno_ts` (`tagno`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Data time-series sensor untuk parameter monitoring';


-- ============================================================
-- 10. VIEW: gabungan data aktif + arsip
-- ============================================================
CREATE OR REPLACE VIEW `v_bearing_aktual_all` AS
    SELECT `aktual_id`, `tagno`, `datetime`, `date_rec`,
           `value`, `equipment_num`, `created_at`, 'aktif' AS `sumber`
    FROM `bearing_aktual`
    UNION ALL
    SELECT `aktual_id`, `tagno`, `datetime`, `date_rec`,
           `value`, `equipment_num`, `created_at`, 'arsip' AS `sumber`
    FROM `bearing_aktual_arsip`;

CREATE OR REPLACE VIEW `v_bearing_prediksi_all` AS
    SELECT `prediksi_id`, `model_id`, `tagno`, `datetime`, `date_rec`,
           `value_aktual`, `value_prediksi`, `selisih`, `high`, `low`,
           `anomali`, `equipment_num`, `unit_id`, `plant_id`,
           `pred_interval`, `run_at`, 'aktif' AS `sumber`
    FROM `bearing_prediksi`
    UNION ALL
    SELECT `prediksi_id`, `model_id`, `tagno`, `datetime`, `date_rec`,
           `value_aktual`, `value_prediksi`, `selisih`, `high`, `low`,
           `anomali`, `equipment_num`, `unit_id`, `plant_id`,
           `pred_interval`, `run_at`, 'arsip' AS `sumber`
    FROM `bearing_prediksi_arsip`;


-- ============================================================
-- 11. STORED PROCEDURE: kelola partisi bulanan + arsip otomatis
--     Dipanggil oleh event evt_manage_bearing_partitions setiap bulan
-- ============================================================
DROP PROCEDURE IF EXISTS `sp_manage_bearing_partitions`;

DELIMITER $$

CREATE PROCEDURE `sp_manage_bearing_partitions`()
BEGIN
    DECLARE RETENTION_MONTHS INT     DEFAULT 12;
    DECLARE v_next_month     DATE;
    DECLARE v_next_p_value   INT;
    DECLARE v_next_p_name    VARCHAR(20);
    DECLARE v_arsip_before   DATE;
    DECLARE v_arsip_p_name   VARCHAR(20);
    DECLARE v_sql            TEXT;
    DECLARE v_rows           INT DEFAULT 0;

    -- Abaikan error partisi sudah ada (1517) atau nama duplikat (1568)
    DECLARE CONTINUE HANDLER FOR 1517 BEGIN END;
    DECLARE CONTINUE HANDLER FOR 1568 BEGIN END;

    -- Bulan berikutnya untuk partisi baru
    SET v_next_month   = DATE_ADD(LAST_DAY(NOW()), INTERVAL 1 DAY);
    SET v_next_p_name  = CONCAT('p', DATE_FORMAT(v_next_month, '%Y%m'));
    SET v_next_p_value = YEAR(DATE_ADD(v_next_month, INTERVAL 1 MONTH)) * 100
                       + MONTH(DATE_ADD(v_next_month, INTERVAL 1 MONTH));

    -- Batas waktu arsip (data lebih lama dari RETENTION_MONTHS)
    SET v_arsip_before = DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL RETENTION_MONTHS MONTH);
    SET v_arsip_p_name = CONCAT('p', DATE_FORMAT(v_arsip_before, '%Y%m'));

    -- ── Arsip bearing_aktual ──────────────────────────────────
    INSERT IGNORE INTO `bearing_aktual_arsip`
        (`aktual_id`, `tagno`, `datetime`, `date_rec`, `value`, `equipment_num`, `created_at`, `arsip_at`)
    SELECT `aktual_id`, `tagno`, `datetime`, `date_rec`,
           `value`, `equipment_num`, `created_at`, NOW()
    FROM `bearing_aktual` WHERE `date_rec` < v_arsip_before;

    GET DIAGNOSTICS v_rows = ROW_COUNT;
    IF v_rows > 0 THEN
        DELETE FROM `bearing_aktual` WHERE `date_rec` < v_arsip_before;
    END IF;

    -- Tambah partisi arsip baru
    SET v_sql = CONCAT(
        'ALTER TABLE bearing_aktual_arsip REORGANIZE PARTITION p_future INTO (',
        'PARTITION ', v_arsip_p_name, ' VALUES LESS THAN (',
            YEAR(DATE_ADD(v_arsip_before, INTERVAL 1 MONTH)) * 100 +
            MONTH(DATE_ADD(v_arsip_before, INTERVAL 1 MONTH)),
        '), PARTITION p_future VALUES LESS THAN MAXVALUE)'
    );
    SET @sql = v_sql; PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    -- Tambah partisi aktif bulan baru
    SET v_sql = CONCAT(
        'ALTER TABLE bearing_aktual REORGANIZE PARTITION p_future INTO (',
        'PARTITION ', v_next_p_name, ' VALUES LESS THAN (', v_next_p_value, '),',
        'PARTITION p_future VALUES LESS THAN MAXVALUE)'
    );
    SET @sql = v_sql; PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    -- ── Arsip bearing_prediksi ────────────────────────────────
    INSERT IGNORE INTO `bearing_prediksi_arsip`
        (`prediksi_id`, `model_id`, `tagno`, `datetime`, `date_rec`,
         `value_aktual`, `value_prediksi`, `selisih`, `high`, `low`,
         `anomali`, `equipment_num`, `unit_id`, `plant_id`, `pred_interval`, `run_at`, `arsip_at`)
    SELECT `prediksi_id`, `model_id`, `tagno`, `datetime`, `date_rec`,
           `value_aktual`, `value_prediksi`, `selisih`, `high`, `low`,
           `anomali`, `equipment_num`, `unit_id`, `plant_id`, `pred_interval`, `run_at`, NOW()
    FROM `bearing_prediksi` WHERE `date_rec` < v_arsip_before;

    GET DIAGNOSTICS v_rows = ROW_COUNT;
    IF v_rows > 0 THEN
        DELETE FROM `bearing_prediksi` WHERE `date_rec` < v_arsip_before;
    END IF;

    SET v_sql = CONCAT(
        'ALTER TABLE bearing_prediksi_arsip REORGANIZE PARTITION p_future INTO (',
        'PARTITION ', v_arsip_p_name, ' VALUES LESS THAN (',
            YEAR(DATE_ADD(v_arsip_before, INTERVAL 1 MONTH)) * 100 +
            MONTH(DATE_ADD(v_arsip_before, INTERVAL 1 MONTH)),
        '), PARTITION p_future VALUES LESS THAN MAXVALUE)'
    );
    SET @sql = v_sql; PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET v_sql = CONCAT(
        'ALTER TABLE bearing_prediksi REORGANIZE PARTITION p_future INTO (',
        'PARTITION ', v_next_p_name, ' VALUES LESS THAN (', v_next_p_value, '),',
        'PARTITION p_future VALUES LESS THAN MAXVALUE)'
    );
    SET @sql = v_sql; PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SELECT
        CONCAT('Partisi baru: ', v_next_p_name)                       AS info_partisi_baru,
        CONCAT('Data sebelum ', v_arsip_before, ' dipindah ke arsip') AS info_arsip,
        CONCAT('Retensi: ', RETENTION_MONTHS, ' bulan')               AS info_retensi;
END$$

DELIMITER ;


-- ============================================================
-- 12. EVENT: jalankan procedure tiap bulan (tanggal 25, 02:00)
-- ============================================================
DROP EVENT IF EXISTS `evt_manage_bearing_partitions`;

CREATE EVENT `evt_manage_bearing_partitions`
    ON SCHEDULE
        EVERY 1 MONTH
        STARTS (DATE_FORMAT(NOW(), '%Y-%m-25 02:00:00'))
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Auto arsip data lama + tambah partisi bulan baru bearing'
    DO CALL `sp_manage_bearing_partitions`();

SET FOREIGN_KEY_CHECKS = 1;

-- Verifikasi semua tabel terbuat
SHOW TABLES;
