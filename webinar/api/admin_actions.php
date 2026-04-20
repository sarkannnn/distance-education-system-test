<?php
require_once '../config/auth.php';
require_once '../config/database.php';

// Only Admin can access
$user = WebinarAuth::getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = WebinarDatabase::getInstance()->getConnection();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $input['action'] ?? '';

try {
    if ($action === 'toggle_status') {
        $userId = intval($input['userId']);
        $status = intval($input['status']);
        
        $stmt = $db->prepare("UPDATE webinar_users SET is_active = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$status, $userId]);
        
        echo json_encode(['success' => true]);
    } 
    elseif ($action === 'change_password') {
        $userId = intval($input['userId']);
        $newPwd = $input['password'];
        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE webinar_users SET password_hash = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$hash, $userId]);
        
        echo json_encode(['success' => true]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
