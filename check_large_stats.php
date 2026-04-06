<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$stats = [];
$stats['attendance'] = $db->fetch("SELECT COUNT(*) as c FROM live_attendance")['c'] ?? 0;
$stats['participants'] = $db->fetch("SELECT COUNT(*) as c FROM live_class_participants")['c'] ?? 0;
$stats['enrollments'] = $db->fetch("SELECT COUNT(*) as c FROM enrollments")['c'] ?? 0;

print_r($stats);
