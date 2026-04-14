<?php
require_once 'config/auth.php';
require_once 'config/database.php';

if (WebinarAuth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$db = WebinarDatabase::getInstance();
$faculties = $db->fetchAll("SELECT * FROM webinar_faculties ORDER BY id ASC");

$pageTitle = "Fakültə Seçimi - NDU Vebinar";
require_once 'includes/header.php';
?>

<div class="max-w-7xl mx-auto py-12 px-4">
    <!-- Premium Header -->
    <div class="text-center mb-16 space-y-4">
        <h2 class="text-5xl font-black italic tracking-tighter text-white">
            VEBİNAR <span class="text-emerald-500">PORTALI</span>
        </h2>
        <p class="text-white/40 font-medium tracking-widest uppercase text-xs">Naxçıvan Dövlət Universiteti • Distant Təhsil Sistemi</p>
        
        <div class="pt-8 max-w-xl mx-auto">
            <div class="relative group">
                <i data-lucide="search" class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-white/20 group-focus-within:text-emerald-500 transition-colors"></i>
                <input type="text" id="facultySearch" 
                       placeholder="Fakültə axtar..." 
                       class="w-full bg-white/5 border border-white/10 rounded-3xl py-5 pl-16 pr-6 text-sm focus:outline-none focus:border-emerald-500/50 transition-all backdrop-blur-xl"
                       onkeyup="filterFaculties()">
            </div>
        </div>
    </div>

    <!-- Faculty Grid -->
    <div id="facultyGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
        <?php foreach ($faculties as $f): ?>
            <div class="faculty-card group bg-[#0a1f44] rounded-[2.5rem] border border-white/5 p-8 transition-all duration-500 hover:border-emerald-500/50 hover:shadow-[0_20px_50px_-12px_rgba(16,185,129,0.2)]" 
                 data-name="<?php echo strtolower($f['name']); ?>">
                <div class="flex items-start justify-between mb-8">
                    <div class="w-16 h-16 rounded-3xl bg-white/5 flex items-center justify-center group-hover:bg-emerald-500/10 transition-all duration-500 group-hover:scale-110">
                        <i data-lucide="graduation-cap" class="w-8 h-8 text-white/20 group-hover:text-emerald-400"></i>
                    </div>
                </div>

                <h3 class="text-xl font-bold mb-8 leading-tight h-14 overflow-hidden"><?php echo e($f['name']); ?></h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <a href="login.php?faculty=<?php echo $f['slug']; ?>&role=teacher" 
                       class="flex items-center justify-center py-4 bg-white/5 hover:bg-emerald-500 text-white rounded-2xl font-bold text-[10px] uppercase tracking-widest transition-all active:scale-95 group/btn border border-white/5">
                        Müəllim
                    </a>
                    <a href="login.php?faculty=<?php echo $f['slug']; ?>&role=student" 
                       class="flex items-center justify-center py-4 bg-emerald-500/10 hover:bg-white text-emerald-400 hover:text-black rounded-2xl font-bold text-[10px] uppercase tracking-widest transition-all active:scale-95 border border-emerald-500/20 hover:border-white">
                        Tələbə
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function filterFaculties() {
    const query = document.getElementById('facultySearch').value.toLowerCase();
    const cards = document.querySelectorAll('.faculty-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name.includes(query)) {
            card.style.display = 'block';
            card.style.opacity = '1';
        } else {
            card.style.display = 'none';
            card.style.opacity = '0';
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
