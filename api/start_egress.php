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
$roomName = $_POST['room_name'] ?? '';

if (!$lessonId || !$roomName) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$egress = new LiveKitEgressService();
$result = $egress->startRecording($lessonId, $roomName);

echo json_encode($result);
