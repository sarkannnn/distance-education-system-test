<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

$auth = new Auth();
requireInstructor();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $live_class_id = $_POST['live_class_id'] ?? null;
    $action = $_POST['action'] ?? 'approve'; // approve or reject

    if (!$live_class_id) {
        echo json_encode(['success' => false, 'message' => 'Dərs ID çatışmır']);
        exit;
    }

    try {
        $currentUser = $auth->getCurrentUser();
        
        // Verify ownership
        $classInfo = $db->fetch("SELECT lc.*, i.user_id as instructor_user_id 
                               FROM live_classes lc 
                               JOIN instructors i ON lc.instructor_id = i.id
                               WHERE lc.id = ?", [$live_class_id]);

        if (!$classInfo) {
            echo json_encode(['success' => false, 'message' => 'Dərs tapılmadı']);
            exit;
        }

        if ($classInfo['instructor_user_id'] != $currentUser['id'] && $_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Bu dərsi təsdiqləmək üçün icazəniz yoxdur']);
            exit;
        }

        if ($action === 'approve') {
            $db->update(
                'live_classes',
                [
                    'status' => 'ended',
                    'is_approved' => 1
                ],
                'id = :id',
                ['id' => $live_class_id]
            );
            echo json_encode(['success' => true, 'message' => 'Dərs uğurla təsdiqləndi']);
        } else {
            // Reject: Keep it pending or mark as rejected? 
            // The requirement says "If the teacher does not approve it, the class should not appear to students."
            // We can mark it as 'rejected' (need to add to enum if we want a formal state)
            // For now, let's just mark is_approved = 0 and status = 'ended' (but hidden)
            // Or keep status = 'pending_approval' but mark it somehow.
            // Let's use status = 'ended' but is_approved = 0 to hide it.
            $db->update(
                'live_classes',
                [
                    'status' => 'ended',
                    'is_approved' => 0
                ],
                'id = :id',
                ['id' => $live_class_id]
            );
            echo json_encode(['success' => true, 'message' => 'Dərs rədd edildi və tələbələrə gizli qaldı']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yanlış sorğu metodu']);
}
