<?php
/**
 * Lightweight endpoint to log client-side interactions (like FAQ button clicks)
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/services/loggerService.php';

// Handle Session Detection
$sessionNames = ['DISTANT_STUDENT_SESSION', 'DISTANT_TEACHER_SESSION'];
foreach ($sessionNames as $sn) {
    if (isset($_COOKIE[$sn])) {
        session_name($sn);
        session_start();
        break;
    }
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? '';
$response = $input['response'] ?? '';
$source = $input['source'] ?? 'local_faq';
$model = $input['model'] ?? null;

if (!empty($query)) {
    LoggerService::log($query, $response, $source, $model);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Məlumat çatışmır']);
}
