<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $pdo = $db->getConnection();
    
    $newHash = password_hash('Ndu2026!', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE webinar_users SET password_hash = ?")->execute([$newHash]);
    
    echo "Webinar users passwords updated successfully in Real DB.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
