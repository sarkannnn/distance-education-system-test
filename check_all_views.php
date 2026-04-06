<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$v1 = $db->fetch("SELECT SUM(views) as s FROM live_classes")['s'] ?? 0;
$v2 = $db->fetch("SELECT SUM(views) as s FROM archived_lessons")['s'] ?? 0;
echo "LiveClassesViews: $v1\n";
echo "ArchivedLessonsViews: $v2\n";
echo "Total: " . ($v1 + $v2) . "\n";
