<?php

/**
 * Check LiveKit Egress recording status for a given lesson.
 * Called periodically by the teacher studio to detect if recording has stopped.
 */
require_once __DIR__ . '/../teacher/config/database.php';
require_once __DIR__ . '/livekit_egress_service.php';

header('Content-Type: application/json');

$lessonId = $_POST['lesson_id'] ?? null;

if (!$lessonId || !ctype_digit((string)$lessonId)) {
    http_response_code(400);
    echo json_encode(['active' => false, 'error' => 'Invalid lesson_id']);
    exit;
}

$db = Database::getInstance();
$lesson = $db->fetch("SELECT egress_id FROM live_classes WHERE id = ?", [$lessonId]);

if (!$lesson || empty($lesson['egress_id'])) {
    echo json_encode(['active' => false, 'error' => 'No active egress for this lesson']);
    exit;
}

$service = new LiveKitEgressService();
$status = $service->checkStatus($lesson['egress_id']);

echo json_encode($status);
