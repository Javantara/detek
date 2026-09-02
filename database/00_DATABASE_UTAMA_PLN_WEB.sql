-- ============================================================
-- PLN WEB APPLICATION DATABASE
-- ============================================================
DROP DATABASE IF EXISTS pln_web;
CREATE DATABASE pln_web CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pln_web;

-- ────────────────────────────────────────────────────────────
-- Tabel: roles
-- Daftar role yang tersedia di sistem
-- ────────────────────────────────────────────────────────────
CREATE TABLE roles (
  role_id   INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Nama role: superadmin, admin, user',
  label     VARCHAR(100) NOT NULL          COMMENT 'Label tampil di UI',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (role_id, role_name, label) VALUES
(1, 'superadmin', 'Super Administrator'),
(2, 'admin',      'Administrator'),
(3, 'user',       'User Operator');

-- ────────────────────────────────────────────────────────────
-- Tabel: plants
-- ────────────────────────────────────────────────────────────
CREATE TABLE plants (
  plant_id    INT AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(200) NOT NULL,
  status      TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO plants (plant_id, description, status) VALUES
(1,  'Kantor Pusat',           1),
(2,  'PLTU Paiton',            1),
(3,  'PLTU Indramayu',         0),
(4,  'PLTU Pacitan',           0),
(5,  'PLTU Paiton #9',         0),
(6,  'PLTU Tanjung Awar-Awar', 0),
(7,  'PLTU Rembang',           0),
(8,  'PLTU Tenayan',           0),
(9,  'PLTU Kaltim Teluk',      0),
(10, 'PLTU Pulang Pisau',      0),
(11, 'PLTA Cirata',            0),
(12, 'PLTMG Arun',             0),
(13, 'PLTA Brantas',           0),
(14, 'PLTMG Bawean',           0),
(15, 'PLTU Tidore',            0),
(16, 'PLTA Asahan',            0),
(17, 'PLTA Batang Toru',       0),
(18, 'PLTD Suppa',             0),
(19, 'PLTG Duri',              0),
(20, 'PLTU Ampana',            0),
(21, 'PLTU Amurang',           0),
(22, 'PLTU Tembilahan',        0),
(23, 'PLTU Talaud',            0),
(24, 'PLTU S2P Cilacap',       0),
(25, 'PLTU Ropa',              0),
(26, 'PLTU Ketapang',          0),
(27, 'PLTU Jawa 7',            0),
(28, 'PLTU Kendari',           0),
(29, 'PLTU Bolok',             0),
(30, 'PLTU Belitung',          0),
(31, 'PLTU Bangka',            0),
(32, 'PLTU Banjarsari',        0),
(33, 'PLTU Anggrek',           0),
(34, 'UP Muara Tawar',         0),
(35, 'UP Muara Karang',        0),
(36, 'UP Gresik',              0),
(37, 'PLTU Tarahan',           0),
(38, 'UP Belawan',             0),
(39, 'UP Bandar Lampung',      0),
(40, 'UP Bakaru',              0),
(41, 'PLTU Sebalang',          0),
(42, 'UP Punagaya',            0),
(43, 'UP Minahasa',            0),
(44, 'UP Pandan',              0),
(45, 'UP Pekanbaru',           0),
(46, 'PLTU Nagan Raya',        0),
(47, 'UP Sengkang',            0),
(48, 'PLTMG BauBau',           0),
(49, 'PLTMG Bima',             0),
(50, 'PLTMG Kendari',          0);

-- ────────────────────────────────────────────────────────────
-- Tabel: units
-- ────────────────────────────────────────────────────────────
CREATE TABLE units (
  unit_id          INT AUTO_INCREMENT PRIMARY KEY,
  unit_name        VARCHAR(100) NOT NULL,
  status           TINYINT(1) DEFAULT 1,
  plant_id         INT NOT NULL,
  tab_manual_aktif INT DEFAULT 0,
  database_name    VARCHAR(100),
  excel_file       VARCHAR(100),
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (plant_id) REFERENCES plants(plant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO units (unit_id, unit_name, status, plant_id, tab_manual_aktif, database_name, excel_file) VALUES
(1,  'PLTU Paiton 2', 1, 2, 3, NULL,          NULL),
(2,  'PLTU Paiton 1', 1, 2, 6, 'db_paiton_1', 'PTPaiton.xlsm'),
(90, 'PLTU Paiton 9', 0, 2, 3, NULL,          NULL);

-- ────────────────────────────────────────────────────────────
-- Tabel: users
-- role_id mengacu ke tabel roles
-- ────────────────────────────────────────────────────────────
CREATE TABLE users (
  user_id         INT AUTO_INCREMENT PRIMARY KEY,
  nip             VARCHAR(20)  UNIQUE,
  username        VARCHAR(50)  NOT NULL UNIQUE,
  email           VARCHAR(100) NOT NULL UNIQUE,
  password        VARCHAR(255) NOT NULL,
  full_name       VARCHAR(100),
  role_id         INT NOT NULL DEFAULT 3 COMMENT 'FK ke tabel roles',
  assigned_plants TEXT         COMMENT 'Comma-separated plant_id',
  assigned_units  TEXT         COMMENT 'Comma-separated unit_id',
  status          ENUM('active','inactive') DEFAULT 'active',
  theme           VARCHAR(10) DEFAULT 'light',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- password default: "password"
INSERT INTO users (nip, username, email, password, full_name, role_id, assigned_plants, assigned_units) VALUES
('10001', 'superadmin', 'superadmin@pln.co.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 1, NULL, NULL),
('20001', 'admin',      'admin@pln.co.id',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Paiton',        2, '2',  '1,2'),
('30001', 'user',       'user@pln.co.id',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User Operator',       3, '2',  '2');

-- ────────────────────────────────────────────────────────────
-- Tabel: menus
-- ────────────────────────────────────────────────────────────
CREATE TABLE menus (
  menu_id    INT AUTO_INCREMENT PRIMARY KEY,
  menu_name  VARCHAR(100) NOT NULL DEFAULT '',
  menu_link  VARCHAR(255) NOT NULL DEFAULT '',
  menu_icon  VARCHAR(100) NOT NULL DEFAULT '',
  menu_order INT          NOT NULL DEFAULT 0,
  roles      VARCHAR(255) NOT NULL DEFAULT 'all' COMMENT 'all / superadmin / admin / user (comma-separated)',
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO menus (menu_name, menu_link, menu_icon, menu_order, roles, status) VALUES
('Performance Test List',   'coming-soon', '', 1, 'superadmin,admin,user', 'active'),
('Performance Test Output', 'coming-soon', '', 2, 'superadmin,admin,user', 'active'),
('Performance Baseline',    'coming-soon', '', 3, 'superadmin,admin,user', 'active'),
('Trend',                   'bearing-view', '', 4, 'user',                  'active'),
('Deteksi Anomali',        'bearing-anomali', '', 5, 'superadmin,admin',      'active');

-- ────────────────────────────────────────────────────────────
-- Tabel: user_activity
-- ────────────────────────────────────────────────────────────
CREATE TABLE user_activity (
  activity_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  nip         VARCHAR(20),
  full_name   VARCHAR(100),
  email       VARCHAR(100),
  action      ENUM('login','logout'),
  plant_id    INT,
  unit_id     INT,
  ip_address  VARCHAR(45),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- Tabel: user_sessions
-- ────────────────────────────────────────────────────────────
CREATE TABLE user_sessions (
  session_id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  selected_plant_id INT,
  selected_unit_id  INT,
  login_time        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  logout_time       TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- TABEL PASSWORD CHANGE REQUESTS
-- Permintaan ganti password dari user, menunggu approval superadmin
-- ────────────────────────────────────────────────────────────
CREATE TABLE password_requests (
    request_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    new_password    VARCHAR(255) NOT NULL     COMMENT 'Password baru (sudah di-hash)',
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    alasan          TEXT DEFAULT NULL         COMMENT 'Alasan approve/reject dari superadmin',
    reviewed_by     INT DEFAULT NULL          COMMENT 'user_id superadmin yang review',
    reviewed_at     TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- PATCH: Database terpisah per unit (jalankan setelah install)
-- ============================================================

-- Tambah kolom database_name & excel_path ke units (jika belum ada)
ALTER TABLE units ADD COLUMN IF NOT EXISTS database_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE units ADD COLUMN IF NOT EXISTS excel_path VARCHAR(255) DEFAULT NULL;

-- Aktifkan PLTU Pacitan dan tambahkan unit 1 & unit 2
UPDATE plants SET status = 1 WHERE plant_id = 4; -- PLTU Pacitan

-- Contoh: PLTU Pacitan unit 1 dan unit 2 (sesuaikan unit_id)
INSERT IGNORE INTO units (unit_id, unit_name, status, plant_id, database_name) VALUES
(10, 'PLTU Pacitan unit 1', 1, 4, 'db_pacitan_1'),
(11, 'PLTU Pacitan unit 2', 1, 4, 'db_pacitan_2');

-- Database terpisah akan di-create otomatis oleh sistem saat unit pertama kali diakses
-- Setiap database unit memiliki tabel: tag_master dan tag_data
