<?php

/**
 * API to get teacher's current dynamic Peer ID - Robust Version
 */
require_once '../includes/auth.php';
require_once '../config/database.php';
header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş tələb olunur']);
    exit;
}

$lessonId = (int) ($_GET['id'] ?? 0);

if ($lessonId > 0) {
    $db = Database::getInstance();
    $lesson = $db->fetch("SELECT zoom_link, peer_server FROM live_classes WHERE id = ?", [$lessonId]);

    if ($lesson && !empty($lesson['zoom_link'])) {
        echo json_encode(['success' => true, 'peer_id' => $lesson['zoom_link'], 'server' => $lesson['peer_server'] ?? 'local']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not started yet']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID missing']);
}
