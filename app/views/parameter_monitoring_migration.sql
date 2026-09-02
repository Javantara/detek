-- Tabel address (daftar tag/sensor)
CREATE TABLE IF NOT EXISTS pm_address (
    address_id   INT AUTO_INCREMENT PRIMARY KEY,
    plant_id     INT NOT NULL,
    unit_id      INT NOT NULL,
    address_no   VARCHAR(200) NOT NULL,
    tag_id       INT NOT NULL,
    deskripsi    VARCHAR(300) NOT NULL,
    satuan       VARCHAR(50)  NOT NULL DEFAULT '',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tag_plant_unit (plant_id, unit_id, tag_id),
    FOREIGN KEY (plant_id) REFERENCES plants(plant_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id)  REFERENCES units(unit_id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel data sensor (time-series)
CREATE TABLE IF NOT EXISTS pm_data (
    data_id      BIGINT AUTO_INCREMENT PRIMARY KEY,
    address_id   INT NOT NULL,
    tag_id       INT NOT NULL,
    recorded_at  DATETIME NOT NULL,
    value        FLOAT   NOT NULL,
    INDEX idx_addr_time (address_id, recorded_at),
    INDEX idx_tag_time  (tag_id, recorded_at),
    FOREIGN KEY (address_id) REFERENCES pm_address(address_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
