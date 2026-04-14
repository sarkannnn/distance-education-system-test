<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

// Fetch Webinars for the current faculty
$webinars = $db->fetchAll(
    "SELECT w.*, u.full_name as teacher_name 
     FROM webinars w 
     JOIN webinar_users u ON w.teacher_id = u.id 
     WHERE w.faculty_id = ? 
     ORDER BY w.scheduled_at DESC",
    [$user['faculty_id']]
);

$pageTitle = "Ana Panel - " . $user['faculty_name'];
require_once 'includes/header.php';
?>

<div class="animate-in fade-in slide-in-from-bottom-5 duration-700">
    <!-- Hero / Welcome Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-[#0a1f44] to-[#060f23] rounded-[3rem] border border-white/5 p-10 md:p-16 mb-12 shadow-2xl">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="text-center md:text-left">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-black text-emerald-400 uppercase tracking-[0.2em] mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    Sistem Aktivdir
                </div>
                <h2 class="text-4xl md:text-6xl font-black tracking-tighter mb-4 leading-none">
                    Xoş gəldiniz,<br/>
                    <span class="text-emerald-500 italic"><?php echo e($user['full_name']); ?>!</span>
                </h2>
                <p class="text-white/40 text-sm md:text-base font-medium max-w-md">
                    Fakültə daxili vebinar dərslərini və arxivləri buradan asanlıqla idarə edə bilərsiniz.
                </p>
            </div>
            
            <?php if ($user['role'] === 'teacher'): ?>
                <div class="flex flex-col items-center gap-6">
                    <button onclick="document.getElementById('createModal').style.display='flex'" 
                            class="group relative px-10 py-6 bg-emerald-500 hover:bg-emerald-400 text-white rounded-[2rem] font-black text-sm uppercase tracking-widest transition-all shadow-[0_20px_50px_-12px_rgba(16,185,129,0.5)] active:scale-95 overflow-hidden">
                        <div class="relative z-10 flex items-center gap-3">
                            <i data-lucide="plus-circle" class="w-6 h-6 group-hover:rotate-90 transition-transform duration-500"></i>
                            YENİ VEBİNAR YARAT
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    </button>
                    <p class="text-[10px] text-white/20 font-bold uppercase tracking-widest">Cəmi <?php echo count($webinars); ?> vebinar planlaşdırılıb</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Decorative background elements -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-emerald-500/10 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-blue-500/10 rounded-full blur-[100px]"></div>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
        <div class="group bg-white/5 border border-white/5 p-8 rounded-[2rem] hover:bg-white/[0.08] transition-all hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <i data-lucide="calendar" class="w-6 h-6 text-blue-400"></i>
            </div>
            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">Ümumi Vebinarlar</p>
            <p class="text-4xl font-black italic tracking-tighter"><?php echo count($webinars); ?></p>
        </div>

        <div class="group bg-white/5 border border-white/5 p-8 rounded-[2rem] hover:bg-white/[0.08] transition-all hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <i data-lucide="radio" class="w-6 h-6 text-emerald-400"></i>
            </div>
            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">Aktiv Dərslər</p>
            <p class="text-4xl font-black italic tracking-tighter text-emerald-400">
                <?php echo count(array_filter($webinars, function($w) { return $w['status'] === 'live'; })); ?>
            </p>
        </div>

        <div class="group bg-white/5 border border-white/5 p-8 rounded-[2rem] hover:bg-white/[0.08] transition-all hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <i data-lucide="clock" class="w-6 h-6 text-amber-400"></i>
            </div>
            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">Planlaşdırılıb</p>
            <p class="text-4xl font-black italic tracking-tighter text-amber-400">
                <?php echo count(array_filter($webinars, function($w) { return $w['status'] === 'scheduled'; })); ?>
            </p>
        </div>

        <div class="group bg-white/5 border border-white/5 p-8 rounded-[2rem] hover:bg-white/[0.08] transition-all hover:-translate-y-1">
            <div class="w-12 h-12 rounded-2xl bg-rose-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <i data-lucide="archive" class="w-6 h-6 text-rose-400"></i>
            </div>
            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">Arxivlənib</p>
            <p class="text-4xl font-black italic tracking-tighter text-rose-400">
                <?php echo count(array_filter($webinars, function($w) { return $w['status'] === 'ended'; })); ?>
            </p>
        </div>
    </div>

    <!-- Webinar List Title Section -->
    <div class="flex items-center justify-between mb-8 px-4">
        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white/20 flex items-center gap-4 italic">
            <span class="w-12 h-px bg-white/10"></span>
            Vebinar Siyahısı
            <span class="w-12 h-px bg-white/10"></span>
        </h3>
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
            <span class="text-[10px] font-bold text-white/40 uppercase tracking-widest">Real Vaxt Yenilənmə</span>
        </div>
    </div>
    
    <?php if (empty($webinars)): ?>
        <div class="bg-white/[0.02] border border-dashed border-white/10 rounded-[3rem] p-24 text-center">
            <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-8 border border-white/5">
                <i data-lucide="calendar-x" class="w-10 h-10 text-white/10"></i>
            </div>
            <h4 class="text-xl font-bold mb-2">Heç bir vebinar yoxdur</h4>
            <p class="text-white/30 text-sm font-medium">Hələ ki, hər hansı bir vebinar planlaşdırılmayıb.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6">
            <?php foreach ($webinars as $w): ?>
                <div class="group relative bg-[#0a1f44]/40 hover:bg-[#0a1f44]/80 border border-white/5 hover:border-emerald-500/30 rounded-[2.5rem] p-8 transition-all duration-500 hover:-translate-y-1">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8">
                        <div class="flex items-start gap-8">
                            <!-- Status Icon -->
                            <div class="relative flex-shrink-0">
                                <div class="w-20 h-20 rounded-3xl <?php echo $w['status'] === 'live' ? 'bg-emerald-500/20 shadow-[0_0_30px_rgba(16,185,129,0.3)]' : 'bg-white/5'; ?> flex items-center justify-center transition-all duration-500 group-hover:scale-105 border border-white/5">
                                    <i data-lucide="<?php echo $w['status'] === 'live' ? 'radio' : ($w['status'] === 'ended' ? 'archive' : 'calendar'); ?>" 
                                       class="w-10 h-10 <?php echo $w['status'] === 'live' ? 'text-emerald-400' : 'text-white/20'; ?>"></i>
                                </div>
                                <?php if ($w['status'] === 'live'): ?>
                                    <span class="absolute -top-2 -right-2 flex h-5 w-5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-5 w-5 bg-emerald-500 border-4 border-[#0a1f44]"></span>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-4">
                                <div class="flex flex-wrap items-center gap-4">
                                    <h4 class="text-2xl font-black tracking-tight group-hover:text-emerald-400 transition-colors"><?php echo e($w['title']); ?></h4>
                                    <?php if ($w['status'] === 'live'): ?>
                                        <span class="px-4 py-1.5 bg-emerald-500 text-white text-[10px] font-black uppercase rounded-xl tracking-[0.2em] shadow-lg shadow-emerald-500/40">CANLI</span>
                                    <?php elseif ($w['status'] === 'ended'): ?>
                                        <span class="px-4 py-1.5 bg-white/10 text-white/40 text-[10px] font-black uppercase rounded-xl tracking-[0.2em] border border-white/5">Arxivlənib</span>
                                    <?php else: ?>
                                        <span class="px-4 py-1.5 bg-amber-500/10 text-amber-500 text-[10px] font-black uppercase rounded-xl tracking-[0.2em] border border-amber-500/20">Gözləyir</span>
                                    <?php endif; ?>
                                </div>

                                    <div class="flex items-center gap-3 text-sm text-white/40 font-bold uppercase tracking-widest pl-10 border-l border-white/5">
                                        <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center">
                                            <i data-lucide="user" class="w-4 h-4 text-emerald-500"></i>
                                        </div>
                                        <?php echo e($w['teacher_name']); ?>
                                    </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4 self-end lg:self-center">
                            <?php if ($w['status'] === 'live'): ?>
                                <a href="<?php echo $user['role'] === 'teacher' ? 'studio.php' : 'view.php'; ?>?id=<?php echo $w['id']; ?>" 
                                   class="group/btn px-10 py-5 bg-emerald-500 hover:bg-emerald-400 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all active:scale-95 flex items-center gap-3 shadow-[0_15px_30px_-12px_rgba(16,185,129,0.5)]">
                                    <i data-lucide="play" class="w-5 h-5 fill-current group-hover/btn:scale-110 transition-transform"></i>
                                    DƏRSƏ QOŞUL
                                </a>
                            <?php elseif ($w['status'] === 'scheduled' && $user['role'] === 'teacher'): ?>
                                <button onclick="startWebinar(<?php echo $w['id']; ?>)"
                                        class="px-10 py-5 bg-white/5 hover:bg-white text-white hover:text-black border border-white/10 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all active:scale-95 italic">
                                    VEBİNARI BAŞLAT
                                </button>
                            <?php elseif ($w['status'] === 'ended'): ?>
                                <a href="play.php?id=<?php echo $w['id']; ?>" 
                                   class="px-10 py-5 bg-white/5 hover:bg-white text-white hover:text-black border border-white/10 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-xl">
                                    VİDEOYA BAX
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['role'] === 'teacher'): ?>
                                <?php if ($w['status'] === 'scheduled'): ?>
                                    <!-- Edit Button - only for scheduled/pending -->
                                    <button onclick='openEditModal(<?php echo json_encode([
                                        "id" => $w["id"],
                                        "title" => $w["title"],
                                        "description" => $w["description"] ?? "",
                                        "scheduled_at" => date("Y-m-d\TH:i", strtotime($w["scheduled_at"])),
                                        "duration" => $w["duration"] ?? 90
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                            class="w-14 h-14 bg-white/5 hover:bg-blue-500/20 text-white/20 hover:text-blue-400 border border-white/5 rounded-2xl transition-all flex items-center justify-center group/edit shadow-xl"
                                            title="Redaktə et">
                                        <i data-lucide="pencil" class="w-6 h-6 group-hover/edit:scale-110 transition-transform"></i>
                                    </button>
                                    <!-- Delete Button - active for scheduled -->
                                    <button onclick="deleteWebinar(<?php echo $w['id']; ?>, '<?php echo e($w['title']); ?>')" 
                                            class="w-14 h-14 bg-white/5 hover:bg-rose-500/20 text-white/20 hover:text-rose-500 border border-white/5 rounded-2xl transition-all flex items-center justify-center group/del shadow-xl"
                                            title="Sil">
                                        <i data-lucide="trash-2" class="w-6 h-6 group-hover/del:scale-110 transition-transform"></i>
                                    </button>
                                <?php elseif ($w['status'] === 'ended'): ?>
                                    <!-- Delete Button - disabled for archived -->
                                    <button disabled 
                                            class="w-14 h-14 bg-white/[0.02] text-white/10 border border-white/5 rounded-2xl flex items-center justify-center cursor-not-allowed opacity-40"
                                            title="Arxivlənmiş vebinar silinə bilməz">
                                        <i data-lucide="trash-2" class="w-6 h-6"></i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Modal (Mühazirəçi üçün) -->
