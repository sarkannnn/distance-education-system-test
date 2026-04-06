<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$count = $db->fetch("SELECT COUNT(*) as c FROM system_logs")['c'];
echo "LOGS: $count\n";
