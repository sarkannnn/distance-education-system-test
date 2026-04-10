<?php
require_once 'student/config/database.php';
$db = Database::getInstance();

echo "LOGS WITH SUCCESSFUL JOIN:\n";
$logs = $db->fetchAll("
    SELECT l.id, l.user_id, l.user_role, u.first_name, u.last_name 
    FROM chatbot_logs l 
    JOIN users u ON (
        (l.user_role = 'student' AND l.user_id = u.student_id) OR
        (l.user_role = 'instructor' AND l.user_id = u.id)
    )
    LIMIT 10
");

if (empty($logs)) {
    echo "NO LOGS FOUND WITH SUCCESSFUL JOIN. This means the JOIN logic is flawed or the data is missing.\n";
} else {
    foreach ($logs as $l) {
        echo "Log ID: {$l['id']} | Role: {$l['user_role']} | UserID: {$l['user_id']} | Name: {$l['first_name']} {$l['last_name']}\n";
    }
}
?>
