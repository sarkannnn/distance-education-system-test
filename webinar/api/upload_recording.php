<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!WebinarAuth::isLoggedIn() || $_SESSION['webinar_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $webinarId = (int) ($_POST['webinar_id'] ?? 0);

    if (!$webinarId || $webinarId <= 0 || !isset($_FILES['video_blob'])) {
        echo json_encode(['success' => false, 'message' => 'Missing data']);
        exit;
    }

    $db = WebinarDatabase::getInstance();
    // Verify faculty isolation
    $webinar = $db->fetch("SELECT id FROM webinars WHERE id = ? AND faculty_id = ?", [$webinarId, $_SESSION['webinar_faculty_id']]);
    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Access denied: webinar not found in your faculty']);
        exit;
    }

    $uploadDir = '../../uploads/webinar_recordings/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit;
        }
    }

    if (!is_writable($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit;
    }

    $mimeType = $_POST['mime_type'] ?? 'video/webm';
    $ext = (strpos($mimeType, 'mp4') !== false) ? '.mp4' : '.webm';
    $fileName = 'webinar_' . $webinarId . $ext;
    $filePath = $uploadDir . $fileName;

    // Append chunk to file
    $tempFile = $_FILES['video_blob']['tmp_name'];
    $chunkData = file_get_contents($tempFile);
    $isFirstChunk = ($_POST['is_first_chunk'] ?? '0') === '1';

    if ($chunkData === false || strlen($chunkData) === 0) {
        echo json_encode(['success' => false, 'message' => 'Empty chunk data']);
        exit;
    }

    // Əgər fayl artıq mövcuddursa və bu yeni sessiyanın ilk parçasıdırsa, 
    // WebM/EBML başlığını silib pleyerin donmasının qarşısını alırıq.
    if ($isFirstChunk && file_exists($filePath) && filesize($filePath) > 100) {
        // WebM Cluster ID: 1F 43 B6 75
        $clusterPos = strpos($chunkData, "\x1F\x43\xB6\x75");
        if ($clusterPos !== false) {
            $chunkData = substr($chunkData, $clusterPos);
        }
    }

    if (file_put_contents($filePath, $chunkData, FILE_APPEND | LOCK_EX)) {
        // Update DB if not already set
        try {
            $db = WebinarDatabase::getInstance();
            $db->update('webinars', ['recording_path' => $fileName], 'id = ? AND (recording_path IS NULL OR recording_path = "")', [$webinarId]);
        } catch (Exception $e) {
            // Silently ignore DB errors if file was saved
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Chunk saved',
            'size' => filesize($filePath)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
}
