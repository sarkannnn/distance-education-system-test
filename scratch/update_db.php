<?php
require_once 'teacher/config/database.php';
$db = Database::getInstance();

try {
    echo "Adding guest_token to webinars table...\n";
    $db->query("ALTER TABLE webinars ADD COLUMN guest_token VARCHAR(30) DEFAULT NULL AFTER peer_id");
    echo "Success!\n";
} catch (Exception $e) {
    echo "Webinars update failed (maybe column already exists): " . $e->getMessage() . "\n";
}

try {
    echo "Adding guest_token to live_classes table...\n";
    $db->query("ALTER TABLE live_classes ADD COLUMN guest_token VARCHAR(30) DEFAULT NULL AFTER webrtc_link");
    echo "Success!\n";
} catch (Exception $e) {
    echo "Live_classes update failed (maybe column already exists): " . $e->getMessage() . "\n";
}
