<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

$auth = new Auth();
$currentUser = $auth->getCurrentUser();
$isAdmin = (($_SESSION['user_role'] ?? '') === 'admin');

if (!$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Bu əməliyyat yalnız Admin tərəfindən icra edilə bilər.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yalnız POST metodu qəbul olunur.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? ''; // 'live_class' və ya 'webinar'
$title = trim($_POST['title'] ?? '');

if (!$id || !$type || !$title) {
    echo json_encode(['success' => false, 'message' => 'Zəruri məlumatlar çatışmır.']);
    exit;
}

$db = Database::getInstance();

try {
    if ($type === 'live_class') {
        $db->update('live_classes', ['title' => $title], 'id = :id', ['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Dərs mövzusu uğurla yeniləndi.']);
    } elseif ($type === 'webinar') {
        // Webinar üçün webinar bazasına qoşulmaq lazımdır
        require_once '../../webinar/config/database.php';
        $wdb = WebinarDatabase::getInstance();
        $wdb->update('webinars', ['title' => $title], 'id = ?', [$id]);
        echo json_encode(['success' => true, 'message' => 'Vebinar mövzusu uğurla yeniləndi.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Yanlış tip.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
}
