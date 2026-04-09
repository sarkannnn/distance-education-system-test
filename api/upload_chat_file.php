<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Authentication check — must be logged in as student or instructor
session_name('DISTANT_STUDENT_SESSION');
session_start();
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Try teacher session
    session_write_close();
    session_name('DISTANT_TEACHER_SESSION');
    session_start();
    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Giriş tələb olunur']);
        exit;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        throw new Exception("Yalnız POST.");
    if (!isset($_FILES['file']))
        throw new Exception("Fayl seçilməyib.");
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
        throw new Exception("Upload Xətası Kod: " . $_FILES['file']['error']);

    $file = $_FILES['file'];

    // Təhlükəsizlik: Fayl uzantısını yoxla
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        throw new Exception("Təhlükəsizlik xətası: Bu fayl növünə icazə verilmir! (" . $ext . ")");
    }

    // MIME type check (Əlavə qat)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // PHP/EXE fayllarının MIME-ni yoxla
    if (strpos($mime, 'php') !== false || strpos($mime, 'javascript') !== false) {
        throw new Exception("Təhlükəli fayl məzmunu aşkarlandı.");
    }

    // Path inside student-information-system
    $targetDir = __DIR__ . "/../uploads/chat_files/";

    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0750, true))
            throw new Exception("Qovluq yaradıla bilmədi.");
    }

    $fileName = uniqid("f_") . "." . $ext;
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {

        $baseUrl = rtrim(getenv('DISTANT_URL') ?: '', '/');
        $url = $baseUrl . "/uploads/chat_files/" . $fileName;

        echo json_encode([
            "success" => true,
            "url" => $url,
            "fileName" => $file['name']
        ]);
    } else {
        throw new Exception("Fayl köçürülə bilmədi.");
    }
} catch (Exception $e) {
    // Only user-facing validation messages are thrown above; log unexpected errors
    error_log('upload_chat_file error: ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
