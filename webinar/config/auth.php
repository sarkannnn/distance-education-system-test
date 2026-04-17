<?php
require_once 'database.php';

session_start();

class WebinarAuth
{
    public static function isLoggedIn()
    {
        return isset($_SESSION['webinar_user_id']);
    }

    public static function getCurrentUser()
    {
        if (!self::isLoggedIn())
            return null;
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
        if ($_SESSION['webinar_role'] !== $role) {
            header('Location: dashboard.php?error=access_denied');
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
