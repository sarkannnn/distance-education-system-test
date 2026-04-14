<?php
require_once 'student/config/database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // 1. Webinar Faculties
    $pdo->exec("CREATE TABLE IF NOT EXISTS webinar_faculties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Webinar Users (Isolated accounts)
    $pdo->exec("CREATE TABLE IF NOT EXISTS webinar_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        role ENUM('teacher', 'student') NOT NULL,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (faculty_id) REFERENCES webinar_faculties(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Webinars (Live and Archived)
    $pdo->exec("CREATE TABLE IF NOT EXISTS webinars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        teacher_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
        scheduled_at DATETIME,
        started_at DATETIME NULL,
        ended_at DATETIME NULL,
        recording_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (faculty_id),
        INDEX (status),
        FOREIGN KEY (faculty_id) REFERENCES webinar_faculties(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES webinar_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed Faculties
    $faculties = [
        ['Fizika-Riyaziyyat', 'fizika-riyaziyyat'],
        ['Təbiətşünaslıq və Kənd təsərrüfatı', 'tebiet-kend-teserrufati'],
        ['Memarlıq və Mühəndislik', 'memarliq-muhendislik'],
        ['Xarici dillər', 'xarici-diller'],
        ['Pedaqoji', 'pedaqoji'],
        ['Tarix-Filologiya', 'tarix-filologiya'],
        ['Beynəlxalq Münasibətlər və Hüquq', 'beynelxalq-huquq'],
        ['İqtisadiyyat və İdarəetmə', 'iqtisadiyyat-idareetme'],
        ['Tibb', 'tibb'],
        ['İncəsənət', 'incesenet'],
        ['Magistratura', 'magistratura'],
        ['Naxçıvan Tibb Kolleci', 'tibb-kolleci'],
        ['Naxçıvan Texniki Kolleci', 'texniki-kolleci'],
        ['Naxçıvan Musiqi Kolleci', 'musiqi-kolleci'],
        ['NDU Beynəlxalq Kembric məktəbi', 'kembric-mektebi'],
        ['NDU nəznində İngilis dili təmayüllü Gimnaziya', 'gimnaziya'],
        ['Əcnəbi təhsil alanlarla iş və hazırlıq mərkəzi', 'ecnebi-merkez']
    ];

    $check = $pdo->query("SELECT COUNT(*) FROM webinar_faculties")->fetchColumn();
    if ($check == 0) {
        $stmt = $pdo->prepare("INSERT INTO webinar_faculties (name, slug) VALUES (?, ?)");
        foreach ($faculties as $f) {
            $stmt->execute($f);
        }
        echo "Faculties seeded successfully.\n";
    } else {
        echo "Faculties already exist.\n";
    }

    echo "Webinar tables created successfully.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
