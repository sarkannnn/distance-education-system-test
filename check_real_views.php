<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$views = $db->fetch("SELECT SUM(views) as s FROM live_classes")['s'] ?? 0;
echo "REAL_VIEWS: $views\n";
