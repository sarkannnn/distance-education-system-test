<?php
/**
 * Distant Təhsil - Müəllim SSO Handle
 */
require_once 'includes/auth.php';

$auth = new Auth();

// Əgər avtomatik olaraq daxil olmuş şəxs varsa və dashboarda yönləndirmək lazımdırsa
if ($auth->isLoggedIn()) {
    header('Location: ./');
    exit;
}

$ssoToken = $_GET['token'] ?? $_GET['sso_token'] ?? '';

if (empty($ssoToken)) {
    // Token yoxdursa standart login səhifəsinə göndər
    header('Location: login.php');
    exit;
}

$apiSecret = getenv('SSO_API_SECRET');
if (!$apiSecret) {
    error_log("SSO xətası: SSO_API_SECRET tapılmadı.");
    header('Location: login.php?error=sso_config');
    exit;
}

// THMIS API-yə müraciət edirik
$ch = curl_init();
$thmisUrl = rtrim(getenv('TMIS_URL') ?: 'https://tmis.ndu.edu.az', '/');
$url = $thmisUrl . "/api/sso/verify?token=" . urlencode($ssoToken);

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-SSO-Secret: " . $apiSecret,
        "Accept: application/json"
    ],
    // Əgər serverdə SSL problemi varsa, lokal mühit üçün verify peer false edə bilərsiniz, 
    // lakin productionda bu true olmalıdır (default truedur).
    CURLOPT_SSL_VERIFYPEER => false,
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
        // Dashboard-a yönləndir
        header('Location: ./');
        exit;
    }
    else {
        error_log("SSO Local Auth Xətası: " . $result['message']);
        header('Location: login.php?error=sso_local&msg=' . urlencode($result['message']));
        exit;
    }
}
else if ($httpCode === 401 || $httpCode === 422) {
    $errorMsg = $responseData['message'] ?? 'Etibarsız və ya istifadə müddəti bitmiş SSO tokeni.';
    error_log("SSO API Xətası ({$httpCode}): " . $errorMsg);
    header('Location: login.php?error=sso_invalid');
    exit;
}
else {
    // Digər HTTP xətaları (500 vb.)
    error_log("SSO API Məlum Olmayan Xəta: HTTP {$httpCode} - " . $response);
    header('Location: login.php?error=sso_error');
    exit;
}
