<?php
/**
 * Video Streaming Endpoint
 * Supports HTTP Range Requests for proper video seeking and progressive playback.
 * Usage: stream_video.php?id=69
 */
require_once '../config/auth.php';
require_once '../config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Missing ID');
}

// Access control
if ($user['role'] === 'admin' && !isset($user['department_id'])) {
    $webinar = $db->fetch("SELECT recording_path FROM webinars WHERE id = ?", [$id]);
} else {
    $webinar = $db->fetch(
        "SELECT recording_path FROM webinars WHERE id = ? AND department_id = ?",
        [$id, $user['department_id']]
    );
}

if (!$webinar || empty($webinar['recording_path'])) {
    http_response_code(404);
    exit('Recording not found');
}

$filePath = realpath('../../uploads/webinar_recordings/' . $webinar['recording_path']);
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

$fileSize = filesize($filePath);
$mimeType = (strpos($filePath, '.mp4') !== false) ? 'video/mp4' : 'video/webm';

// Disable output buffering
if (ob_get_level()) ob_end_clean();

// Handle Range Requests (essential for video seeking)
$start = 0;
$end = $fileSize - 1;
$length = $fileSize;

if (isset($_SERVER['HTTP_RANGE'])) {
    // Parse range header: "bytes=START-END"
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = ($matches[1] !== '') ? intval($matches[1]) : 0;
        $end = ($matches[2] !== '') ? intval($matches[2]) : $fileSize - 1;
        
        // Validate range
        if ($start > $end || $start >= $fileSize) {
            http_response_code(416); // Range Not Satisfiable
            header("Content-Range: bytes */$fileSize");
            exit;
        }
        
        $length = $end - $start + 1;
        
        http_response_code(206); // Partial Content
        header("Content-Range: bytes $start-$end/$fileSize");
    }
} else {
    http_response_code(200);
}

// Send headers
header("Content-Type: $mimeType");
header("Content-Length: $length");
header("Accept-Ranges: bytes");
header("Cache-Control: public, max-age=86400");
header("Content-Disposition: inline");

// Stream the file
$handle = fopen($filePath, 'rb');
if (!$handle) {
    http_response_code(500);
    exit('Cannot open file');
}

fseek($handle, $start);
$remaining = $length;
$bufferSize = 65536; // 64KB chunks

while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
    $readSize = min($bufferSize, $remaining);
    echo fread($handle, $readSize);
    $remaining -= $readSize;
    flush();
}

fclose($handle);
