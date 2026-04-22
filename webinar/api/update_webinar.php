<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();

if ($user['role'] !== 'teacher' && $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Bu əməliyyat üçün icazəniz yoxdur.']);
    exit;
}

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
        if (empty($user['department_id'])) {
            // Master Admin
            $webinar = $db->fetch("SELECT * FROM webinars WHERE id = ?", [$id]);
        } else {
            // Department Admin
            $webinar = $db->fetch("SELECT * FROM webinars WHERE id = ? AND department_id = ?", [$id, $user['department_id']]);
        }
    } else {
        // Teacher
        $webinar = $db->fetch(
            "SELECT * FROM webinars WHERE id = ? AND teacher_id = ? AND department_id = ?",
            [$id, $user['id'], $user['department_id']]
        );
    }

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Vebinar tapılmadı və ya icazəniz yoxdur.']);
        exit;
    }

    if (!in_array($webinar['status'], ['scheduled', 'ended'])) {
        echo json_encode(['success' => false, 'message' => 'Yalnız gözləyən və ya arxivləşdirilmiş vebinarlar redaktə edilə bilər.']);
        exit;
    }

    $updateData = [
        'title' => $title,
        'description' => $description
    ];

    if ($webinar['status'] === 'scheduled') {
        $updateData['scheduled_at'] = $scheduled_at;
        $updateData['duration'] = $duration;
    }

    $db->update('webinars', $updateData, 'id = ?', [$id]);

    echo json_encode(['success' => true, 'message' => 'Vebinar uğurla yeniləndi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
