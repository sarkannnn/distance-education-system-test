<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $pdo = $db->getConnection();
    $res = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
