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
    $durationMs = (float) ($_POST['duration_ms'] ?? 0);
    $mimeType = $_POST['mime_type'] ?? 'video/webm';

    if (!$webinarId || $durationMs <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    $db = WebinarDatabase::getInstance();
    $webinar = $db->fetch("SELECT recording_path FROM webinars WHERE id = ? AND faculty_id = ?", [$webinarId, $_SESSION['webinar_faculty_id']]);

    if (!$webinar || empty($webinar['recording_path'])) {
        echo json_encode(['success' => false, 'message' => 'Recording not found']);
        exit;
    }

    $uploadDir = '../../uploads/webinar_recordings/';
    $filePath = $uploadDir . $webinar['recording_path'];

    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Physical file not found']);
        exit;
    }

    // --- WebM EBML Patching Logic ---
    // This adds the 'Duration' element to the WebM header so browsers can seek.
    if (strpos($mimeType, 'webm') !== false) {
        if (filesize($filePath) > 1024) { // Only patch if file has data
            try {
                patchWebmDuration($filePath, $durationMs);
            } catch (Exception $e) {
                error_log("WebM Patching Error for Webinar $webinarId: " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recording finalized and optimized',
        'duration' => $durationMs
    ]);
}

/**
 * Patches a WebM file to include duration metadata.
 * Rewrites the file with a corrected EBML Segment Info block.
 */
function patchWebmDuration($filePath, $durationMs)
{
    // WebM Duration float is often expected in seconds by browsers, 
    // despite the Matroska spec mentioning TimecodeScale units.
    $durationSeconds = (float)($durationMs / 1000.0);

    $handle = fopen($filePath, 'r+b');
    if (!$handle) return;

    // Read the first 128KB for header analysis (slightly more than before for safety)
    $bufferSize = 131072;
    $header = fread($handle, $bufferSize);

    // Segment Info ID: 15 49 A9 66
    $infoId = "\x15\x49\xA9\x66";
    $pos = strpos($header, $infoId);

    if ($pos === false) {
        fclose($handle);
        return;
    }

    // Determine Info size (EBML VINT)
    $sizeByte = ord($header[$pos + 4]);
    $sizeLen = 0;
    if ($sizeByte & 0x80) $sizeLen = 1;
    else if ($sizeByte & 0x40) $sizeLen = 2;
    else if ($sizeByte & 0x20) $sizeLen = 3;
    else if ($sizeByte & 0x10) $sizeLen = 4;

    if ($sizeLen === 0) {
        fclose($handle);
        return;
    }

    $infoBodyStart = $pos + 4 + $sizeLen;
    
    // Simple VINT decode for sizes up to 4 bytes
    $infoSize = $sizeByte & (0xFF >> $sizeLen);
    for ($i = 1; $i < $sizeLen; $i++) {
        $infoSize = ($infoSize << 8) | ord($header[$pos + 4 + $i]);
    }

    // Verify if we have the whole Info block in our buffer
    if ($infoBodyStart + $infoSize > $bufferSize) {
        fclose($handle);
        return; // Header too large for this simple patcher
    }

    // Check if Duration already exists inside Info: 44 89
    if (strpos(substr($header, $infoBodyStart, $infoSize), "\x44\x89") !== false) {
        fclose($handle);
        return; // Already patched or present
    }

    // Construct Duration element: 44 89 (ID) + 88 (Size: 8 bytes float) + [8 bytes float BE]
    // Using 'G' for 64-bit float Big-Endian (available in PHP 7.2+)
    $durationBuf = "\x44\x89\x88" . pack('G', $durationSeconds);

    // Update Info Size in the result
    $newInfoSize = $infoSize + strlen($durationBuf);
    
    // Construct new VINT for Size (using same length as original for simplicity)
    $newSizeVint = "";
    $tempSize = $newInfoSize;
    for ($i = $sizeLen - 1; $i >= 0; $i--) {
        $byte = $tempSize & 0xFF;
        if ($i === 0) $byte |= (0x80 >> ($sizeLen - 1));
        $newSizeVint = chr($byte) . $newSizeVint;
        $tempSize >>= 8;
    }

    // Reconstruct the file safely
    $tempPath = $filePath . '.tmp';
    $tempHandle = fopen($tempPath, 'wb');

    // Part prior to Size
    fwrite($tempHandle, substr($header, 0, $pos + 4));
    // New Size VINT
    fwrite($tempHandle, $newSizeVint);
    // Info Body
    fwrite($tempHandle, substr($header, $infoBodyStart, $infoSize));
    // New Duration Element
    fwrite($tempHandle, $durationBuf);
    // Everything else in buffer
    fwrite($tempHandle, substr($header, $infoBodyStart + $infoSize));

    // Pipe the remainder of the file
    fseek($handle, strlen($header));
    while (!feof($handle)) {
        fwrite($tempHandle, fread($handle, 65536));
    }

    fclose($handle);
    fclose($tempHandle);

    // Overwrite original
    rename($tempPath, $filePath);
}
?>