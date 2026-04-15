<?php

/**
 * Distant Təhsil - Müəllim Autentifikasiya Sistemi
 */

session_name('DISTANT_T_SESSION_V4');
$isSecure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $isSecure = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $isSecure = true;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
date_default_timezone_set('Asia/Baku');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tmis_api.php';

class Auth
{
    public function __construct() {}

    public function loginViaTmis(string $username, string $password): array
    {
        $tmisResult = TmisApi::loginTeacher($username, $password);

        if (!$tmisResult['success']) {
            return ['success' => false, 'message' => $tmisResult['message']];
        }

        $tmisData = $tmisResult['data'];

        // TMİS profil məlumatlarını al (əgər login cavabında yoxdursa)
        $profileData = [];
        if (isset($tmisData['user'])) {
            $profileData = $tmisData;
        } else {
            $profileResult = TmisApi::me($tmisData['access_token']);
            $profileData = $profileResult['success'] ? $profileResult['data'] : [];
        }

        // Məlumatları çıxar (Ssenari 2 API strukturuna uyğun)
        $p = $profileData['user'] ?? $profileData;


        $fullName = $p['name'] ?? ($p['fullname'] ?? '');
        $email = $p['email'] ?? ($username . '@ndu.edu.az');

        // Ad və soyadı ayır (heuristic)
        $nameParts = explode(' ', $fullName);
        $firstName = $p['first_name'] ?? ($nameParts[1] ?? ($nameParts[0] ?? ''));
        $lastName = $p['last_name'] ?? ($nameParts[0] ?? '');

        $userData = [
            'tmis_id' => $tmisData['id'] ?? ($profileData['id'] ?? 0),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => 'instructor',
            'faculty' => $p['faculty'] ?? ($p['faculty_name'] ?? ''),
            'department' => $p['department'] ?? '',
            'specialty' => $p['specialty'] ?? ($p['profession_name'] ?? ''),
            'group' => $p['group'] ?? ($p['class_name'] ?? ''),
            'avatar_url' => $p['avatar_url'] ?? ($p['avatar'] ?? ''),
            'academic_title' => $p['academic_title'] ?? 'Müəllim'
        ];

        // Lokal baza ilə sinxronizasiya et
        $localUserId = $this->syncUserWithDb($userData);

        if ($localUserId) {
            $userData['id'] = $localUserId;
        } else {
            $userData['id'] = $tmisData['id'] ?? (time() % 100000);
        }

        $this->createSession($userData, $tmisData, $username, $password);

        return ['success' => true, 'user' => $userData];
    }

    public function loginLocal(string $email, string $password): array
    {
        try {
            $db = Database::getInstance();
            $user = $db->fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);

            if (!$user) {
                return ['success' => false, 'message' => 'İstifadəçi tapılmadı.'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Şifrə yalnışdır.'];
            }

            // Create local session (no TMIS data)
            $this->createSession($user, null, $email, $password);

            return ['success' => true, 'user' => $user];
        } catch (\Exception $e) {
            error_log('Local Login xətası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Giriş zamanı sistem xətası baş verdi.'];
        }
    }

