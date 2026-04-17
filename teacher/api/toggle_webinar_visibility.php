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
$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);
$isVisible = intval($data['is_visible'] ?? 1);

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
            echo json_encode(['success' => false, 'message' => 'Səlahiyyətiniz yoxdur.']);
            exit;
        }
    }

    $pdo = $wdb->getConnection();
    $stmt = $pdo->prepare("UPDATE webinars SET is_visible = ? WHERE id = ?");
    $stmt->execute([$isVisible, $id]);

    echo json_encode(['success' => true, 'message' => 'Görünürlük statusu yeniləndi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
