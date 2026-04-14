<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!WebinarAuth::isLoggedIn() || $_SESSION['webinar_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = WebinarDatabase::getInstance();
$webinarId = $_GET['id'] ?? $_GET['webinar_id'] ?? null;
$peerId = $_GET['peer_id'] ?? null;
$user = WebinarAuth::getCurrentUser();

if (!$webinarId || !$peerId) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    // Ensure the teacher owns this webinar or is in the same faculty
    $webinar = $db->fetch("SELECT id FROM webinars WHERE id = ? AND faculty_id = ?", [$webinarId, $user['faculty_id']]);

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Webinar not found or access denied']);
        exit;
    }

    // Since we don't have a peer_id column in the webinars table yet, let's add it or use a metadata table.
    // For now, I'll update the webinars table (I should check if I need to add the column first).
    // Actually, I'll just try to update it. If it fails, I'll add the column.
    
    // Check if column exists
    $pdo = $db->getConnection();
    $columns = $pdo->query("DESCRIBE webinars")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('peer_id', $columns)) {
        $pdo->exec("ALTER TABLE webinars ADD COLUMN peer_id VARCHAR(255) NULL AFTER status");
    }

    $db->update('webinars', ['peer_id' => $peerId], 'id = ?', [$webinarId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
