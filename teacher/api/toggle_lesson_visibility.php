<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

$auth = new Auth();
requireInstructor();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $type = $data['type'] ?? ''; // 'arch' or 'live'
    $id = $data['id'] ?? null;
    $is_visible = isset($data['is_visible']) ? (int)$data['is_visible'] : 1;

    if (!$type || !$id) {
        echo json_encode(['success' => false, 'message' => 'Parametrlər çatışmır']);
        exit;
    }

    try {
        $currentUser = $auth->getCurrentUser();
        $table = ($type === 'live') ? 'live_classes' : 'archived_lessons';
        
        // Ownership verification
        $lesson = $db->fetch("SELECT instructor_id FROM {$table} WHERE id = ?", [$id]);
        if (!$lesson) {
            echo json_encode(['success' => false, 'message' => 'Dərs tapılmadı']);
            exit;
        }

        // Verify if the instructor owns the lesson or is admin
        $instructor = $db->fetch("SELECT user_id FROM instructors WHERE id = ?", [$lesson['instructor_id']]);
        if (!$instructor || ($instructor['user_id'] != $currentUser['id'] && $_SESSION['user_role'] !== 'admin')) {
            echo json_encode(['success' => false, 'message' => 'Bu əməliyyat üçün icazəniz yoxdur']);
            exit;
        }

        // Update visibility
        $db->update($table, ['is_visible' => $is_visible], 'id = :id', ['id' => $id]);

        echo json_encode([
            'success' => true, 
            'message' => $is_visible ? 'Dərs tələbələrə görünür' : 'Dərs tələbələrdən gizlədildi',
            'is_visible' => $is_visible
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yanlış sorğu metodu']);
}
