-- ==============================================================================
-- NDU DISTANT TƏHSİL SİSTEMİ
-- Miqrasiya Faylı: Super User və Vebinar Sistemi Üçün Baza Yemilənmələri
-- ==============================================================================

-- 1. YENİ 'ADMIN / SUPER USER' HESABININ ƏLAVƏ EDİLMƏSİ (users cədvəlinə)
-- Qeyd: Əgər artıq əlavə edilibsə xəta verməməsi üçün IGNORE istifadə olunur.
INSERT IGNORE INTO `users` (`student_id`, `first_name`, `last_name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`) 
VALUES (
    'ADMIN-001', 
    'Super', 
    'User', 
    'superadmin@ndu.edu.az', 
    '$2y$10$2RrAGnX4YVmC3f58jgzAP.5TK3ZFSqxSsZWCYKbiwAFtd7rjx6KSG', -- Müvəqqəti Şifrə: NDU_Admin_2024!
    'admin', 
    1, 
    NOW(), 
    NOW()
);

-- ==============================================================================

-- 2. VEBİNAR SİSTEMİ ÜÇÜN CƏDVƏLLƏRİN YARADILMASI

-- 2.1 Vebinar Fakültələri Cədvəli
CREATE TABLE IF NOT EXISTS `webinar_faculties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.2 Vebinar İstifadəçiləri (Hesabları) Cədvəli
CREATE TABLE IF NOT EXISTS `webinar_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `role` ENUM('teacher', 'student') NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `webinar_faculties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 Vebinarlar Cədvəli
-- Sütunlara 'recording_path' (Video arxivi) əlavə edilmiş son haldır.
CREATE TABLE IF NOT EXISTS `webinars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
    `scheduled_at` DATETIME,
    `duration` INT NULL DEFAULT 90,
    `started_at` DATETIME NULL,
    `ended_at` DATETIME NULL,
    `recording_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`faculty_id`),
    INDEX (`status`),
    FOREIGN KEY (`faculty_id`) REFERENCES `webinar_faculties`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `webinar_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================

-- 3. VEBİNAR FAKÜLTƏLƏRİNİN BAZAYA ƏLAVƏ EDİLMƏSİ (Başlanğıc/Default Məlumatlar)
INSERT IGNORE INTO `webinar_faculties` (`name`, `slug`) VALUES
('Fizika-Riyaziyyat', 'fizika-riyaziyyat'),
('Təbiətşünaslıq və Kənd təsərrüfatı', 'tebiet-kend-teserrufati'),
('Memarlıq və Mühəndislik', 'memarliq-muhendislik'),
('Xarici dillər', 'xarici-diller'),
('Pedaqoji', 'pedaqoji'),
('Tarix-Filologiya', 'tarix-filologiya'),
('Beynəlxalq Münasibətlər və Hüquq', 'beynelxalq-huquq'),
('İqtisadiyyat və İdarəetmə', 'iqtisadiyyat-idareetme'),
('Tibb', 'tibb'),
('İncəsənət', 'incesenet'),
('Magistratura', 'magistratura'),
('Naxçıvan Tibb Kolleci', 'tibb-kolleci'),
('Naxçıvan Texniki Kolleci', 'texniki-kolleci'),
('Naxçıvan Musiqi Kolleci', 'musiqi-kolleci'),
('NDU Beynəlxalq Kembric məktəbi', 'kembric-mektebi'),
('NDU nəznində İngilis dili təmayüllü Gimnaziya', 'gimnaziya'),
('Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi', 'ecnebi-merkez');
