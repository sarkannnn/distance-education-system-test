<?php
require_once '../includes/auth.php';
require_once '../../webinar/config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$user = $auth->getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Səlahiyyət yoxdur.']);
    exit;
}

$isAdmin = (($_SESSION['user_role'] ?? '') === 'admin');
$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID göstərilməyib.']);
    exit;
}

try {
    $wdb = WebinarDatabase::getInstance();
    $webinar = $wdb->fetch("SELECT * FROM webinars WHERE id = ?", [$id]);

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Vebinar tapılmadı.']);
        exit;
    }

    if (!$isAdmin) {
        if ($webinar['teacher_id'] != $user['id'] && $webinar['teacher_id'] != $_SESSION['tmis_id']) {
            echo json_encode(['success' => false, 'message' => 'Bu vebinarı silmək üçün səlahiyyətiniz yoxdur.']);
            exit;
        }
    }

    $pdo = $wdb->getConnection();
    $stmt = $pdo->prepare("DELETE FROM webinars WHERE id = ?");
    $stmt->execute([$id]);

    // Optional: Delete physical file if needed
    if (!empty($webinar['recording_path']) && $webinar['recording_path'] !== '#') {
        $filePath = __DIR__ . '/../../uploads/webinar_recordings/' . $webinar['recording_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Vebinar arxivdən silindi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