    public function loginViaSso(array $profileData): array
    {
        // Yüklənmiş JSON məlumatlarını sistemin obyekt strukturuna uyğunlaşdırırıq
        // "identifier", "first_name", "last_name", "fullname", "email" vb.

        try {
            $fullName = $profileData['fullname'] ?? ($profileData['name'] ?? '');
            $email = $profileData['ndu_mail'] ?? $profileData['email'] ?? '';

            // Ad və soyad tam olaraq gəlməyibsə, onu tam addan ayırırıq
            $nameParts = explode(' ', $fullName);
            $firstName = $profileData['first_name'] ?? ($nameParts[1] ?? ($nameParts[0] ?? ''));
            $lastName = $profileData['last_name'] ?? ($nameParts[0] ?? '');

            if (empty($email)) {
                // Əgər email yoxdursa, placeholder kimi identifier istifadə edək, və ya error ataq
                return ['success' => false, 'message' => 'SSO profili natamamdır: Email tapılmadı.'];
            }

            $userData = [
                'tmis_id' => $profileData['identifier'] ?? ($profileData['id'] ?? 0),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'role' => 'instructor',
                'faculty' => $profileData['faculty'] ?? ($profileData['faculty_name'] ?? ''),
                'department' => $profileData['department'] ?? '',
                'specialty' => $profileData['specialty'] ?? ($profileData['profession_name'] ?? ''),
                'group' => $profileData['group'] ?? ($profileData['class_name'] ?? ''),
                'avatar_url' => $profileData['avatar_url'] ?? ($profileData['avatar'] ?? ''),
                'academic_title' => $profileData['academic_title'] ?? 'Müəllim'
            ];

            // Lokal baza ilə sinxronizasiya et
            $localUserId = $this->syncUserWithDb($userData);

            if ($localUserId) {
                $userData['id'] = $localUserId;
            } else {
                $userData['id'] = $profileData['identifier'] ?? (time() % 100000);
            }

            $this->createSession($userData, [
                'id'           => $userData['tmis_id'],
                'access_token' => $profileData['access_token'] ?? '',
                'expires_in'   => max($profileData['expires_in'] ?? 43200, 43200),
            ], null, null);

            return ['success' => true, 'user' => $userData];
        } catch (\Exception $e) {
            error_log('SSO Login xətası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SSO girişində sistem xətası baş verdi.'];
        }
    }

    /**
     * Bridge token vasitəsilə giriş (bridge.php tərəfindən çağırılır).
     * Token TMİS-in /api/sso/verify endpoint-i ilə doğrulanır.
     */
    public function loginViaBridge(string $token): array
    {
        $apiSecret = getenv('SSO_API_SECRET');
        if (!$apiSecret) {
            error_log('Bridge xətası: SSO_API_SECRET tapılmadı.');
            return ['success' => false, 'message' => 'SSO konfiqurasiya xətası.'];
        }

        $tmisUrl = rtrim(getenv('TMIS_URL') ?: 'https://tmis.ndu.edu.az', '/');
        $url = $tmisUrl . '/api/sso/verify?token=' . urlencode($token);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-SSO-Secret: ' . $apiSecret,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('Bridge cURL xətası: ' . $curlError);
            return ['success' => false, 'message' => 'Server əlaqə xətası.'];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && is_array($data) && !isset($data['error'])) {
            $profileData = $data['user'] ?? $data['data'] ?? $data;
            return $this->loginViaSso($profileData);
        }

