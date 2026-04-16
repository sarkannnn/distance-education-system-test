-- Distant Təhsil Sistemi - Super User (Admin) Quraşdırma Skripti
-- Tarix: 2026-04-16

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'instructor', 'admin') DEFAULT 'student',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of users (Super Admin)
-- Email: admin@ndu.edu.az
-- Password: Ndu2026!
-- Hash: $2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq
-- ----------------------------
INSERT INTO `users` (`student_id`, `first_name`, `last_name`, `email`, `password`, `role`) 
VALUES ('SUPERADMIN', 'Super', 'Admin', 'admin@ndu.edu.az', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'admin')
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role` = 'admin';

SET FOREIGN_KEY_CHECKS = 1;
