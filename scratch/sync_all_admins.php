<?php
require_once 'webinar/config/database.php';
$db = WebinarDatabase::getInstance();
$adminsInMain = $db->fetchAll("SELECT id, student_id, first_name, last_name FROM users WHERE role = 'admin'");

echo "Found " . count($adminsInMain) . " admins in main system.\n";

foreach ($adminsInMain as $a) {
    try {
        $fullName = ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '');
        if (trim($fullName) == '') $fullName = 'Admin (' . ($a['student_id'] ?? 'ID_'.$a['id']) . ')';
        
        $db->insert('webinar_users', [
            'id' => $a['id'],
            'username' => 'admin_' . $a['id'],
            'password_hash' => '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK',
            'full_name' => $fullName,
            'role' => 'admin',
            'faculty_id' => null,
            'is_active' => 1
        ]);
        echo "Synced Admin ID {$a['id']} ({$a['student_id']})\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
             $db->update('webinar_users', ['role' => 'admin', 'faculty_id' => null], 'id = ?', [$a['id']]);
             echo "Updated Admin ID {$a['id']}\n";
        } else {
            echo "Error syncing ID {$a['id']}: " . $e->getMessage() . "\n";
        }
    }
}
