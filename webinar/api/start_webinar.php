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
    // Admin can start any webinar, teachers only within their faculty
    if ($user['role'] === 'admin') {
        $webinar = $db->fetch("SELECT * FROM webinars WHERE id = ?", [$id]);
    } else {
        $webinar = $db->fetch("SELECT * FROM webinars WHERE id = ? AND faculty_id = ?", [$id, $user['faculty_id']]);
    }

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Webinar not found or access denied']);
        exit;
    }

    // Prevent starting a new live class if the teacher already has one active
    $activeWebinar = $db->fetch("SELECT id FROM webinars WHERE teacher_id = ? AND status = 'live'", [$webinar['teacher_id']]);
    if ($activeWebinar && $activeWebinar['id'] != $id) {
        echo json_encode(['success' => false, 'message' => 'Bu kafedranın (müəllimin) artıq davam edən (canlı) dərsi var. Eyni anda iki dərs başladıla bilməz. Əvvəlcə aktiv dərsi bitirin.']);
        exit;
    }

    $db->update('webinars', 
        ['status' => 'live', 'started_at' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$id]
    );

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
