<?php
require_once '../config/auth.php';
require_once '../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

$db = WebinarDatabase::getInstance();
$webinar = $db->fetch("SELECT peer_id FROM webinars WHERE id = ?", [$id]);

if ($webinar) {
    echo json_encode(['success' => true, 'peer_id' => $webinar['peer_id']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Not found']);
}
