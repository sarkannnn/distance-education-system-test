<?php
require_once 'student/config/database.php';
$db = Database::getInstance();

echo "USER TABLE (First 3 students):\n";
$users = $db->fetchAll("SELECT id, student_id, first_name, last_name, role FROM users WHERE role = 'student' LIMIT 3");
foreach ($users as $u) {
    echo "Local ID: {$u['id']} | StudentID (TMIS): {$u['student_id']} | Name: {$u['first_name']}\n";
}

echo "\nCHATBOT LOGS (First 3 students):\n";
$logs = $db->fetchAll("SELECT id, user_id, user_role, query FROM chatbot_logs WHERE user_role = 'student' LIMIT 3");
foreach ($logs as $l) {
    echo "Log ID: {$l['id']} | Log UserID: {$l['user_id']} | Query: {$l['query']}\n";
}
?>
