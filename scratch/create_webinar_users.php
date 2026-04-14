<?php
require_once 'webinar/config/database.php';

try {
    $db = WebinarDatabase::getInstance();
    $pdo = $db->getConnection();

    $faculties = $db->fetchAll("SELECT * FROM webinar_faculties");
    
    $commonPassword = "NDU_Webinar_2024!";
    $passwordHash = password_hash($commonPassword, PASSWORD_DEFAULT);

    $createdCount = 0;
    foreach ($faculties as $f) {
        $slug = $f['slug'];
        
        // 1. Teacher account
        $teacherUsername = $slug . "_teacher";
        $teacherFullName = $f['name'] . " - Müəllim";
        
        $checkTeacher = $db->fetch("SELECT id FROM webinar_users WHERE username = ?", [$teacherUsername]);
        if (!$checkTeacher) {
            $db->insert('webinar_users', [
                'faculty_id' => $f['id'],
                'role' => 'teacher',
                'username' => $teacherUsername,
                'password_hash' => $passwordHash,
                'full_name' => $teacherFullName
            ]);
            $createdCount++;
        }

        // 2. Student account
        $studentUsername = $slug . "_student";
        $studentFullName = $f['name'] . " - Tələbə";
        
        $checkStudent = $db->fetch("SELECT id FROM webinar_users WHERE username = ?", [$studentUsername]);
        if (!$checkStudent) {
            $db->insert('webinar_users', [
                'faculty_id' => $f['id'],
                'role' => 'student',
                'username' => $studentUsername,
                'password_hash' => $passwordHash,
                'full_name' => $studentFullName
            ]);
            $createdCount++;
        }
    }

    echo "Successfully created/verified $createdCount accounts.\n";
    echo "Common password for all accounts: " . $commonPassword . "\n";
    echo "Example Teacher: " . $faculties[0]['slug'] . "_teacher\n";
    echo "Example Student: " . $faculties[0]['slug'] . "_student\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
