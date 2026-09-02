-- ============================================================
-- 02_APP_DATABASE.sql
-- Database utama aplikasi: pln_web
-- Jalankan di: phpMyAdmin → pilih database "pln_web" → tab SQL
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ────────────────────────────────────────────────────────────
-- 1. Parameter Monitoring — master alamat/tag
--    Digunakan oleh fitur Parameter Monitoring
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pm_addresses` (
    `address_id`  INT           NOT NULL AUTO_INCREMENT,
    `plant_id`    INT           NOT NULL,
    `unit_id`     INT           NOT NULL,
    `address_no`  VARCHAR(255)  NOT NULL,
    `tag_id`      INT           NOT NULL UNIQUE,
    `deskripsi`   VARCHAR(500)  NOT NULL,
    `satuan`      VARCHAR(50)   NOT NULL DEFAULT '',
    `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`address_id`),
    FOREIGN KEY (`plant_id`) REFERENCES `plants`(`plant_id`) ON DELETE CASCADE,
    FOREIGN KEY (`unit_id`)  REFERENCES `units`(`unit_id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Master alamat/tag sensor untuk fitur Parameter Monitoring';

-- ────────────────────────────────────────────────────────────
-- 2. Parameter Monitoring — data time-series
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pm_data` (
    `data_id`    BIGINT    NOT NULL AUTO_INCREMENT,
    `tag_id`     INT       NOT NULL,
    `timestamp`  DATETIME  NOT NULL,
    `value`      DOUBLE    NOT NULL,
    PRIMARY KEY (`data_id`),
    INDEX `idx_tag_ts` (`tag_id`, `timestamp`),
    FOREIGN KEY (`tag_id`) REFERENCES `pm_addresses`(`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Data time-series sensor untuk Parameter Monitoring';

-- ────────────────────────────────────────────────────────────
-- 3. Tambah menu "Upload CSV Sensor" ke sidebar
--    Lewati jika sudah ada (INSERT IGNORE)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `menus` (`menu_name`, `menu_link`, `roles`, `status`, `menu_order`)
VALUES (
    'Upload CSV Sensor',
    'bearing-csv',
    'all',
    'active',
    (SELECT COALESCE(MAX(m2.`menu_order`), 0) + 10 FROM `menus` m2)
);

SET FOREIGN_KEY_CHECKS = 1;

-- Verifikasi
SELECT `menu_id`, `menu_name`, `menu_link`, `status`, `menu_order`
FROM `menus`
ORDER BY `menu_order` ASC, `menu_id` ASC;
