<?php

/**
 * Upload Recording API
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ignore_user_abort(true);
set_time_limit(3600); // 1 hour for background processing
header('Content-Type: application/json');

// Fatal Error Handler
function shutdownHandler()
{
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        $logMsg = date('Y-m-d H:i:s') . " - FATAL ERROR: " . print_r($error, true) . "\n";
        // file_put_contents('../../uploads/upload_error.log', $logMsg, FILE_APPEND);
        file_put_contents(__DIR__ . '/../../uploads/upload_error.log', $logMsg, FILE_APPEND);



        // Try to send JSON if headers haven't been sent (though unpredictable on fatal)
        if (!headers_sent()) {
            echo json_encode(['success' => false, 'message' => 'Serverdə kritik xəta baş verdi']);
        }
    }
}
register_shutdown_function('shutdownHandler');

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/tmis_api.php';
require_once '../includes/helpers.php';


$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'İcazə yoxdur: Giriş edilməyib']);
    exit;
}

$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'], ['instructor', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'İcazə yoxdur: Yalnız müəllimlər üçün']);
    exit;
}

// Debug Log
$logFile = '../../uploads/upload_debug.log';
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? '0';
$logData = date('Y-m-d H:i:s') . " - Request Received. Content-Length: " . $contentLength . " bytes\n";
file_put_contents($logFile, $logData, FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $logData = date('Y-m-d H:i:s') . " - Error: Invalid Request Method\n";
    file_put_contents($logFile, $logData, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

// Check POST Size Limit BEFORE trying to find $_POST vars
if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
    $logData = date('Y-m-d H:i:s') . " - Error: Request body too large or malformed. Content-Length: " . $contentLength . "\n";
    file_put_contents($logFile, $logData, FILE_APPEND);

    // Tələbəyə/Müəllimə aydın xəbərdarlıq göndərmək üçün 200 HTTP status code amma success=false formatı işlədək, 
    // çünki fetch uzağı 413 atır catch-ə düşür, mesaji göstərə bilmir.
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Xəta: Dərs videosunun həcmi çox böyükdür (' . round($contentLength / 1024 / 1024, 2) . ' MB). Server limiti (' . ini_get('post_max_size') . ') aşılıb.'
    ]);
    exit;
}

$lessonId = (int) ($_POST['lesson_id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$noVideo = isset($_POST['no_video']) && $_POST['no_video'] == '1';
$videoFile = $_FILES['video'] ?? null;

if ($lessonId <= 0) {
    $logData = date('Y-m-d H:i:s') . " - Error: Missing ID. lesson_id: INVALID\n";
    file_put_contents($logFile, $logData, FILE_APPEND);
    jsonResponse(['success' => false, 'message' => 'Dərs ID çatışmır']);
}

$db = Database::getInstance();
$lesson = $db->fetch("SELECT lc.*, c.title as course_title FROM live_classes lc LEFT JOIN courses c ON lc.course_id = c.id WHERE lc.id = ?", [$lessonId]);

// Fallback 1: tmis_session_id ilə axtar
if (!$lesson) {
    $lesson = $db->fetch("SELECT lc.*, c.title as course_title FROM live_classes lc LEFT JOIN courses c ON lc.course_id = c.id WHERE lc.tmis_session_id = ?", [$lessonId]);
    if ($lesson) {
        $lessonId = $lesson['id']; // Real DB id istifadə et
        $logData = date('Y-m-d H:i:s') . " - Fallback: Found by tmis_session_id, real id=" . $lessonId . "\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
    }
}

// Fallback 2: course_id ilə aktiv dərsi axtar
if (!$lesson && $courseId) {
    $lesson = $db->fetch("SELECT lc.*, c.title as course_title FROM live_classes lc LEFT JOIN courses c ON lc.course_id = c.id WHERE lc.course_id = ? AND lc.status = 'live' ORDER BY lc.id DESC LIMIT 1", [$courseId]);
    if ($lesson) {
        $lessonId = $lesson['id']; // Real DB id istifadə et
        $logData = date('Y-m-d H:i:s') . " - Fallback: Found by course_id=$courseId, real id=" . $lessonId . "\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
    }
}

$logData = date('Y-m-d H:i:s') . " - lesson_id=$lessonId, course_id=$courseId, lesson_found=" . ($lesson ? 'YES' : 'NO') . "\n";
file_put_contents($logFile, $logData, FILE_APPEND);

$title = ($lesson && !empty($lesson['title'])) ? $lesson['title'] : ($lesson ? ($lesson['course_title'] . " - Video Yazı") : "Canlı Dərs Yazısı #" . $lessonId);
if (!$courseId) {
    $courseId = $lesson ? ($lesson['course_id'] ?? 0) : 0;
}

$msgDuration = 0;
if ($lesson && isset($lesson['start_time']) && $lesson['start_time']) {
    $startT = strtotime($lesson['start_time']);
    $endT = time();
    $msgDuration = round(($endT - $startT) / 60);
    if ($msgDuration < 1)
        $msgDuration = 1;
} else {
    $msgDuration = 90; // Default if local lesson missing
}

// ==========================================================
// TMİS API-yə canlı dərsi bitirmək bildirişi göndərmək — DISABLED (local only)
// ==========================================================
/*
$tmisToken = TmisApi::getToken();
if ($tmisToken) {
    try {
        $tmisEndResult = TmisApi::endLiveSession($tmisToken, [
            'live_session_id' => (int) $lessonId,
            'duration_minutes' => $msgDuration
        ]);
        if (!$tmisEndResult['success']) {
            $logData = date('Y-m-d H:i:s') . " - TMIS End Error: " . ($tmisEndResult['message'] ?? '') . "\n";
            file_put_contents($logFile, $logData, FILE_APPEND);
        } else {
            $logData = date('Y-m-d H:i:s') . " - TMIS Dərs uğurla bitirildi: $lessonId\n";
            file_put_contents($logFile, $logData, FILE_APPEND);
        }
    } catch (Exception $e) {
        $logData = date('Y-m-d H:i:s') . " - TMIS End Exception: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
    }
}
*/

