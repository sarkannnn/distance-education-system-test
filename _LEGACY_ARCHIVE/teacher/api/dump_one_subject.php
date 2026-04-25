<?php
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
if (!$auth->isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
try {
    if ($res) {
        echo implode(", ", array_keys($res));
        echo "\n---\n";
        print_r($res);
    } else {
        echo "No subjects found";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
