<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$students = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'] ?? 0;
$instructors = $db->fetch("SELECT COUNT(*) as count FROM instructors")['count'] ?? 0;
$live = $db->fetch("SELECT COUNT(*) as count FROM live_classes WHERE status = 'live'")['count'] ?? 0;
$archives = $db->fetch("SELECT COUNT(*) as count FROM live_classes WHERE recording_path IS NOT NULL AND recording_path != ''")['count'] ?? 0;

echo "STUDENTS: $students\n";
echo "INSTRUCTORS: $instructors\n";
echo "LIVE: $live\n";
echo "ARCHIVES: $archives\n";
