<?php
/**
 * Ümumi köməkçi funksiyalar
 */

/**
 * Tarix formatlaşdırma
 */
function formatDate($date, $format = 'd F Y')
{
    $months_az = [
        'January' => 'Yanvar',
        'February' => 'Fevral',
        'March' => 'Mart',
        'April' => 'Aprel',
        'May' => 'May',
        'June' => 'İyun',
        'July' => 'İyul',
        'August' => 'Avqust',
        'September' => 'Sentyabr',
        'October' => 'Oktyabr',
        'November' => 'Noyabr',
        'December' => 'Dekabr'
    ];

    $formatted = date($format, strtotime($date));
    return str_replace(array_keys($months_az), array_values($months_az), $formatted);
}

/**
 * Vaxt fərqi hesabla
 */
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'İndicə';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' dəqiqə əvvəl';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' saat əvvəl';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' gün əvvəl';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Gün qaldığını hesabla
 */
function daysRemaining($dueDate)
{
    $due = strtotime($dueDate);
    $now = time();
    $diff = floor(($due - $now) / 86400);
    return $diff;
}

/**
 * Prosent hesabla
 */
function calculatePercentage($completed, $total)
{
    if ($total == 0)
        return 0;
    return round(($completed / $total) * 100, 1);
}

/**
 * HTML escape
 */
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF token yarad
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token yoxla
 */
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Status rəngini al
 */
function getStatusColor($status)
{
    $colors = [
        'active' => 'green',
        'inactive' => 'gray',
        'completed' => 'purple',
        'pending' => 'yellow',
        'submitted' => 'blue',
        'graded' => 'green',
        'overdue' => 'red',
        'live' => 'red',
        'starting-soon' => 'blue',
        'ending-soon' => 'yellow'
    ];

    return $colors[$status] ?? 'gray';
}

/**
 * Status mətnini al
 */
function getStatusText($status)
{
    $texts = [
        'active' => 'Aktiv',
        'inactive' => 'Qeyri-aktiv',
        'completed' => 'Tamamlandı',
        'pending' => 'Gözləyir',
        'submitted' => 'Təqdim edilib',
        'graded' => 'Qiymətləndirilib',
        'overdue' => 'Gecikmiş',
        'live' => 'Canlı',
        'starting-soon' => 'Tezliklə başlayır',
        'ending-soon' => 'Tezliklə bitir'
    ];

    return $texts[$status] ?? $status;
}

/**
 * Həftənin günlərini al
 */
function getDaysOfWeek()
{
    return [
        'Bazar ertəsi',
        'Çərşənbə axşamı',
        'Çərşənbə',
        'Cümə axşamı',
        'Cümə',
        'Şənbə',
        'Bazar'
    ];
}

/**
 * Ayları al
 */
function getMonths()
{
    return [
        'Yanvar',
        'Fevral',
        'Mart',
        'Aprel',
        'May',
        'İyun',
        'İyul',
        'Avqust',
        'Sentyabr',
        'Oktyabr',
        'Noyabr',
        'Dekabr'
    ];
}
/**
 * Mətni müəyyən uzunluğa qədər qısalt
 */
function truncate($text, $length = 80)
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . '...';
}

/**
 * JSON Response helper
 */
function jsonResponse($data, $statusCode = 200)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
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
