<?php
require_once 'config/auth.php';
require_once 'config/database.php';

if (WebinarAuth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$db = WebinarDatabase::getInstance();
$faculties = $db->fetchAll("SELECT * FROM webinar_faculties ORDER BY id ASC");
$departments = $db->fetchAll("SELECT * FROM webinar_departments ORDER BY name ASC");

$pageTitle = "Vebinar Portalı - NDU";
require_once 'includes/header.php';
?>

<div class="max-w-7xl mx-auto py-12 px-4 min-h-[80vh] flex flex-col justify-center">
    <!-- Premium Header -->
    <div class="text-center mb-16 space-y-4 animate-in fade-in zoom-in duration-1000">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-black text-emerald-400 uppercase tracking-[0.2em] mb-4">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            Canlı Tədris Sistemi
        </div>
        <h2 class="text-6xl md:text-8xl font-black italic tracking-tighter text-white leading-none">
            VEBİNAR <span class="text-emerald-500">PORTALI</span>
        </h2>
        <p class="text-white/40 font-bold tracking-[0.3em] uppercase text-[10px] md:text-xs">Naxçıvan Dövlət Universiteti • Distant Təhsil Bölməsi</p>
        
        <div class="pt-12 max-w-2xl mx-auto">
            <div class="relative group">
                <i data-lucide="search" class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-white/20 group-focus-within:text-emerald-500 transition-colors"></i>
                <input type="text" id="facultySearch" 
                       placeholder="Fakültə və ya struktur axtar..." 
                       class="w-full bg-white/5 border border-white/10 rounded-[2rem] py-6 pl-16 pr-6 text-sm focus:outline-none focus:border-emerald-500/50 transition-all backdrop-blur-xl font-bold placeholder:text-white/10"
                       onkeyup="filterFaculties()">
            </div>
        </div>
    </div>

    <!-- Faculty Grid -->
    <div id="facultyGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 animate-in fade-in slide-in-from-bottom-10 duration-1000 delay-300">
        <?php foreach ($faculties as $f): ?>
            <div onclick="showDepartments(<?php echo $f['id']; ?>, '<?php echo e($f['name']); ?>')" 
                 class="faculty-card group relative bg-[#0a1f44]/40 hover:bg-[#0a1f44] rounded-[2.5rem] border border-white/5 p-8 transition-all duration-500 cursor-pointer hover:border-emerald-500/30 hover:-translate-y-2" 
                 data-name="<?php echo strtolower($f['name']); ?>">
                
                <div class="flex items-center justify-between mb-8">
                    <div class="w-14 h-14 rounded-2xl bg-white/5 flex items-center justify-center group-hover:bg-emerald-500/10 transition-all duration-500">
                        <i data-lucide="layout-grid" class="w-6 h-6 text-white/20 group-hover:text-emerald-400"></i>
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                        <i data-lucide="arrow-right" class="w-6 h-6 text-emerald-500"></i>
                    </div>
                </div>

                <h3 class="text-lg font-black leading-tight mb-2"><?php echo e($f['name']); ?></h3>
                <p class="text-[10px] text-white/20 font-bold uppercase tracking-widest">
                    <?php 
                        $count = count(array_filter($departments, function($d) use ($f) { return $d['faculty_id'] == $f['id']; }));
                        echo $count;
                    ?> KAFEDRA / ŞÖBƏ
                </p>

                <!-- Decorative blur -->
                <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl group-hover:bg-emerald-500/10 transition-colors"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Department Modal -->
<div id="deptModal" class="fixed inset-0 z-[100] bg-[#060f23]/95 backdrop-blur-xl hidden items-center justify-center p-4 md:p-8 animate-in fade-in duration-300">
    <div class="bg-[#0a1f44] w-full max-w-4xl max-h-[90vh] rounded-[3.5rem] border border-white/10 flex flex-col shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] overflow-hidden">
        <!-- Modal Header -->
        <div class="p-10 md:p-12 border-b border-white/5 flex items-center justify-between shrink-0">
            <div>
                <h3 id="modalFacultyName" class="text-3xl md:text-4xl font-black italic tracking-tighter text-white mb-2">Fakültə Adı</h3>
                <p class="text-emerald-500 font-bold text-[10px] md:text-xs uppercase tracking-[0.3em]">Müvafiq kafedranı seçib daxil olun</p>
            </div>
            <button onclick="hideDepartments()" class="w-14 h-14 rounded-full bg-white/5 flex items-center justify-center text-white/20 hover:text-white hover:bg-white/10 transition-all active:scale-90">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Search in Modal -->
        <div class="px-10 md:px-12 py-6 border-b border-white/5 shrink-0">
            <div class="relative">
                <i data-lucide="search" class="absolute left-6 top-1/2 -translate-y-1/2 w-4 h-4 text-white/20"></i>
                <input type="text" id="deptSearch" placeholder="Kafedra axtar..." 
                       class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 pl-14 pr-6 text-xs focus:outline-none focus:border-emerald-500/50 transition-all font-bold"
                       onkeyup="filterDepartments()">
            </div>
        </div>

        <!-- Modal Body (Department List) -->
        <div id="deptList" class="p-6 md:p-12 overflow-y-auto custom-scrollbar grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Dynamic Content -->
        </div>

        <!-- Modal Footer -->
        <div class="p-8 bg-white/[0.02] border-t border-white/5 text-center shrink-0">
            <p class="text-[9px] text-white/20 font-bold uppercase tracking-[0.2em]">Siyahıda kafedranız yoxdursa texniki şöbəyə müraciət edin</p>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.2); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.4); }
</style>

<script>
const departments = <?php echo json_encode($departments); ?>;

function showDepartments(facId, facName) {
    const modal = document.getElementById('deptModal');
    const modalTitle = document.getElementById('modalFacultyName');
    const deptList = document.getElementById('deptList');
    
    modalTitle.innerText = facName;
    deptList.innerHTML = '';
    
    const facultyDepts = departments.filter(d => d.faculty_id == facId);
    
    facultyDepts.forEach(dept => {
        const item = document.createElement('div');
        item.className = 'dept-item group bg-white/5 hover:bg-emerald-500 p-6 rounded-3xl cursor-pointer transition-all border border-white/5 hover:border-transparent flex items-center justify-between';
        item.setAttribute('data-name', dept.name.toLowerCase());
        item.onclick = () => window.location.href = `login.php?dept=${dept.id}`;
        
        item.innerHTML = `
            <div>
                <h4 class="text-sm font-black text-white/80 group-hover:text-white transition-colors uppercase tracking-tight">${dept.name}</h4>
                <div class="flex items-center gap-2 mt-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 group-hover:bg-white animate-pulse"></span>
                    <span class="text-[9px] font-bold text-white/20 group-hover:text-white/60 tracking-widest uppercase italic">Giriş Paneli</span>
                </div>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-white/10 group-hover:text-white transition-all transform group-hover:translate-x-1"></i>
        `;
        deptList.appendChild(item);
    });
    
    if (window.lucide) window.lucide.createIcons();
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideDepartments() {
    document.getElementById('deptModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function filterFaculties() {
    const query = document.getElementById('facultySearch').value.toLowerCase();
    const cards = document.querySelectorAll('.faculty-card');
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        card.style.display = name.includes(query) ? 'block' : 'none';
    });
}

function filterDepartments() {
    const query = document.getElementById('deptSearch').value.toLowerCase();
    const items = document.querySelectorAll('.dept-item');
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        item.style.display = name.includes(query) ? 'flex' : 'none';
    });
}

// Close modal on escape
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideDepartments();
});
</script>

<?php require_once 'includes/footer.php'; ?>
