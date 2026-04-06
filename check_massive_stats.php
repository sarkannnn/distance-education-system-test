<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$notifs = $db->fetch("SELECT COUNT(*) as c FROM notifications")['c'] ?? 0;
$sessions = $db->fetch("SELECT COUNT(*) as c FROM user_sessions")['c'] ?? 0;
$total_classes = $db->fetch("SELECT COUNT(*) as c FROM live_classes")['c'] ?? 0;

echo "NOTIFICATIONS: $notifs\n";
echo "SESSIONS: $sessions\n";
echo "TOTAL_CLASSES: $total_classes\n";
