<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Only Admin can access
WebinarAuth::requireRole('admin');
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

// Fetch all departments with their users
$data = $db->fetchAll("
    SELECT d.id as dept_id, d.name as dept_name, f.name as fac_name,
           u.id as user_id, u.username, u.role, u.is_active, u.full_name
    FROM webinar_departments d
    JOIN webinar_faculties f ON d.faculty_id = f.id
    LEFT JOIN webinar_users u ON d.id = u.department_id
    ORDER BY f.name, d.name, u.role DESC
");

// Group data by department
$departments = [];
foreach ($data as $row) {
    $did = $row['dept_id'];
    if (!isset($departments[$did])) {
        $departments[$did] = [
            'id' => $did,
            'name' => $row['dept_name'],
            'faculty' => $row['fac_name'],
            'users' => []
        ];
    }
    if ($row['user_id']) {
        $departments[$did]['users'][] = [
            'id' => $row['user_id'],
            'username' => $row['username'],
            'role' => $row['role'],
            'is_active' => $row['is_active'],
            'full_name' => $row['full_name']
        ];
    }
}

$pageTitle = "İstifadəçi İdarəetmə Paneli";
require_once 'includes/header.php';
?>

<div class="animate-in fade-in slide-in-from-bottom-5 duration-700">
    <div class="flex flex-col md:flex-row justify-between items-end gap-6 mb-12">
        <div>
            <h2 class="text-4xl font-black tracking-tighter italic mb-4">İstifadəçi <span class="text-emerald-500">İdarəetməsi</span></h2>
            <p class="text-white/40 text-base font-medium max-w-2xl">Bütün kafedra hesablarını burada aktivləşdirə, deaktivləşdirə və şifrələrini yeniləyə bilərsiniz.</p>
        </div>
        
        <div class="relative w-full max-w-xs group">
            <i data-lucide="search" class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-white/20 group-focus-within:text-emerald-500 transition-colors"></i>
            <input type="text" id="adminSearchInput" onkeyup="searchAdminDepartments()" placeholder="Kafedra və ya Username axtar..." 
                   class="w-full bg-[#0a1f44] border border-white/10 rounded-[2rem] py-5 pl-16 pr-8 text-sm font-bold text-white focus:outline-none focus:border-emerald-500/50 transition-all shadow-2xl">
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" id="adminDeptGrid">
        <?php foreach ($departments as $dept): ?>
        <div class="dept-card group bg-[#0a1f44] border border-white/5 rounded-[3rem] p-10 hover:border-emerald-500/20 transition-all duration-500"
             data-name="<?php echo strtolower($dept['name'] . ' ' . $dept['faculty']); ?>">
            <div class="mb-8">
                <span class="text-[9px] font-black text-emerald-500 uppercase tracking-[0.3em] block mb-2 opacity-50"><?php echo e($dept['faculty']); ?></span>
                <h3 class="text-2xl font-black tracking-tight group-hover:text-emerald-400 transition-colors"><?php echo e($dept['name']); ?></h3>
            </div>

            <div class="space-y-6">
                <?php foreach ($dept['users'] as $u): ?>
                <div class="flex items-center justify-between p-6 bg-white/5 rounded-2xl border border-white/5">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                            <i data-lucide="<?php echo $u['role'] === 'teacher' ? 'user' : 'users'; ?>" class="w-5 h-5 text-white/30"></i>
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-white/80"><?php echo e($u['role'] === 'teacher' ? 'Mühazirəçi' : 'İştirakçı'); ?></p>
                            <p class="text-[10px] font-bold text-white/30">@<?php echo e($u['username']); ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Status Toggle -->
                        <button onclick="toggleUserStatus(<?php echo $u['id']; ?>, <?php echo $u['is_active']; ?>)" 
                                class="px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all <?php echo $u['is_active'] ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-500 border border-rose-500/20'; ?>">
                            <?php echo $u['is_active'] ? 'AKTİV' : 'DEAKTİV'; ?>
                        </button>
                        
                        <!-- PWD Reset -->
                        <button onclick="openPasswordModal(<?php echo $u['id']; ?>, '<?php echo e($u['username']); ?>')"
                                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 text-white/30 hover:text-white flex items-center justify-center transition-all">
                            <i data-lucide="key-round" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Password Reset Modal -->
<div id="pwdModal" class="fixed inset-0 z-[60] bg-[#060f23]/90 backdrop-blur-md hidden items-center justify-center p-4">
    <div class="bg-[#0a1f44] w-full max-w-md rounded-[3rem] border border-white/10 p-12 shadow-2xl relative">
        <button onclick="closePasswordModal()" class="absolute top-8 right-8 text-white/20 hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        <h3 class="text-2xl font-black italic mb-2">Şifrəni <span class="text-emerald-500">Yenilə</span></h3>
        <p class="text-white/40 text-xs mb-8">"<span id="modalUsername" class="text-white"></span>" üçün yeni şifrə təyin edin.</p>
        
        <input type="hidden" id="resetUserId">
        <div class="space-y-6">
            <div class="space-y-3">
                <label class="text-[9px] font-black text-white/30 uppercase tracking-[0.3em] ml-4">YENİ ŞİFRƏ</label>
                <input type="text" id="newPassword" 
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-6 py-4 text-sm font-bold focus:border-emerald-500/50 outline-none transition-all">
            </div>
            <button onclick="submitPasswordChange()" class="w-full bg-emerald-500 text-white font-black py-5 rounded-2xl text-[10px] uppercase tracking-widest shadow-xl shadow-emerald-500/20">TƏSDİQLƏ</button>
        </div>
    </div>
</div>

<script>
function searchAdminDepartments() {
    const query = document.getElementById('adminSearchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.dept-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        if (name.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function toggleUserStatus(userId, currentStatus) {
    const btn = event.currentTarget;
    const originalText = btn.innerText;
    btn.innerText = '...';
    btn.disabled = true;
    
    const newStatus = currentStatus ? 0 : 1;
    fetch('api/admin_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle_status', userId: userId, status: newStatus })
    }).then(r => r.json()).then(d => {
        if(d.success) window.location.reload();
        else {
            alert(d.message);
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }).catch(e => {
        alert('Xəta baş verdi');
        btn.innerText = originalText;
        btn.disabled = false;
    });
}

function openPasswordModal(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('modalUsername').innerText = username;
    document.getElementById('pwdModal').style.display = 'flex';
}

function closePasswordModal() {
    document.getElementById('pwdModal').style.display = 'none';
}

function submitPasswordChange() {
    const userId = document.getElementById('resetUserId').value;
    const newPwd = document.getElementById('newPassword').value;
    if(!newPwd) return alert('Şifrəni daxil edin');
    
    fetch('api/admin_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'change_password', userId: userId, password: newPwd })
    }).then(r => r.json()).then(d => {
        if(d.success) {
            alert('Şifrə uğurla dəyişdirildi!');
            closePasswordModal();
            document.getElementById('newPassword').value = '';
        } else alert(d.message);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
