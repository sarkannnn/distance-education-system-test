<?php
require_once 'student/config/database.php';
$db = Database::getInstance();

$email = 'superadmin@ndu.edu.az';
$firstName = 'Super';
$lastName = 'User';
$password = 'NDU_Admin_2024!'; // Temporary secure password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';
$studentId = 'ADMIN-001';

try {
    // Check if exists
    $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        $db->query("UPDATE users SET password = ?, role = ? WHERE email = ?", [$hashedPassword, $role, $email]);
        echo "Admin hesabı mövcud idi, şifrə və rol yeniləndi.\n";
    } else {
        $db->query(
            "INSERT INTO users (student_id, first_name, last_name, email, password, role, is_active, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())",
            [$studentId, $firstName, $lastName, $email, $hashedPassword, $role]
        );
        echo "Super User hesabı uğurla yaradıldı.\n";
    }
    
    echo "----------------------------------\n";
    echo "GİRİŞ MƏLUMATLARI:\n";
    echo "E-poçt: $email\n";
    echo "Şifrə: $password\n";
    echo "Rol: $role\n";
    echo "----------------------------------\n";
    echo "Xahiş edirik daxil olduqdan sonra şifrəni dəyişəsiniz.\n";

} catch (Exception $e) {
    echo "XƏTA: " . $e->getMessage() . "\n";
}
?>
