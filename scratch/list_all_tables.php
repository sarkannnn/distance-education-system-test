<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $pdo = $db->getConnection();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in " . getenv('DB_NAME') . ":\n";
    foreach($tables as $t) {
        echo "- $t\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
