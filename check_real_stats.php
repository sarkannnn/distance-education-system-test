<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$stats = [];
$stats['subjects'] = $db->fetch("SELECT COUNT(*) as c FROM subjects")['c'] ?? 0;
$stats['courses'] = $db->fetch("SELECT COUNT(*) as c FROM courses")['c'] ?? 0;
$stats['specializations'] = $db->fetch("SELECT COUNT(*) as c FROM specializations")['c'] ?? 0;
$stats['assignments'] = $db->fetch("SELECT COUNT(*) as c FROM assignments")['c'] ?? 0;
$stats['total_duration'] = $db->fetch("SELECT SUM(duration_minutes) as s FROM live_classes WHERE status='ended'")['s'] ?? 0;

print_r($stats);
