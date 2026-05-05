<?php

/**
 * LiveKit Egress Management Service
 * Handles starting and stopping server-side recordings.
 */
require_once __DIR__ . '/../teacher/config/database.php';
require_once __DIR__ . '/livekit_helper.php';

class LiveKitEgressService
{
    private $apiKey;
    private $apiSecret;
    private $apiHost;
    private $verifySSL;
    private $requestTimeout;
    private $connectTimeout;

    public function __construct()
    {
        $this->apiKey = $_ENV['LIVEKIT_API_KEY'] ?? $_SERVER['LIVEKIT_API_KEY'] ?? getenv('LIVEKIT_API_KEY');
        $this->apiSecret = $_ENV['LIVEKIT_API_SECRET'] ?? $_SERVER['LIVEKIT_API_SECRET'] ?? getenv('LIVEKIT_API_SECRET');
        $this->apiHost = $_ENV['LIVEKIT_HOST'] ?? $_SERVER['LIVEKIT_HOST'] ?? getenv('LIVEKIT_HOST') ?? 'https://distant-l.ndu.edu.az';

        // Remove wss:// or ws:// for API calls
        $this->apiHost = str_replace(['wss://', 'ws://'], ['https://', 'http://'], $this->apiHost);

        // SSL verification (can be disabled for self-signed certs via .env)
        $verifySslStr = getenv('LIVEKIT_VERIFY_SSL');
        $this->verifySSL = $verifySslStr === 'false' ? false : true;

        // Timeouts (in seconds)
        $this->connectTimeout = (int)(getenv('LIVEKIT_CONNECT_TIMEOUT') ?: 10);
        $this->requestTimeout = (int)(getenv('LIVEKIT_REQUEST_TIMEOUT') ?: 30);

        // Validate credentials
        if (
            empty($this->apiKey) || $this->apiKey === 'your_api_key_here' ||
            empty($this->apiSecret) || $this->apiSecret === 'your_api_secret_here'
        ) {
            error_log("WARNING: LiveKit credentials not properly configured. Check .env file.");
            error_log("Expected: LIVEKIT_API_KEY and LIVEKIT_API_SECRET environment variables");
        }
    }

    private function generateRecorderSecret($lessonId)
    {
        $salt = getenv('EGRESS_SECRET_SALT') ?: 'change-this-in-production';
        return hash_hmac('sha256', "lesson_{$lessonId}", $salt);
    }

