<?php
require_once 'student/config/database.php';
$db = Database::getInstance();

echo "CHATBOT LOGS (First 5 entries):\n";
$logs = $db->fetchAll("SELECT id, user_id, user_role, query FROM chatbot_logs ORDER BY id DESC LIMIT 5");
foreach ($logs as $l) {
    echo "Log ID: {$l['id']} | Log UserID: {$l['user_id']} | Role: {$l['user_role']} | Query: {$l['query']}\n";
}

echo "\nUSERS TABLE SEARCH:\n";
foreach ($logs as $l) {
    if (!$l['user_id']) continue;
    $uid = $l['user_id'];
    
    // Using positional parameters to be safe with the Database class implementation seen earlier
    $u = $db->fetch("SELECT id, student_id, first_name, last_name, role FROM users WHERE id = ? OR student_id = ?", [$uid, $uid]);
    
    if ($u) {
        echo "Match for $uid: Local ID: {$u['id']} | StudentID: {$u['student_id']} | DB Role: {$u['role']} | Name: {$u['first_name']} {$u['last_name']}\n";
    } else {
        echo "NO MATCH for $uid in users table.\n";
    }
}
?>
