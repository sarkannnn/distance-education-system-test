<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $pdo = $db->getConnection();
    
    // Check if column exists
    $columns = $pdo->query("DESCRIBE webinars")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('recording_path', $columns)) {
        $pdo->exec("ALTER TABLE webinars ADD COLUMN recording_path VARCHAR(255) NULL AFTER ended_at");
        echo "Column 'recording_path' added successfully.";
    } else {
        echo "Column 'recording_path' already exists.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
