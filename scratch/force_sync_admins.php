<?php
require_once 'webinar/config/database.php';
$db = WebinarDatabase::getInstance();
$admins = [
    ['id' => 5, 'username' => 'admin_5', 'full_name' => 'Super User (5)'],
    ['id' => 7, 'username' => 'admin_7', 'full_name' => 'Super User (7)'],
    ['id' => 1, 'username' => 'admin_default', 'full_name' => 'Super User (Default)']
];

foreach ($admins as $a) {
    try {
        $db->insert('webinar_users', [
            'id' => $a['id'],
            'username' => $a['username'],
            'password_hash' => '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK',
            'full_name' => $a['full_name'],
            'role' => 'admin',
            'faculty_id' => null,
            'is_active' => 1
        ]);
        echo "Inserted {$a['username']}\n";
    } catch (Exception $e) {
        echo "Skip {$a['username']}: " . $e->getMessage() . "\n";
    }
}
