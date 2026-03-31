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

// ─── Fallback: STUN-only config (works on LAN, fails on mobile) ───
echo json_encode([
    'success' => true,
    'iceServers' => [
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
        ['urls' => 'stun:stun2.l.google.com:19302'],
        ['urls' => 'stun:stun3.l.google.com:19302'],
        ['urls' => 'stun:stun4.l.google.com:19302'],
    ],
    'source' => 'fallback_stun_only',
    'warning' => 'No TURN server configured. Mobile/LTE connections will fail. Set METERED_API_KEY in .env',
    'ttl' => 3600
]);
