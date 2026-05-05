<?php

/**
 * Simple JWT Helper for LiveKit Token Generation
 * Without external dependencies (Standalone)
 */

class LiveKitHelper
{
    public static function generateToken($apiKey, $apiSecret, $participantIdentity, $participantName, $roomName, $canPublish = true, $canSubscribe = true, $customPermissions = [])
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        $videoPermissions = [
            'room' => (string)$roomName,
            'roomJoin' => ($roomName !== ''),
            'canPublish' => (bool)$canPublish,
            'canSubscribe' => (bool)$canSubscribe,
            'canPublishData' => true,
            'canUpdateMetadata' => true,
        ];

        // Merge custom permissions (e.g., egress: true)
        $payloadData = [
            'iss' => $apiKey,
            'sub' => $participantIdentity,
            'name' => $participantName,
            'jti' => $participantIdentity . '-' . time(),
            'nbf' => time() - 30,
            'exp' => time() + 14400,
            // 'video' => array_merge($videoPermissions, $customPermissions)
        ];

        // To this — only merge video-related keys into video block:
        $videoKeys = ['room', 'roomJoin', 'canPublish', 'canSubscribe', 'canPublishData', 'canUpdateMetadata'];
        $videoOnly = array_intersect_key($customPermissions, array_flip($videoKeys));
        $rootOnly  = array_diff_key($customPermissions, array_flip($videoKeys));

        $payloadData['video'] = array_merge($videoPermissions, $videoOnly);
        foreach ($rootOnly as $k => $v) {
            $payloadData[$k] = $v;
        }

        // Add root level custom permissions if any
        // foreach ($customPermissions as $k => $v) {
        //     if (!in_array($k, ['room', 'roomJoin', 'canPublish', 'canSubscribe'])) {
        //         $payloadData[$k] = $v;
        //     }
        // }

        $payload = json_encode($payloadData);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $apiSecret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private static function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
