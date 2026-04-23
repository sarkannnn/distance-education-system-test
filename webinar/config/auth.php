<?php
require_once 'database.php';

// Sessiya müddəti (8 saat)
ini_set('session.gc_maxlifetime', 28800);
ini_set('session.cookie_lifetime', 28800);
session_set_cookie_params(28800);

// Detect session name (Student vs Teacher/Admin)
$sessionName = 'DISTANT_T_SESSION_V4';
if (isset($_COOKIE['DISTANT_STUDENT_SESSION']) && !isset($_COOKIE['DISTANT_T_SESSION_V4'])) {
    $sessionName = 'DISTANT_STUDENT_SESSION';
}
session_name($sessionName);
session_start();

function logDebug($msg) {
    $logFile = __DIR__ . '/debug_auth.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

class WebinarAuth
{
    public static function isLoggedIn()
    {
        if (isset($_SESSION['webinar_user_id'])) {
            return true;
        }

        // Bridge check: If super user (admin) is logged into the main portal
        // The main portal uses 'DISTANT_T_SESSION_V4' (handled by session_name in auth.php)
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return true;
        }

        return false;
    }

    public static function getCurrentUser()
    {
        logDebug("getCurrentUser Called. Session: " . json_encode($_SESSION));
        if (!self::isLoggedIn()) {
            logDebug("isLoggedIn returned false.");
            return null;
        }

        $db = WebinarDatabase::getInstance();

        // Priority 1: Bridge Main Portal Admin to Webinar Session
        if (!isset($_SESSION['webinar_user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $fullName = $_SESSION['user_name'] ?? 'Super User';
            
            // Try to find an existing admin account in webinar_users
            $user = $db->fetch("SELECT u.*, f.name as faculty_name 
                               FROM webinar_users u 
                               LEFT JOIN webinar_faculties f ON u.faculty_id = f.id 
                               WHERE u.role = 'admin' AND (u.username = 'admin' OR u.full_name = ?)", [$fullName]);
            
            if (!$user) {
                // Fallback to the first admin account (usually ID 1)
                $user = $db->fetch("SELECT u.*, f.name as faculty_name 
                                   FROM webinar_users u 
                                   LEFT JOIN webinar_faculties f ON u.faculty_id = f.id 
                                   WHERE u.role = 'admin' ORDER BY u.id ASC LIMIT 1");
            }

            if ($user) {
                logDebug("Bridging Main Portal Admin to Webinar Admin: " . $user['username']);
                $_SESSION['webinar_user_id'] = $user['id'];
                $_SESSION['webinar_username'] = $user['username'];
                // Prioritize the name from the main portal session if available
                $_SESSION['webinar_full_name'] = $_SESSION['user_name'] ?? $user['full_name'];
                $_SESSION['webinar_role'] = $user['role'];
                $_SESSION['webinar_faculty_id'] = $user['faculty_id'];
                $_SESSION['webinar_faculty_name'] = $user['faculty_name'] ?? '';
                $_SESSION['webinar_department_id'] = $user['department_id'] ?? null;
            } else {
                logDebug("Bridge failed: No admin account found in webinar_users.");
            }
        }

        // Priority 2: Standard webinar session
        if (isset($_SESSION['webinar_user_id'])) {
            return [
                'id' => $_SESSION['webinar_user_id'],
                'username' => $_SESSION['webinar_username'],
                'full_name' => $_SESSION['webinar_full_name'],
                'role' => $_SESSION['webinar_role'],
                'faculty_id' => $_SESSION['webinar_faculty_id'],
                'faculty_slug' => $_SESSION['webinar_faculty_slug'] ?? null,
                'faculty_name' => $_SESSION['webinar_faculty_name'] ?? null,
                'department_id' => $_SESSION['webinar_department_id'] ?? null,
                'department_name' => $_SESSION['webinar_department_name'] ?? null
            ];
        }

        return null;
    }

    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }

        // Ensure current user session is populated (triggering bridge if needed)
        $user = self::getCurrentUser();
        if (!$user) {
            header('Location: login.php');
            exit;
        }

        // Real-time bypass check for deactivated accounts
        if (isset($_SESSION['webinar_user_id'])) {
            $db = WebinarDatabase::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT is_active FROM webinar_users WHERE id = ?");
            $stmt->execute([$_SESSION['webinar_user_id']]);
            $is_active = $stmt->fetchColumn();
            
            if ($is_active == 0) {
                self::logout();
            }
        }
    }

    public static function requireRole($role)
    {
        self::requireLogin();
        $user = self::getCurrentUser();
        
        // Admin has access to everything
        if ($user['role'] === 'admin') return;
        
        if ($user['role'] !== $role) {
            // Determine the base path to avoid 404 from api/ folder
            $redirectUrl = (strpos($_SERVER['PHP_SELF'], '/api/') !== false) ? '../dashboard.php' : 'dashboard.php';
            header('Location: ' . $redirectUrl . '?error=access_denied');
            exit;
        }
    }

    public static function getFacultyId()
    {
        return $_SESSION['webinar_faculty_id'] ?? null;
    }

    public static function logout()
    {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

/**
 * Global helper functions
 */
function requireWebinarLogin()
{
    WebinarAuth::requireLogin();
}

function requireWebinarTeacher()
{
    WebinarAuth::requireRole('teacher');
}

function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