<?php if ($user['role'] === 'teacher'): ?>
<div id="createModal" class="fixed inset-0 z-[60] bg-[#060f23]/90 backdrop-blur-md hidden items-center justify-center p-4 animate-in fade-in duration-300">
    <div class="bg-[#0a1f44] w-full max-w-2xl rounded-[3.5rem] border border-white/10 p-12 md:p-16 shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] relative overflow-hidden">
        <!-- Close Button -->
        <button onclick="document.getElementById('createModal').style.display='none'" 
                class="absolute top-10 right-10 w-12 h-12 rounded-full bg-white/5 flex items-center justify-center text-white/30 hover:text-white hover:bg-white/10 transition-all active:scale-90">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="mb-12">
            <h3 class="text-3xl font-black italic tracking-tighter mb-2">Yeni Vebinar <span class="text-emerald-500">Planla</span></h3>
            <p class="text-white/40 text-sm font-medium">İştirakçılar üçün yeni bir tədris seansını burada yarada bilərsiniz.</p>
        </div>
        
        <form action="api/create_webinar.php" method="POST" class="space-y-8">
            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Vebinar Mövzusu</label>
                <input type="text" name="title" required 
                       class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold placeholder:text-white/10"
                       placeholder="Məs: Kiber Təhlükəsizlik Və Bulud Texnologiyaları">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Başlama Tarixi</label>
                    <input type="datetime-local" name="scheduled_at" required 
                           class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold text-white/40">
                </div>
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Müddət (Dəq)</label>
                    <input type="number" name="duration" value="90" 
                           class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold"
                           placeholder="90">
                </div>
            </div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Dərs Haqda Qısa Qeyd</label>
                <textarea name="description" rows="3"
                          class="w-full bg-white/5 border border-white/10 rounded-3xl px-8 py-5 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/10"
                          placeholder="İştirakçıların öncədən bilməsi vacib olan məlumatlar..."></textarea>
            </div>

            <button type="submit" 
                    class="group relative w-full bg-emerald-500 text-white py-6 rounded-2xl font-black text-xs uppercase tracking-[0.3em] hover:bg-emerald-400 transition-all active:scale-95 shadow-[0_20px_40px_-10px_rgba(16,185,129,0.5)] overflow-hidden">
                <span class="relative z-10 flex items-center justify-center gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    Vebinarı Təsdiqlə
                </span>
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
            </button>
        </form>

        <!-- Decoration -->
        <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl -z-10"></div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Modal (Mühazirəçi üçün) -->
