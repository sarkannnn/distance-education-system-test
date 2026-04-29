<?php
/**
 * Webinar V2 - LiveKit Token Generator
 * Supports: Teacher, Student, Guest Host
 */

require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../../api/livekit_helper.php';

header('Content-Type: application/json');

// --- Auth: Determine identity & role ---
$user = WebinarAuth::getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş qadağandır.']);
    exit;
}

$identity = $user['role'] . '_' . $user['id'];
$name = $user['full_name'];
$role = $user['role']; // teacher, student, admin

// --- Room name ---
$roomName = $_GET['room'] ?? '';
if (empty($roomName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Otaq adı (room) qeyd olunmalıdır.']);
    exit;
}

// --- LiveKit credentials from .env ---
// Load .env manually if not already loaded
$envFile = realpath(__DIR__ . '/../../.env');
if ($envFile && file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (!getenv($k)) putenv("$k=$v");
    }
}

$apiKey = getenv('LIVEKIT_API_KEY');
$apiSecret = getenv('LIVEKIT_API_SECRET');
$apiHost = getenv('LIVEKIT_HOST');

if (!$apiKey || !$apiSecret) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'LiveKit server tənzimləmələri çatışmır.']);
    exit;
}

// --- Permissions based on role ---
$canPublish = in_array($role, ['teacher', 'admin']); // Students get publish rights when joining stage
$canSubscribe = true;

// If student explicitly requests publish (joining stage)
if ($role === 'student' && isset($_GET['publish']) && $_GET['publish'] === 'true') {
    $canPublish = true;
}

try {
    $token = LiveKitHelper::generateToken(
        $apiKey, $apiSecret,
        $identity, $name,
        $roomName,
        $canPublish, $canSubscribe
    );

    echo json_encode([
        'success' => true,
        'token' => $token,
        'identity' => $identity,
        'name' => $name,
        'role' => $role,
        'serverUrl' => $apiHost
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Token yaradılarkən xəta: ' . $e->getMessage()]);
}
