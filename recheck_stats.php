<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$stats = [];
$stats['journal_topics'] = $db->fetch("SELECT COUNT(*) as c FROM journal_topics")['c'] ?? 0;
$stats['subjects'] = $db->fetch("SELECT COUNT(DISTINCT subject_name) as c FROM live_classes WHERE subject_name IS NOT NULL AND subject_name != ''")['c'] ?? 0;
$stats['total_views'] = $db->fetch("SELECT SUM(views) as s FROM live_classes")['s'] ?? 0;
$stats['total_minutes'] = $db->fetch("SELECT SUM(duration_minutes) as s FROM live_classes")['s'] ?? 0;
$stats['total_assignments'] = $db->fetch("SELECT COUNT(*) as c FROM assignments")['c'] ?? 0;

print_r($stats);