// ============================================================
// Pre-saved chunk faylını yoxla (periodic flush zamanı yazılıb)
// ============================================================
$hasChunks = isset($_POST['has_chunks']) && $_POST['has_chunks'] == '1';
$chunkFile = '../../uploads/live_recordings/lesson_' . $lessonId . '.webm';
$chunkFileExists = file_exists($chunkFile) && filesize($chunkFile) > 0;

$logData = date('Y-m-d H:i:s') . " - has_chunks=$hasChunks, chunkFileExists=$chunkFileExists" .
    ($chunkFileExists ? " (size: " . filesize($chunkFile) . " bytes)" : "") . "\n";
file_put_contents($logFile, $logData, FILE_APPEND);

if ($noVideo || !$videoFile || $videoFile['error'] !== UPLOAD_ERR_OK) {
    if ($chunkFileExists) {
        // Pre-saved parçalar var, onları final video kimi istifadə et
        $logData = date('Y-m-d H:i:s') . " - No final video blob, using pre-saved chunks as recording.\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
    } else {
        // Heç bir video yoxdur — sadəcə dərsi bitir
        $db->query("UPDATE live_classes SET status = 'pending_approval', end_time = NOW(), duration_minutes = ? WHERE id = ?", [$msgDuration, $lessonId]);
        $db->query("UPDATE schedule SET status = 'completed' WHERE live_class_id = ?", [$lessonId]);

        $logData = date('Y-m-d H:i:s') . " - Success: Lesson " . $lessonId . " ended without video.\n";
        file_put_contents($logFile, $logData, FILE_APPEND);

        jsonResponse([
            'success' => true,
            'message' => 'Dərs uğurla bitirildi (Video kadr mövcud olmadığından arxivlənmədi)'
        ]);
    }
}

// Qovluq yoxdursa yarat
$uploadDir = '../../uploads/videos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0750, true);
}

// Fayl adı
$fileName = 'lesson_' . $lessonId . '_' . time() . '.webm';
$targetPath = $uploadDir . $fileName;

// ============================================================
// Chunk faylı + final blob birləşdir
// ============================================================
$fileSaved = false;

