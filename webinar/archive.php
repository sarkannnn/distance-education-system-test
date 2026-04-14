<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

// Fetch Archived (Ended) Webinars
$archived = $db->fetchAll(
    "SELECT w.*, u.full_name as teacher_name 
     FROM webinars w 
     JOIN webinar_users u ON w.teacher_id = u.id 
     WHERE w.faculty_id = ? AND w.status = 'ended'
     ORDER BY w.ended_at DESC",
    [$user['faculty_id']]
);

$pageTitle = "Vebinar Arxivi - " . $user['faculty_name'];
require_once 'includes/header.php';
?>

<div class="animate-in fade-in slide-in-from-bottom-5 duration-700">
    <div class="mb-12">
        <h2 class="text-4xl font-black tracking-tighter italic mb-4">Vebinar <span class="text-emerald-500">Arxivi</span></h2>
        <p class="text-white/40 text-base font-medium max-w-2xl">Keçmişdə baş tutan dərslərin qeydlərinə, tədris materiallarına və video arxivlərə buradan daxil ola bilərsiniz.</p>
    </div>

    <?php if (empty($archived)): ?>
        <div class="bg-white/[0.02] border border-dashed border-white/10 rounded-[3rem] p-32 text-center">
            <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-8 border border-white/5">
                <i data-lucide="archive" class="w-10 h-10 text-white/10"></i>
            </div>
            <h4 class="text-xl font-bold mb-2">Arxiv boşdur</h4>
            <p class="text-white/30 text-sm font-medium uppercase tracking-[0.2em]">Hələ ki, arxivlənmiş heç bir dərs tapılmadı.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($archived as $w): ?>
                <div class="group relative bg-[#0a1f44]/40 hover:bg-[#0a1f44] rounded-[2.5rem] border border-white/5 hover:border-emerald-500/30 p-10 transition-all duration-500 hover:-translate-y-2">
                    <div class="flex items-start justify-between mb-8">
                        <div class="w-16 h-16 rounded-[1.5rem] bg-white/5 flex items-center justify-center group-hover:bg-emerald-500/10 border border-white/5 group-hover:border-emerald-500/20 transition-all duration-500 group-hover:scale-110">
                            <i data-lucide="video" class="w-8 h-8 text-white/20 group-hover:text-emerald-400"></i>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black text-white/20 uppercase tracking-widest mb-1">Tarix</p>
                            <p class="text-xs font-bold text-white/60"><?php echo date('d M, Y', strtotime($w['ended_at'])); ?></p>
                        </div>
                    </div>
                    
                    <h3 class="text-xl font-black mb-8 leading-tight h-14 overflow-hidden group-hover:text-emerald-400 transition-colors uppercase tracking-tight"><?php echo e($w['title']); ?></h3>
                    
                    <div class="flex items-center gap-4 mb-10 pt-6 border-t border-white/5">
                        <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center">
                            <i data-lucide="user" class="w-4 h-4 text-emerald-500"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-white/20 uppercase tracking-[0.2em] mb-0.5">Mühazirəçi</p>
                            <p class="text-xs font-bold text-white/80"><?php echo e($w['teacher_name']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($w['recording_path']): ?>
                        <a href="play.php?id=<?php echo $w['id']; ?>" class="group/btn relative overflow-hidden flex items-center justify-center w-full px-8 py-5 bg-emerald-500 text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] hover:bg-emerald-400 transition-all active:scale-95 shadow-[0_15px_30px_-12px_rgba(16,185,129,0.5)]">
                            <span class="relative z-10 flex items-center gap-2">
                                <i data-lucide="play" class="w-4 h-4 fill-current"></i>
                                VİDEOYA BAX
                            </span>
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover/btn:translate-x-full transition-transform duration-1000"></div>
                        </a>
                    <?php else: ?>
                        <div class="flex items-center justify-center w-full px-8 py-5 bg-white/5 text-white/20 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] border border-dashed border-white/10 cursor-not-allowed italic">
                            <i data-lucide="clock" class="w-4 h-4 mr-3 animate-pulse"></i>
                            Yazı Gözlənilir
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
