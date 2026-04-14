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

$webinar = $db->fetch(
    "SELECT w.*, f.name as faculty_name, u.full_name as teacher_name 
     FROM webinars w 
     JOIN webinar_faculties f ON w.faculty_id = f.id 
     JOIN webinar_users u ON w.teacher_id = u.id
     WHERE w.id = ? AND w.faculty_id = ?",
    [$id, $user['faculty_id']]
);

if (!$webinar || !$webinar['recording_path']) {
    die("Dərs yazısı tapılmadı.");
}

$videoPath = "../uploads/webinar_recordings/" . $webinar['recording_path'];
$pageTitle = "Arxiv: " . $webinar['title'];
require_once 'includes/header.php';
?>

<div class="max-w-5xl mx-auto py-10">
    <div class="mb-8 flex items-center justify-between">
        <a href="archive.php" class="flex items-center gap-2 text-white/40 hover:text-white transition-colors text-sm font-bold uppercase tracking-widest">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Arxiva Qayıt
        </a>
        <div class="text-right">
            <span class="px-3 py-1 bg-white/5 rounded-full text-[10px] font-bold text-white/40 uppercase tracking-widest border border-white/5">
                <?php echo date('d.m.Y H:i', strtotime($webinar['ended_at'])); ?>
            </span>
        </div>
    </div>

    <div class="bg-black rounded-[2.5rem] overflow-hidden border border-white/5 shadow-2xl relative aspect-video group">
        <video controls class="w-full h-full object-contain">
            <source src="<?php echo $videoPath; ?>" type="video/webm">
            Sizin brauzeriniz video playeri dəstəkləmir.
        </video>
    </div>

    <div class="mt-10 bg-[#0a1f44] rounded-[2.5rem] border border-white/5 p-10">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight mb-2"><?php echo e($webinar['title']); ?></h2>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2 text-xs text-white/40 font-bold uppercase tracking-widest">
                        <i data-lucide="user" class="w-4 h-4 text-emerald-400"></i>
                        <?php echo e($webinar['teacher_name']); ?>
                    </div>
                </div>
            </div>
            
            <button onclick="window.print()" class="px-6 py-3 bg-white/5 hover:bg-white/10 text-white rounded-2xl text-xs font-bold uppercase tracking-widest border border-white/5 transition-all">
                Məlumatı Paylaş
            </button>
        </div>
        
        <?php if (!empty($webinar['description'])): ?>
            <div class="mt-8 pt-8 border-t border-white/5">
                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-400 mb-4">Dərs haqqında</p>
                <div class="text-white/60 leading-relaxed italic">
                    <?php echo nl2br(e($webinar['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
