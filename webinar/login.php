<?php
require_once 'config/auth.php';
require_once 'config/database.php';

if (WebinarAuth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$facultySlug = $_GET['faculty'] ?? '';
$role = $_GET['role'] ?? 'student'; // Default to student

if (!in_array($role, ['teacher', 'student'])) $role = 'student';

$db = WebinarDatabase::getInstance();
$faculty = $db->fetch("SELECT * FROM webinar_faculties WHERE slug = ?", [$facultySlug]);

if (!$faculty) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = $db->fetch(
        "SELECT u.*, f.name as faculty_name, f.slug as faculty_slug 
         FROM webinar_users u 
         JOIN webinar_faculties f ON u.faculty_id = f.id 
         WHERE u.username = ? AND u.role = ? AND u.faculty_id = ?",
        [$username, $role, $faculty['id']]
    );

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['webinar_user_id'] = $user['id'];
        $_SESSION['webinar_username'] = $user['username'];
        $_SESSION['webinar_full_name'] = $user['full_name'];
        $_SESSION['webinar_role'] = $user['role'];
        $_SESSION['webinar_faculty_id'] = $user['faculty_id'];
        $_SESSION['webinar_faculty_name'] = $user['faculty_name'];
        $_SESSION['webinar_faculty_slug'] = $user['faculty_slug'];

        header('Location: dashboard.php');
        exit;
    } else {
        $error = "İstifadəçi adı və ya şifrə yanlışdır.";
    }
}

$pageTitle = ($role === 'teacher' ? 'Mühazirəçi' : 'İştirakçı') . " Girişi - " . $faculty['name'];
require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto py-20">
    <div class="bg-[#0a1f44] rounded-[2.5rem] border border-white/5 p-10 shadow-2xl">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl <?php echo $role === 'teacher' ? 'bg-slate-700' : 'bg-blue-600'; ?> mb-6 shadow-2xl">
                <i data-lucide="<?php echo $role === 'teacher' ? 'user-circle' : 'graduation-cap'; ?>" class="w-10 h-10 text-white"></i>
            </div>
            <h2 class="text-2xl font-bold mb-1 uppercase tracking-tight"><?php echo $role === 'teacher' ? 'Mühazirəçi' : 'İştirakçı'; ?> Girişi</h2>
            <p class="text-white/40 text-xs font-bold uppercase tracking-widest"><?php echo e($faculty['name']); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-500 p-4 rounded-2xl mb-8 text-sm font-medium text-center">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-white/40 uppercase tracking-widest ml-4">İstifadəçi Adı</label>
                <input type="text" name="username" required 
                       class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium"
                       placeholder="İstifadəçi adınız">
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-bold text-white/40 uppercase tracking-widest ml-4">Şifrə</label>
                <div class="relative">
                    <input type="password" name="password" id="login_password" required 
                           class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 pr-12 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium"
                           placeholder="••••••••">
                    <button type="button" onclick="toggleLoginPassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer p-1">
                        <i data-lucide="eye" id="eye_login_password" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <button type="submit" 
                    class="w-full bg-emerald-500 text-white py-5 rounded-2xl font-bold text-sm uppercase tracking-widest hover:bg-emerald-400 transform transition-all active:scale-95 shadow-lg shadow-emerald-500/20">
                Sistemə Daxil Ol
            </button>
        </form>

        <div class="mt-8 pt-8 border-t border-white/5 text-center">
            <a href="index.php" class="text-xs font-bold text-white/30 hover:text-white transition-colors uppercase tracking-widest flex items-center justify-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Fakültə seçiminə qayıt
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
