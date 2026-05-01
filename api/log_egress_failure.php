<?php

/**
 * Log a client-side Egress failure for admin review.
 * Called by the teacher studio when all retry attempts for startEgressRecording() fail.
 */
require_once __DIR__ . '/../teacher/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['lesson_id']) || !ctype_digit((string)$data['lesson_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

$lessonId = (int)$data['lesson_id'];
$errorMsg = isset($data['error']) ? substr((string)$data['error'], 0, 1000) : 'unknown';

$db = Database::getInstance();

// Verify lesson exists before inserting
$lesson = $db->fetch("SELECT id FROM live_classes WHERE id = ?", [$lessonId]);
if (!$lesson) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lesson not found']);
    exit;
}

$db->query(
    "INSERT INTO egress_failures (lesson_id, error_message, created_at) VALUES (?, ?, NOW())",
    [$lessonId, $errorMsg]
);

error_log("[Egress Failure] lesson_id={$lessonId} error={$errorMsg}");

echo json_encode(['success' => true]);
