<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role using session
$role = $_SESSION['user_role'] ?? 'undefined';
if ($role !== 'instructor' && $role !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Forbidden: Instructor access only. Your role: ' . $role]);
    exit;
}

$db = Database::getInstance();
$liveClassId = (int) trim($_POST['live_class_id'] ?? $_GET['live_class_id'] ?? '');
$targetUserId = (int) trim($_POST['user_id'] ?? $_GET['user_id'] ?? '');

if ($liveClassId <= 0 || $targetUserId <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Un-kick the student (allow rejoin)
    // We update the record where is_kicked = 1
    $db->query(
        "UPDATE live_attendance SET is_kicked = 0 
         WHERE live_class_id = ? AND user_id = ? AND is_kicked = 1",
        [$liveClassId, $targetUserId]
    );

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean();
    error_log('approve_rejoin error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Əməliyyat uğursuz oldu']);
}
