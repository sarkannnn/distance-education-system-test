<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$db = WebinarDatabase::getInstance();
$user = WebinarAuth::getCurrentUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Admin uses main 'users' table, others use 'webinar_users'
    if ($user['role'] === 'admin') {
        require_once '../teacher/includes/database.php';
        $mainDb = Database::getInstance();
        $dbUser = $mainDb->fetch("SELECT * FROM users WHERE email = ?", [$_SESSION['webinar_username'] ?? 'admin@ndu.edu.az']);
        $passwordField = 'password';
    } else {
        $dbUser = $db->fetch("SELECT * FROM webinar_users WHERE id = ?", [$user['id']]);
        $passwordField = 'password_hash';
    }

    if (!$dbUser || !password_verify($oldPassword, $dbUser[$passwordField])) {
        $error = 'Köhnə şifrəniz yalnışdır.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Yeni şifrələr uyğun gəlmir.';
    } elseif (strlen($newPassword) < 6 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = 'Yeni şifrə ən azı 6 simvol uzunluğunda olmalı, tərkibində ən azı 1 böyük hərf və 1 rəqəm olmalıdır.';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        if ($user['role'] === 'admin') {
            $updated = $mainDb->execute("UPDATE users SET password = ? WHERE email = ?", [$newHash, $dbUser['email']]);
        } else {
            $updated = $db->update('webinar_users', ['password_hash' => $newHash], 'id = ?', [$user['id']]);
        }
        
        if ($updated !== false) {
            session_destroy();
            session_start();
            $_SESSION['system_success'] = 'Şifrəniz uğurla dəyişdirildi. Təhlükəsizlik məqsədilə sistemdən çıxış edildi. Zəhmət olmasa yeni şifrənizlə yenidən daxil olun.';
            header("Location: index.php");
            exit;
        } else {
            $error = 'Şifrəni yeniləyərkən xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.';
        }
    }
}

$pageTitle = "Hesabım - " . $user['faculty_name'];
require_once 'includes/header.php';
?>

<div class="animate-in fade-in slide-in-from-bottom-5 duration-700 max-w-4xl mx-auto px-4 py-8">
    <div class="mb-12 text-center">
        <div class="w-24 h-24 rounded-[2rem] bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-3xl font-black mx-auto mb-6 shadow-2xl shadow-blue-500/20">
            <?php 
                $names = explode(' ', $user['full_name']);
                echo strtoupper(substr($names[0] ?? '', 0, 1) . substr($names[1] ?? '', 0, 1));
            ?>
        </div>
        <h2 class="text-4xl font-black tracking-tighter italic mb-2"><?php echo e($user['full_name']); ?></h2>
        <p class="<?php echo $user['role'] === 'admin' ? 'text-amber-400' : 'text-emerald-500'; ?> font-black uppercase tracking-widest text-sm"><?php 
            if ($user['role'] === 'admin') echo 'SİSTEM ADMİNİ';
            elseif ($user['role'] === 'teacher') echo 'MÜHAZİRƏÇİ';
            else echo 'İŞTİRAKÇI';
        ?> • <?php echo e($user['faculty_name']); ?></p>
        <p class="text-white/40 font-bold uppercase tracking-widest text-[10px] mt-2">İstifadəçi Adı: <?php echo e($user['username']); ?></p>
    </div>

    <div class="bg-[#0a1f44] rounded-[3rem] border border-white/5 p-8 md:p-12 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-50"></div>
        
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 bg-emerald-500/10 rounded-2xl flex items-center justify-center border border-emerald-500/20">
                <i data-lucide="shield-check" class="w-6 h-6 text-emerald-400"></i>
            </div>
            <div>
                <h3 class="text-xl font-black uppercase tracking-widest">Şifrəni Yenilə</h3>
                <p class="text-xs text-white/40 font-medium mt-1">Hesabınızın təhlükəsizliyi üçün şifrənizi mütəmadi olaraq yeniləyin.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-500 p-6 rounded-3xl mb-8 flex items-start gap-4 animate-in shake duration-500">
                <i data-lucide="alert-circle" class="w-6 h-6 shrink-0 mt-0.5"></i>
                <div>
                    <h4 class="font-black uppercase tracking-widest text-sm mb-1">XƏTA BAŞ VERDİ</h4>
                    <p class="text-sm font-medium"><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 max-w-xl mx-auto">
            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] ml-4 flex items-center gap-2">
                    <i data-lucide="lock" class="w-3 h-3"></i> Mövcud Şifrə
                </label>
                <div class="relative">
                    <input type="password" name="old_password" id="old_password" required 
                           class="w-full bg-white/5 border border-white/10 rounded-3xl px-8 py-5 pr-14 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/20"
                           placeholder="Hazırkı şifrəniz">
                    <button type="button" onclick="togglePassword('old_password')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer p-2">
                        <i data-lucide="eye" id="eye_old_password" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="h-px w-full bg-gradient-to-r from-transparent via-white/10 to-transparent my-8"></div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] ml-4 flex items-center gap-2">
                    <i data-lucide="key" class="w-3 h-3"></i> Yeni Şifrə
                </label>
                <div class="relative">
                    <input type="password" name="new_password" id="new_password" required minlength="6"
                           class="w-full bg-white/5 border border-white/10 rounded-3xl px-8 py-5 pr-14 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/20"
                           placeholder="Yeni şifrəniz">
                    <button type="button" onclick="togglePassword('new_password')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer p-2">
                        <i data-lucide="eye" id="eye_new_password" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="bg-blue-500/5 border border-blue-500/20 rounded-2xl p-5 mb-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-blue-400 mb-3 ml-2">Şifrə Tələbləri:</p>
                <ul class="text-[11px] text-white/60 font-medium space-y-2 ml-2">
                    <li class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]"></div> Ən azı 6 simvol uzunluğunda olmalıdır.</li>
                    <li class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]"></div> Ən azı 1 böyük hərf (A-Z) olmalıdır.</li>
                    <li class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]"></div> Ən azı 1 rəqəm (0-9) olmalıdır.</li>
                </ul>
            </div>

            <div class="space-y-3">
                <label class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] ml-4 flex items-center gap-2">
                    <i data-lucide="key-round" class="w-3 h-3"></i> Yeni Şifrə (Təkrar)
                </label>
                <div class="relative">
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                           class="w-full bg-white/5 border border-white/10 rounded-3xl px-8 py-5 pr-14 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/20"
                           placeholder="Yeni şifrənizi təsdiqləyin">
                    <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer p-2">
                        <i data-lucide="eye" id="eye_confirm_password" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="pt-6">
                <button type="submit" 
                        class="w-full bg-emerald-500 text-white py-6 rounded-3xl font-black text-[11px] uppercase tracking-[0.2em] hover:bg-emerald-400 transform transition-all active:scale-95 shadow-[0_20px_40px_-15px_rgba(16,185,129,0.5)] flex items-center justify-center gap-3 group">
                    <i data-lucide="save" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                    DƏYİŞİKLİKLƏRİ YADDA SAXLA
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById('eye_' + inputId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}
</script>

<?php require_once 'includes/footer.php'; ?>
