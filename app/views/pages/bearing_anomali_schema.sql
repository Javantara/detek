-- ============================================================
-- SCHEMA: Bearing Anomali v3 — dengan plant_id & unit_id
-- ============================================================
USE pln_web;

-- Tabel model ML
CREATE TABLE IF NOT EXISTS bearing_models (
    model_id      INT AUTO_INCREMENT PRIMARY KEY,
    model_name    VARCHAR(100) NOT NULL,
    bearing_label ENUM('Y1','Y2') NOT NULL,
    files_x       TEXT,
    files_y       TEXT,
    files_load    TEXT,
    train_start   DATE NOT NULL,
    train_end     DATE NOT NULL,
    load_min      FLOAT DEFAULT 100,
    batas         FLOAT DEFAULT 5,
    coef_a        FLOAT NOT NULL DEFAULT 0,
    coef_b        FLOAT NOT NULL DEFAULT 0,
    r2_score      FLOAT NOT NULL,
    mae_train     FLOAT,
    n_train       INT,
    model_type    ENUM('xgboost','linear') NOT NULL DEFAULT 'xgboost',
    model_blob    LONGTEXT,
    plant_id      INT DEFAULT NULL,
    unit_id       INT DEFAULT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by    VARCHAR(100),
    notes         TEXT,
    is_active     TINYINT(1) DEFAULT 1,
    INDEX idx_label      (bearing_label),
    INDEX idx_created    (created_at),
    INDEX idx_plant_unit (plant_id, unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrasi jika tabel sudah ada:
-- ALTER TABLE bearing_models
--   ADD COLUMN plant_id INT DEFAULT NULL AFTER model_blob,
--   ADD COLUMN unit_id  INT DEFAULT NULL AFTER plant_id,
--   ADD INDEX idx_plant_unit (plant_id, unit_id);

-- Tabel log anomali
CREATE TABLE IF NOT EXISTS bearing_anomaly_log (
    log_id        BIGINT AUTO_INCREMENT PRIMARY KEY,
    model_id      INT NOT NULL,
    run_date      DATETIME DEFAULT CURRENT_TIMESTAMP,
    pred_start    DATE NOT NULL,
    pred_end      DATE NOT NULL,
    sensor_date   DATE NOT NULL,
    value_actual  FLOAT,
    value_pred    FLOAT,
    value_x       FLOAT,
    deviation     FLOAT,
    is_anomaly    TINYINT(1) DEFAULT 0,
    INDEX idx_model   (model_id),
    INDEX idx_date    (sensor_date),
    INDEX idx_anomaly (is_anomaly),
    FOREIGN KEY (model_id) REFERENCES bearing_models(model_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel metadata CSV — dengan plant_id & unit_id
CREATE TABLE IF NOT EXISTS bearing_csv_files (
    file_id       INT AUTO_INCREMENT PRIMARY KEY,
    filename      VARCHAR(255) NOT NULL,
    filepath      VARCHAR(500) NOT NULL,
    file_size     INT,
    row_count     INT,
    date_min      DATE,
    date_max      DATE,
    plant_id      INT DEFAULT NULL,
    unit_id       INT DEFAULT NULL,
    uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by   VARCHAR(100),
    UNIQUE KEY uk_file_unit (filename, unit_id),
    INDEX idx_filename   (filename),
    INDEX idx_plant_unit (plant_id, unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrasi jika tabel sudah ada:
-- ALTER TABLE bearing_csv_files
--   DROP INDEX idx_filename,
--   ADD COLUMN plant_id INT DEFAULT NULL AFTER date_max,
--   ADD COLUMN unit_id  INT DEFAULT NULL AFTER plant_id,
--   ADD UNIQUE KEY uk_file_unit (filename, unit_id),
--   ADD INDEX idx_plant_unit (plant_id, unit_id);
