<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

// Fetch Webinars (Admin sees all, others see department specific)
if ($user['role'] === 'admin' && empty($user['department_id'])) {
    $webinars = $db->fetchAll(
        "SELECT w.*, u.full_name as teacher_name, d.name as dept_name, f.name as fac_name 
         FROM webinars w 
         LEFT JOIN webinar_users u ON w.teacher_id = u.id 
         LEFT JOIN webinar_departments d ON w.department_id = d.id
         LEFT JOIN webinar_faculties f ON w.faculty_id = f.id
         ORDER BY w.scheduled_at DESC"
    );
} else {
// Normal user or Admin logged in as Department
$sql = "SELECT w.*, u.full_name as teacher_name, f.name as fac_name 
     FROM webinars w 
     LEFT JOIN webinar_users u ON w.teacher_id = u.id 
     LEFT JOIN webinar_faculties f ON w.faculty_id = f.id
     WHERE w.department_id = ?";
    $params = [$user['department_id']];

    // Teachers see all webinars in their department regardless of who created them
    // This allows Super Users to schedule webinars for specific departments.

    $sql .= " ORDER BY w.scheduled_at DESC";
    $webinars = $db->fetchAll($sql, $params);
}

// Fetch Departments (for Admin filter and creation modal)
$filterItems = [];
if ($user['role'] === 'admin' && !isset($user['department_id'])) {
    $filterItems = $db->fetchAll("
        SELECT d.*, f.name as fac_name 
        FROM webinar_departments d 
        JOIN webinar_faculties f ON d.faculty_id = f.id 
        ORDER BY f.name, d.name ASC
    ");
}


$pageTitle = "Dashboard - " . ($user['department_name'] ?? $user['faculty_name']);
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
                <h2 class="text-3xl md:text-5xl lg:text-6xl font-black tracking-tighter mb-4 leading-none">
                    Xoş gəldiniz,<br/>
                    <span class="text-emerald-500 italic"><?php echo e($user['full_name']); ?>!</span>
                </h2>
                <p class="text-white/40 text-sm md:text-base font-medium max-w-md">
                    Kafedra daxili vebinar dərslərini və arxivləri buradan asanlıqla idarə edə bilərsiniz.
                </p>
            </div>
            
            <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
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
    </div>
    
    <?php if ($user['role'] === 'admin' && !empty($filterItems)): ?>
    <!-- Department Filter (Admin Only) -->
    <div class="mb-10 space-y-4">
        <div class="px-4 flex items-center justify-between gap-4">
            <div class="relative w-full max-w-xs group">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-white/20 group-focus-within:text-emerald-500 transition-colors"></i>
                <input type="text" id="deptSearchInput" placeholder="Kafedra axtar..." 
                       class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-11 pr-4 text-[11px] font-bold text-white focus:outline-none focus:border-emerald-500/50 transition-all"
                       onkeyup="searchDepartments()">
            </div>
            <div class="text-[10px] font-black text-white/20 uppercase tracking-[0.2em] hidden sm:block">
                Cəmi <?php echo count($filterItems); ?> Kafedra
            </div>
        </div>

        <div class="relative group/filter px-4">
            <div class="flex items-center gap-3 overflow-x-auto pb-6 no-scrollbar" id="deptScroll">
                <button onclick="filterByDept(0)" class="dept-pill active whitespace-nowrap px-5 py-3 rounded-2xl bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest transition-all">
                    BÜTÜN KAFEDRALAR
                </button>
                <?php foreach ($filterItems as $item): ?>
                    <button onclick="filterByDept(<?php echo $item['id']; ?>)" 
                            data-name="<?php echo strtolower($item['name'] . ' ' . $item['fac_name']); ?>"
                            class="dept-pill whitespace-nowrap px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 text-white/40 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all border border-white/5">
                        <span class="opacity-50 text-[8px] block mb-0.5"><?php echo e($item['fac_name']); ?></span>
                        <?php echo e($item['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <!-- Gradient Fades -->
            <div class="absolute left-0 top-0 bottom-6 w-12 bg-gradient-to-r from-[#060f23] to-transparent pointer-events-none opacity-0 group-hover/filter:opacity-100 transition-opacity"></div>
            <div class="absolute right-0 top-0 bottom-6 w-12 bg-gradient-to-l from-[#060f23] to-transparent pointer-events-none"></div>
        </div>
    </div>
    
    <style>
        #deptScroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(16, 185, 129, 0.2) transparent;
        }
        #deptScroll::-webkit-scrollbar {
            height: 4px;
        }
        #deptScroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
        }
        #deptScroll::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.3);
            border-radius: 10px;
        }
        #deptScroll::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.5);
        }
        .dept-pill.active { background-color: #10b981 !important; color: white !important; box-shadow: 0 10px 20px -5px rgba(16,185,129,0.4); border-color: transparent !important; }
    </style>
    <?php endif; ?>
    
    <?php if (empty($webinars)): ?>
        <div class="bg-white/[0.02] border border-dashed border-white/10 rounded-[3rem] p-24 text-center">
            <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-8 border border-white/5">
                <i data-lucide="calendar-x" class="w-10 h-10 text-white/10"></i>
            </div>
            <h4 class="text-xl font-bold mb-2">Heç bir vebinar yoxdur</h4>
            <p class="text-white/30 text-sm font-medium">Hələ ki, hər hansı bir vebinar planlaşdırılmayıb.</p>
        </div>
    <?php else: ?>
    <div class="grid grid-cols-1 gap-6" id="webinarList">
            <?php foreach ($webinars as $w): ?>
                <div class="webinar-card group relative bg-[#0a1f44]/40 hover:bg-[#0a1f44]/80 border border-white/5 hover:border-emerald-500/30 rounded-[2.5rem] p-8 transition-all duration-500 hover:-translate-y-1"
                     data-dept-id="<?php echo $w['department_id']; ?>">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 md:gap-8">
                        <div class="flex flex-col md:flex-row md:items-start gap-4 md:gap-8 w-full">
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
                                        <span class="flex items-center gap-2 px-4 py-1.5 bg-white/10 text-white/40 text-[10px] font-black uppercase rounded-xl tracking-[0.2em] border border-white/5">
                                            Arxivlənib
                                            <span class="w-1 h-1 rounded-full bg-white/30"></span>
                                            <?php echo date('d.m.Y H:i', strtotime($w['ended_at'] ?? $w['scheduled_at'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="flex items-center gap-2 px-4 py-1.5 bg-amber-500/10 text-amber-500 text-[10px] font-black uppercase rounded-xl tracking-[0.2em] border border-amber-500/20">
                                            Gözləyir
                                            <span class="w-1 h-1 rounded-full bg-amber-500/50"></span>
                                            <?php echo date('d.m.Y H:i', strtotime($w['scheduled_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 mt-2 w-full">
                                            <span class="px-3 md:px-4 py-1.5 md:py-1.5 bg-blue-500/10 text-blue-400 text-[8px] md:text-[10px] font-black uppercase rounded-lg md:rounded-xl tracking-[0.1em] md:tracking-[0.2em] border border-blue-500/20 whitespace-normal break-words leading-snug w-full sm:w-auto text-center sm:text-left">
                                                <?php echo e($w['fac_name']); ?>
                                            </span>
                                            <span class="px-3 md:px-4 py-1.5 md:py-1.5 bg-emerald-500/10 text-emerald-400 text-[8px] md:text-[10px] font-black uppercase rounded-lg md:rounded-xl tracking-[0.1em] md:tracking-[0.2em] border border-emerald-500/20 whitespace-normal break-words leading-snug w-full sm:w-auto text-center sm:text-left">
                                                <?php echo e($w['dept_name']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                    <div class="flex items-center gap-3 text-sm text-white/40 font-bold uppercase tracking-widest md:pl-10 md:border-l border-white/5 pt-2 md:pt-0 w-full sm:w-auto">
                                        <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center shrink-0">
                                            <i data-lucide="user" class="w-4 h-4 text-emerald-500"></i>
                                        </div>
                                        <span class="truncate"><?php echo e($w['teacher_name']); ?></span>
                                    </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto mt-4 lg:mt-0 lg:justify-end">
                            <?php if ($w['status'] === 'live'): ?>
                                <a href="<?php echo ($user['role'] === 'teacher' || $user['role'] === 'admin') ? 'studio.php' : 'view.php'; ?>?id=<?php echo $w['id']; ?>" 
                                   class="flex-1 lg:flex-none justify-center group/btn px-6 md:px-10 py-4 md:py-5 bg-emerald-500 hover:bg-emerald-400 text-white rounded-xl md:rounded-2xl font-black text-[10px] md:text-xs uppercase tracking-[0.2em] transition-all active:scale-95 flex items-center gap-2 md:gap-3 shadow-[0_15px_30px_-12px_rgba(16,185,129,0.5)]">
                                    <i data-lucide="play" class="w-4 h-4 md:w-5 md:h-5 fill-current group-hover/btn:scale-110 transition-transform"></i>
                                    DƏRSƏ QOŞUL
                                </a>
                            <?php elseif ($w['status'] === 'scheduled' && ($user['role'] === 'teacher' || $user['role'] === 'admin')): ?>
                                <button onclick="startWebinar(<?php echo $w['id']; ?>)"
                                        class="flex-1 lg:flex-none justify-center px-6 md:px-10 py-4 md:py-5 bg-white/5 hover:bg-white text-white hover:text-black border border-white/10 rounded-xl md:rounded-2xl font-black text-[10px] md:text-xs uppercase tracking-[0.2em] transition-all active:scale-95 italic flex items-center justify-center">
                                    <span class="md:hidden">BAŞLAT</span><span class="hidden md:inline">VEBİNARI BAŞLAT</span>
                                </button>
                            <?php elseif ($w['status'] === 'ended'): ?>
                                <a href="play.php?id=<?php echo $w['id']; ?>" 
                                   class="flex-1 lg:flex-none justify-center px-6 md:px-10 py-4 md:py-5 bg-white/5 hover:bg-white text-white hover:text-black border border-white/10 rounded-xl md:rounded-2xl font-black text-[10px] md:text-xs uppercase tracking-[0.2em] transition-all shadow-xl">
                                    VİDEOYA BAX
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
                                <div class="flex items-center gap-2 shrink-0">
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
                                    <!-- Edit Button array for ended webinars -->
                                    <button onclick='openEditModal(<?php echo json_encode([
                                        "id" => $w["id"],
                                        "title" => $w["title"],
                                        "description" => $w["description"] ?? "",
                                        "scheduled_at" => date("Y-m-d\TH:i", strtotime($w["ended_at"] ?? $w["scheduled_at"] ?? date("Y-m-d H:i"))),
                                        "duration" => $w["duration"] ?? 90,
                                        "status" => "ended"
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                            class="w-14 h-14 bg-white/5 hover:bg-blue-500/20 text-white/20 hover:text-blue-400 border border-white/5 rounded-2xl transition-all flex items-center justify-center group/edit shadow-xl"
                                            title="Mövzu Adını Redaktə et">
                                        <i data-lucide="pencil" class="w-6 h-6 group-hover/edit:scale-110 transition-transform"></i>
                                    </button>
                                    <!-- Delete Button - disabled for archived -->
                                    <button disabled 
                                            class="w-14 h-14 bg-white/[0.02] text-white/10 border border-white/5 rounded-2xl flex items-center justify-center cursor-not-allowed opacity-40"
                                            title="Arxivlənmiş vebinar silinə bilməz">
                                        <i data-lucide="trash-2" class="w-6 h-6"></i>
                                    </button>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Modal (Mühazirəçi və Admin üçün) -->
<?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
<div id="createModal" class="fixed inset-0 z-[60] bg-[#060f23]/90 backdrop-blur-md hidden items-center justify-center p-4 animate-in fade-in duration-300 overflow-y-auto">
    <div class="bg-[#0a1f44] w-full max-w-2xl rounded-[2.5rem] md:rounded-[3.5rem] border border-white/10 p-8 md:p-16 shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] relative overflow-hidden my-auto">
        <!-- Close Button -->
        <button onclick="document.getElementById('createModal').style.display='none'" 
                class="absolute top-6 right-6 md:top-10 md:right-10 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/5 flex items-center justify-center text-white/30 hover:text-white hover:bg-white/10 transition-all active:scale-90">
            <i data-lucide="x" class="w-5 h-5 md:w-6 md:h-6"></i>
        </button>

        <div class="mb-8 md:mb-12">
            <h3 class="text-2xl md:text-3xl font-black italic tracking-tighter mb-2">Yeni Vebinar <span class="text-emerald-500">Planla</span></h3>
            <p class="text-white/40 text-xs md:text-sm font-medium">İştirakçılar üçün yeni bir tədris seansını burada yarada bilərsiniz.</p>
        </div>
        
        <form action="api/create_webinar.php" method="POST" class="space-y-8">
            <?php if ($user['role'] === 'admin' && !empty($filterItems)): ?>
            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Kafedra Seçin</label>
                <select name="department_id" required 
                        class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold text-white appearance-none">
                    <?php 
                    $currFac = "";
                    foreach ($filterItems as $item): 
                        if ($currFac !== $item['fac_name']):
                            if ($currFac !== "") echo "</optgroup>";
                            $currFac = $item['fac_name'];
                            echo "<optgroup label='" . e($currFac) . "' class='bg-[#0a1f44] text-emerald-400'>";
                        endif;
                    ?>
                        <option value="<?php echo $item['id']; ?>" class="bg-[#0a1f44] text-white"><?php echo e($item['name']); ?></option>
                    <?php endforeach; echo "</optgroup>"; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Vebinar Mövzusu</label>
                <input type="text" name="title" required 
                       class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold placeholder:text-white/10"
                       placeholder="Məs: Kiber Təhlükəsizlik Və Bulud Texnologiyaları">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Başlama Tarixi</label>
                    <input type="datetime-local" name="scheduled_at" required 
                           class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold text-white/40 min-h-[3.5rem] md:min-h-0">
                </div>
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Müddət (Dəq)</label>
                    <input type="number" name="duration" value="90" 
                           class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold"
                           placeholder="90">
                </div>
            </div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Dərs Haqda Qısa Qeyd</label>
                <textarea name="description" rows="3"
                          class="w-full bg-white/5 border border-white/10 rounded-2xl md:rounded-3xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/10"
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

<!-- Edit Modal (Mühazirəçi və Admin üçün) -->
<?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
<div id="editModal" class="fixed inset-0 z-[60] bg-[#060f23]/90 backdrop-blur-md hidden items-center justify-center p-4 animate-in fade-in duration-300 overflow-y-auto">
    <div class="bg-[#0a1f44] w-full max-w-2xl rounded-[2.5rem] md:rounded-[3.5rem] border border-white/10 p-8 md:p-16 shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] relative overflow-hidden my-auto">
        <!-- Close Button -->
        <button onclick="closeEditModal()" 
                class="absolute top-6 right-6 md:top-10 md:right-10 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/5 flex items-center justify-center text-white/30 hover:text-white hover:bg-white/10 transition-all active:scale-90">
            <i data-lucide="x" class="w-5 h-5 md:w-6 md:h-6"></i>
        </button>

        <div class="mb-8 md:mb-12">
            <h3 class="text-2xl md:text-3xl font-black italic tracking-tighter mb-2">Vebinarı <span class="text-blue-400">Redaktə Et</span></h3>
            <p class="text-white/40 text-xs md:text-sm font-medium">Gözləyən statusda olan vebinarın məlumatlarını burada dəyişə bilərsiniz.</p>
        </div>
        
        <form id="editForm" onsubmit="submitEditForm(event)" class="space-y-8">
            <input type="hidden" id="edit_id" name="id">
            
            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Vebinar Mövzusu</label>
                <input type="text" id="edit_title" name="title" required 
                       class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-blue-400/50 transition-all font-bold placeholder:text-white/10"
                       placeholder="Məs: Kiber Təhlükəsizlik Və Bulud Texnologiyaları">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Başlama Tarixi</label>
                    <input type="datetime-local" id="edit_scheduled_at" name="scheduled_at" required 
                           class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-blue-400/50 transition-all font-bold text-white/40 min-h-[3.5rem] md:min-h-0">
                </div>
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Müddət (Dəq)</label>
                    <input type="number" id="edit_duration" name="duration" value="90" 
                           class="w-full bg-white/5 border border-white/10 rounded-xl md:rounded-2xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-blue-400/50 transition-all font-bold"
                           placeholder="90">
                </div>
            </div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-4 md:ml-6">Dərs Haqda Qısa Qeyd</label>
                <textarea id="edit_description" name="description" rows="3"
                          class="w-full bg-white/5 border border-white/10 rounded-2xl md:rounded-3xl px-6 py-4 md:px-8 md:py-5 text-xs md:text-sm focus:outline-none focus:border-blue-400/50 transition-all font-medium placeholder:text-white/10"
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
// ===== DEPARTMENT FILTERING =====
function searchDepartments() {
    const query = document.getElementById('deptSearchInput').value.toLowerCase();
    const pills = document.querySelectorAll('.dept-pill');
    
    pills.forEach(pill => {
        if (!pill.getAttribute('data-name')) return; // Skip "Bütün Kafedralar"
        const name = pill.getAttribute('data-name').toLowerCase();
        if (name.includes(query)) {
            pill.style.display = 'block';
        } else {
            pill.style.display = 'none';
        }
    });
}

function filterByDept(deptId) {
    // Update active state of pills
    document.querySelectorAll('.dept-pill').forEach(pill => {
        pill.classList.remove('active', 'bg-emerald-500', 'text-white');
        pill.classList.add('bg-white/5', 'text-white/40');
    });
    
    const currentPill = event.currentTarget;
    currentPill.classList.add('active', 'bg-emerald-500', 'text-white');
    currentPill.classList.remove('bg-white/5', 'text-white/40');

    const cards = document.querySelectorAll('.webinar-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        if (deptId === 0 || card.getAttribute('data-dept-id') == deptId) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Handle empty state
    let existingMsg = document.getElementById('noWebinarsMsg');
    if (existingMsg) existingMsg.remove();

    if (visibleCount === 0) {
        const webinarList = document.getElementById('webinarList');
        const msg = document.createElement('div');
        msg.id = 'noWebinarsMsg';
        msg.className = 'col-span-full bg-white/[0.02] border border-dashed border-white/10 rounded-[3rem] p-24 text-center animate-in fade-in duration-500';
        msg.innerHTML = `
            <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-8 border border-white/5">
                <i data-lucide="calendar-x" class="w-10 h-10 text-white/10"></i>
            </div>
            <h4 class="text-xl font-bold mb-2">Vebinar tapılmadı</h4>
            <p class="text-white/30 text-sm font-medium">Bu kafedra üçün hələ ki, hər hansı bir vebinar planlaşdırılmayıb.</p>
        `;
        webinarList.appendChild(msg);
        if (window.lucide) window.lucide.createIcons();
    }
}

// Auto-open create modal if action=create is in URL
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'create') {
        const createModal = document.getElementById('createModal');
        if (createModal) createModal.style.display = 'flex';
    }
    
    // Show error or success messages from URL
    if (urlParams.has('error')) {
        showToast('Xəta: ' + decodeURIComponent(urlParams.get('error')), 'error');
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (urlParams.has('success')) {
        let msg = 'Əməliyyat uğurla tamamlandı.';
        if (urlParams.get('success') === 'webinar_created') msg = 'Vebinar uğurla yaradıldı!';
        showToast(msg, 'success');
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
