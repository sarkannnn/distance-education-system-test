<?php
header('Content-Type: application/json');
require_once 'livekit_egress_service.php';

// Simple auth check (instructor only)
session_name('DISTANT_T_SESSION_V4');
@session_start();
if (empty($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lessonId = $_POST['lesson_id'] ?? '';

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$egress = new LiveKitEgressService();
$result = $egress->stopRecording($lessonId);

echo json_encode($result);
