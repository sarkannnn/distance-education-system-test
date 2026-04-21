-- Webinar Kafedra Sistemi Yenilənmə Faylı
-- Hazırlanma tarixi: 2026-04-20 10:45:09

-- 1. Struktur Dəyişiklikləri
CREATE TABLE IF NOT EXISTS `webinar_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`faculty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `webinar_users` ADD COLUMN IF NOT EXISTS `department_id` INT DEFAULT NULL AFTER `faculty_id`;
ALTER TABLE `webinars` ADD COLUMN IF NOT EXISTS `department_id` INT DEFAULT NULL AFTER `faculty_id`;

-- Admin dəstəyi üçün struktur yeniləmələri
ALTER TABLE `webinar_users` MODIFY role ENUM('teacher', 'student', 'admin') NOT NULL;
ALTER TABLE `webinar_users` MODIFY faculty_id INT NULL;
ALTER TABLE `webinars` MODIFY faculty_id INT NULL;

-- 2. Fakültə Məlumatları
INSERT INTO `webinar_faculties` (id, name) VALUES (1, 'Təbiətşünaslıq və Kənd təsərrüfatı') ON DUPLICATE KEY UPDATE name = 'Təbiətşünaslıq və Kənd təsərrüfatı';
INSERT INTO `webinar_faculties` (id, name) VALUES (2, 'İncəsənət') ON DUPLICATE KEY UPDATE name = 'İncəsənət';
INSERT INTO `webinar_faculties` (id, name) VALUES (3, 'Tibb') ON DUPLICATE KEY UPDATE name = 'Tibb';
INSERT INTO `webinar_faculties` (id, name) VALUES (4, 'Beynəlxalq Münasibətlər və Hüquq') ON DUPLICATE KEY UPDATE name = 'Beynəlxalq Münasibətlər və Hüquq';
INSERT INTO `webinar_faculties` (id, name) VALUES (5, 'Pedaqoji') ON DUPLICATE KEY UPDATE name = 'Pedaqoji';
INSERT INTO `webinar_faculties` (id, name) VALUES (6, 'Fizika-Riyaziyyat') ON DUPLICATE KEY UPDATE name = 'Fizika-Riyaziyyat';
INSERT INTO `webinar_faculties` (id, name) VALUES (7, 'İqtisadiyyat və İdarəetmə') ON DUPLICATE KEY UPDATE name = 'İqtisadiyyat və İdarəetmə';
INSERT INTO `webinar_faculties` (id, name) VALUES (8, 'Memarlıq və Mühəndislik') ON DUPLICATE KEY UPDATE name = 'Memarlıq və Mühəndislik';
INSERT INTO `webinar_faculties` (id, name) VALUES (9, 'Tarix-Filologiya') ON DUPLICATE KEY UPDATE name = 'Tarix-Filologiya';
INSERT INTO `webinar_faculties` (id, name) VALUES (10, 'Xarici dillər') ON DUPLICATE KEY UPDATE name = 'Xarici dillər';
INSERT INTO `webinar_faculties` (id, name) VALUES (11, 'Naxçıvan Tibb Kolleci') ON DUPLICATE KEY UPDATE name = 'Naxçıvan Tibb Kolleci';
INSERT INTO `webinar_faculties` (id, name) VALUES (12, 'Naxçıvan Texniki Kolleci') ON DUPLICATE KEY UPDATE name = 'Naxçıvan Texniki Kolleci';
INSERT INTO `webinar_faculties` (id, name) VALUES (13, 'NDU Beynəlxalq Kembric məktəbi') ON DUPLICATE KEY UPDATE name = 'NDU Beynəlxalq Kembric məktəbi';
INSERT INTO `webinar_faculties` (id, name) VALUES (14, 'Naxçıvan Musiqi Kolleci') ON DUPLICATE KEY UPDATE name = 'Naxçıvan Musiqi Kolleci';
INSERT INTO `webinar_faculties` (id, name) VALUES (15, 'Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi') ON DUPLICATE KEY UPDATE name = 'Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi';
INSERT INTO `webinar_faculties` (id, name) VALUES (16, 'NDU nəznində İngilis dili təmayüllü Gimnaziya') ON DUPLICATE KEY UPDATE name = 'NDU nəznində İngilis dili təmayüllü Gimnaziya';
INSERT INTO `webinar_faculties` (id, name) VALUES (17, 'Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi') ON DUPLICATE KEY UPDATE name = 'Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi';

