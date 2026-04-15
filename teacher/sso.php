<?php

/**
 * Distant Təhsil - Müəllim SSO Handle
 */
require_once 'includes/auth.php';

$auth = new Auth();

$ssoToken = $_GET['token'] ?? $_GET['sso_token'] ?? '';

// No SSO token — normal session check
if (empty($ssoToken)) {
    if ($auth->isLoggedIn()) {
        header('Location: ./');
        exit;
    }
    header('Location: login.php');
    exit;
}

// SSO token present — always process it fresh.
// Log out any existing session so stale data cannot cause a redirect loop.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}
session_name('DISTANT_T_SESSION_V4');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
session_start();
session_regenerate_id(true);

$apiSecret = getenv('SSO_API_SECRET');
if (!$apiSecret) {
    error_log("SSO xətası: SSO_API_SECRET tapılmadı.");
    header('Location: login.php?error=sso_config');
    exit;
}

// TMIS API-yə müraciət edirik
$ch = curl_init();
$tmisUrl = rtrim(getenv('TMIS_URL') ?: 'https://tmis.ndu.edu.az', '/');
$url = $tmisUrl . "/api/sso/verify?token=" . urlencode($ssoToken);

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-SSO-Secret: " . $apiSecret,
        "Accept: application/json"
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("SSO API cURL Xətası: " . $curlError);
    header('Location: login.php?error=sso_server');
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData) && (isset($responseData['success']) ? $responseData['success'] : true)) {
    // Təsdiqlənmiş məlumat
    $profileData = $responseData['user'] ?? $responseData['data'] ?? $responseData;

    // Auth sinifində yaratdığımız SSO logging metodunu çağırırıq
    $result = $auth->loginViaSso($profileData);

    if ($result['success']) {
        // Clear SSO loop counter on successful login
        unset($_SESSION['sso_attempts'], $_SESSION['sso_last_attempt']);
        // Dashboard-a yönləndir
        header('Location: ./');
        exit;
    } else {
        error_log("SSO Local Auth Xətası: " . $result['message']);
        header('Location: login.php?error=sso_local&msg=' . urlencode($result['message']));
        exit;
    }
} else if ($httpCode === 401 || $httpCode === 422) {
    $errorMsg = $responseData['message'] ?? 'Etibarsız və ya istifadə müddəti bitmiş SSO tokeni.';
    error_log("SSO API Xətası ({$httpCode}): " . $errorMsg);
    header('Location: login.php?error=sso_invalid');
    exit;
} else {
    // Digər HTTP xətaları (500 vb.)
    error_log("SSO API Məlum Olmayan Xəta: HTTP {$httpCode} - " . $response);
    header('Location: login.php?error=sso_error');
    exit;
}
