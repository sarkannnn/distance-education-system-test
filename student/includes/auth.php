<?php

/**
 * Distant Təhsil - Tələbə Autentifikasiya Sistemi
 */

session_name('DISTANT_STUDENT_SESSION');
session_start();
date_default_timezone_set('Asia/Baku');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tmis_api.php';

class Auth
{
    public function __construct() {}

    public function loginViaTmis(string $username, string $password): array
    {
        $tmisResult = TmisApi::loginStudent($username, $password);

        if (!$tmisResult['success']) {
            return ['success' => false, 'message' => $tmisResult['message']];
        }

        $tmisData = $tmisResult['data'];
        $token = $tmisData['access_token'] ?? '';

        // İlk öncə /me endpointini yoxla
        $profileResult = TmisApi::me($token);
        $profileData = $profileResult['success'] ? ($profileResult['data'] ?? []) : [];

        // Response unwrapping (TMIS standard response wrapper handle)
        if (isset($profileData['success']) && $profileData['success'] && isset($profileData['data'])) {
            $profileData = $profileData['data'];
        }

        // Əgər /me natamam cavab verirsə, /student/me-dən əlavə məlumat çək
        if (empty($profileData['faculty']) || empty($profileData['specialty'])) {
            $studentProfile = TmisApi::studentProfile($token);
            if ($studentProfile['success'] && isset($studentProfile['data'])) {
                $spData = $studentProfile['data'];

                // Response unwrapping for student profile
                if (isset($spData['success']) && $spData['success'] && isset($spData['data'])) {
                    $spData = $spData['data'];
                }

                foreach (['first_name', 'last_name', 'name', 'surname', 'faculty', 'department', 'specialty', 'group', 'course_year', 'avatar_url', 'father_name'] as $field) {
                    if (empty($profileData[$field]) && !empty($spData[$field])) {
                        $profileData[$field] = $spData[$field];
                    }
                }
            }
        }

        $userId = $tmisData['id'] ?? (time() % 100000);

        $localUser = [
            'id' => $userId,
            'first_name' => $profileData['first_name'] ?? ($profileData['name'] ?? ''),
            'last_name' => $profileData['last_name'] ?? ($profileData['surname'] ?? ''),
            'father_name' => $profileData['father_name'] ?? '',
            'email' => $profileData['email'] ?? ($username . '@ndu.edu.az'),
            'role' => 'student',
            'faculty' => $profileData['faculty'] ?? '',
            'department' => $profileData['department'] ?? '',
            'specialty' => $profileData['specialty'] ?? '',
            'group' => $profileData['group'] ?? '',
            'course_year' => $profileData['course_year'] ?? '',
            'avatar_url' => $profileData['avatar_url'] ?? '',
        ];

        $this->createSession($localUser, $tmisData, $username, $password);
        $this->syncToLocalDb($localUser);

        return ['success' => true, 'user' => $localUser];
    }

    /**
     * TMİS-dən gələn məlumatları lokal users cədvəlinə sinxronizasiya edir.
     * Bu, lokal DB join-ləri üçün vacibdir.
     */
    private function syncToLocalDb(array $user): void
    {
        try {
            $db = Database::getInstance();
            $existing = $db->fetch("SELECT id FROM users WHERE id = ?", [$user['id']]);

            if ($existing) {
                $db->query(
                    "UPDATE users SET first_name = ?, last_name = ?, father_name = ?, email = ?, role = 'student', updated_at = NOW() WHERE id = ?",
                    [$user['first_name'], $user['last_name'], $user['father_name'] ?? '', $user['email'], $user['id']]
                );
            } else {
                // Şifrə hissəsi boş qalır, çünki TMİS ilə daxil olur
                $db->query(
                    "INSERT INTO users (id, first_name, last_name, father_name, email, role, is_active, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, 'student', 1, NOW(), NOW())",
                    [$user['id'], $user['first_name'], $user['last_name'], $user['father_name'] ?? '', $user['email']]
                );
            }
        } catch (Exception $e) {
            error_log("Auth Sync Error: " . $e->getMessage());
        }
    }

    /**
     * Log a student in using pre-validated profile data from the TMIS SSO API.
     * Called by sso.php after token verification.
     */
    public function loginViaSso(array $profileData): array
    {
        try {
            $email = $profileData['ndu_mail'] ?? $profileData['email'] ?? '';

            if (empty($email)) {
                return ['success' => false, 'message' => 'SSO profili natamamdır: Email tapılmadı.'];
            }

            $userId = $profileData['id'] ?? (time() % 100000);

            $localUser = [
                'id'          => $userId,
                'first_name'  => $profileData['first_name'] ?? '',
                'last_name'   => $profileData['last_name']  ?? '',
                'father_name' => $profileData['father_name'] ?? '',
                'email'       => $email,
                'role'        => 'student',
                'faculty'     => $profileData['faculty']     ?? '',
                'department'  => $profileData['department']  ?? '',
                'specialty'   => $profileData['specialty']   ?? '',
                'group'       => $profileData['group']       ?? '',
                'course_year' => $profileData['course_year'] ?? '',
                'avatar_url'  => $profileData['avatar_url']  ?? '',
            ];

            $this->createSession($localUser, [
                'id'           => $userId,
                'access_token' => $profileData['access_token'] ?? '',
                'expires_in'   => $profileData['expires_in'] ?? 3600,
            ], null, null);
            $this->syncToLocalDb($localUser);

            return ['success' => true, 'user' => $localUser];
        } catch (\Exception $e) {
            error_log('SSO Login xətası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SSO girişində sistem xətası baş verdi.'];
        }
    }

