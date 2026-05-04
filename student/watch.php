<?php

/**
 * Arxiv Video Pleyer - Watch Lesson
 */
$currentPage = 'archive';
$pageTitle = 'Dərsi İzlə';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

if (!$type || !$id) {
    header('Location: archive.php');
    exit;
}

$lesson = null;
$videoUrl = '';
$title = '';
$course = '';
$instructor = '';
$date = '';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = rtrim(getenv('DISTANT_URL') ?: ($protocol . "://" . $host), '/');

try {
    if ($type === 'live') {
        // Canlı dərs yazısı
        $lesson = $db->fetch(
            "SELECT lc.* FROM live_classes lc WHERE lc.id = ?",
            [$id]
        );

        if ($lesson && $lesson['recording_path']) {
            // Check finalized path first, fall back to chunk path (crash/no-finalization case)
            $videoUrl = file_exists(__DIR__ . '/../uploads/videos/' . $lesson['recording_path'])
                ? '../uploads/videos/' . $lesson['recording_path']
                : '../uploads/live_recordings/' . $lesson['recording_path'];
            $title = $lesson['title'] ?: ($lesson['subject_name'] ?? 'Canlı Dərs');
            $course = $lesson['subject_name'] ?? 'Fənn';
            $instructor = trim($lesson['instructor_name'] ?? 'Müəllim');
            $date = formatDate($lesson['start_time']);
            $duration = ($lesson['duration_minutes'] ?? 0) . ' dəqiqə';
            $description = '';

            // Increment views
            $db->query("UPDATE live_classes SET views = IFNULL(views, 0) + 1 WHERE id = ?", [$id]);
        }
    } else {
        // Arxiv materialı
        $lesson = $db->fetch(
            "SELECT al.* FROM archived_lessons al WHERE al.id = ?",
            [$id]
        );

        if ($lesson && $lesson['video_url']) {
            $rawUrl = $lesson['video_url'];

            if (str_starts_with($rawUrl, 'http')) {
                // If it's a full URL, check if it's local first
                $filename = basename(parse_url($rawUrl, PHP_URL_PATH));
                if (file_exists(__DIR__ . '/../teacher/uploads/archive/' . $filename)) {
                    $videoUrl = '../teacher/uploads/archive/' . $filename;
                } else {
                    $videoUrl = $rawUrl;
                }
            } elseif (str_starts_with($rawUrl, 'teacher/')) {
                $videoUrl = '../' . $rawUrl;
            } elseif (!str_starts_with($rawUrl, '../')) {
                // Assume it's in teacher/uploads/archive/ if not specified
                $videoUrl = '../teacher/uploads/archive/' . $rawUrl;
            } else {
                $videoUrl = $rawUrl;
            }

            $title = $lesson['title'];
            $course = $lesson['subject_name'] ?? 'Ümumi';
            $instructor = trim($lesson['instructor_name'] ?? 'Müəllim');
            $date = formatDate($lesson['archived_date'] ?? $lesson['created_at']);
            $duration = $lesson['duration'] ?? '';
            $description = $lesson['description'] ?? '';

            // Increment views
            $db->query("UPDATE archived_lessons SET views = IFNULL(views, 0) + 1 WHERE id = ?", [$id]);
        }
    }
} catch (Exception $e) {
    // Error handling
}

if (!$videoUrl) {
    header('Location: archive.php?error=not_found');
    exit;
}

