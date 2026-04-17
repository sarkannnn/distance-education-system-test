<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

WebinarAuth::requireRole('teacher');
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yalnız POST metodu qəbul olunur.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$scheduled_at = $_POST['scheduled_at'] ?? '';
$duration = intval($_POST['duration'] ?? 90);

if (!$id || !$title || !$scheduled_at) {
    echo json_encode(['success' => false, 'message' => 'Zəruri sahələr boşdur.']);
    exit;
}

try {
    // Admin can edit any webinar, teachers only their own
    if ($user['role'] === 'admin') {
        $webinar = $db->fetch("SELECT * FROM webinars WHERE id = ?", [$id]);
    } else {
        $webinar = $db->fetch(
            "SELECT * FROM webinars WHERE id = ? AND teacher_id = ? AND faculty_id = ?",
            [$id, $user['id'], $user['faculty_id']]
        );
    }

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Vebinar tapılmadı və ya icazəniz yoxdur.']);
        exit;
    }

    if ($webinar['status'] !== 'scheduled') {
        echo json_encode(['success' => false, 'message' => 'Yalnız gözləyən statusda olan vebinarlar redaktə edilə bilər.']);
        exit;
    }

    $db->update('webinars', [
        'title' => $title,
        'description' => $description,
        'scheduled_at' => $scheduled_at,
        'duration' => $duration
    ], 'id = ?', [$id]);

    echo json_encode(['success' => true, 'message' => 'Vebinar uğurla yeniləndi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
