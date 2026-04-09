<?php

/**
 * API to update Peer ID - AGNOSTIC VERSION (GET/POST)
 */
header('Content-Type: application/json');

// Authentication check — only logged-in instructors can update peer IDs
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş tələb olunur']);
    exit;
}
$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'] ?? '', ['instructor', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'İcazə yoxdur']);
    exit;
}

// Accept POST or GET but sanitize inputs
$liveClassId = (int) ($_REQUEST['live_class_id'] ?? 0);
// peer_id must be alphanumeric/hyphen (PeerJS format) — never a URL
$rawPeerId = $_REQUEST['peer_id'] ?? '';
$peerId = preg_replace('/[^a-zA-Z0-9\-_]/', '', substr($rawPeerId, 0, 64));

if ($liveClassId > 0 && !empty($peerId)) {
    try {
        $db = Database::getInstance();

        // Ensure 'started_at' column exists for lesson duration tracking
        try {
            $db->query("SELECT started_at FROM live_classes LIMIT 1");
        } catch (Exception $e) {
            $db->query("ALTER TABLE live_classes ADD COLUMN started_at DATETIME DEFAULT NULL");
        }

        // Ensure 'peer_server' column exists
        try {
            $db->query("SELECT peer_server FROM live_classes LIMIT 1");
        } catch (Exception $e) {
            $db->query("ALTER TABLE live_classes ADD COLUMN peer_server VARCHAR(50) DEFAULT 'local'");
        }

        // Whitelist allowed server values
        $rawServer = $_REQUEST['server'] ?? 'local';
        $peerServer = in_array($rawServer, ['local', 'cloud'], true) ? $rawServer : 'local';

        // Verify the instructor owns this live class before updating
        $instructor = $db->fetch("SELECT id FROM instructors WHERE user_id = ?", [$currentUser['id']]);
        if (!$instructor && $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Müəllim tapılmadı']);
            exit;
        }

        $ownershipWhere = ($currentUser['role'] === 'admin')
            ? "(id = ? OR tmis_session_id = ?)"
            : "(id = ? OR tmis_session_id = ?) AND (instructor_id = ? OR instructor_id IN (SELECT id FROM instructors WHERE user_id = ?))";
        $ownershipParams = ($currentUser['role'] === 'admin')
            ? [$liveClassId, $liveClassId]
            : [$liveClassId, $liveClassId, $instructor['id'], $currentUser['id']];

        $classCheck = $db->fetch("SELECT id FROM live_classes WHERE {$ownershipWhere}", $ownershipParams);
        if (!$classCheck) {
            echo json_encode(['success' => false, 'message' => 'Dərs tapılmadı və ya icazəniz yoxdur']);
            exit;
        }

        $db->query(
            "UPDATE live_classes 
             SET zoom_link = ?, 
                 peer_server = ?,
                 status = 'live',
                 started_at = IF(started_at IS NULL, NOW(), started_at) 
             WHERE id = ? OR tmis_session_id = ?",
            [$peerId, $peerServer, $liveClassId, $liveClassId]
        );

        echo json_encode(['success' => true, 'method' => $_SERVER['REQUEST_METHOD']]);
    } catch (Exception $e) {
        error_log('update_peer_id error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Əməliyyat uğursuz oldu']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Parametrlər çatışmır']);
}
