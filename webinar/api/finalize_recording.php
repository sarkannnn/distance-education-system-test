<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');
set_time_limit(300); // Allow up to 5 minutes for ffmpeg processing

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

    $method = 'none';

    // --- Strategy 1: FFmpeg Remux (BEST — adds Cues, fixes Duration, enables seeking) ---
    if (strpos($mimeType, 'webm') !== false && filesize($filePath) > 1024) {
        $ffmpegPath = findFFmpeg();
        
        if ($ffmpegPath) {
            $tempOut = $filePath . '.remuxed.webm';
            // -c copy = no re-encoding (fast), just repackaging with proper metadata
            $cmd = escapeshellarg($ffmpegPath) . ' -y -i ' . escapeshellarg(realpath($filePath)) 
                 . ' -c copy -fflags +genpts ' . escapeshellarg($tempOut) . ' 2>&1';
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempOut) && filesize($tempOut) > 1024) {
                // Replace original with remuxed version
                unlink($filePath);
                rename($tempOut, $filePath);
                $method = 'ffmpeg';
            } else {
                // FFmpeg failed — cleanup and fall through to EBML patch
                if (file_exists($tempOut)) unlink($tempOut);
                error_log("FFmpeg remux failed for webinar $webinarId: " . implode("\n", $output));
                
                // Fallback to EBML patch
                try {
                    patchWebmDuration($filePath, $durationMs);
                    $method = 'ebml_patch';
                } catch (Exception $e) {
                    error_log("EBML Patch also failed for webinar $webinarId: " . $e->getMessage());
                }
            }
        } else {
            // No FFmpeg — use EBML patch
            try {
                patchWebmDuration($filePath, $durationMs);
                $method = 'ebml_patch';
            } catch (Exception $e) {
                error_log("WebM Patching Error for Webinar $webinarId: " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recording finalized',
        'method' => $method,
        'duration' => $durationMs,
        'file_size' => filesize($filePath)
    ]);
}

/**
 * Find ffmpeg binary in common locations
 */
function findFFmpeg()
{
    // Check if ffmpeg is in PATH
    $whereCmd = (PHP_OS_FAMILY === 'Windows') ? 'where ffmpeg 2>NUL' : 'which ffmpeg 2>/dev/null';
    $path = trim(shell_exec($whereCmd) ?? '');
    if ($path && file_exists(explode("\n", $path)[0])) {
        return explode("\n", $path)[0];
    }

    // Common locations
    $locations = [
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\laragon\\bin\\ffmpeg\\ffmpeg.exe',
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
    ];

    foreach ($locations as $loc) {
        if (file_exists($loc)) return $loc;
    }

    return null;
}

/**
 * Fallback: Patches a WebM file to include duration metadata.
 * WebM Duration is stored in MILLISECONDS (not seconds) as per Matroska spec
 * when TimecodeScale is the default 1,000,000 nanoseconds.
 */
function patchWebmDuration($filePath, $durationMs)
{
    // WebM/Matroska Duration field = duration in TimecodeScale units
    // Default TimecodeScale = 1,000,000 ns = 1ms
    // So Duration should be in MILLISECONDS (float64)
    $durationValue = (float) $durationMs;

    $handle = fopen($filePath, 'r+b');
    if (!$handle) return;

    $bufferSize = min(filesize($filePath), 262144);
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
    for ($bit = 7; $bit >= 0; $bit--) {
        if ($sizeByte & (1 << $bit)) {
            $sizeLen = 8 - $bit;
            break;
        }
    }

    if ($sizeLen === 0 || $sizeLen > 4) {
        fclose($handle);
        return;
    }

    $infoBodyStart = $pos + 4 + $sizeLen;

    $infoSize = $sizeByte & (0xFF >> $sizeLen);
    for ($i = 1; $i < $sizeLen; $i++) {
        $infoSize = ($infoSize << 8) | ord($header[$pos + 4 + $i]);
    }

    if ($infoBodyStart + $infoSize > $bufferSize) {
        fclose($handle);
        return;
    }

    // Check if Duration already exists: 44 89
    if (strpos(substr($header, $infoBodyStart, $infoSize), "\x44\x89") !== false) {
        fclose($handle);
        return;
    }

    // Duration element: 44 89 (ID) + 88 (Size=8) + float64 BE
    $durationBuf = "\x44\x89\x88" . pack('G', $durationValue);
    $newInfoSize = $infoSize + strlen($durationBuf);

    // VINT overflow check
    $maxForLen = (1 << (7 * $sizeLen)) - 2;
    if ($newInfoSize > $maxForLen) {
        fclose($handle);
        error_log("WebM Patch: VINT overflow for $filePath");
        return;
    }

    $newSizeVint = "";
    $tempSize = $newInfoSize;
    for ($i = $sizeLen - 1; $i >= 0; $i--) {
        $byte = $tempSize & 0xFF;
        if ($i === 0) $byte |= (0x80 >> ($sizeLen - 1));
        $newSizeVint = chr($byte) . $newSizeVint;
        $tempSize >>= 8;
    }

    $tempPath = $filePath . '.tmp';
    $tempHandle = fopen($tempPath, 'wb');
    if (!$tempHandle) {
        fclose($handle);
        return;
    }

    try {
        fwrite($tempHandle, substr($header, 0, $pos + 4));
        fwrite($tempHandle, $newSizeVint);
        fwrite($tempHandle, substr($header, $infoBodyStart, $infoSize));
        fwrite($tempHandle, $durationBuf);
        fwrite($tempHandle, substr($header, $infoBodyStart + $infoSize));

        fseek($handle, strlen($header));
        while (!feof($handle)) {
            fwrite($tempHandle, fread($handle, 65536));
        }

        fclose($handle);
        fclose($tempHandle);
        rename($tempPath, $filePath);
    } catch (Exception $e) {
        fclose($handle);
        fclose($tempHandle);
        if (file_exists($tempPath)) unlink($tempPath);
        throw $e;
    }
}
?>