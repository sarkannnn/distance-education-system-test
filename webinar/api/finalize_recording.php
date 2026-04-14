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
    $handle = fopen($filePath, 'r+b');
    if (!$handle)
        return;

    // Read the first 64KB for header analysis
    $header = fread($handle, 65536);

    // Segment Info ID: 15 49 A9 66
    $infoId = "\x15\x49\xA9\x66";
    $pos = strpos($header, $infoId);

    if ($pos === false) {
        fclose($handle);
        return;
    }

    // Get Info size (VINT)
    $sizeByte = ord($header[$pos + 4]);
    $sizeLen = 0;
    if ($sizeByte & 0x80)
        $sizeLen = 1;
    else if ($sizeByte & 0x40)
        $sizeLen = 2;
    // ... we assume 1 byte for simplicity in typical MediaRecorder output (under 127 bytes)

    if ($sizeLen !== 1) {
        fclose($handle);
        return;
    }

    $infoBodyStart = $pos + 5;
    $infoSize = $sizeByte & 0x7F;

    // Check if Duration already exists inside Info: 44 89
    if (strpos(substr($header, $infoBodyStart, $infoSize), "\x44\x89") !== false) {
        fclose($handle);
        return; // Already patched or present
    }

    // Construct Duration element: 44 89 (ID) + 88 (Size: 8 bytes float) + [8 bytes float]
    // WebM durations are usually in timecode units (usually 1ms if TimecodeScale is 1,000,000)
    $durationBuf = "\x44\x89\x88" . pack('E', $durationMs); // 'E' is 64-bit float little-endian, but WebM needs big-endian

    // Convert to Big-Endian (WebM standard)
    $durationBuf = "\x44\x89\x88" . strrev(pack('d', $durationMs)); // 'd' is architecture-dependent, strrev for big-endian fix

    // Update Info Size
    $newInfoSize = $infoSize + strlen($durationBuf);
    $newSizeByte = chr(0x80 | $newInfoSize);

    // Reconstruct the file
    // [Header up to InfoID] + [InfoID] + [NewSize] + [OldInfoBody] + [DurationBuf] + [RestOfFile]

    $part1 = substr($header, 0, $pos + 4);
    $part2 = substr($header, $infoBodyStart, $infoSize);
    $restOfHeader = substr($header, $infoBodyStart + $infoSize);

    // Use a temp file to rewrite safely
    $tempPath = $filePath . '.tmp';
    $tempHandle = fopen($tempPath, 'wb');

    fwrite($tempHandle, $part1);
    fwrite($tempHandle, $newSizeByte);
    fwrite($tempHandle, $part2);
    fwrite($tempHandle, $durationBuf);
    fwrite($tempHandle, $restOfHeader);

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