<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!WebinarAuth::isLoggedIn() || $_SESSION['webinar_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $webinarId = (int)($_POST['webinar_id'] ?? 0);
    $durationMs = (float)($_POST['duration_ms'] ?? 0);
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
 * Robustly patches a WebM file to include duration metadata.
 * Handles EBML VINT headers up to 8 bytes and injects/updates Duration (0x4489).
 */
function patchWebmDuration($filePath, $durationMs) {
    if (!file_exists($filePath)) return;
    
    $handle = fopen($filePath, 'r+b');
    if (!$handle) return;

    // Read start for header analysis
    $header = fread($handle, 131072); // Read 128KB to be safe
    
    // 1. Find Segment Info ID: 15 49 A9 66
    $infoId = "\x15\x49\xA9\x66";
    $infoOffset = strpos($header, $infoId);
    if ($infoOffset === false) {
        fclose($handle);
        return;
    }

    // 2. Decode Info size
    $sizeInfo = decodeVint($header, $infoOffset + 4);
    if (!$sizeInfo) { fclose($handle); return; }
    
    $infoDataOffset = $infoOffset + 4 + $sizeInfo['length'];
    $infoDataSize = $sizeInfo['value'];
    $infoBody = substr($header, $infoDataOffset, $infoDataSize);

    // 3. Check for existing Duration (0x4489)
    $durationId = "\x44\x89";
    $durOffset = strpos($infoBody, $durationId);

    // Construct Duration element: 44 89 (ID) + 88 (Size: 8 bytes float) + [8 bytes double big-endian]
    $durationElement = $durationId . "\x88" . strrev(pack('d', (double)$durationMs));

    if ($durOffset !== false) {
        // Duration exists, update it if it's 8 bytes
        $durSizeVint = ord($infoBody[$durOffset + 2]);
        if (($durSizeVint & 0x80) && ($durSizeVint & 0x7F) === 8) {
            $newInfoBody = substr($infoBody, 0, $durOffset) . $durationElement . substr($infoBody, $durOffset + 11);
        } else {
            // Unexpected size, just leave it (rare)
            fclose($handle);
            return;
        }
    } else {
        // Duration missing, append it
        $newInfoBody = $infoBody . $durationElement;
    }

    // 4. Update Info Size VINT
    $newInfoDataSize = strlen($newInfoBody);
    $newSizeVint = encodeVint($newInfoDataSize);

    // 5. Reconstruct file
    $tempPath = $filePath . '.patch.tmp';
    $tempHandle = fopen($tempPath, 'wb');
    
    // Part before Info size: [EBML...InfoID]
    fwrite($tempHandle, substr($header, 0, $infoOffset + 4));
    // New size
    fwrite($tempHandle, $newSizeVint);
    // New body
    fwrite($tempHandle, $newInfoBody);
    // Rest of original header
    fwrite($tempHandle, substr($header, $infoDataOffset + $infoDataSize));
    
    // Stream rest of physical file
    fseek($handle, strlen($header));
    while (!feof($handle)) {
        fwrite($tempHandle, fread($handle, 131072));
    }

    fclose($handle);
    fclose($tempHandle);
    
    // Finalize
    if (filesize($tempPath) > 0) {
        rename($tempPath, $filePath);
    } else {
        unlink($tempPath);
    }
}

/**
 * Decodes EBML Variable Sized Integer (VINT)
 */
function decodeVint($buffer, $offset) {
    if (!isset($buffer[$offset])) return null;
    $firstByte = ord($buffer[$offset]);
    $length = 0;
    $mask = 0x80;
    
    for ($i = 1; $i <= 8; $i++) {
        if ($firstByte & $mask) {
            $length = $i;
            break;
        }
        $mask >>= 1;
    }
    
    if ($length === 0) return null;
    
    $value = $firstByte & ($mask - 1);
    for ($i = 1; $i < $length; $i++) {
        $value = ($value << 8) | ord($buffer[$offset + $i]);
    }
    
    return ['value' => $value, 'length' => $length];
}

/**
 * Encodes integer as EBML VINT
 */
function encodeVint($value) {
    $length = 1;
    if ($value >= 0x7FFFFFFFFFFFFF) $length = 8; // unlikely for metadata
    else if ($value >= 0xFFFFFFFFFFFFF) $length = 7;
    else if ($value >= 0x1FFFFFFFFFFF) $length = 6;
    else if ($value >= 0x3FFFFFFFFF) $length = 5;
    else if ($value >= 0x7FFFFFFF) $length = 4;
    else if ($value >= 0xFFFFFF) $length = 3;
    else if ($value >= 0x7F) $length = 2;
    
    $bytes = array();
    for ($i = 1; $i < $length; $i++) {
        $bytes[] = $value & 0xFF;
        $value >>= 8;
    }
    
    $firstByte = (0x80 >> ($length - 1)) | $value;
    $result = chr($firstByte);
    for ($i = count($bytes) - 1; $i >= 0; $i--) {
        $result .= chr($bytes[$i]);
    }
    
    return $result;
}
?>
