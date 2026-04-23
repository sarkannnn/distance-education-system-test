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

// Get input safely (read once)
$input = json_decode(file_get_contents('php://input'), true);

$portalHint = $input['portal'] ?? 'guest';

// Handle Session Detection
$sessionNames = ['DISTANT_STUDENT_SESSION', 'DISTANT_STUDENT_SESSION'];
if ($portalHint === 'student') {
    $sessionNames = ['DISTANT_STUDENT_SESSION', 'DISTANT_STUDENT_SESSION'];
}

$sessionFound = false;

foreach ($sessionNames as $sn) {
    if (isset($_COOKIE[$sn])) {
        @session_name($sn);
        if (@session_start()) {
            if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                $sessionFound = true;
                break;
            }
            @session_write_close();
        }
    }
}

if (!$sessionFound && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

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
