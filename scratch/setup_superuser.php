<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $pdo = $db->getConnection();
    
    $password = 'Ndu2026!';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if super user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@ndu.edu.az' OR student_id = 'SUPERADMIN'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE id = ?")->execute([$hash, $admin['id']]);
        echo "Super User updated successfully in Real DB.\n";
    } else {
        $pdo->prepare("INSERT INTO users (student_id, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute(['SUPERADMIN', 'Super', 'Admin', 'admin@ndu.edu.az', $hash, 'admin']);
        echo "Super User created successfully in Real DB.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
