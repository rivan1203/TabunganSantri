CREATE DATABASE IF NOT EXISTS tabungan_santri CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tabungan_santri;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 password_display VARCHAR(255) NULL,
 role ENUM('admin','pengurus','santri') NOT NULL,
 student_id BIGINT UNSIGNED NULL UNIQUE,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE students (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 student_code VARCHAR(128) NOT NULL UNIQUE,
 nis VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 class_name VARCHAR(80) NOT NULL,
 pin_hash VARCHAR(255) NOT NULL,
 balance BIGINT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
ALTER TABLE users ADD CONSTRAINT fk_users_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE SET NULL;
CREATE TABLE transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 receipt_no VARCHAR(40) NOT NULL UNIQUE,
 student_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 type ENUM('topup','withdrawal') NOT NULL,
 amount BIGINT NOT NULL,
 balance_before BIGINT NOT NULL,
 balance_after BIGINT NOT NULL,
 note VARCHAR(255) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(student_id) REFERENCES students(id),
 FOREIGN KEY(user_id) REFERENCES users(id),
 INDEX idx_student_date(student_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE menu_permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role ENUM('admin','pengurus','santri') NOT NULL,
 menu_key VARCHAR(50) NOT NULL,
 allowed TINYINT(1) NOT NULL DEFAULT 0,
 UNIQUE KEY uq_role_menu(role,menu_key)
) ENGINE=InnoDB;
INSERT INTO users(username,password_hash,role) VALUES('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin');
INSERT INTO menu_permissions(role,menu_key,allowed) VALUES
('admin','dashboard',1),('admin','students',1),('admin','scan',1),('admin','transactions',1),('admin','permissions',1),('admin','users',1),
('pengurus','dashboard',1),('pengurus','students',1),('pengurus','scan',1),('pengurus','transactions',1),
('santri','dashboard',1),('santri','my_balance',1),('santri','cek_saldo',1),('santri','transactions',1);

CREATE TABLE IF NOT EXISTS site_identity (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, pesantren_name VARCHAR(180) NOT NULL DEFAULT 'Nama Pesantren', logo_path VARCHAR(255) NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO site_identity (id, pesantren_name, logo_path) VALUES (1, 'Nama Pesantren', NULL);
