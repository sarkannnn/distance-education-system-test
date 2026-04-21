<?php
require_once 'webinar/config/database.php';
$db = WebinarDatabase::getInstance();
$teachersInMain = $db->fetchAll("SELECT id, student_id, first_name, last_name FROM users WHERE role = 'instructor'");

echo "Found " . count($teachersInMain) . " instructors in main system.\n";

foreach ($teachersInMain as $t) {
    try {
        $fullName = ($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '');
        if (trim($fullName) == '') $fullName = 'Müəllim (' . ($t['student_id'] ?? 'ID_'.$t['id']) . ')';
        
        $db->insert('webinar_users', [
            'id' => $t['id'],
            'username' => 'teacher_' . $t['id'],
            'password_hash' => '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK',
            'full_name' => $fullName,
            'role' => 'teacher',
            'faculty_id' => null,
            'is_active' => 1
        ]);
        echo "Synced Teacher ID {$t['id']} ({$t['student_id']})\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
             echo "Already exists Teacher ID {$t['id']}\n";
        } else {
            echo "Error syncing ID {$t['id']}: " . $e->getMessage() . "\n";
        }
    }
}
