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

    if (!$title || !$scheduled_at) {
        header('Location: ../dashboard.php?error=missing_fields');
        exit;
    }

    try {
        // Admin üçün teacher_id-ni həll et
        $teacherId = $user['id'];
        if ($user['role'] === 'admin') {
            // Admin üçün fakültə seçimi (POST-dan və ya session-dan)
            if (!empty($_POST['faculty_id'])) {
                $facultyId = intval($_POST['faculty_id']);
            }
            // Fakültənin ilk müəllimini tap
            $firstTeacher = $db->fetch(
                "SELECT id FROM webinar_users WHERE faculty_id = ? AND role = 'teacher' ORDER BY id ASC LIMIT 1",
                [$facultyId]
            );
            if ($firstTeacher) {
                $teacherId = $firstTeacher['id'];
            } else {
                header('Location: ../dashboard.php?error=' . urlencode('Bu fakültədə heç bir müəllim yoxdur.'));
                exit;
            }
        }

        $db->insert('webinars', [
            'faculty_id' => $facultyId,
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