if ($chunkFileExists) {
    // Pre-saved chunk faylını final target-ə köçür
    copy($chunkFile, $targetPath);
    $logData = date('Y-m-d H:i:s') . " - Chunk file copied to target: " . filesize($targetPath) . " bytes\n";
    file_put_contents($logFile, $logData, FILE_APPEND);

    // Final video blob varsa, onu da əlavə et (append)
    if ($videoFile && $videoFile['error'] === UPLOAD_ERR_OK) {
        $finalData = file_get_contents($videoFile['tmp_name']);
        
        // Final blob-un da WebM header-ini silib yalnız Cluster-ləri append et (əgər chunk file artıq varsa)
        // Bu, pleyerlərin videonun sonuna qədər oxuya bilməsini təmin edir.
        $clusterPos = strpos($finalData, "\x1F\x43\xB6\x75");
        if ($clusterPos !== false) {
            $finalData = substr($finalData, $clusterPos);
        }

        file_put_contents($targetPath, $finalData, FILE_APPEND);
        $logData = date('Y-m-d H:i:s') . " - Final blob (stripped) appended: " . strlen($finalData) . " bytes. Total: " . filesize($targetPath) . " bytes\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
    }

    // Chunk faylını təmizlə
    @unlink($chunkFile);
    $fileSaved = true;
} elseif ($videoFile && $videoFile['error'] === UPLOAD_ERR_OK) {
    // Chunk faylı yoxdur, sadəcə uploaded blob-u istifadə et (əvvəlki davranış)
    $fileSaved = move_uploaded_file($videoFile['tmp_name'], $targetPath);
}

if ($fileSaved) {
    // Video linki
    $videoLink = '../../uploads/videos/' . $fileName;

    // Patch WebM Duration to enable seeking in the browser player
    $exactDurationMs = (float) ($_POST['duration_ms'] ?? 0);
    
    if ($exactDurationMs <= 0) {
        $exactDurationMs = 90 * 60 * 1000; // fallback
        if ($lesson && isset($lesson['start_time']) && $lesson['start_time']) {
            $startT = strtotime($lesson['start_time']);
            $endT = time();
            if ($endT > $startT) {
                $exactDurationMs = ($endT - $startT) * 1000;
            }
        }
    }
    patchWebmDuration($targetPath, $exactDurationMs);

    try {
        $db->query("UPDATE live_classes SET recording_path = ?, status = 'pending_approval', end_time = NOW(), duration_minutes = ? WHERE id = ?", [$fileName, $msgDuration, $lessonId]);
        $db->query("UPDATE schedule SET status = 'completed' WHERE live_class_id = ?", [$lessonId]);

        $logData = date('Y-m-d H:i:s') . " - Local Success: Lesson " . $lessonId . " saved and DB updated. Duration patched ($exactDurationMs ms). Proceeding to background TMIS upload.\n";
        file_put_contents($logFile, $logData, FILE_APPEND);

        // Send early success response to user
        if (function_exists('fastcgi_finish_request')) {
            echo json_encode([
                'success' => true,
                'message' => 'Video uğurla yükləndi. Arxivləmə arxa fonda davam edir.',
                'path' => $videoLink
            ]);
            fastcgi_finish_request();
        } else {
            // Optional fallback for non-FastCGI: obs_start() + headers if we really want, 
            // but usually FastCGI is used with Laragon/Nginx.
        }

        // ==========================================================
        // TMİS-ə həmçinin Arxiv Materialı kimi yüklə — DISABLED (local only)
        // ==========================================================
        /*
        if ($tmisToken && $courseId > 0) {
            $uploadFilePath = realpath($targetPath);
            if ($uploadFilePath && file_exists($uploadFilePath)) {
                $tmisArchiveResult = TmisApi::uploadArchive($tmisToken, [
                    'subject_id' => (int) $courseId,
                    'title' => $title,
                    'type' => 'video',
                    'duration_minutes' => $msgDuration
                ], $uploadFilePath);

                if (!$tmisArchiveResult['success']) {
                    $logData = date('Y-m-d H:i:s') . " - TMIS Archive Upload Error: " . ($tmisArchiveResult['message'] ?? 'Unknown') . "\n";
                    file_put_contents($logFile, $logData, FILE_APPEND);
                } else {
                    $logData = date('Y-m-d H:i:s') . " - TMIS Arxivə uğurla əlavə edildi.\n";
                    file_put_contents($logFile, $logData, FILE_APPEND);
                }
            }
        }
        */

        // Final response logic if fastcgi_finish_request was not used
        if (!function_exists('fastcgi_finish_request')) {
            jsonResponse([
                'success' => true,
                'message' => 'Video uğurla yükləndi və arxivləndi',
                'path' => $videoLink
            ]);
        }
    } catch (Exception $e) {
        $logData = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
        jsonResponse(['success' => false, 'message' => 'Xəta: ' . $e->getMessage()]);
    }
} else {
    $logData = date('Y-m-d H:i:s') . " - Error: Failed to save final recording. Path: " . $targetPath . "\n";
    file_put_contents($logFile, $logData, FILE_APPEND);
    jsonResponse(['success' => false, 'message' => 'Faylı server diskində saxlamaq mümkün olmadı']);
}
