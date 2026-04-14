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

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Vebinar ID göstərilməyib.']);
    exit;
}

try {
    // Verify the webinar belongs to this teacher
    $webinar = $db->fetch(
        "SELECT * FROM webinars WHERE id = ? AND teacher_id = ? AND faculty_id = ?",
        [$id, $user['id'], $user['faculty_id']]
    );

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Vebinar tapılmadı və ya icazəniz yoxdur.']);
        exit;
    }

    // Archived (ended) webinars cannot be deleted
    if ($webinar['status'] === 'ended') {
        echo json_encode(['success' => false, 'message' => 'Arxivlənmiş vebinarlar silinə bilməz.']);
        exit;
    }

    // Live webinars cannot be deleted either
    if ($webinar['status'] === 'live') {
        echo json_encode(['success' => false, 'message' => 'Canlı yayımda olan vebinar silinə bilməz.']);
        exit;
    }

    // Delete the webinar
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("DELETE FROM webinars WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Vebinar uğurla silindi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