    public function startRecording($lessonId, $roomName)
    {
        $db = Database::getInstance();

        // Base URL for the recording view
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        // Use a fixed public host if defined (recommended for Egress)
        $publicHost = getenv('PUBLIC_BASE_URL') ?: "$protocol://$host/distant-tehsil-test";

        $secret = $this->generateRecorderSecret($lessonId);
        $templateUrl = "$publicHost/teacher/live-record_view.php?id=$lessonId&secret=$secret";

        // 1. Generate Admin Token with Egress permissions
        $token = LiveKitHelper::generateToken(
            $this->apiKey,
            $this->apiSecret,
            'admin_recorder',
            'Admin',
            (string)$roomName,   // ✅ pass the room
            false,
            false,
            ['roomRecord' => true]
        );

        // 2. Prepare Egress Request (RoomComposite)
        $data = [
            'room_name' => (string)$roomName,
            'layout' => 'custom',
            'custom_base_url' => $templateUrl,
            'file' => [
                'filepath' => "/recordings/lesson_{$lessonId}_" . time() . ".mp4",
                'disable_manifest' => true
            ]
        ];

        // 3. Call LiveKit Egress API
        $url = rtrim($this->apiHost, '/') . '/twirp/livekit.Egress/StartRoomCompositeEgress';

        error_log("[LiveKit Egress] Starting recording for lesson $lessonId in room '$roomName'");
        error_log("[LiveKit Egress] API URL: $url");
        error_log("[LiveKit Egress] SSL Verify: " . ($this->verifySSL ? 'enabled' : 'DISABLED'));
        error_log("[LiveKit Egress] Timeouts - Connect: {$this->connectTimeout}s, Request: {$this->requestTimeout}s");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $transferTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        // Log the API call for debugging
        error_log("[LiveKit Egress] API Response: HTTP $httpCode (took {$transferTime}s)");
        if ($curlError) {
            error_log("[LiveKit Egress] cURL Error: $curlError");
        }
        if (strlen($response) > 500) {
            error_log("[LiveKit Egress] Response: " . substr($response, 0, 500) . "...");
        } else {
            error_log("[LiveKit Egress] Response: $response");
        }

        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result['egress_id'])) {
            // Save Egress ID to database to stop it later
            $db->query("UPDATE live_classes SET egress_id = ? WHERE id = ?", [$result['egress_id'], $lessonId]);
            return ['success' => true, 'egress_id' => $result['egress_id']];
        }

        // Return detailed error information
        $errorMsg = "LiveKit API Error (HTTP $httpCode): ";
        if ($curlError) {
            $errorMsg .= "Connection failed - $curlError. ";
            if (strpos($curlError, 'timeout') !== false) {
                $errorMsg .= "Increase LIVEKIT_REQUEST_TIMEOUT in .env if connection is slow.";
            }
            if (!$this->verifySSL) {
                $errorMsg .= " (SSL verification disabled)";
            }
        } elseif ($httpCode === 503) {
            $errorMsg .= "Egress service unavailable (HTTP 503). The livekit-egress Docker container is likely not running or not connected to the LiveKit server. Run: docker ps --filter name=egress";
        } elseif ($httpCode === 401) {
            $errorMsg .= "Authentication failed. Invalid LIVEKIT_API_KEY or LIVEKIT_API_SECRET";
        } elseif ($httpCode === 0) {
            $errorMsg .= "No HTTP response. Check if LiveKit API is reachable at: $url";
        } else {
            $errorMsg .= $response;
        }

        error_log("[LiveKit Egress] ERROR: $errorMsg");
        return ['success' => false, 'error' => $errorMsg, 'code' => $httpCode];
    }

    public function stopRecording($lessonId)
    {
        $db = Database::getInstance();
        $lesson = $db->fetch("SELECT egress_id FROM live_classes WHERE id = ?", [$lessonId]);

        if (!$lesson || empty($lesson['egress_id'])) {
            return ['success' => false, 'message' => 'Egress ID tapılmadı.'];
        }

        $egressId = $lesson['egress_id'];

        $token = LiveKitHelper::generateToken(
            $this->apiKey,
            $this->apiSecret,
            'admin_recorder',
            'Admin',
            '',
            false,
            false,
            ['roomRecord' => true]
        );

        $data = ['egress_id' => $egressId];
        $url = rtrim($this->apiHost, '/') . '/twirp/livekit.Egress/StopEgress';

        error_log("[LiveKit Egress] Stopping recording for lesson $lessonId (egress_id: $egressId)");
        error_log("[LiveKit Egress] API URL: $url");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("[LiveKit Egress] Stop Response: HTTP $httpCode");
        if ($curlError) {
            error_log("[LiveKit Egress] cURL Error: $curlError");
        }

        if ($httpCode === 200) {
            $db->query("UPDATE live_classes SET egress_id = NULL WHERE id = ?", [$lessonId]);
            error_log("[LiveKit Egress] Recording stopped successfully for lesson $lessonId");
            return ['success' => true];
        }

        $errorMsg = "Failed to stop recording: HTTP $httpCode - $response";
        error_log("[LiveKit Egress] ERROR: $errorMsg");
        return ['success' => false, 'error' => $errorMsg];
    }

    public function checkStatus($egressId)
    {
        $token = LiveKitHelper::generateToken(
            $this->apiKey,
            $this->apiSecret,
            'admin_recorder',
            'Admin',
            '',
            false,
            false,
            ['roomRecord' => true]
        );

        $url = rtrim($this->apiHost, '/') . '/twirp/livekit.Egress/ListEgress';
        $payload = json_encode(['egress_id' => $egressId]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['active' => false, 'error' => "API returned HTTP $httpCode"];
        }

        $result = json_decode($response, true);

        if (!empty($result['items'])) {
            $egress = $result['items'][0];
            $activeStatuses = ['EGRESS_STARTING', 'EGRESS_ACTIVE'];
            return [
                'active' => in_array($egress['status'] ?? '', $activeStatuses),
                'status' => $egress['status'] ?? 'unknown',
                'error'  => $egress['error'] ?? null
            ];
        }

        return ['active' => false, 'error' => 'Egress not found'];
    }
}
