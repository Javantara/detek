-- Tabel address/tag master data
CREATE TABLE IF NOT EXISTS pm_addresses (
    address_id  INT AUTO_INCREMENT PRIMARY KEY,
    plant_id    INT NOT NULL,
    unit_id     INT NOT NULL,
    address_no  VARCHAR(255) NOT NULL,
    tag_id      INT NOT NULL UNIQUE,
    deskripsi   VARCHAR(500) NOT NULL,
    satuan      VARCHAR(50)  NOT NULL DEFAULT '',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(plant_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id)  REFERENCES units(unit_id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel data time-series
CREATE TABLE IF NOT EXISTS pm_data (
    data_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
    tag_id      INT NOT NULL,
    timestamp   DATETIME NOT NULL,
    value       FLOAT   NOT NULL,
    INDEX idx_tag_ts (tag_id, timestamp),
    FOREIGN KEY (tag_id) REFERENCES pm_addresses(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
