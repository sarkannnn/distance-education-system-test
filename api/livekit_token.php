<?php
/**
 * Universal LiveKit Token Generator
 * Supports Teacher, Student and Guest authentication
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../teacher/config/database.php';
require_once __DIR__ . '/livekit_helper.php';

// Detect Session & Identity
$identity = '';
$name = '';
$role = '';

// Identify Role Based on Cookie Presence
if (isset($_COOKIE['DISTANT_T_SESSION_V4'])) {
    session_name('DISTANT_T_SESSION_V4');
    @session_start();
    if (!empty($_SESSION['logged_in']) && $_SESSION['user_role'] === 'instructor') {
        $identity = 'teacher_' . $_SESSION['user_id'];
        $name = $_SESSION['user_name'];
        $role = 'teacher';
    }
    session_write_close();
}

if (!$identity && isset($_COOKIE['DISTANT_STUDENT_SESSION'])) {
    session_name('DISTANT_STUDENT_SESSION');
    @session_start();
    if (!empty($_SESSION['logged_in']) && $_SESSION['user_role'] === 'student') {
        $identity = 'student_' . $_SESSION['user_id'];
        $name = $_SESSION['user_name'];
        $role = 'student';
    }
    session_write_close();
}

// Check for Guest Token (Optional for now, but implemented for future-proof)
if (!$identity && !empty($_GET['guest_token'])) {
    $guestToken = $_GET['guest_token'];
    $db = Database::getInstance();
    $webinar = $db->fetch("SELECT title FROM webinars WHERE guest_token = ?", [$guestToken]);
    
    if ($webinar) {
        $identity = 'guest_' . bin2hex(random_bytes(4));
        $name = $_GET['guest_name'] ?? 'Qonaq Müəllim';
        $role = 'guest';
    }
}

if (!$identity) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş qadağandır və ya sessiya bitib.']);
    exit;
}

// Get Room Name
$roomName = $_GET['room'] ?? '';
if (empty($roomName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Otaq adı (room) qeyd olunmalıdır.']);
    exit;
}

// LiveKit Config from .env
$apiKey = $_ENV['LIVEKIT_API_KEY'] ?? $_SERVER['LIVEKIT_API_KEY'] ?? getenv('LIVEKIT_API_KEY');
$apiSecret = $_ENV['LIVEKIT_API_SECRET'] ?? $_SERVER['LIVEKIT_API_SECRET'] ?? getenv('LIVEKIT_API_SECRET');
$apiHost = $_ENV['LIVEKIT_HOST'] ?? $_SERVER['LIVEKIT_HOST'] ?? getenv('LIVEKIT_HOST');

if (!$apiKey || !$apiSecret) {
    http_response_code(500);
    $err = 'LiveKit server tənzimləmələri çatışmır.';
    file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . " - " . $err . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $err]);
    exit;
}

// Permissions based on role
// Teacher and Guest Teacher can publish
// Students can also publish for interactivity (if needed)
$canPublish = true; 
$canSubscribe = true;

try {
    $token = LiveKitHelper::generateToken($apiKey, $apiSecret, $identity, $name, $roomName, $canPublish, $canSubscribe);
    echo json_encode([
        'success' => true,
        'token' => $token,
        'identity' => $identity,
        'name' => $name,
        'serverUrl' => $apiHost
    ]);
} catch (Exception $e) {
    http_response_code(500);
    $err = 'Token yaradılarkən xəta: ' . $e->getMessage();
    file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . " - " . $err . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $err]);
}
