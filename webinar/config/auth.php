<?php
require_once 'database.php';

session_name('DISTANT_T_SESSION_V4');
session_start();

class WebinarAuth
{
    public static function isLoggedIn()
    {
        if (isset($_SESSION['webinar_user_id'])) return true;
        
        // Admin from main portal should have access
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return true;
        }
        
        return false;
    }

    public static function getCurrentUser()
    {
        if (!self::isLoggedIn())
            return null;

        // If it's a main portal admin session
        if (!isset($_SESSION['webinar_user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return [
                'id' => $_SESSION['user_id'] ?? 1,
                'username' => 'admin',
                'full_name' => 'Super User',
                'role' => 'admin',
                'faculty_id' => 1, // Default faculty or handle appropriately
                'faculty_slug' => 'all',
                'faculty_name' => 'Bütün Fakültələr'
            ];
        }

        return [
            'id' => $_SESSION['webinar_user_id'],
            'username' => $_SESSION['webinar_username'],
            'full_name' => $_SESSION['webinar_full_name'],
            'role' => $_SESSION['webinar_role'],
            'faculty_id' => $_SESSION['webinar_faculty_id'],
            'faculty_slug' => $_SESSION['webinar_faculty_slug'],
            'faculty_name' => $_SESSION['webinar_faculty_name']
        ];
    }

    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
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
