<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/tmis_api.php';

$auth = new Auth();
requireInstructor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $user = $auth->getCurrentUser();
    $tmisToken = TmisApi::getToken();

    $archive_id = (int) ($_POST['archive_id'] ?? 0);

    if ($archive_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tapılmadı']);
        exit;
    }

    try {
        $deletedFromTmis = false;
        $deletedFromLocal = false;

        // 1. TMİS-dən silməyə çalış
        if ($tmisToken) {
            $tmisResult = TmisApi::deleteArchive($tmisToken, (int) $archive_id);
            if ($tmisResult['success']) {
                $deletedFromTmis = true;
            }
        }

        // 2. Lokal bazadan silməyə çalış
        $isAdmin = (strtolower($_SESSION['user_role'] ?? '') === 'admin');
        
        $instructor = $db->fetch(
            "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
            [$user['id'], $user['email']]
        );

        $instructorId = $instructor ? $instructor['id'] : 0;

        // Admin hər şeyi silə bilər, müəllim isə ancaq özününkünü
        $archiveQuery = $isAdmin 
            ? "SELECT * FROM archived_lessons WHERE id = ?" 
            : "SELECT * FROM archived_lessons WHERE id = ? AND instructor_id = ?";
        $archiveParams = $isAdmin ? [$archive_id] : [$archive_id, $instructorId];

        $archive = $db->fetch($archiveQuery, $archiveParams);
        if ($archive) {
            $db->delete('archived_lessons', 'id = :id', ['id' => $archive_id]);
            $deletedFromLocal = true;
        }

        if ($deletedFromTmis || $deletedFromLocal) {
            echo json_encode(['success' => true, 'message' => 'Material silindi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Material tapılmadı və ya silinmə zamanı xəta baş verdi']);
        }
    } catch (Exception $e) {
        error_log('delete_archive error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server xətası baş verdi']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yanlış sorğu']);
}
