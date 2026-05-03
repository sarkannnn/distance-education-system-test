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
    $cols = array_map(function ($r) {
        return $r['Field'];
    }, $res);
    file_put_contents(__DIR__ . '/lc_cols.json', json_encode($cols));
    echo "Done";
} catch (Exception $e) {
    echo $e->getMessage();
}