<?php if ($user['role'] === 'teacher'): ?>
<div id="editModal" class="fixed inset-0 z-[60] bg-[#060f23]/90 backdrop-blur-md hidden items-center justify-center p-4 animate-in fade-in duration-300">
    <div class="bg-[#0a1f44] w-full max-w-2xl rounded-[3.5rem] border border-white/10 p-12 md:p-16 shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] relative overflow-hidden">
        <!-- Close Button -->
        <button onclick="closeEditModal()" 
                class="absolute top-10 right-10 w-12 h-12 rounded-full bg-white/5 flex items-center justify-center text-white/30 hover:text-white hover:bg-white/10 transition-all active:scale-90">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="mb-12">
            <h3 class="text-3xl font-black italic tracking-tighter mb-2">Vebinarı <span class="text-blue-400">Redaktə Et</span></h3>
            <p class="text-white/40 text-sm font-medium">Gözləyən statusda olan vebinarın məlumatlarını burada dəyişə bilərsiniz.</p>
        </div>
        
        <form id="editForm" onsubmit="submitEditForm(event)" class="space-y-8">
            <input type="hidden" id="edit_id" name="id">
            
            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Vebinar Mövzusu</label>
                <input type="text" id="edit_title" name="title" required 
                       class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-blue-400/50 transition-all font-bold placeholder:text-white/10"
                       placeholder="Məs: Kiber Təhlükəsizlik Və Bulud Texnologiyaları">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Başlama Tarixi</label>
                    <input type="datetime-local" id="edit_scheduled_at" name="scheduled_at" required 
                           class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-blue-400/50 transition-all font-bold text-white/40">
                </div>
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Müddət (Dəq)</label>
                    <input type="number" id="edit_duration" name="duration" value="90" 
                           class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-blue-400/50 transition-all font-bold"
                           placeholder="90">
                </div>
            </div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Dərs Haqda Qısa Qeyd</label>
                <textarea id="edit_description" name="description" rows="3"
                          class="w-full bg-white/5 border border-white/10 rounded-3xl px-8 py-5 text-sm focus:outline-none focus:border-blue-400/50 transition-all font-medium placeholder:text-white/10"
                          placeholder="İştirakçıların öncədən bilməsi vacib olan məlumatlar..."></textarea>
            </div>

            <button type="submit" 
                    class="group relative w-full bg-blue-500 text-white py-6 rounded-2xl font-black text-xs uppercase tracking-[0.3em] hover:bg-blue-400 transition-all active:scale-95 shadow-[0_20px_40px_-10px_rgba(59,130,246,0.5)] overflow-hidden">
                <span class="relative z-10 flex items-center justify-center gap-3">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    Dəyişiklikləri Yadda Saxla
                </span>
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
            </button>
        </form>

        <!-- Decoration -->
        <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl -z-10"></div>
    </div>
