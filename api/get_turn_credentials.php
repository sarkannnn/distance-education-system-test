<?php

/**
 * Get TURN Server Credentials
 * 
 * Fetches temporary TURN credentials from Metered.ca API.
 * These credentials are needed for WebRTC connections across
 * different networks (mobile/LTE, different ISPs).
 * 
 * Returns JSON with ICE servers configuration.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Authentication check — must be a logged-in student or instructor
$authenticated = false;
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

foreach (['DISTANT_T_SESSION_V4', 'DISTANT_STUDENT_SESSION'] as $sessionName) {
    session_name($sessionName);
    @session_start();
    if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $authenticated = true;
        session_write_close();
        break;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['error' => 'Giriş tələb olunur']);
    exit;
}

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            if (!empty($key)) {
                putenv("$key=$value");
            }
        }
    }
}

$apiKey = getenv('METERED_API_KEY');
$appDomain = getenv('METERED_DOMAIN') ?: 'ndu.metered.live'; // Fallback to guess but allow override

// ─── If Metered API key is configured, fetch dynamic credentials ───
if (!empty($apiKey)) {
    // Try the specific subdomain first
    $url = "https://{$appDomain}/api/v1/turn/credentials?apiKey=" . urlencode($apiKey);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // If subdomain fails (404/DNS), try the global metered.ca endpoint as fallback
    if ($httpCode !== 200) {
        $globalUrl = "https://www.metered.ca/api/v1/turn/credentials?apiKey=" . urlencode($apiKey);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $globalUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if ($httpCode === 200 && $response) {
        $turnServers = json_decode($response, true);

        if (is_array($turnServers) && count($turnServers) > 0) {
            // Build the full ICE servers array: STUN + fetched TURN servers
            $iceServers = [
                // Google STUN servers for fast IP discovery
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302'],
            ];

            // Add all TURN servers from Metered
            foreach ($turnServers as $server) {
                $entry = ['urls' => $server['urls']];
                if (isset($server['username'])) $entry['username'] = $server['username'];
                if (isset($server['credential'])) $entry['credential'] = $server['credential'];
                $iceServers[] = $entry;
            }

            echo json_encode([
                'success' => true,
                'iceServers' => $iceServers,
                'source' => 'metered',
                'ttl' => 86400 // credentials valid for 24 hours
            ]);
            exit;
        }
    }

    // Metered API failed — log the issue and fall through to fallback
    error_log("TURN credential fetch failed: HTTP $httpCode, Error: $curlError");
}

// ─── Fallback: Local TURN + STUN servers ───
$turnUsername = getenv('TURN_USERNAME') ?: 'livekit';
$turnPassword = getenv('TURN_PASSWORD') ?: 'yourpassword123';
$turnServer = getenv('TURN_SERVER') ?: 'distant-l-turn.ndu.edu.az';

echo json_encode([
    'success' => true,
    'iceServers' => [
        // Local TURN server (TCP + UDP)
        [
            'urls' => [
                "turn:{$turnServer}:3478?transport=udp",
                "turn:{$turnServer}:3478?transport=tcp",
                "turns:{$turnServer}:5349?transport=tcp",
            ],
            'username' => $turnUsername,
            'credential' => $turnPassword
        ],
        // Google STUN servers for redundancy
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
        ['urls' => 'stun:stun2.l.google.com:19302'],
        ['urls' => 'stun:stun.voiparound.com'],
        ['urls' => 'stun:stun.voipbuster.com'],
        ['urls' => 'stun:stun.voipstunt.com'],
    ],
    'source' => 'local_turn_with_stun',
    'ttl' => 3600
]);
