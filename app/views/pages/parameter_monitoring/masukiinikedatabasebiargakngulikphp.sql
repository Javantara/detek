#masukin ini ke databasenya pln_web 

USE pln_web;

ALTER TABLE units ADD COLUMN IF NOT EXISTS database_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE units ADD COLUMN IF NOT EXISTS excel_path VARCHAR(255) DEFAULT NULL;

UPDATE plants SET status = 1 WHERE description LIKE '%Pacitan%';

INSERT IGNORE INTO units (unit_name, status, plant_id, database_name) 
    VALUES ('PLTU Pacitan unit 1', 1, 4, 'db_pacitan_1');
INSERT IGNORE INTO units (unit_name, status, plant_id, database_name) 
    VALUES ('PLTU Pacitan unit 2', 1, 4, 'db_pacitan_2');

#masukin ini kalau gak sengaja ke built data lama buat migrasi ke data partisi yang baru
USE db_pacitan_2; 
// ini diganti sesuai db yang mau di ganti

RENAME TABLE tag_data TO tag_data_old;

CREATE TABLE tag_data (
    data_id   BIGINT AUTO_INCREMENT,
    tag_id    INT NOT NULL,
    timestamp DATETIME NOT NULL,
    value     FLOAT NOT NULL,
    PRIMARY KEY (data_id, timestamp),
    INDEX idx_tag_ts (tag_id, timestamp)
) ENGINE=InnoDB
PARTITION BY RANGE (TO_DAYS(timestamp)) (
    PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
    PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
    PARTITION p202603 VALUES LESS THAN (TO_DAYS('2026-04-01')),
    PARTITION p202604 VALUES LESS THAN (TO_DAYS('2026-05-01')),
    PARTITION p202605 VALUES LESS THAN (TO_DAYS('2026-06-01')),
    PARTITION p202606 VALUES LESS THAN (TO_DAYS('2026-07-01')),
    PARTITION p202607 VALUES LESS THAN (TO_DAYS('2026-08-01')),
    PARTITION p202608 VALUES LESS THAN (TO_DAYS('2026-09-01')),
    PARTITION p202609 VALUES LESS THAN (TO_DAYS('2026-10-01')),
    PARTITION p202610 VALUES LESS THAN (TO_DAYS('2026-11-01')),
    PARTITION p202611 VALUES LESS THAN (TO_DAYS('2026-12-01')),
    PARTITION p202612 VALUES LESS THAN (TO_DAYS('2027-01-01')),
    PARTITION pmax    VALUES LESS THAN MAXVALUE
);

INSERT INTO tag_data SELECT * FROM tag_data_old;

DROP TABLE tag_data_old;