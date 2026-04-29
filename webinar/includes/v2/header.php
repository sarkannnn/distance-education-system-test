<?php
// includes/v2/header.php
if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Scripts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/v2/studio.css?v=<?php echo time(); ?>">
</head>
<body class="bg-[#060f23]">
    <header class="h-16 lg:h-20 border-b border-white/5 bg-[#0a1f44]/80 backdrop-blur-md flex items-center justify-between px-6 z-50">
        <!-- Logo & Title -->
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-2xl bg-emerald-500/20 flex items-center justify-center border border-emerald-500/20">
                <i data-lucide="video" class="w-6 h-6 text-emerald-400"></i>
            </div>
            <div>
                <h1 class="text-sm lg:text-base font-black text-white leading-none mb-1">
                    <?php echo e($webinar['title']); ?>
                </h1>
                <p class="text-[10px] text-white/40 font-bold uppercase tracking-widest">
                    <?php echo e($webinar['dept_name']); ?>
                </p>
            </div>
        </div>

        <!-- Session Status -->
        <div class="hidden md:flex items-center gap-6">
            <div class="flex flex-col items-end">
                <span class="text-[9px] font-black text-white/20 uppercase tracking-widest mb-1">
                    <?php echo ($user['role'] === 'teacher') ? 'Müəllim' : 'İştirakçı'; ?>
                </span>
                <span class="text-xs font-bold text-white/80"><?php echo e($user['full_name']); ?></span>
            </div>
            <div class="w-px h-8 bg-white/5"></div>
            
            <?php if ($user['role'] === 'teacher'): ?>
                <button onclick="endWebinar()" class="px-6 py-2.5 bg-rose-500 hover:bg-rose-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-rose-500/20 active:scale-95">
                    Yayıma Yekun Vur
                </button>
            <?php else: ?>
                <a href="dashboard.php" class="px-6 py-2.5 bg-white/5 hover:bg-white/10 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all border border-white/5 active:scale-95">
                    Dərsdən Ayrıl
                </a>
            <?php endif; ?>
        </div>

        <!-- Mobile Menu Toggle (Simplified) -->
        <div class="md:hidden">
            <button class="p-2 text-white/60">
                <i data-lucide="more-vertical"></i>
            </button>
        </div>
    </header>
