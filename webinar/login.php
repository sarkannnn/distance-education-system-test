<?php
require_once 'config/auth.php';
require_once 'config/database.php';

if (WebinarAuth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$deptId = $_GET['dept'] ?? 0;
$db = WebinarDatabase::getInstance();

$department = $db->fetch("
    SELECT d.*, f.name as faculty_name 
    FROM webinar_departments d 
    JOIN webinar_faculties f ON d.faculty_id = f.id 
    WHERE d.id = ?", 
    [$deptId]
);

if (!$department) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check user with department filter and is_active status
    $user = $db->fetch(
        "SELECT u.*, f.name as faculty_name, f.slug as faculty_slug, d.name as dept_name 
         FROM webinar_users u 
         JOIN webinar_faculties f ON u.faculty_id = f.id 
         LEFT JOIN webinar_departments d ON u.department_id = d.id
         WHERE u.username = ? AND u.department_id = ? AND u.is_active = 1",
        [$username, $department['id']]
    );

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['webinar_user_id'] = $user['id'];
        $_SESSION['webinar_username'] = $user['username'];
        $_SESSION['webinar_full_name'] = $user['full_name'];
        $_SESSION['webinar_role'] = $user['role'];
        $_SESSION['webinar_faculty_id'] = $user['faculty_id'];
        $_SESSION['webinar_faculty_name'] = $user['faculty_name'];
        $_SESSION['webinar_faculty_slug'] = $user['faculty_slug'];
        $_SESSION['webinar_department_id'] = $user['department_id'];
        $_SESSION['webinar_department_name'] = $user['dept_name'];

        header('Location: dashboard.php');
        exit;
    } else {
        $error = "İstifadəçi adı və ya şifrə yanlışdır.";
    }
}

$pageTitle = "Giriş Paneli - " . $department['name'];
require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto py-20 animate-in fade-in slide-in-from-bottom-10 duration-700">
    <div class="bg-[#0a1f44] rounded-[3.5rem] border border-white/5 p-12 shadow-2xl relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute -top-10 -right-10 w-32 h-32 bg-emerald-500/10 rounded-full blur-3xl"></div>
        
        <div class="text-center mb-10 relative z-10">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-emerald-500/10 border border-emerald-500/20 mb-8 shadow-2xl">
                <i data-lucide="shield-check" class="w-10 h-10 text-emerald-500"></i>
            </div>
            <h2 class="text-3xl font-black italic tracking-tighter mb-2 uppercase">
                Sistemə <span class="text-emerald-500">Giriş</span>
            </h2>
            <div class="space-y-1">
                <p class="text-white/40 text-[10px] font-black uppercase tracking-[0.2em]"><?php echo e($department['faculty_name']); ?></p>
                <p class="text-white/80 text-xs font-bold uppercase tracking-widest"><?php echo e($department['name']); ?></p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-500 p-5 rounded-2xl mb-8 text-xs font-bold text-center animate-shake">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 relative z-10">
            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">İstifadəçi Adı</label>
                <input type="text" name="username" required
                    class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold placeholder:text-white/10"
                    placeholder="Kafedra və ya şəxsi istifadəçi adı">
            </div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] ml-6">Şifrə</label>
                <div class="relative">
                    <input type="password" name="password" id="login_password" required
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 pr-14 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-bold placeholder:text-white/10"
                        placeholder="••••••••">
                    <button type="button" onclick="toggleLoginPassword()"
                        class="absolute right-5 top-1/2 -translate-y-1/2 text-white/20 hover:text-emerald-400 transition-colors cursor-pointer p-1">
                        <i data-lucide="eye" id="eye_login_password" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="group relative w-full bg-emerald-500 text-white py-6 rounded-2xl font-black text-xs uppercase tracking-[0.3em] hover:bg-emerald-400 transition-all active:scale-95 shadow-[0_20px_40px_-10px_rgba(16,185,129,0.5)] overflow-hidden">
                <span class="relative z-10 flex items-center justify-center gap-3">
                    <i data-lucide="log-in" class="w-5 h-5"></i>
                    DAXİL OL
                </span>
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
            </button>
        </form>

        <div class="mt-12 pt-8 border-t border-white/5 text-center relative z-10">
            <a href="index.php"
                class="text-[10px] font-black text-white/20 hover:text-white transition-colors uppercase tracking-[0.3em] flex items-center justify-center gap-3">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                BAŞA QAYIT
            </a>
        </div>
    </div>
</div>

<script>
    function toggleLoginPassword() {
        const input = document.getElementById('login_password');
        const icon = document.getElementById('eye_login_password');
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>