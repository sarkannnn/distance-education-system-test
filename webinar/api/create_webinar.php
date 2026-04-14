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

    if (!$title || !$scheduled_at) {
        header('Location: ../dashboard.php?error=missing_fields');
        exit;
    }

    try {
        $db->insert('webinars', [
            'faculty_id' => $user['faculty_id'],
            'teacher_id' => $user['id'],
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