// SECURITY: CHECK IF STUDENT IS ENROLLED IN THIS COURSE
if ($currentUser['role'] === 'student') {
    $courseId = (int)$lesson['course_id'];
    $tmisSubjectId = isset($lesson['tmis_subject_id']) ? (int)$lesson['tmis_subject_id'] : 0;

    $isEnrolled = false;

    // 1. Check local enrollments
    // We need to check both by student_id (TMIS ID) and local user ID because the system might use either
    $localUser = $db->fetch("SELECT id FROM users WHERE student_id = ?", [$currentUser['id']]);
    $localId = $localUser ? $localUser['id'] : 0;

    $checkLocal = $db->fetch(
        "SELECT id FROM enrollments WHERE (user_id = ? OR user_id = ?) AND course_id = ?",
        [$currentUser['id'], $localId, $courseId]
    );

    if ($checkLocal) {
        $isEnrolled = true;
    } else {
        // 2. Fallback: Check TMIS subjects list (Source of truth)
        $studentSubjects = tmis_get('/student/subjects');
        if ($studentSubjects && is_array($studentSubjects)) {
            foreach ($studentSubjects as $subj) {
                $subjId = (int)($subj['id'] ?? 0);
                if ($subjId > 0 && ($subjId === $courseId || $subjId === $tmisSubjectId)) {
                    $isEnrolled = true;
                    break;
                }
            }
        }
    }

    if (!$isEnrolled) {
        die("
            <div style='background:#f8fafc; color:#1e293b; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif;'>
                <div style='background:white; padding:40px; border-radius:24px; text-align:center; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1); max-width:400px;'>
                    <div style='width:64px; height:64px; background:#fee2e2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;'>
                        <i data-lucide='lock' style='width:32px; height:32px;'></i>
                    </div>
                    <h2 style='color:#0f172a; margin-bottom:12px; font-weight:800;'>Giriş Qadağandır</h2>
                    <p style='color:#64748b; line-height:1.6;'>Siz bu fənn üzrə qeydiyyatda deyilsiniz. Arxiv videolarına yalnız kursun tələbələri baxa bilər.</p>
                    <a href='archive.php' style='display:inline-block; margin-top:24px; background:#3b82f6; color:white; padding:12px 30px; border-radius:12px; text-decoration:none; font-weight:700;'>Arxivə Qayıt</a>
                </div>
            </div>
            <script src='https://unpkg.com/lucide@latest'></script>
            <script>lucide.createIcons();</script>
        ");
    }
}

require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php require_once 'includes/topnav.php'; ?>

    <main class="main-content">
        <div class="content-container">
            <!-- Back Button -->
            <div style="margin-bottom: 24px;">
                <a href="archive.php" class="btn btn-secondary"
                    style="display: inline-flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.05); color: var(--text-primary); border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600;">
                    <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                    Arxivə Qayıt
                </a>
            </div>

            <!-- Video Player Card -->
            <div
                style="background: var(--card-bg); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                <!-- Player Area -->
                <div style="aspect-ratio: 16/9; background: #000; position: relative;">
                    <video id="player" controls playsinline src="<?php echo e($videoUrl); ?>" style="width: 100%; height: 100%;">
                        Brauzeriniz video pleyeri dəstəkləmir.
                    </video>
                </div>

                <!-- Info Area -->
                <div style="padding: 30px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 300px;">
                            <h1
                                style="font-size: 24px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
                                <?php echo e($title); ?>
                            </h1>
                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <div
                                    style="display: flex; align-items: center; gap: 6px; color: var(--primary); font-weight: 700; font-size: 14px;">
                                    <i data-lucide="book-open" style="width: 16px; height: 16px;"></i>
                                    <?php echo e($course); ?>
                                </div>
                                <div
                                    style="display: flex; align-items: center; gap: 6px; color: var(--text-muted); font-size: 14px; font-weight: 500;">
                                    <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                    <?php echo e($date); ?>
                                </div>
                                <?php if (!empty($duration)): ?>
                                    <div
                                        style="display: flex; align-items: center; gap: 6px; color: var(--text-muted); font-size: 14px; font-weight: 500;">
                                        <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                        <?php echo e($duration); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($description)): ?>
                                <p style="margin-top: 12px; color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                                    <?php echo e($description); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 12px;">
                            <a href="<?php echo e($videoUrl); ?>" download class="btn btn-secondary"
                                style="display: flex; align-items: center; gap: 8px; border-radius: 12px; padding: 12px 24px;">
                                <i data-lucide="download"></i>
                                Yüklə
                            </a>
                        </div>
                    </div>

                    <div
                        style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px;">
                        <div
                            style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 18px;">
                            <?php echo mb_substr($instructor, 0, 1); ?>
                        </div>
                        <div>
                            <p
                                style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">
                                Təlimatçı</p>
                            <p style="font-size: 16px; font-weight: 700; color: var(--text-primary);">
                                <?php echo e($instructor); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Simple view tracking persistence
    const player = document.getElementById('player');
    let tracked = false;

    player.addEventListener('play', function() {
        if (!tracked) {
            console.log('Video started playing');
            tracked = true;
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>