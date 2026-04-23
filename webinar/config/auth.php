<?php
require_once 'database.php';

// Sessiya müddəti (8 saat)
ini_set('session.gc_maxlifetime', 28800);
ini_set('session.cookie_lifetime', 28800);
session_set_cookie_params(28800);

session_name('DISTANT_T_SESSION_V4');
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
        if (isset($_SESSION['webinar_user_id'])) return true;
        
        // Portal users (Admin, Instructor, Student) should have access
        if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'instructor', 'student'])) {
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

        // Priority 1: User from main portal (Admin, Instructor, Student)
        if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'instructor', 'student'])) {
            $mainUserId = $_SESSION['user_id'] ?? 1;
            
            // If student, return student object
            if ($_SESSION['user_role'] === 'student') {
                $studentFacultyName = $_SESSION['student_faculty'] ?? '';
                $studentDeptName = $_SESSION['student_department'] ?? '';
                
                // Fetch IDs for student faculty/dept
                $faculty = $db->fetch("SELECT id FROM webinar_faculties WHERE name = ?", [$studentFacultyName]);
                $dept = $db->fetch("SELECT id FROM webinar_departments WHERE name = ?", [$studentDeptName]);
                
                return [
                    'id' => $mainUserId,
                    'username' => $_SESSION['tmis_username'] ?? 'student_' . $mainUserId,
                    'full_name' => $_SESSION['user_name'] ?? 'Tələbə',
                    'role' => 'student',
                    'faculty_id' => $faculty['id'] ?? null,
                    'department_id' => $dept['id'] ?? null,
                    'faculty_name' => $studentFacultyName,
                    'department_name' => $studentDeptName
                ];
            }

            // Check if this user exists in webinar_users (for Admin/Instructor)
            $webinarUser = $db->fetch("SELECT * FROM webinar_users WHERE id = ?", [$mainUserId]);
            
            if (!$webinarUser) {
                logDebug("WebinarAuth: User {$mainUserId} ({$_SESSION['user_role']}) not found in webinar_users. Syncing...");
                
                // Get more info from session for sync
                $role = ($_SESSION['user_role'] === 'admin') ? 'admin' : 'teacher';
                $fullName = $_SESSION['user_name'] ?? ($_SESSION['user_role'] === 'admin' ? 'Super User' : 'Müəllim');
                
                try {
                    $db->insert('webinar_users', [
                        'id' => $mainUserId,
                        'username' => strtolower($role) . '_' . $mainUserId,
                        'password_hash' => '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK',
                        'full_name' => $fullName,
                        'role' => $role,
                        'faculty_id' => null, 
                        'is_active' => 1
                    ]);
                    logDebug("WebinarAuth: User {$mainUserId} synced successfully.");
                } catch (Exception $e) {
                    logDebug("WebinarAuth: Failed to sync User to webinar_users: " . $e->getMessage());
                }
            }

            if ($_SESSION['user_role'] === 'admin') {
                return [
                    'id' => $mainUserId,
                    'username' => 'admin',
                    'full_name' => $_SESSION['user_name'] ?? 'Super User',
                    'role' => 'admin',
                    'faculty_id' => null,
                    'faculty_slug' => 'all',
                    'faculty_name' => 'Bütün Kafedralar'
                ];
            } else {
                // Return teacher data (faculty/department handled by session later or defaults)
                return [
                    'id' => $mainUserId,
                    'username' => 'teacher',
                    'full_name' => $_SESSION['user_name'] ?? 'Müəllim',
                    'role' => 'teacher',
                    'faculty_id' => $_SESSION['teacher_faculty_id'] ?? null,
                    'department_id' => $_SESSION['teacher_department_id'] ?? null
                ];
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
                'faculty_slug' => $_SESSION['webinar_faculty_slug'],
                'faculty_name' => $_SESSION['webinar_faculty_name'],
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
