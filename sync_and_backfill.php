<?php
/**
 * Sync Subjects and Backfill Metadata
 */

require_once 'teacher/includes/auth.php';
require_once 'teacher/config/database.php';
require_once 'teacher/includes/TmisApi.php';

// Since we're in CLI, we might need to bypass auth or use a manual token
// For security, tell the user to run it via browser if needed, 
// but for this task I'll try to use the database directly first 
// to see if I can find a token in user_sessions if available.

// Actually, I'll just write the script to be run via Browser 
// to ensure it has the correct session.

?>
<!DOCTYPE html>
<html>
<head>
    <title>Metadata Sync</title>
</head>
<body>
    <h1>Metadata Sync & Backfill</h1>
    <pre>
<?php
$auth = new Auth();
if (!isset($_SESSION['tmis_token'])) {
    die("Error: No TMIS token in session. Please log in first.");
}

$tmisToken = $_SESSION['tmis_token'];
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "Fetching subjects from TMIS API...\n";
$coursesResult = TmisApi::getSubjectsList($tmisToken);

if (!$coursesResult['success'] || !isset($coursesResult['data'])) {
    die("Failed to fetch subjects from TMIS: " . ($coursesResult['error'] ?? 'Unknown error'));
}

$subjects = $coursesResult['data'];
echo "Found " . count($subjects) . " subjects from TMIS.\n";

// 1. Sync specializations
echo "Syncing specializations...\n";
foreach ($subjects as $cs) {
    if (!empty($cs['profession_id'])) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO specializations (id, name) VALUES (?, ?)");
        $stmt->execute([$cs['profession_id'], $cs['profession_name'] ?? 'Təyin edilməyib']);
    }
}

// 2. Sync subjects
echo "Syncing subjects table...\n";
foreach ($subjects as $cs) {
    $stmt = $pdo->prepare("INSERT INTO subjects (id, education_year_id, faculty_name_id, profession_id, course, subject_name) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE course = VALUES(course), subject_name = VALUES(subject_name)");
    $stmt->execute([
        $cs['id'],
        $cs['education_year_id'] ?? null,
        $cs['faculty_id'] ?? null, // faculty_name_id in local DB
        $cs['profession_id'] ?? null,
        $cs['course'] ?? null,
        $cs['subject_name'] ?? ''
    ]);
}
echo "Subjects sync completed.\n";

// 3. Backfill archived_lessons
echo "Backfilling archived_lessons...\n";
$archived = $db->fetchAll("SELECT id, course_id, tmis_subject_id FROM archived_lessons");
foreach ($archived as $row) {
    $courseId = $row['tmis_subject_id'] ?: $row['course_id'];
    $subjectSq = $db->fetch("SELECT s.course, sp.name as profession_name FROM subjects s LEFT JOIN specializations sp ON s.profession_id = sp.id WHERE s.id = ?", [$courseId]);
    
    if ($subjectSq) {
        $db->update('archived_lessons', [
            'specialty_name' => $subjectSq['profession_name'] ?: 'Təyin edilməyib',
            'course_level' => $subjectSq['course'] ? $subjectSq['course'] . '-cü kurs' : 'Təyin edilməyib'
        ], 'id = ?', [$row['id']]);
    }
}

// 4. Backfill live_classes
echo "Backfilling live_classes...\n";
$live = $db->fetchAll("SELECT id, course_id, tmis_subject_id FROM live_classes");
foreach ($live as $row) {
    $courseId = $row['tmis_subject_id'] ?: $row['course_id'];
    $subjectSq = $db->fetch("SELECT s.course, sp.name as profession_name FROM subjects s LEFT JOIN specializations sp ON s.profession_id = sp.id WHERE s.id = ?", [$courseId]);
    
    if ($subjectSq) {
        $db->update('live_classes', [
            'specialty_name' => $subjectSq['profession_name'] ?: 'Təyin edilməyib',
            'course_level' => $subjectSq['course'] ? $subjectSq['course'] . '-cü kurs' : 'Təyin edilməyib'
        ], 'id = ?', [$row['id']]);
    }
}

echo "All tasks completed successfully!\n";
?>
    </pre>
</body>
</html>
