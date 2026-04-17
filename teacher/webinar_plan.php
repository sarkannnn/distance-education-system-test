<?php
/**
 * Webinar Archive - Vebinar Arxiv və Resurslar
 */
$currentPage = 'webinar_plan';
$pageTitle = 'Vebinar Arxivi';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
$currentUser = $auth->getCurrentUser();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$isAdmin = (($_SESSION['user_role'] ?? '') === 'admin');

if (!$isAdmin) {
    header('Location: ./');
    exit;
}

// Webinar bazasına qoşulma
require_once '../webinar/config/database.php';
$wdb = WebinarDatabase::getInstance();

$webinars = [];
$totalWebinars = 0;
$videoCount = 0;
$totalViews = 0;

try {
    $where = "w.status IN ('ended', 'completed')";
    $params = [];
    if (!$isAdmin) {
        $where .= " AND u.username = ?";
        $params[] = $currentUser['email'] ?? $currentUser['username'] ?? '';
    }

    $webinarsList = $wdb->fetchAll("
        SELECT w.id, w.title, w.status, w.ended_at, w.started_at, w.created_at, w.recording_path, w.is_visible,
               f.name as faculty_name, u.full_name as teacher_name
        FROM webinars w
        LEFT JOIN webinar_faculties f ON w.faculty_id = f.id
        LEFT JOIN webinar_users u ON w.teacher_id = u.id
        WHERE {$where}
        ORDER BY w.ended_at DESC, w.created_at DESC
    ", $params);

    foreach ($webinarsList as $rec) {
        $vCountExt = 0;
        if (!empty($rec['recording_path']) && $rec['recording_path'] !== '#') {
            $videoCount++;
            $vCountExt = 1;
        }

        // Hesablamalar
        $durationMinutes = 0;
        if (!empty($rec['started_at']) && !empty($rec['ended_at'])) {
            $startTs = strtotime($rec['started_at']);
            $endTs = strtotime($rec['ended_at']);
            if ($startTs && $endTs && $endTs > $startTs) {
                $durationMinutes = (int) ceil(($endTs - $startTs) / 60);
            }
        }
        if ($durationMinutes >= 60) {
            $hours = floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $formattedDuration = $hours . ' saat' . ($mins > 0 ? ' ' . $mins . ' dəq' : '');
        } elseif ($durationMinutes > 1) {
            $formattedDuration = $durationMinutes . ' dəq';
        } else {
            $formattedDuration = '-';
        }

        $date = !empty($rec['ended_at']) ? $rec['ended_at'] : (!empty($rec['started_at']) ? $rec['started_at'] : $rec['created_at']);
        
        // Path fix
        $fileUrl = $rec['recording_path'] ?: '#';
        if ($fileUrl != '#' && strpos($fileUrl, 'http') !== 0) {
            $fileUrl = '../uploads/webinar_recordings/' . $fileUrl;
        }

        $webinars[] = [
            'id' => 'arch_' . $rec['id'],
            'db_id' => $rec['id'],
            'title' => $rec['title'],
            'course_name' => 'Vebinar',
            'specialization_name' => $rec['faculty_name'] ?? 'Təyin edilməyib',
            'course_level' => '-',
            'lesson_type' => 'Vebinar Mövzusu',
            'instructor_name' => $rec['teacher_name'] ?? 'Məlum deyil',
            'date' => $date,
            'views' => 0, // Vebinar baxışları əgər DB-də varsa dəyişin
            'file_url' => $fileUrl,
            'duration' => $formattedDuration,
            'is_live' => false,
            'type' => 'video',
            'is_visible' => isset($rec['is_visible']) ? (int)$rec['is_visible'] : 1
        ];
    }
    $totalWebinars = count($webinars);
} catch (Exception $e) {
    error_log('Webinar API error: ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php require_once 'includes/topnav.php'; ?>

    <main class="main-content">
        <div class="content-container">

            <div class="page-header flex justify-between items-center mb-8">
                <div>
                    <h1 style="font-size: 32px; font-weight: 900; color: var(--text-primary); margin: 0;">Vebinar Arxiv və Resurslar</h1>
                    <p style="color: var(--text-muted); font-weight: 500; font-size: 16px;">Sistemdə keçirilmiş vebinarların video yazıları və materialları</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid-mockup" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-card-mockup pink">
                    <div class="stat-icon-mockup pink">
                        <i data-lucide="radio"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalWebinars; ?></div>
                    <div class="stat-label-mockup pink">Ümumi Vebinarlar</div>
                </div>

                <div class="stat-card-mockup blue">
                    <div class="stat-icon-mockup blue">
                        <i data-lucide="play-circle"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $videoCount; ?></div>
                    <div class="stat-label-mockup blue">Video Yazılar</div>
                </div>

                <div class="stat-card-mockup purple">
                    <div class="stat-icon-mockup purple">
                        <i data-lucide="eye"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalViews; ?></div>
                    <div class="stat-label-mockup purple">Ümumi Baxışlar</div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-10" style="padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--gray-50);">
                <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                    <div style="position: relative; flex: 1; min-width: 250px;">
                        <i data-lucide="search" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="text" id="archiveSearch" placeholder="Vebinar axtar..." class="form-input" style="padding-left: 50px; height: 55px; border-radius: 15px;" onkeyup="filterArchives()">
                    </div>
                </div>
            </div>

            <div id="archiveGrid" class="grid-3" style="gap: 30px;">
                <?php if (empty($webinars)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 50px;">
                        <i data-lucide="folder-open" style="width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px;"></i>
                        <p>Hələ ki, heç bir vebinar arxivi yoxdur.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($webinars as $lesson): ?>
                        <div class="archive-card card p-0 overflow-hidden"
                            data-title="<?php echo strtolower(e($lesson['title'])); ?>"
                            data-course-name="<?php echo strtolower(e($lesson['course_name'])); ?>"
                            style="background: var(--bg-white) !important; border-radius: 24px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                            
                            <!-- Video Placeholder -->
                            <div style="height: 180px; background: #0f172a; position: relative; display: flex; align-items: center; justify-content: center; color: white;">
                                <div style="width: 65px; height: 65px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="play" fill="white" style="width: 30px; height: 30px; margin-left: 3px;"></i>
                                </div>
                                <span style="position: absolute; bottom: 15px; right: 15px; background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;">
                                    <?php echo $lesson['duration']; ?>
                                </span>
                            </div>

                            <!-- Content Body -->
                            <div style="padding: 24px;">
                                <h3 class="archive-card-title" style="font-size: 20px; font-weight: 950; color: var(--text-primary); margin: 0 0 12px 0; line-height: 1.3; min-height: 26px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                    <?php echo e($lesson['title']); ?>
                                </h3>

                                <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px;">
                                    <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                        <span style="color: var(--text-muted); min-width: 55px;">Aparat:</span>
                                        <span style="color: var(--primary);"><?php echo e($lesson['specialization_name']); ?></span>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                        <span style="color: var(--text-muted); min-width: 55px;">Dərs Növü:</span>
                                        <span><?php echo e($lesson['lesson_type']); ?></span>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                        <span style="color: var(--text-muted); min-width: 55px;">Müddət:</span>
                                        <span><?php echo e($lesson['duration']); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($lesson['instructor_name'])): ?>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 12px; display: flex; align-items: center; gap: 4px;">
                                        <i data-lucide="user" style="width: 14px; height: 14px;"></i>
                                        <?php echo e($lesson['instructor_name']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="archive-card-meta-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <i data-lucide="calendar" style="width: 14px;"></i>
                                        <?php echo date('d.m.Y', strtotime($lesson['date'])); ?>
                                    </span>
                                    
                                    <!-- Visibility Toggle -->
                                    <div class="visibility-status <?php echo $lesson['is_visible'] ? 'is-visible' : 'is-hidden'; ?>">
                                        <div class="status-badge" style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; background: #f8fafc; border-radius: 8px;">
                                            <div class="status-icon-wrapper">
                                                <i data-lucide="<?php echo $lesson['is_visible'] ? 'eye' : 'eye-off'; ?>" style="width: 16px;"></i>
                                            </div>
                                            <span class="status-text" style="font-weight: 600; color: #475569;">
                                                <?php echo $lesson['is_visible'] ? 'Tələbə: Açıq' : 'Tələbə: Gizli'; ?>
                                            </span>
                                        </div>
                                        <label class="switch" style="position: relative; display: inline-block; width: 34px; height: 20px; margin-left: 8px;">
                                            <input type="checkbox" <?php echo $lesson['is_visible'] ? 'checked' : ''; ?> 
                                                   onchange="toggleVisibility(<?php echo $lesson['db_id']; ?>, this)" style="opacity: 0; width: 0; height: 0;">
                                            <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $lesson['is_visible'] ? '#10b981' : '#cbd5e1'; ?>; transition: .4s; border-radius: 34px;">
                                                <span style="position: absolute; content: ''; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; transform: <?php echo $lesson['is_visible'] ? 'translateX(14px)' : 'none'; ?>;"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Card Actions -->
                                <div style="display: flex; gap: 10px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                                    <?php if ($lesson['file_url'] !== '#'): ?>
                                        <a href="<?php echo $lesson['file_url']; ?>" target="_blank" class="btn btn-primary flex-1" style="height: 48px; border-radius: 12px; font-weight: 800;">
                                            <i data-lucide="play" style="width: 18px;"></i>
                                            İzlə
                                        </a>
                                        
                                        <a href="api/download_file.php?url=<?php echo urlencode($lesson['file_url']); ?>&filename=<?php echo urlencode(preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $lesson['title']) . '.mp4'); ?>" class="btn btn-secondary" style="width: 48px; height: 48px; border-radius: 12px; padding: 0; color: #3b82f6;" title="Videonu Yüklə">
                                            <i data-lucide="download" style="width: 20px;"></i>
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="btn btn-secondary flex-1" style="height: 48px; border-radius: 12px; font-weight: 800; opacity: 0.6;">
                                            Video mövcud deyil
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $userRole = strtolower($_SESSION['user_role'] ?? '');
                                    $canDelete = false;
                                    if ($userRole === 'admin') {
                                        $canDelete = true;
                                    } elseif ($userRole === 'teacher' || $userRole === 'instructor') {
                                        $canDelete = true;
                                    }
                                    
                                    if ($canDelete):
                                    ?>
                                        <button onclick="deleteWebinar(<?php echo $lesson['db_id']; ?>, '<?php echo addslashes($lesson['title']); ?>')"
                                                class="btn btn-secondary"
                                                style="width: 48px; height: 48px; border-radius: 12px; padding: 0; color: #ef4444; border: 1px solid #fee2e2;"
                                                title="Sil">
                                            <i data-lucide="trash-2" style="width: 20px; color: #ef4444;"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
.visibility-status { display: flex; align-items: center; justify-content: flex-end; margin-top: -5px; }
.switch input:checked + .slider { background-color: #10b981 !important; }
.switch input:checked + .slider > span { transform: translateX(14px) !important; }
</style>

<script>
    function filterArchives() {
        const q = document.getElementById('archiveSearch').value.toLowerCase();
        document.querySelectorAll('.archive-card').forEach(c => {
            const t = c.getAttribute('data-title') || '';
            const cn = c.getAttribute('data-course-name') || '';
            const matchesSearch = t.includes(q) || cn.includes(q);
            c.style.display = matchesSearch ? 'block' : 'none';
        });
    }

    function updateVisibilityUI(checkbox, isVisible) {
        const container = checkbox.closest('.visibility-status');
        if (!container) return;
        
        const label = container.querySelector('.status-text');
        const iconWrapper = container.querySelector('.status-icon-wrapper');
        const sliderStyle = checkbox.nextElementSibling;
        
        container.classList.remove('is-visible', 'is-hidden');
        container.classList.add(isVisible ? 'is-visible' : 'is-hidden');
        
        if (label) {
            label.textContent = isVisible ? 'Tələbə: Açıq' : 'Tələbə: Gizli';
        }
        
        if (iconWrapper) {
            iconWrapper.innerHTML = `<i data-lucide="${isVisible ? 'eye' : 'eye-off'}" style="width: 16px;"></i>`;
            if (window.lucide) window.lucide.createIcons();
        }
        
        sliderStyle.style.backgroundColor = isVisible ? '#10b981' : '#cbd5e1';
        sliderStyle.children[0].style.transform = isVisible ? 'translateX(14px)' : 'none';
    }

    function toggleVisibility(id, checkbox) {
        const isVisible = checkbox.checked ? 1 : 0;
        
        updateVisibilityUI(checkbox, isVisible);
        
        fetch('api/toggle_webinar_visibility.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, is_visible: isVisible })
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert(d.message || 'Xəta baş verdi');
                checkbox.checked = !checkbox.checked;
                updateVisibilityUI(checkbox, !isVisible);
            }
        })
        .catch(err => {
            alert('Serverlə əlaqə kəsildi');
            checkbox.checked = !checkbox.checked;
            updateVisibilityUI(checkbox, !isVisible);
        });
    }

    function deleteWebinar(id, title) {
        const displayTitle = title && title !== '-' ? title : 'bu vebinarı';
        if (!confirm(`"${displayTitle}" adlı vebinar silinsin?`)) return;

        const fd = new FormData();
        fd.append('id', id);

        fetch('api/delete_webinar_archive.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert(d.message || 'Silinmə zamanı xəta baş verdi');
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            alert('Serverlə əlaqə kəsildi');
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
