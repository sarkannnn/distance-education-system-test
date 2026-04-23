<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: archive.php');
    exit;
}

if ($user['role'] === 'admin' && !isset($user['department_id'])) {
    $webinar = $db->fetch(
        "SELECT w.*, d.name as dept_name, u.full_name as teacher_name 
         FROM webinars w 
         LEFT JOIN webinar_departments d ON w.department_id = d.id 
         LEFT JOIN webinar_users u ON w.teacher_id = u.id
         WHERE w.id = ?",
        [$id]
    );
} else {
    $webinar = $db->fetch(
        "SELECT w.*, d.name as dept_name, u.full_name as teacher_name 
         FROM webinars w 
         LEFT JOIN webinar_departments d ON w.department_id = d.id 
         JOIN webinar_users u ON w.teacher_id = u.id
         WHERE w.id = ? AND w.department_id = ?",
        [$id, $user['department_id']]
    );
}

if (!$webinar || !$webinar['recording_path']) {
    die("Dərs yazısı tapılmadı.");
}

$videoPath = "../uploads/webinar_recordings/" . $webinar['recording_path'];
$pageTitle = "Arxiv: " . $webinar['title'];
require_once 'includes/header.php';
?>

<div class="max-w-5xl mx-auto py-4 sm:py-10 px-3 sm:px-6">
    <div class="mb-4 sm:mb-8 flex items-center justify-between">
        <a href="archive.php" class="flex items-center gap-1.5 sm:gap-2 text-white/40 hover:text-white transition-colors text-[10px] sm:text-sm font-bold uppercase tracking-widest">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i> <span class="hidden sm:inline">Arxiva Qayıt</span><span class="sm:hidden">Geri</span>
        </a>
        <div class="text-right">
            <span class="px-2 py-0.5 sm:px-3 sm:py-1 bg-white/5 rounded-full text-[8px] sm:text-[10px] font-bold text-white/40 uppercase tracking-widest border border-white/5">
                <?php echo date('d.m.Y H:i', strtotime($webinar['ended_at'])); ?>
            </span>
        </div>
    </div>

    <div class="bg-black rounded-2xl sm:rounded-[2.5rem] overflow-hidden border border-white/5 shadow-2xl relative aspect-video group">
        <video controls preload="metadata" class="w-full h-full object-contain">
            <?php 
                $mimeType = (strpos($webinar['recording_path'], '.mp4') !== false) ? 'video/mp4' : 'video/webm';
            ?>
            <source src="api/stream_video.php?id=<?php echo (int)$id; ?>" type="<?php echo $mimeType; ?>">
            Sizin brauzeriniz video playeri dəstəkləmir.
        </video>
    </div>

    <div class="mt-4 sm:mt-10 bg-[#0a1f44] rounded-2xl sm:rounded-[2.5rem] border border-white/5 p-4 sm:p-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-6">
            <div class="min-w-0">
                <h2 class="text-xl sm:text-3xl font-extrabold tracking-tight mb-1 sm:mb-2 truncate"><?php echo e($webinar['title']); ?></h2>
                <div class="flex items-center gap-4 sm:gap-6">
                    <div class="flex items-center gap-1.5 sm:gap-2 text-[10px] sm:text-xs text-white/40 font-bold uppercase tracking-widest">
                        <i data-lucide="user" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-400"></i>
                        <?php echo e($webinar['teacher_name']); ?>
                    </div>
                </div>
            </div>
            
            <button onclick="window.print()" class="w-full sm:w-auto px-4 sm:px-6 py-2.5 sm:py-3 bg-white/5 hover:bg-white/10 text-white rounded-xl sm:rounded-2xl text-[10px] sm:text-xs font-bold uppercase tracking-widest border border-white/5 transition-all text-center">
                Məlumatı Paylaş
            </button>
        </div>
        
        <?php if (!empty($webinar['description'])): ?>
            <div class="mt-4 sm:mt-8 pt-4 sm:pt-8 border-t border-white/5">
                <p class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-emerald-400 mb-2 sm:mb-4">Dərs haqqında</p>
                <div class="text-white/60 leading-relaxed italic text-sm sm:text-base">
                    <?php echo nl2br(e($webinar['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
