<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$logs = $db->fetchAll("SELECT * FROM chatbot_logs ORDER BY created_at DESC LIMIT 5");
echo "LAST 5 LOGS:\n";
foreach ($logs as $log) {
    echo "ID: {$log['id']} | Role: {$log['user_role']} | UserID: {$log['user_id']} | Query: {$log['query']}\n";
}
?>
