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
        header('Location: ../dashboard.php?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: ../dashboard.php');
}
