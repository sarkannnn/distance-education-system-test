SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for users (Distant Təhsil Sistemi - Super User)
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
-- Pass: Ndu2026!
-- ----------------------------
INSERT INTO `users` (`student_id`, `first_name`, `last_name`, `email`, `password`, `role`) 
VALUES ('SUPERADMIN', 'Super', 'Admin', 'admin@ndu.edu.az', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'admin')
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role` = 'admin';


-- ----------------------------
-- Table structure for webinar_faculties
-- ----------------------------
DROP TABLE IF EXISTS `webinar_faculties`;
CREATE TABLE `webinar_faculties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for webinar_users
-- ----------------------------
DROP TABLE IF EXISTS `webinar_users`;
CREATE TABLE `webinar_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `role` ENUM('teacher', 'student') NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `webinar_faculties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for webinars
-- ----------------------------
DROP TABLE IF EXISTS `webinars`;
CREATE TABLE `webinars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
    `scheduled_at` DATETIME,
    `duration` INT DEFAULT 90,
    `peer_id` VARCHAR(255) NULL,
    `started_at` DATETIME NULL,
    `ended_at` DATETIME NULL,
    `recording_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`faculty_id`),
    INDEX (`status`),
    FOREIGN KEY (`faculty_id`) REFERENCES `webinar_faculties`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `webinar_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of webinar_faculties
-- ----------------------------
INSERT INTO `webinar_faculties` (`id`, `name`, `slug`) VALUES
(1, 'Fizika-Riyaziyyat', 'fizika-riyaziyyat'),
(2, 'Təbiətşünaslıq və Kənd təsərrüfatı', 'tebiet-kend-teserrufati'),
(3, 'Memarlıq və Mühəndislik', 'memarliq-muhendislik'),
(4, 'Xarici dillər', 'xarici-diller'),
(5, 'Pedaqoji', 'pedaqoji'),
(6, 'Tarix-Filologiya', 'tarix-filologiya'),
(7, 'Beynəlxalq Münasibətlər və Hüquq', 'beynelxalq-huquq'),
(8, 'İqtisadiyyat və İdarəetmə', 'iqtisadiyyat-idareetme'),
(9, 'Tibb', 'tibb'),
(10, 'İncəsənət', 'incesenet'),
(11, 'Magistratura', 'magistratura'),
(12, 'Naxçıvan Tibb Kolleci', 'tibb-kolleci'),
(13, 'Naxçıvan Texniki Kolleci', 'texniki-kolleci'),
(14, 'Naxçıvan Musiqi Kolleci', 'musiqi-kolleci'),
(15, 'NDU Beynəlxalq Kembric məktəbi', 'kembric-mektebi'),
(16, 'NDU nəznində İngilis dili təmayüllü Gimnaziya', 'gimnaziya'),
(17, 'Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi', 'ecnebi-merkez');

-- ----------------------------
-- Records of webinar_users
-- Note: Usernames match the real DB suffixes (_muhazireci and _istirakci)
-- All passwords: 'Ndu2026!'
-- Hash: $2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq
-- ----------------------------
INSERT INTO `webinar_users` (`faculty_id`, `role`, `username`, `password_hash`, `full_name`) VALUES
(1, 'teacher', 'fizika-riyaziyyat_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Fizika-Riyaziyyat Mühazirəçisi'),
(1, 'student', 'fizika-riyaziyyat_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Fizika-Riyaziyyat İştirakçısı'),
(2, 'teacher', 'tebiet-kend-teserrufati_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Təbiətşünaslıq Mühazirəçisi'),
(2, 'student', 'tebiet-kend-teserrufati_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Təbiətşünaslıq İştirakçısı'),
(3, 'teacher', 'memarliq-muhendislik_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Memarlıq Mühazirəçisi'),
(3, 'student', 'memarliq-muhendislik_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Memarlıq İştirakçısı'),
(4, 'teacher', 'xarici-diller_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Xarici dillər Mühazirəçisi'),
(4, 'student', 'xarici-diller_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Xarici dillər İştirakçısı'),
(5, 'teacher', 'pedaqoji_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Pedaqoji Mühazirəçisi'),
(5, 'student', 'pedaqoji_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Pedaqoji İştirakçısı'),
(6, 'teacher', 'tarix-filologiya_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Tarix-Filologiya Mühazirəçisi'),
(6, 'student', 'tarix-filologiya_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Tarix-Filologiya İştirakçısı'),
(7, 'teacher', 'beynelxalq-huquq_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Beynəlxalq Münasibətlər və Hüquq Mühazirəçisi'),
(7, 'student', 'beynelxalq-huquq_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Beynəlxalq Münasibətlər və Hüquq İştirakçısı'),
(8, 'teacher', 'iqtisadiyyat-idareetme_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'İqtisadiyyat və İdarəetmə Mühazirəçisi'),
(8, 'student', 'iqtisadiyyat-idareetme_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'İqtisadiyyat və İdarəetmə İştirakçısı'),
(9, 'teacher', 'tibb_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Tibb Mühazirəçisi'),
(9, 'student', 'tibb_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Tibb İştirakçısı'),
(10, 'teacher', 'incesenet_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'İncəsənət Mühazirəçisi'),
(10, 'student', 'incesenet_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'İncəsənət İştirakçısı'),
(11, 'teacher', 'magistratura_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Magistratura Mühazirəçisi'),
(11, 'student', 'magistratura_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Magistratura İştirakçısı'),
(12, 'teacher', 'tibb-kolleci_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Naxçıvan Tibb Kolleci Mühazirəçisi'),
(12, 'student', 'tibb-kolleci_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Naxçıvan Tibb Kolleci İştirakçısı'),
(13, 'teacher', 'texniki-kolleci_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Naxçıvan Texniki Kolleci Mühazirəçisi'),
(13, 'student', 'texniki-kolleci_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Naxçıvan Texniki Kolleci İştirakçısı'),
(14, 'teacher', 'musiqi-kolleci_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Naxçıvan Musiqi Kolleci Mühazirəçisi'),
(14, 'student', 'musiqi-kolleci_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Naxçıvan Musiqi Kolleci İştirakçısı'),
(15, 'teacher', 'kembric-mektebi_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'NDU Beynəlxalq Kembric məktəbi Mühazirəçisi'),
(15, 'student', 'kembric-mektebi_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'NDU Beynəlxalq Kembric məktəbi İştirakçısı'),
(16, 'teacher', 'gimnaziya_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'İngilis dili təmayüllü Gimnaziya Mühazirəçisi'),
(16, 'student', 'gimnaziya_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'İngilis dili təmayüllü Gimnaziya İştirakçısı'),
(17, 'teacher', 'ecnebi-merkez_muhazireci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Əcnəbi təhsil alanlarla iş mərkəzi Mühazirəçisi'),
(17, 'student', 'ecnebi-merkez_istirakci', '$2y$10$Y741yx9atZvW1VEETBDYruJpp7JSjXE0DmzRSxg/Sw3VhH9WVRMFq', 'Əcnəbi təhsil alanlarla iş mərkəzi İştirakçısı');

SET FOREIGN_KEY_CHECKS = 1;
