<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');
set_time_limit(0); // Allow long uploads if server is slow

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
    
    // Check 'webinars' table first
    $webinar = $db->fetch("SELECT id, 'webinar' as type FROM webinars WHERE id = ? AND faculty_id = ?", [$webinarId, $_SESSION['webinar_faculty_id']]);
    
    // Fallback: Check 'live_classes' table
    if (!$webinar) {
        $webinar = $db->fetch("SELECT id, 'live_class' as type FROM live_classes WHERE id = ? AND (instructor_id = ? OR faculty_id = ?)", [
            $webinarId, 
            $_SESSION['webinar_user_id'] ?? 0,
            $_SESSION['webinar_faculty_id'] ?? 0
        ]);
    }

    if (!$webinar) {
        echo json_encode(['success' => false, 'message' => 'Access denied: webinar/class not found or unauthorized']);
        exit;
    }

    $isLiveClass = ($webinar['type'] === 'live_class');
    $uploadDir = $isLiveClass ? '../../uploads/videos/' : '../../uploads/webinar_recordings/';
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
    $tempFile = $_FILES['video_blob']['tmp_name'] ?? '';
    $uploadError = $_FILES['video_blob']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($uploadError !== UPLOAD_ERR_OK || $tempFile === '' || !is_uploaded_file($tempFile)) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
        ];
        $msg = $errorMessages[$uploadError] ?? "Upload error code: $uploadError";
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    $chunkData = file_get_contents($tempFile);
    $isFirstChunk = ($_POST['is_first_chunk'] ?? '0') === '1';

    if ($chunkData === false || strlen($chunkData) === 0) {
        echo json_encode(['success' => false, 'message' => 'Empty chunk data']);
        exit;
    }

    // Don't strip headers - concatenating full WebM sessions is safer for playback 
    // even if it creates multiple segments (Chained WebM). Striping creates broken clusters.

    $mode = $isFirstChunk ? LOCK_EX : (FILE_APPEND | LOCK_EX);
    if (file_put_contents($filePath, $chunkData, $mode)) {
        // Ensure recording_path is always set in DB
        try {
            $db = WebinarDatabase::getInstance();
            $table = $isLiveClass ? 'live_classes' : 'webinars';
            $existing = $db->fetch("SELECT recording_path FROM $table WHERE id = ?", [$webinarId]);
            if (!$existing || empty($existing['recording_path'])) {
                $db->update($table, ['recording_path' => $fileName], 'id = ?', [$webinarId]);
            }
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