    public function getCurrentUser(): ?array
    {
        $user = $this->getUserData();
        if (!$user)
            return null;

        // Sessiya boyu 1 dəfə sinxronizasiya et
        if (!isset($_SESSION['synced_to_local'])) {
            $this->syncToLocalDb($user);
            $_SESSION['synced_to_local'] = true;
        }

        return $user;
    }

    private function getUserData(): ?array
    {
        if (!$this->isLoggedIn())
            return null;

        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'email' => $_SESSION['user_email'] ?? '',
            'first_name' => $_SESSION['user_first_name'] ?? '',
            'last_name' => $_SESSION['user_last_name'] ?? '',
            'father_name' => $_SESSION['user_father_name'] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'student',
            'faculty' => $_SESSION['student_faculty'] ?? '',
            'department' => $_SESSION['student_department'] ?? '',
            'specialty' => $_SESSION['student_specialty'] ?? '',
            'group' => $_SESSION['student_group'] ?? '',
            'course_year' => $_SESSION['student_course_year'] ?? '',
            'avatar_url' => $_SESSION['student_avatar_url'] ?? '',
        ];
    }

    private function createSession(array $user, ?array $tmisData = null, ?string $username = null, ?string $password = null): void
    {
        // If session was destroyed (e.g. by logout() inside isLoggedIn()),
        // start a new one so the data is actually persisted after redirect.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('DISTANT_STUDENT_SESSION');
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' ' . ($user['father_name'] ?? ''));
        $_SESSION['user_first_name'] = $user['first_name'] ?? '';
        $_SESSION['user_last_name'] = $user['last_name'] ?? '';
        $_SESSION['user_father_name'] = $user['father_name'] ?? '';
        $_SESSION['user_role'] = $user['role'] ?? 'student';

        $_SESSION['student_faculty'] = $user['faculty'] ?? '';
        $_SESSION['student_department'] = $user['department'] ?? '';
        $_SESSION['student_specialty'] = $user['specialty'] ?? '';
        $_SESSION['student_group'] = $user['group'] ?? '';
        $_SESSION['student_course_year'] = $user['course_year'] ?? '';
        $_SESSION['student_avatar_url'] = $user['avatar_url'] ?? '';

        $_SESSION['logged_in'] = true;

        if ($tmisData) {
            $_SESSION['tmis_id'] = $tmisData['id'] ?? $user['id'];
            $_SESSION['tmis_token'] = $tmisData['access_token'] ?? '';
            $_SESSION['tmis_expires'] = time() + ($tmisData['expires_in'] ?? 3600);
        }

        if ($username)
            $_SESSION['tmis_username'] = $username;
        if ($password) {
            $key = substr(md5('DISTANT_TMIS_KEY_2024'), 0, 16);
            $iv = substr(md5('DISTANT_TMIS_IV_2024'), 0, 16);
            $_SESSION['tmis_pwd_enc'] = base64_encode(openssl_encrypt($password, 'AES-128-CBC', $key, 0, $iv));
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
                return true; // Uğurla yeniləndi
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
            $key = substr(md5('DISTANT_TMIS_KEY_2024'), 0, 16);
            $iv = substr(md5('DISTANT_TMIS_IV_2024'), 0, 16);
            $password = openssl_decrypt(base64_decode($encPwd), 'AES-128-CBC', $key, 0, $iv);

            if (!$password) {
                return false;
            }

            $tmisResult = TmisApi::loginStudent($username, $password);
            if (!$tmisResult['success']) {
                return false;
            }

            $tmisData = $tmisResult['data'];

            // Yalnız token məlumatlarını yenilə, session-u silmə
            $_SESSION['tmis_token'] = $tmisData['access_token'] ?? '';
            $_SESSION['tmis_expires'] = time() + ($tmisData['expires_in'] ?? 3600);
            $_SESSION['tmis_id'] = $tmisData['id'] ?? $_SESSION['user_id'];

            error_log('TMİS Silent Re-Login uğurlu: ' . $username);
            return true;
        } catch (\Exception $e) {
            error_log('TMİS Silent Re-Login xətası: ' . $e->getMessage());
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

function requireStudent()
{
    requireLogin();
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'admin') {
        (new Auth())->logout();
        header('Location: login.php?error=access_denied');
        exit;
    }
}
