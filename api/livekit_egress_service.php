<?php
/**
 * LiveKit Egress Management Service
 * Handles starting and stopping server-side recordings.
 */
require_once __DIR__ . '/../teacher/config/database.php';
require_once __DIR__ . '/livekit_helper.php';

class LiveKitEgressService {
    private $apiKey;
    private $apiSecret;
    private $apiHost;

    public function __construct() {
        $this->apiKey = $_ENV['LIVEKIT_API_KEY'] ?? $_SERVER['LIVEKIT_API_KEY'] ?? getenv('LIVEKIT_API_KEY');
        $this->apiSecret = $_ENV['LIVEKIT_API_SECRET'] ?? $_SERVER['LIVEKIT_API_SECRET'] ?? getenv('LIVEKIT_API_SECRET');
        $this->apiHost = $_ENV['LIVEKIT_HOST'] ?? $_SERVER['LIVEKIT_HOST'] ?? getenv('LIVEKIT_HOST');
        
        // Remove wss:// or ws:// for API calls
        $this->apiHost = str_replace(['wss://', 'ws://'], ['https://', 'http://'], $this->apiHost);
    }

    public function startRecording($lessonId, $roomName) {
        $db = Database::getInstance();
        
        // Base URL for the recording view
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        // Use a fixed public host if defined (recommended for Egress)
        $publicHost = getenv('PUBLIC_BASE_URL') ?: "$protocol://$host/distant-tehsil-test";
        
        $templateUrl = "$publicHost/teacher/live-record_view.php?id=$lessonId";

        // 1. Generate Admin Token with Egress permissions
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

        // 2. Prepare Egress Request (RoomComposite)
        $data = [
            'room_name' => (string)$roomName,
            'layout' => 'custom',
            'custom_base_url' => $templateUrl,
            'file' => [
                'filepath' => "recordings/lesson_{$lessonId}_" . time() . ".mp4",
                'disable_manifest' => true
            ]
        ];

        // 3. Call LiveKit Egress API
        $url = rtrim($this->apiHost, '/') . '/twirp/livekit.Egress/StartRoomCompositeEgress';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['egress_id'])) {
            // Save Egress ID to database to stop it later
            $db->query("UPDATE live_classes SET egress_id = ? WHERE id = ?", [$result['egress_id'], $lessonId]);
            return ['success' => true, 'egress_id' => $result['egress_id']];
        }

        return ['success' => false, 'error' => $response, 'code' => $httpCode];
    }

    public function stopRecording($lessonId) {
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

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $db->query("UPDATE live_classes SET egress_id = NULL WHERE id = ?", [$lessonId]);
            return ['success' => true];
        }

        return ['success' => false, 'error' => $response];
    }
}