</div>
<?php endif; ?>

<script>
function startWebinar(id) {
    if (confirm('Vebinarı indi başlatmaq istəyirsiniz?')) {
        fetch('api/start_webinar.php?id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'studio.php?id=' + id;
                } else {
                    alert('Xəta: ' + data.message);
                }
            });
    }
}

// ===== EDIT FUNCTIONALITY =====
function openEditModal(webinar) {
    document.getElementById('edit_id').value = webinar.id;
    document.getElementById('edit_title').value = webinar.title;
    document.getElementById('edit_description').value = webinar.description || '';
    document.getElementById('edit_scheduled_at').value = webinar.scheduled_at;
    document.getElementById('edit_duration').value = webinar.duration || 90;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function submitEditForm(e) {
    e.preventDefault();
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="relative z-10 flex items-center justify-center gap-3"><i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>Yenilənir...</span>';

    fetch('api/update_webinar.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Show success and reload
            showToast('Vebinar uğurla yeniləndi!', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast('Xəta: ' + data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        showToast('Şəbəkə xətası baş verdi.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// ===== DELETE FUNCTIONALITY =====
function deleteWebinar(id, title) {
    // Show custom confirm
    const confirmed = confirm('"' + title + '" adlı vebinarı silmək istədiyinizə əminsiniz?\n\nBu əməliyyat geri qaytarıla bilməz!');
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('api/delete_webinar.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Vebinar uğurla silindi!', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast('Xəta: ' + data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Şəbəkə xətası baş verdi.', 'error');
    });
}

// ===== TOAST NOTIFICATION =====
function showToast(message, type) {
    // Remove existing toasts
    document.querySelectorAll('.toast-notification').forEach(el => el.remove());

    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    const bgColor = type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(239,68,68,0.95)';
    const icon = type === 'success' ? '✓' : '✕';
    
    toast.style.cssText = `
        position: fixed; bottom: 2rem; right: 2rem; z-index: 9999;
        background: ${bgColor}; color: white;
        padding: 1rem 1.5rem; border-radius: 1rem;
        font-size: 0.875rem; font-weight: 700;
        display: flex; align-items: center; gap: 0.75rem;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        transform: translateY(100px); opacity: 0;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        backdrop-filter: blur(12px);
    `;
    toast.innerHTML = `<span style="font-size:1.25rem">${icon}</span> ${message}`;
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