-- 3. Kafedra Məlumatları
TRUNCATE TABLE `webinar_departments`;
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (1, 1, 'Baytarlıq təbabəti');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (2, 1, 'Biologiya');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (3, 1, 'Coğrafiya');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (4, 1, 'Kimya');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (5, 2, 'Teatr və mədəniyyətşünaslıq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (6, 2, 'Təsviri incəsənət');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (7, 2, 'Musiqinin tarixi və nəzəriyyəsi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (8, 2, 'Fortepiano');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (9, 2, 'Musiqi təlimi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (10, 2, 'Xalq çalğı alətləri');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (11, 2, 'Orkestr alətləri və dirijorluq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (12, 3, 'Klinik fənlər və hərbi tibb');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (13, 3, 'Stomatologiya');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (14, 3, 'Təməl tibb fənləri');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (15, 3, 'Əczaçılıq və biokimya');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (16, 4, 'Beynəlxalq münasibətlər');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (17, 4, 'Sosial və ictimai münasibətlər');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (18, 4, 'Ümumi hüquq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (19, 4, 'Xüsusi hüquq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (20, 5, 'Pedaqogika və psixologiya');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (21, 5, 'Texnologiya kafedrası');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (22, 5, 'Çağırışaqədərki hazırlıq və mülki müdafiə');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (23, 5, 'Məşqçilik');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (24, 6, 'İnformatika');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (25, 6, 'Ümumi və nəzəri fizika');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (26, 6, 'Ümumi Riyaziyyat');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (27, 7, 'İqtisadiyyat və marketinq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (28, 7, 'Mühasibat və maliyyə');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (29, 7, 'Beynəlxalq ticarət və menecment');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (30, 7, 'Bələdiyyə və turizm');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (31, 8, 'Elektronika və informasiya texnologiyaları');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (32, 8, 'Meliorasiya və ekologiya mühəndisliyi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (33, 8, 'Elektroenergetika mühəndisliyi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (34, 8, 'Memarlıq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (35, 8, 'Nəqliyyat mühəndisliyi və texniki fənlər');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (36, 9, 'Azərbaycan ədəbiyyatı');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (37, 9, 'Azərbaycan tarixi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (38, 9, 'Ümumi Tarix');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (39, 9, 'Jurnalistika və xarici ölkələr ədəbiyyatı');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (40, 9, 'Muzeyşünaslıq, arxiv işi və kitabxanaçılıq');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (41, 9, 'Azərbaycan dilçiliyi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (42, 10, 'Avropa dilləri');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (43, 10, 'İngilis dili və tərcümə');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (44, 10, 'Şərq dilləri');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (45, 10, 'İngilis dili və metodika');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (46, 11, '1 ci sobe');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (47, 11, '2 ci sobe');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (48, 12, 'Aqrar və iqtisadiyyat şöbəsi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (49, 12, 'Texniki ixtisaslar şöbəsi');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (50, 13, 'International School');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (51, 14, 'NMK Xalq çalğı alətləri');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (52, 14, 'NMK Fortepiano');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (53, 14, 'NMK Aktyor sənəti və dekorativ tətbiqi sənət');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (54, 14, 'NMK Orkestr alətləri və vokal sənəti');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (55, 14, 'NMK Ümumi təhsil və humanitar fənlər');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (56, 14, 'NMK Ümumi fortepiano və konsertmeysterlik');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (57, 14, 'NMK Ümumi nəzəri fənlər');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (58, 15, 'Əcnəbi təhsil alanların hazırlıq kursu');
INSERT INTO `webinar_departments` (id, faculty_id, name) VALUES (59, 16, 'NDU nəznində Gimnaziya');

-- 4. İstifadəçi Məlumatları (Şifrə: Ndu2026!)
DELETE FROM `webinar_users` WHERE role != 'admin';
-- Super User hesablarını təmin edirik (Foreign Key xətalarının qarşısını almaq üçün)
INSERT INTO `webinar_users` (id, username, password_hash, full_name, role, faculty_id, is_active) VALUES (1, 'admin', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Super User', 'admin', NULL, 1) ON DUPLICATE KEY UPDATE role = 'admin';
INSERT INTO `webinar_users` (id, username, password_hash, full_name, role, faculty_id, is_active) VALUES (5, 'admin_5', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Super User (5)', 'admin', NULL, 1) ON DUPLICATE KEY UPDATE role = 'admin';
INSERT INTO `webinar_users` (id, username, password_hash, full_name, role, faculty_id, is_active) VALUES (7, 'admin_7', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Super User (7)', 'admin', NULL, 1) ON DUPLICATE KEY UPDATE role = 'admin';

INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('baytarliq-tebabeti_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Baytarlıq təbabəti - Mühazirəçi', 'teacher', 1, 1);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('baytarliq-tebabeti_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Baytarlıq təbabəti - İştirakçı', 'student', 1, 1);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('biologiya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Biologiya - Mühazirəçi', 'teacher', 1, 2);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('biologiya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Biologiya - İştirakçı', 'student', 1, 2);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('cografiya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Coğrafiya - Mühazirəçi', 'teacher', 1, 3);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('cografiya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Coğrafiya - İştirakçı', 'student', 1, 3);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('kimya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Kimya - Mühazirəçi', 'teacher', 1, 4);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('kimya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Kimya - İştirakçı', 'student', 1, 4);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('teatr-ve-medeniyyetsunasliq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Teatr və mədəniyyətşünaslıq - Mühazirəçi', 'teacher', 2, 5);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('teatr-ve-medeniyyetsunasliq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Teatr və mədəniyyətşünaslıq - İştirakçı', 'student', 2, 5);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('tesviri-incesenet_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Təsviri incəsənət - Mühazirəçi', 'teacher', 2, 6);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('tesviri-incesenet_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Təsviri incəsənət - İştirakçı', 'student', 2, 6);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('musiqinin-tarixi-ve-nezeriyyesi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Musiqinin tarixi və nəzəriyyəsi - Mühazirəçi', 'teacher', 2, 7);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('musiqinin-tarixi-ve-nezeriyyesi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Musiqinin tarixi və nəzəriyyəsi - İştirakçı', 'student', 2, 7);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('fortepiano_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Fortepiano - Mühazirəçi', 'teacher', 2, 8);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('fortepiano_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Fortepiano - İştirakçı', 'student', 2, 8);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('musiqi-telimi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Musiqi təlimi - Mühazirəçi', 'teacher', 2, 9);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('musiqi-telimi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Musiqi təlimi - İştirakçı', 'student', 2, 9);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('xalq-calgi-aletleri_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Xalq çalğı alətləri - Mühazirəçi', 'teacher', 2, 10);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('xalq-calgi-aletleri_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Xalq çalğı alətləri - İştirakçı', 'student', 2, 10);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('orkestr-aletleri-ve-dirijorluq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Orkestr alətləri və dirijorluq - Mühazirəçi', 'teacher', 2, 11);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('orkestr-aletleri-ve-dirijorluq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Orkestr alətləri və dirijorluq - İştirakçı', 'student', 2, 11);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('klinik-fenler-ve-herbi-tibb_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Klinik fənlər və hərbi tibb - Mühazirəçi', 'teacher', 3, 12);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('klinik-fenler-ve-herbi-tibb_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Klinik fənlər və hərbi tibb - İştirakçı', 'student', 3, 12);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('stomatologiya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Stomatologiya - Mühazirəçi', 'teacher', 3, 13);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('stomatologiya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Stomatologiya - İştirakçı', 'student', 3, 13);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('temel-tibb-fenleri_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Təməl tibb fənləri - Mühazirəçi', 'teacher', 3, 14);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('temel-tibb-fenleri_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Təməl tibb fənləri - İştirakçı', 'student', 3, 14);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('eczaciliq-ve-biokimya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Əczaçılıq və biokimya - Mühazirəçi', 'teacher', 3, 15);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('eczaciliq-ve-biokimya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Əczaçılıq və biokimya - İştirakçı', 'student', 3, 15);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('beynelxalq-munasibetler_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Beynəlxalq münasibətlər - Mühazirəçi', 'teacher', 4, 16);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('beynelxalq-munasibetler_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Beynəlxalq münasibətlər - İştirakçı', 'student', 4, 16);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('sosial-ve-ictimai-munasibetler_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Sosial və ictimai münasibətlər - Mühazirəçi', 'teacher', 4, 17);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('sosial-ve-ictimai-munasibetler_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Sosial və ictimai münasibətlər - İştirakçı', 'student', 4, 17);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-huquq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi hüquq - Mühazirəçi', 'teacher', 4, 18);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-huquq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi hüquq - İştirakçı', 'student', 4, 18);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('xususi-huquq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Xüsusi hüquq - Mühazirəçi', 'teacher', 4, 19);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('xususi-huquq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Xüsusi hüquq - İştirakçı', 'student', 4, 19);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('pedaqogika-ve-psixologiya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Pedaqogika və psixologiya - Mühazirəçi', 'teacher', 5, 20);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('pedaqogika-ve-psixologiya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Pedaqogika və psixologiya - İştirakçı', 'student', 5, 20);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('texnologiya-kafedrasi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Texnologiya kafedrası - Mühazirəçi', 'teacher', 5, 21);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('texnologiya-kafedrasi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Texnologiya kafedrası - İştirakçı', 'student', 5, 21);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('cagirisaqederki-hazirliq-ve-mulki-mudafie_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Çağırışaqədərki hazırlıq və mülki müdafiə - Mühazirəçi', 'teacher', 5, 22);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('cagirisaqederki-hazirliq-ve-mulki-mudafie_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Çağırışaqədərki hazırlıq və mülki müdafiə - İştirakçı', 'student', 5, 22);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('mesqcilik_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Məşqçilik - Mühazirəçi', 'teacher', 5, 23);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('mesqcilik_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Məşqçilik - İştirakçı', 'student', 5, 23);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('informatika_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İnformatika - Mühazirəçi', 'teacher', 6, 24);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('informatika_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İnformatika - İştirakçı', 'student', 6, 24);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-ve-nezeri-fizika_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi və nəzəri fizika - Mühazirəçi', 'teacher', 6, 25);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-ve-nezeri-fizika_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi və nəzəri fizika - İştirakçı', 'student', 6, 25);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-riyaziyyat_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi Riyaziyyat - Mühazirəçi', 'teacher', 6, 26);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-riyaziyyat_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi Riyaziyyat - İştirakçı', 'student', 6, 26);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('iqtisadiyyat-ve-marketinq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İqtisadiyyat və marketinq - Mühazirəçi', 'teacher', 7, 27);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('iqtisadiyyat-ve-marketinq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İqtisadiyyat və marketinq - İştirakçı', 'student', 7, 27);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('muhasibat-ve-maliyye_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Mühasibat və maliyyə - Mühazirəçi', 'teacher', 7, 28);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('muhasibat-ve-maliyye_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Mühasibat və maliyyə - İştirakçı', 'student', 7, 28);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('beynelxalq-ticaret-ve-menecment_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Beynəlxalq ticarət və menecment - Mühazirəçi', 'teacher', 7, 29);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('beynelxalq-ticaret-ve-menecment_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Beynəlxalq ticarət və menecment - İştirakçı', 'student', 7, 29);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('belediyye-ve-turizm_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Bələdiyyə və turizm - Mühazirəçi', 'teacher', 7, 30);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('belediyye-ve-turizm_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Bələdiyyə və turizm - İştirakçı', 'student', 7, 30);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('elektronika-ve-informasiya-texnologiyalari_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Elektronika və informasiya texnologiyaları - Mühazirəçi', 'teacher', 8, 31);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('elektronika-ve-informasiya-texnologiyalari_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Elektronika və informasiya texnologiyaları - İştirakçı', 'student', 8, 31);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('meliorasiya-ve-ekologiya-muhendisliyi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Meliorasiya və ekologiya mühəndisliyi - Mühazirəçi', 'teacher', 8, 32);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('meliorasiya-ve-ekologiya-muhendisliyi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Meliorasiya və ekologiya mühəndisliyi - İştirakçı', 'student', 8, 32);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('elektroenergetika-muhendisliyi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Elektroenergetika mühəndisliyi - Mühazirəçi', 'teacher', 8, 33);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('elektroenergetika-muhendisliyi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Elektroenergetika mühəndisliyi - İştirakçı', 'student', 8, 33);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('memarliq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Memarlıq - Mühazirəçi', 'teacher', 8, 34);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('memarliq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Memarlıq - İştirakçı', 'student', 8, 34);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('neqliyyat-muhendisliyi-ve-texniki-fenler_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Nəqliyyat mühəndisliyi və texniki fənlər - Mühazirəçi', 'teacher', 8, 35);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('neqliyyat-muhendisliyi-ve-texniki-fenler_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Nəqliyyat mühəndisliyi və texniki fənlər - İştirakçı', 'student', 8, 35);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('azerbaycan-edebiyyati_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Azərbaycan ədəbiyyatı - Mühazirəçi', 'teacher', 9, 36);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('azerbaycan-edebiyyati_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Azərbaycan ədəbiyyatı - İştirakçı', 'student', 9, 36);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('azerbaycan-tarixi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Azərbaycan tarixi - Mühazirəçi', 'teacher', 9, 37);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('azerbaycan-tarixi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Azərbaycan tarixi - İştirakçı', 'student', 9, 37);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-tarix_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi Tarix - Mühazirəçi', 'teacher', 9, 38);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('umumi-tarix_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Ümumi Tarix - İştirakçı', 'student', 9, 38);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('jurnalistika-ve-xarici-olkeler-edebiyyati_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Jurnalistika və xarici ölkələr ədəbiyyatı - Mühazirəçi', 'teacher', 9, 39);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('jurnalistika-ve-xarici-olkeler-edebiyyati_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Jurnalistika və xarici ölkələr ədəbiyyatı - İştirakçı', 'student', 9, 39);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('muzeysunasliq-arxiv-isi-ve-kitabxanaciliq_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Muzeyşünaslıq, arxiv işi və kitabxanaçılıq - Mühazirəçi', 'teacher', 9, 40);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('muzeysunasliq-arxiv-isi-ve-kitabxanaciliq_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Muzeyşünaslıq, arxiv işi və kitabxanaçılıq - İştirakçı', 'student', 9, 40);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('azerbaycan-dilciliyi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Azərbaycan dilçiliyi - Mühazirəçi', 'teacher', 9, 41);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('azerbaycan-dilciliyi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Azərbaycan dilçiliyi - İştirakçı', 'student', 9, 41);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('avropa-dilleri_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Avropa dilləri - Mühazirəçi', 'teacher', 10, 42);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('avropa-dilleri_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Avropa dilləri - İştirakçı', 'student', 10, 42);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ingilis-dili-ve-tercume_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İngilis dili və tərcümə - Mühazirəçi', 'teacher', 10, 43);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ingilis-dili-ve-tercume_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İngilis dili və tərcümə - İştirakçı', 'student', 10, 43);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('serq-dilleri_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Şərq dilləri - Mühazirəçi', 'teacher', 10, 44);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('serq-dilleri_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Şərq dilləri - İştirakçı', 'student', 10, 44);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ingilis-dili-ve-metodika_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İngilis dili və metodika - Mühazirəçi', 'teacher', 10, 45);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ingilis-dili-ve-metodika_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'İngilis dili və metodika - İştirakçı', 'student', 10, 45);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('1-ci-sobe_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', '1 ci sobe - Mühazirəçi', 'teacher', 11, 46);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('1-ci-sobe_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', '1 ci sobe - İştirakçı', 'student', 11, 46);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('2-ci-sobe_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', '2 ci sobe - Mühazirəçi', 'teacher', 11, 47);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('2-ci-sobe_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', '2 ci sobe - İştirakçı', 'student', 11, 47);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('aqrar-ve-iqtisadiyyat-sobesi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Aqrar və iqtisadiyyat şöbəsi - Mühazirəçi', 'teacher', 12, 48);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('aqrar-ve-iqtisadiyyat-sobesi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Aqrar və iqtisadiyyat şöbəsi - İştirakçı', 'student', 12, 48);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('texniki-ixtisaslar-sobesi_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Texniki ixtisaslar şöbəsi - Mühazirəçi', 'teacher', 12, 49);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('texniki-ixtisaslar-sobesi_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Texniki ixtisaslar şöbəsi - İştirakçı', 'student', 12, 49);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('international-school_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'International School - Mühazirəçi', 'teacher', 13, 50);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('international-school_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'International School - İştirakçı', 'student', 13, 50);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-xalq-calgi-aletleri_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Xalq çalğı alətləri - Mühazirəçi', 'teacher', 14, 51);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-xalq-calgi-aletleri_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Xalq çalğı alətləri - İştirakçı', 'student', 14, 51);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-fortepiano_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Fortepiano - Mühazirəçi', 'teacher', 14, 52);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-fortepiano_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Fortepiano - İştirakçı', 'student', 14, 52);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-aktyor-seneti-ve-dekorativ-tetbiqi-senet_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Aktyor sənəti və dekorativ tətbiqi sənət - Mühazirəçi', 'teacher', 14, 53);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-aktyor-seneti-ve-dekorativ-tetbiqi-senet_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Aktyor sənəti və dekorativ tətbiqi sənət - İştirakçı', 'student', 14, 53);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-orkestr-aletleri-ve-vokal-seneti_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Orkestr alətləri və vokal sənəti - Mühazirəçi', 'teacher', 14, 54);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-orkestr-aletleri-ve-vokal-seneti_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Orkestr alətləri və vokal sənəti - İştirakçı', 'student', 14, 54);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-umumi-tehsil-ve-humanitar-fenler_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Ümumi təhsil və humanitar fənlər - Mühazirəçi', 'teacher', 14, 55);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-umumi-tehsil-ve-humanitar-fenler_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Ümumi təhsil və humanitar fənlər - İştirakçı', 'student', 14, 55);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-umumi-fortepiano-ve-konsertmeysterlik_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Ümumi fortepiano və konsertmeysterlik - Mühazirəçi', 'teacher', 14, 56);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-umumi-fortepiano-ve-konsertmeysterlik_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Ümumi fortepiano və konsertmeysterlik - İştirakçı', 'student', 14, 56);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-umumi-nezeri-fenler_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Ümumi nəzəri fənlər - Mühazirəçi', 'teacher', 14, 57);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('nmk-umumi-nezeri-fenler_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NMK Ümumi nəzəri fənlər - İştirakçı', 'student', 14, 57);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ecnebi-tehsil-alanlarin-hazirliq-kursu_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Əcnəbi təhsil alanların hazırlıq kursu - Mühazirəçi', 'teacher', 15, 58);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ecnebi-tehsil-alanlarin-hazirliq-kursu_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'Əcnəbi təhsil alanların hazırlıq kursu - İştirakçı', 'student', 15, 58);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ndu-nezninde-gimnaziya_muhazireci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NDU nəznində Gimnaziya - Mühazirəçi', 'teacher', 16, 59);
INSERT INTO `webinar_users` (username, password_hash, full_name, role, faculty_id, department_id) VALUES ('ndu-nezninde-gimnaziya_istirakci', '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK', 'NDU nəznində Gimnaziya - İştirakçı', 'student', 16, 59);
