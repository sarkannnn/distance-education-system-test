<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!WebinarAuth::isLoggedIn() || $_SESSION['webinar_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = WebinarDatabase::getInstance();
$id = $_GET['id'] ?? null;
$user = WebinarAuth::getCurrentUser();

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID missing']);
    exit;
}

try {
    // Only the teacher who created it (or any teacher in same faculty depending on business logic, but let's stick to isolation)
    $webinar = $db->fetch("SELECT * FROM webinars WHERE id = ? AND faculty_id = ?", [$id, $user['faculty_id']]);

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Webinar not found or access denied']);
        exit;
    }

    $db->update('webinars', 
        ['status' => 'live', 'started_at' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$id]
    );

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
