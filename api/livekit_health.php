<?php

/**
 * LiveKit Server Health Check
 * Returns 200 + JSON when healthy, 503 when the LiveKit host is unreachable.
 */
require_once __DIR__ . '/livekit_helper.php';

header('Content-Type: application/json');

$host = getenv('LIVEKIT_HOST') ?: 'https://distant-l.ndu.edu.az';
// Normalize to HTTP for the health probe
$host = str_replace(['wss://', 'ws://'], ['https://', 'http://'], rtrim($host, '/'));

$verifySsl = getenv('LIVEKIT_VERIFY_SSL') !== 'false';
$timeout   = 5;

$ch = curl_init($host);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_CONNECTTIMEOUT => $timeout,
    CURLOPT_SSL_VERIFYPEER => $verifySsl,
    CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    CURLOPT_NOBODY         => true, // HEAD request — no body
]);

$startTime = microtime(true);
curl_exec($ch);
$latencyMs = round((microtime(true) - $startTime) * 1000);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// LiveKit returns 200 on its root endpoint when healthy
if ($httpCode >= 200 && $httpCode < 500) {
    echo json_encode([
        'status'     => 'healthy',
        'host'       => $host,
        'latency_ms' => $latencyMs,
    ]);
} else {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'host'   => $host,
        'error'  => $curlError ?: "HTTP $httpCode",
    ]);
}
