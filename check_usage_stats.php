<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$views = $db->fetch("SELECT SUM(views) as s FROM live_classes")['s'] ?? 0;
$distinct_subjects = $db->fetch("SELECT COUNT(DISTINCT subject_name) as c FROM live_classes WHERE subject_name IS NOT NULL AND subject_name != ''")['c'] ?? 0;
$distinct_specialties = $db->fetch("SELECT COUNT(DISTINCT specialty_name) as c FROM live_classes WHERE specialty_name IS NOT NULL AND specialty_name != ''")['c'] ?? 0;

echo "VIEWS: $views\n";
echo "SUBJECTS_IN_LIVE: $distinct_subjects\n";
echo "SPECIALTIES_IN_LIVE: $distinct_specialties\n";
