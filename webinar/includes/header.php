<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'NDU Vebinar'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .webinar-emerald { background: #10b981; }
        .text-emerald { color: #10b981; }
    </style>
</head>
<body class="bg-[#060f23] text-white min-h-screen">
<?php if (WebinarAuth::isLoggedIn()): ?>
    <header class="border-b border-white/5 bg-[#0a1f44]/80 backdrop-blur-xl sticky top-0 z-50">
        <div class="container mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4 group cursor-pointer" onclick="window.location.href='dashboard.php'">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 group-hover:scale-105 transition-transform">
                    <img src="../assets/logo.png" alt="Logo" class="w-8 h-8 object-contain">
                </div>
                <div class="hidden sm:block">
                    <h1 class="text-sm font-black uppercase tracking-widest text-white/90">Vebinar <span class="text-emerald-500">Portalı</span></h1>
                    <p class="text-[9px] text-white/30 font-bold uppercase tracking-[0.2em] mt-0.5 leading-none"><?php echo e($_SESSION['webinar_faculty_name']); ?></p>
                </div>
            </div>
            
            <nav class="flex items-center bg-white/5 p-1 rounded-xl md:rounded-2xl border border-white/5 mx-2">
                <a href="dashboard.php" class="px-3 md:px-6 py-1.5 md:py-2 rounded-lg md:rounded-xl text-[9px] md:text-xs font-bold uppercase tracking-widest transition-all flex items-center justify-center whitespace-nowrap <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/40 hover:text-white'; ?>">
                    Ana <span class="hidden sm:inline">&nbsp;Panel</span>
                </a>
                <a href="archive.php" class="px-3 md:px-6 py-1.5 md:py-2 rounded-lg md:rounded-xl text-[9px] md:text-xs font-bold uppercase tracking-widest transition-all flex items-center justify-center whitespace-nowrap <?php echo strpos($_SERVER['PHP_SELF'], 'archive.php') !== false ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/40 hover:text-white'; ?>">
                    Arxiv
                </a>
                <a href="account.php" class="px-3 md:px-6 py-1.5 md:py-2 rounded-lg md:rounded-xl text-[9px] md:text-xs font-bold uppercase tracking-widest transition-all flex items-center justify-center whitespace-nowrap <?php echo strpos($_SERVER['PHP_SELF'], 'account.php') !== false ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/40 hover:text-white'; ?>">
                    Hesabım
                </a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-6">
                <div class="flex items-center gap-2 sm:gap-3 pl-2 sm:pl-6 border-l border-white/10">
                    <div class="hidden lg:block text-right">
                        <p class="text-xs font-bold text-white leading-tight"><?php echo e($_SESSION['webinar_full_name']); ?></p>
                        <p class="text-[9px] font-black uppercase tracking-widest mt-0.5 <?php echo $_SESSION['webinar_role'] === 'admin' ? 'text-amber-400' : 'text-emerald-500'; ?>"><?php 
                            if ($_SESSION['webinar_role'] === 'admin') echo 'SİSTEM ADMİNİ';
                            elseif ($_SESSION['webinar_role'] === 'teacher') echo 'Mühazirəçi';
                            else echo 'İştirakçı';
                        ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-xs font-black shadow-lg shadow-emerald-500/20">
                        <?php 
                            $names = explode(' ', $_SESSION['webinar_full_name']);
                            echo strtoupper(substr($names[0] ?? '', 0, 1) . substr($names[1] ?? '', 0, 1));
                        ?>
                    </div>
                    <a href="logout.php" class="w-10 h-10 rounded-xl bg-rose-500/10 hover:bg-rose-500 group flex items-center justify-center transition-all shadow-lg" title="Çıxış">
                        <i data-lucide="log-out" class="w-5 h-5 text-rose-500 group-hover:text-white transition-colors"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>
<?php endif; ?>
<main class="container mx-auto px-4 py-8">