        $errMsg = $data['error'] ?? $data['message'] ?? 'Etibarsız bridge tokeni.';
        error_log("Bridge API xətası ({$httpCode}): {$errMsg}");
        return ['success' => false, 'message' => $errMsg];
    }

    /**
     * TMİS-dən gələn məlumatları lokal baza ilə sinxronizasiya edir
     */
    private function syncUserWithDb(array $data): ?int
    {
        try {
            $db = Database::getInstance();
            error_log('Auth Sync: Sinxronizasiya başlayır: ' . $data['email']);

            // 1. Users cədvəli
            $existingUser = $db->fetch("SELECT id FROM users WHERE email = ?", [$data['email']]);

            $userFields = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'role' => 'instructor',
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (!empty($data['avatar_url'])) {
                $userFields['avatar'] = $data['avatar_url'];
            }

            if ($existingUser) {
                error_log('Auth Sync: Mövcud istifadəçi tapıldı (ID: ' . $existingUser['id'] . '). Yenilənir...');
                // Database class-ındakı update metodunda mixing parameter problemi ola bilər, direct query istifadə edək
                $db->query(
                    "UPDATE users SET first_name = ?, last_name = ?, role = ?, is_active = ?, updated_at = ? WHERE id = ?",
                    [$userFields['first_name'], $userFields['last_name'], $userFields['role'], $userFields['is_active'], $userFields['updated_at'], $existingUser['id']]
                );
                $localUserId = $existingUser['id'];
            } else {
                error_log('Auth Sync: Yeni istifadəçi yaradılır...');
                $userFields['created_at'] = date('Y-m-d H:i:s');
                $userFields['password'] = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                // Bəzi bazalarda student_id vacib ola bilər (Null deyilse)
                $userFields['student_id'] = 'INS-' . ($data['tmis_id'] ?? time());

                $localUserId = $db->insert('users', $userFields);
                error_log('Auth Sync: Yeni istifadəçi yaradıldı (ID: ' . $localUserId . ')');
            }

            if (!$localUserId) {
                error_log('Auth Sync: XƏTA: İstifadəçi ID-si alınmadı.');
                return null;
            }

            // 2. Instructors cədvəli
            $existingInstructor = $db->fetch(
                "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
                [$localUserId, $data['email']]
            );

            $instructorFields = [
                'user_id' => $localUserId,
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'department' => $data['department'] ?? '',
                'title' => $data['academic_title'] ?? 'Müəllim',
                'faculty' => $data['faculty'] ?? '',
                'specialty' => $data['profession_name'] ?? $data['specialty'] ?? '',
                'academic_title' => $data['academic_title'] ?? 'Müəllim',
                'course_level' => $data['course_level'] ?? '-'
            ];

            if ($existingInstructor) {
                error_log('Auth Sync: Mövcud müəllim qeydi tapıldı (ID: ' . $existingInstructor['id'] . '). Yenilənir...');
                $db->query(
                    "UPDATE instructors SET name = ?, email = ?, department = ?, title = ?, faculty = ?, specialty = ?, academic_title = ?, course_level = ? WHERE id = ?",
                    [
                        $instructorFields['name'],
                        $instructorFields['email'],
                        $instructorFields['department'],
                        $instructorFields['title'],
                        $instructorFields['faculty'],
                        $instructorFields['specialty'],
                        $instructorFields['academic_title'],
                        $instructorFields['course_level'],
                        $existingInstructor['id']
                    ]
                );
            } else {
                error_log('Auth Sync: Yeni müəllim qeydi yaradılır...');
                $db->insert('instructors', $instructorFields);
                error_log('Auth Sync: Yeni müəllim qeydi yaradıldı.');
            }

            error_log('Auth Sync: Sinxronizasiya uğurla başa çatdı.');
            return (int)$localUserId;
        } catch (\Exception $e) {
            error_log('Auth Sync Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn())
            return null;

        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'email' => $_SESSION['user_email'] ?? '',
            'first_name' => explode(' ', $_SESSION['user_name'] ?? '')[0] ?? '',
            'last_name' => explode(' ', $_SESSION['user_name'] ?? '')[1] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'instructor',
            'faculty' => $_SESSION['teacher_faculty'] ?? '',
            'department' => $_SESSION['teacher_department'] ?? '',
            'specialty' => $_SESSION['teacher_specialty'] ?? '',
            'group' => $_SESSION['teacher_group'] ?? '',
            'avatar_url' => $_SESSION['teacher_avatar_url'] ?? '',
            'academic_title' => $_SESSION['user_academic_title'] ?? 'Müəllim',
            'course_level' => $_SESSION['teacher_course_level'] ?? '-',
        ];
    }

    private function createSession(array $user, ?array $tmisData = null, ?string $username = null, ?string $password = null): void
    {
        // If session was destroyed (e.g. by logout() inside isLoggedIn()),
        // start a new one so the data is actually persisted after redirect.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            session_name('DISTANT_T_SESSION_V4');
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['user_role'] = $user['role'] ?? 'instructor';

        $_SESSION['teacher_faculty'] = $user['faculty'] ?? '';
        $_SESSION['teacher_department'] = $user['department'] ?? '';
        $_SESSION['teacher_specialty'] = $user['specialty'] ?? '';
        $_SESSION['teacher_group'] = $user['group'] ?? '';
        $_SESSION['teacher_avatar_url'] = $user['avatar_url'] ?? '';
        $_SESSION['user_academic_title'] = $user['academic_title'] ?? 'Müəllim';
        $_SESSION['teacher_course_level'] = $user['course_level'] ?? '-';

        $_SESSION['logged_in'] = true;

        if ($tmisData) {
            $_SESSION['tmis_id'] = $tmisData['id'] ?? $user['id'];
            $_SESSION['tmis_token'] = $tmisData['access_token'] ?? '';
            $_SESSION['tmis_expires'] = time() + max($tmisData['expires_in'] ?? 43200, 43200);
        }

        if ($username)
            $_SESSION['tmis_username'] = $username;
        if ($password) {
            $appKey = getenv('APP_KEY') ?: '';
            if (!empty($appKey)) {
                $key = hash('sha256', $appKey, true);
                $iv  = random_bytes(16);
                $enc = openssl_encrypt($password, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                $_SESSION['tmis_pwd_enc'] = base64_encode($iv . $enc);
            }
        }

        // Persistent Activity Log
        try {
            $db = Database::getInstance();
            $db->query("INSERT INTO system_logs (user_id, role, ip_address, activity_type) VALUES (?, 'instructor', ?, 'login')", [
                $user['id'] ?? $user['tmis_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
        } catch (\Exception $e) {
            // Silently fail if logging errors
        }
    }

    public function logout(): void
    {
        if (isset($_SESSION['tmis_token']))
            TmisApi::logout($_SESSION['tmis_token']);
        $_SESSION = [];
        session_destroy();
    }

    public function exitToPortal(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
            return false;
        if (isset($_SESSION['tmis_expires']) && time() > $_SESSION['tmis_expires']) {
            // Token bitib — əvvəlcə silent re-login cəhd et
            if ($this->silentReLogin()) {
                return true;
            }
            $this->logout();
            return false;
        }
        return true;
    }

    /**
     * Session-da saxlanılmış şifrələnmiş credentials ilə avtomatik yenidən giriş
     */
    private function silentReLogin(): bool
    {
        $username = $_SESSION['tmis_username'] ?? null;
        $encPwd = $_SESSION['tmis_pwd_enc'] ?? null;

        if (!$username || !$encPwd) {
            return false;
        }

        try {
            $appKey = getenv('APP_KEY') ?: '';
            if (empty($appKey)) {
                return false;
            }
            $key    = hash('sha256', $appKey, true);
            $raw    = base64_decode($encPwd);
            $iv     = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $password = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            if (!$password) {
                return false;
            }

            $tmisResult = TmisApi::loginTeacher($username, $password);
            if (!$tmisResult['success']) {
                return false;
            }

            $tmisData = $tmisResult['data'];

            // Yalnız token məlumatlarını yenilə, session-u silmə
            $_SESSION['tmis_token'] = $tmisData['access_token'] ?? '';
            $_SESSION['tmis_expires'] = time() + max($tmisData['expires_in'] ?? 43200, 43200);
            $_SESSION['tmis_id'] = $tmisData['id'] ?? $_SESSION['user_id'];

            error_log('TMİS Teacher Silent Re-Login uğurlu: ' . $username);
            return true;
        } catch (\Exception $e) {
            error_log('TMİS Teacher Silent Re-Login xətası: ' . $e->getMessage());
            return false;
        }
    }
}

function requireLogin()
{
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireInstructor()
{
    requireLogin();
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'instructor' && $_SESSION['user_role'] !== 'admin') {
        (new Auth())->logout();
        header('Location: login.php?error=access_denied');
        exit;
    }
}

function requireAdmin()
{
    requireLogin();
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: login.php?error=access_denied');
        exit;
    }
}
