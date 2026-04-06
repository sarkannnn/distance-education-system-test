<?php
// Replicating the logic in index.php precisely
require_once 'student/config/database.php';
$db = Database::getInstance();

try {
    $v1 = $db->fetch("SELECT SUM(views) as s FROM live_classes")['s'] ?? 0;
    $v2 = $db->fetch("SELECT SUM(views) as s FROM archived_lessons")['s'] ?? 0;
    
    echo "Live Classes Views: $v1\n";
    echo "Archived Lessons Views: $v2\n";
    echo "Combined Total: " . ($v1 + $v2) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
