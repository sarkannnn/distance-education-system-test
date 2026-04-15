<?php

/**
 * WebRTC Live Recording Chunk Upload API (V3 - Fixed Auth)
 * Periodic flush zamanı recording parçalarını serverə yazır
 */
require_once '../../teacher/includes/auth.php';
require_once '../../teacher/config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: not logged in']);
    exit;
}

$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'], ['instructor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: ' . ($currentUser['role'] ?? 'no-role')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lessonId = (int) ($_POST['lesson_id'] ?? 0);

    if (!$lessonId || $lessonId <= 0 || !isset($_FILES['video_blob'])) {
        echo json_encode(['success' => false, 'message' => 'Missing data: lesson_id=' . ($lessonId ?: 'invalid') . ', video_blob=' . (isset($_FILES['video_blob']) ? 'yes' : 'no')]);
        exit;
    }

    $uploadDir = '../../uploads/live_recordings/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    $fileName = 'lesson_' . $lessonId . '.webm';
    $filePath = $uploadDir . $fileName;

    // Gelen parçanı mövcud fayla APPEND et
    $tempFile = $_FILES['video_blob']['tmp_name'];
    $chunkData = file_get_contents($tempFile);
    $isFirstChunk = ($_POST['is_first_chunk'] ?? '0') === '1';

    if ($chunkData === false || strlen($chunkData) === 0) {
        echo json_encode(['success' => false, 'message' => 'Empty chunk data']);
        exit;
    }

    // Əgər fayl artıq mövcuddursa və bu yeni sessiyanın ilk parçasıdırsa (məs: müəllim refresh edib),
    // WebM/EBML header-ini silib yalnız Cluster-ləri append etməliyik ki, pleyer donmasın.
    if ($isFirstChunk && file_exists($filePath) && filesize($filePath) > 100) {
        // WebM Cluster ID: 1F 43 B6 75
        $clusterPos = strpos($chunkData, "\x1F\x43\xB6\x75");
        if ($clusterPos !== false) {
            $chunkData = substr($chunkData, $clusterPos);
        }
    }

    if (file_put_contents($filePath, $chunkData, FILE_APPEND | LOCK_EX)) {
        // Veritabanını yenilə
        try {
            $db = Database::getInstance();
            $db->query("UPDATE live_classes SET recording_path = ? WHERE id = ? AND (recording_path IS NULL OR recording_path = '')", [$fileName, $lessonId]);
        } catch (Exception $e) {
            // Log error if needed
        }

        echo json_encode([
            'success' => true,
            'message' => 'Chunk saved',
            'size' => filesize($filePath),
            'chunk_size' => strlen($chunkData)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write file to: ' . $filePath]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
}
