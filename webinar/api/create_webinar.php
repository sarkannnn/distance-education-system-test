<?php
require_once '../config/auth.php';
require_once '../config/database.php';

WebinarAuth::requireRole('teacher');
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $facultyId = $user['faculty_id'];
    $deptId = $user['department_id'] ?? null;

    if (!$title || !$scheduled_at) {
        header('Location: ../dashboard.php?error=missing_fields');
        exit;
    }

    try {
        $teacherId = $user['id'];
        
        // Ensure teacher exists in webinar_users to satisfy foreign key
        $checkTeacher = $db->fetch("SELECT id FROM webinar_users WHERE id = ?", [$teacherId]);
        if (!$checkTeacher) {
            logDebug("CreateWebinar Critical: Teacher ID $teacherId not found in webinar_users for user " . ($user['username'] ?? 'unknown'));
            // Try to force sync for any portal user (Admin or Instructor)
            if ($user['role'] === 'admin' || $user['role'] === 'teacher') {
                logDebug("CreateWebinar: Attempting emergency sync for {$user['role']} $teacherId");
                $db->insert('webinar_users', [
                    'id' => $teacherId,
                    'username' => strtolower($user['role']) . '_' . $teacherId,
                    'password_hash' => '$2y$10$O0NSKsQUtpcJSG0OsVYPQ.j0Z3J9rIK3iGdglkFGdJypS5Z6ixJdK',
                    'full_name' => $user['full_name'] ?? 'İstifadəçi',
                    'role' => $user['role'],
                    'faculty_id' => $user['faculty_id'] ?? null,
                    'is_active' => 1
                ]);
                logDebug("CreateWebinar: Emergency sync successful.");
            } else {
                throw new Exception("Sizin hesabınız vebinar bazasında tapılmadı (ID: $teacherId). Lütfən çıxış edib yenidən daxil olun.");
            }
        }

        if ($user['role'] === 'admin') {
            if (!empty($_POST['department_id'])) {
                $deptId = intval($_POST['department_id']);
                // Department-in faculty_id-sini tapırıq
                $deptInfo = $db->fetch("SELECT faculty_id FROM webinar_departments WHERE id = ?", [$deptId]);
                if ($deptInfo) {
                    $facultyId = $deptInfo['faculty_id'];
                }
            } elseif (!empty($_POST['faculty_id'])) {
                $facultyId = intval($_POST['faculty_id']);
            }
        }

        logDebug("CreateWebinar: Inserting with TeacherID: $teacherId, FacultyID: " . ($facultyId ?? 'NULL') . ", DeptID: " . ($deptId ?? 'NULL'));

        $db->insert('webinars', [
            'faculty_id' => $facultyId,
            'department_id' => $deptId,
            'teacher_id' => $teacherId,
            'title' => $title,
            'description' => $description,
            'scheduled_at' => $scheduled_at,
            'status' => 'scheduled'
        ]);

        header('Location: ../dashboard.php?success=webinar_created');
    } catch (Exception $e) {
        logDebug("CreateWebinar Error: " . $e->getMessage() . " | TeacherID: $teacherId | FacultyID: $facultyId");
        header('Location: ../dashboard.php?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: ../dashboard.php');
}
