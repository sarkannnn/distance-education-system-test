<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!WebinarAuth::isLoggedIn() || ($_SESSION['webinar_role'] !== 'teacher' && $_SESSION['webinar_role'] !== 'admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = WebinarDatabase::getInstance();
$id = $_GET['id'] ?? null;
$user = WebinarAuth::getCurrentUser();

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID missing']);
    exit;
}

try {
    // Admin can end any webinar, teachers only within their faculty/own class
    $webinar = null;
    $type = 'webinar';

    if ($user['role'] === 'admin') {
        $webinar = $db->fetch("SELECT id, 'webinar' as type FROM webinars WHERE id = ?", [$id]);
        if (!$webinar) {
            $webinar = $db->fetch("SELECT id, 'live_class' as type FROM live_classes WHERE id = ?", [$id]);
            $type = 'live_class';
        }
    } else {
        $webinar = $db->fetch("SELECT id, 'webinar' as type FROM webinars WHERE id = ? AND faculty_id = ?", [$id, $user['faculty_id']]);
        if (!$webinar) {
            $webinar = $db->fetch("SELECT id, 'live_class' as type FROM live_classes WHERE id = ? AND (instructor_id = ? OR faculty_id = ?)", [
                $id, 
                $_SESSION['webinar_user_id'] ?? 0,
                $user['faculty_id']
            ]);
            $type = 'live_class';
        }
    }

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Webinar/Class not found or access denied']);
        exit;
    }

    if ($type === 'webinar') {
        $db->update('webinars', 
            ['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$id]
        );
    } else {
        $db->update('live_classes', 
            ['status' => 'pending_approval', 'end_time' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$id]
        );
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
