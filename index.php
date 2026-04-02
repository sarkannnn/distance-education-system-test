<?php

/**
 * 🎓 NDU Distant Təhsil Sistemi - Publik Cədvəl & Arxiv (V2 UI)
 * Image-based exact recreation of the public-schedule layout.
 */
require_once 'student/config/database.php';
require_once 'student/includes/helpers.php';

$db = Database::getInstance();

// Bu günün Azərbaycan dilində adını al
$weekdays = [
    1 => 'Bazar ertəsi',
    2 => 'Çərşənbə axşamı',
    3 => 'Çərşənbə',
    4 => 'Cümə axşamı',
    5 => 'Cümə',
    6 => 'Şənbə',
    0 => 'Bazar'
];
$todayName = $weekdays[date('w')];

// 1. Bugünkü dərslər - Sadece HAL-HAZIRDA canlı olan dərsləri göstər (Live)
$todayLessons = [];
try {
    $todayLessons = $db->fetchAll(
        "SELECT DISTINCT  lc.id, lc.title as topic_name, lc.status, lc.start_time,
                COALESCE(NULLIF(lc.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name IS NOT NULL AND lc.specialty_name != '' AND lc.specialty_name != 'Axın (çoxlu ixtisas)' THEN lc.specialty_name ELSE COALESCE(NULLIF(NULLIF(lc.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') END) as specialization_name,
                COALESCE(NULLIF(lc.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(lc.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(lc.instructor_title, ''), i.title, '') as instructor_title
         FROM live_classes lc
         LEFT JOIN courses c ON lc.course_id = c.tmis_subject_id OR lc.course_id = c.id
         LEFT JOIN instructors i ON lc.instructor_id = i.user_id OR lc.instructor_id = i.id
         LEFT JOIN users u ON i.user_id = u.id OR lc.instructor_id = u.id
         WHERE lc.status = 'live'
         ORDER BY lc.start_time ASC"
    );
} catch (Exception $e) {
    if (isset($_GET['debug']))
        echo "Today Error: " . $e->getMessage();
    $todayLessons = [];
}

// 2. Keçirilmiş dərslər (Arxiv) - Ən sonuncu dərsi ən üstdə göstər
$archivedLessons = [];
try {
    // a. Canlı dərslərin yazıları
    $liveRecs = $db->fetchAll(
        "SELECT DISTINCT lc.id, 
                lc.title as topic_name, 
                COALESCE(NULLIF(lc.subject_name, 'Fənn'), c.title, 'Fənn') as course_title, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name IS NOT NULL AND lc.specialty_name != '' AND lc.specialty_name != 'Axın (çoxlu ixtisas)' THEN lc.specialty_name ELSE COALESCE(NULLIF(NULLIF(lc.specialty_name, ''), 'Axın (çoxlu ixtisas)'), i.specialty, i.department, 'Ümumi') END) as specialization_name,
                COALESCE(NULLIF(lc.course_level, '-'), i.course_level, '-') as course_level_val,
                COALESCE(NULLIF(lc.instructor_name, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), i.name, 'Müəllim təyin edilməyib') as instructor_display_name, 
                COALESCE(NULLIF(lc.instructor_title, ''), i.title, '') as instructor_title,
                lc.start_time as activity_date,
                lc.end_time as end_time,
                COALESCE(lc.duration_minutes, 0) as duration,
                0 as views,
                lc.recording_path as video_url,
                NULL as pdf_url,
                'live' as record_type
         FROM live_classes lc
         LEFT JOIN courses c ON lc.course_id = c.tmis_subject_id OR lc.course_id = c.id
         LEFT JOIN instructors i ON lc.instructor_id = i.user_id OR lc.instructor_id = i.id
         LEFT JOIN users u ON i.user_id = u.id OR lc.instructor_id = u.id
         WHERE lc.status = 'ended' AND lc.recording_path IS NOT NULL AND lc.recording_path != ''"
    );

    $allArchives = array_merge($liveRecs);
    // Sort by date (newest first - DESC)
    usort($allArchives, function ($a, $b) {
        $t1 = strtotime($a['activity_date']);
        $t2 = strtotime($b['activity_date']);
        return $t2 - $t1;
    });
    $archivedLessons = $allArchives;
} catch (Exception $e) {
    if (isset($_GET['debug']))
        echo "Archive Error: " . $e->getMessage();
    $archivedLessons = [];
}
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NDU — Distant Təhsil Platforması</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0a1f44',
                        secondary: '#060f23',
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Smooth Scroll (Lenis) -->
    <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/lenis@1.1.18/dist/lenis.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
        }

        .bg-grid {
            background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0);
            background-size: 40px 40px;
        }

        .hero-gradient {
            background: linear-gradient(135deg, #60a5fa 0%, #93c5fd 50%, #60a5fa 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Animations to mimic Frame Motion */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        .delay-6 {
            animation-delay: 0.6s;
        }

        #main-header.scrolled {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            background-color: #0d2551;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Optimization for hover artifacts */
        .group {
            will-change: transform;
            backface-visibility: hidden;
            transform: translateZ(0);
        }

        /* Scroll Reveal Animation */
        .reveal-item {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: opacity, transform;
        }

        .reveal-active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body class="bg-secondary text-white overflow-x-hidden">

    <!-- Header Section -->
    <header id="main-header" class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 bg-transparent py-5">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between">
                <!-- Logo Section -->
                <a href="#home" class="flex items-center gap-4 group">
                    <div class="relative">
                        <div
                            class="absolute -inset-3 bg-blue-500/10 rounded-[1.5rem] blur opacity-0 group-hover:opacity-100 transition-all duration-500">
                        </div>
                        <div
                            class="relative w-14 h-14 bg-white rounded-2xl overflow-hidden flex items-center justify-center border border-white/5 shadow-xl transition-transform duration-500 group-hover:scale-105">
                            <img src="assets/logo.png" alt="NDU Logo" class="w-full h-full object-contain p-1.5 pt-2">
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-bold text-base leading-tight tracking-tight uppercase">NAXÇIVAN
                            DÖVLƏT</span>
                        <span
                            class="text-white font-bold text-base leading-tight tracking-tight uppercase">UNİVERSİTETİ</span>
                    </div>
                </a>

                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex items-center gap-2">
                    <a href="#home"
                        class="px-4 py-2 text-sm font-medium text-blue-10/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300">Ana
                        Səhifə</a>
                    <a href="#about"
                        class="px-4 py-2 text-sm font-medium text-blue-50/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300">Haqqımızda</a>
                    <a href="#features"
                        class="px-4 py-2 text-sm font-medium text-blue-50/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300">Platforma</a>
                    <a href="#portals"
                        class="px-4 py-2 text-sm font-medium text-blue-50/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300">Portallar</a>
                    <a href="#contact"
                        class="px-4 py-2 text-sm font-medium text-blue-50/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-300">Əlaqə</a>
                </nav>

                <!-- Login Actions -->
                <div class="hidden lg:flex items-center gap-4 relative">
                    <div class="h-8 w-px bg-white/10 mx-2"></div>
                    <button id="login-btn"
                        class="flex items-center gap-3 px-6 py-3 bg-white/5 border border-white/20 text-white rounded-2xl font-bold text-xs tracking-widest hover:bg-white hover:text-primary transition-all duration-300 shadow-lg group">
                        <i data-lucide="log-in" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                        PORTALA GİRİŞ
                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-300"></i>
                    </button>
                    <!-- Dropdown -->
                    <div id="login-dropdown"
                        class="hidden absolute top-full right-0 mt-4 w-72 bg-[#0d2551] rounded-[2rem] shadow-[0_30px_60px_-12px_rgba(0,0,0,0.6)] border border-white/10 overflow-hidden transform transition-all duration-300 scale-95 opacity-0 z-[60]">
                        <div class="p-4 space-y-3">
                            <a href="student/login.php"
                                class="flex items-center gap-4 p-4 rounded-3xl bg-white/5 border border-white/5 hover:bg-white/10 hover:border-blue-500/30 transition-all group">
                                <div
                                    class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center shadow-[0_0_20px_rgba(37,99,235,0.3)] transition-transform group-hover:scale-110">
                                    <i data-lucide="graduation-cap" class="w-6 h-6 text-white"></i>
                                </div>
                                <div>
                                    <p class="text-white font-bold text-sm">Tələbə Portalı</p>
                                    <p class="text-blue-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                                        SİSTEMƏ GİRİŞ</p>
                                </div>
                            </a>

                            <a href="teacher/login.php"
                                class="flex items-center gap-4 p-4 rounded-3xl bg-white/5 border border-white/5 hover:bg-white/10 hover:border-slate-500/30 transition-all group">
                                <div
                                    class="w-12 h-12 rounded-2xl bg-slate-700 flex items-center justify-center shadow-[0_0_20px_rgba(71,85,105,0.3)] transition-transform group-hover:scale-110">
                                    <i data-lucide="user-circle" class="w-6 h-6 text-white"></i>
                                </div>
                                <div>
                                    <p class="text-white font-bold text-sm">Müəllim Portalı</p>
                                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                                        SİSTEMƏ GİRİŞ</p>
                                </div>
                            </a>
                        </div>
                        <div class="bg-black/20 p-4 text-center border-t border-white/5">
                            <p class="text-[9px] text-white/30 font-bold uppercase tracking-[0.3em]">NDU DİSTANT TƏHSİL
                                MƏRKƏZİ</p>
                        </div>
                    </div>
                </div>

                <!-- Mobile Menu Toggle -->
                <button id="mobile-toggle"
                    class="lg:hidden p-3 text-white hover:bg-white/10 rounded-2xl transition-colors border border-white/10">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu" class="hidden fixed inset-0 z-[49] bg-[#0a1f44] pt-24 px-4 transition-all duration-300">
        <nav class="flex flex-col gap-2">
            <a href="#home"
                class="px-6 py-4 text-lg font-medium text-blue-100 hover:text-white hover:bg-white/5 rounded-2xl">Ana
                Səhifə</a>
            <a href="#about"
                class="px-6 py-4 text-lg font-medium text-blue-100 hover:text-white hover:bg-white/5 rounded-2xl">Haqqımızda</a>
            <a href="#features"
                class="px-6 py-4 text-lg font-medium text-blue-100 hover:text-white hover:bg-white/5 rounded-2xl">Platforma</a>
            <a href="#portals"
                class="px-6 py-4 text-lg font-medium text-blue-100 hover:text-white hover:bg-white/5 rounded-2xl">Portallar</a>
            <a href="#contact"
                class="px-6 py-4 text-lg font-medium text-blue-100 hover:text-white hover:bg-white/5 rounded-2xl">Əlaqə</a>
        </nav>
        <div class="pt-6 border-t border-white/10 grid grid-cols-1 gap-4 mt-8">
            <a href="student/login.php"
                class="flex items-center justify-center gap-3 py-5 bg-white text-[#0a1f44] rounded-2xl font-bold">
                <i data-lucide="graduation-cap" class="w-5 h-5"></i> Tələbə Girişi
            </a>
            <a href="teacher/login.php"
                class="flex items-center justify-center gap-3 py-5 bg-white/10 border border-white/20 text-white rounded-2xl font-bold">
                <i data-lucide="user-circle" class="w-5 h-5"></i> Müəllim Girişi
            </a>
        </div>
    </div>

    <!-- Hero Section -->
    <section id="home" class="relative min-h-screen flex items-center overflow-hidden bg-secondary">
        <!-- BG Elements -->
        <div class="absolute inset-0 z-0 bg-[#0a1f44]">
            <!-- Arxa fon şəkli -->
            <img id="hero-img"
                src="https://azertag.az/xeber/naxchivan_dovlet_universitetinin_beynelxalq_elaqeleri_genislenir-1229195"
                class="w-full h-full object-cover opacity-50" alt="NDU Campus">

            <!-- Overlay (Shadowed Gradient) -->
            <div class="absolute inset-0 bg-gradient-to-r from-[#0a1f44] via-[#0a1f44]/90 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#0a1f44] via-transparent to-transparent"></div>
            <div class="absolute inset-0 bg-black/20 mix-blend-multiply"></div>
        </div>
        <div class="bg-grid absolute inset-0 opacity-[0.03] pointer-events-none"></div>

        <div
            class="relative z-10 container mx-auto px-4 lg:px-8 py-32 lg:py-0 flex flex-col lg:flex-row items-center gap-16">
            <div class="flex-1 max-w-4xl opacity-0 animate-fade-in">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500/20 border border-blue-400/20 rounded-full text-blue-300 text-xs font-bold tracking-widest uppercase mb-8 delay-1">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Naxçıvan Dövlət Universiteti — Distant Təhsil
                    Mərkəzi
                </div>
                <div class="delay-2">
                    <h1 class="text-white font-extrabold leading-tight mb-2" style="font-size: clamp(3rem, 6vw, 5rem);">
                        NAXÇIVAN DÖVLƏT</h1>
                    <h1 class="hero-gradient font-extrabold leading-tight mb-6"
                        style="font-size: clamp(3rem, 6vw, 5rem);">UNİVERSİTETİ</h1>
                    <h2 class="text-blue-100/90 font-semibold mb-8 leading-tight"
                        style="font-size: clamp(1.4rem, 3vw, 2.2rem);">Distant Təhsil Platforması</h2>
                </div>
                <p class="text-xl text-blue-100/60 leading-relaxed max-w-2xl mb-12 animate-fadeInUp"
                    style="animation-delay: 0.4s;">
                    Naxçıvan Dövlət Universitetinin köklü akademik ənənələrini rəqəmsal dünyanın sonsuz imkanları ilə
                    kəşf edin.
                    Zaman və məkan sərhədlərini aşaraq, NDU-nun intellektual gücünə ən müasir texnologiyalarla hər
                    yerdən qoşulun.
                </p>
                <div class="flex flex-col sm:flex-row gap-6 pt-12 animate-fadeInUp" style="animation-delay: 0.6s;">
                    <a href="#schedule"
                        class="group relative px-10 py-5 bg-white text-secondary rounded-full font-extrabold flex items-center justify-center gap-4 hover:scale-105 transition-all duration-500 shadow-[0_20px_50px_rgba(255,255,255,0.15)] overflow-hidden">
                        <div
                            class="w-10 h-10 rounded-full bg-secondary text-white flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i data-lucide="play" class="w-5 h-5 fill-current"></i>
                        </div>
                        <span class="text-lg">Dərslər və Cədvəl</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                    <a href="#about"
                        class="px-10 py-5 bg-white/5 border border-white/15 text-white rounded-full font-bold text-lg hover:bg-white/10 transition-all flex items-center justify-center">
                        Daha Ətrafı
                    </a>
                </div>
            </div>

            <!-- Side Card -->
            <div class="hidden xl:block relative opacity-0 animate-fade-in delay-6">
                <div
                    class="relative z-10 w-96 p-8 bg-white/10 backdrop-blur-2xl border border-white/20 rounded-[3rem] shadow-2xl">
                    <div class="space-y-8">
                        <div class="flex items-center gap-5">
                            <div
                                class="w-12 h-12 rounded-2xl bg-blue-500/20 flex items-center justify-center border border-blue-400/30 text-blue-400">
                                <i data-lucide="globe" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-bold">Qlobal Əlçatanlıq</h4>
                                <p class="text-blue-300 text-xs mt-1">Hər yerdən dərslərə qoşulma</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-5">
                            <div
                                class="w-12 h-12 rounded-2xl bg-indigo-500/20 flex items-center justify-center border border-indigo-400/30 text-indigo-400">
                                <i data-lucide="zap" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-bold">Canlı Dərslər</h4>
                                <p class="text-blue-300 text-xs mt-1">İnteraktiv tədris mühiti</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-5">
                            <div
                                class="w-12 h-12 rounded-2xl bg-cyan-500/20 flex items-center justify-center border border-cyan-400/30 text-cyan-400">
                                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h4 class="text-white font-bold text-sm">Təhsildə Uğur</h4>
                                <p class="text-blue-200/40 text-[10px]">Rəqəmsal monitorinq</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scroll Hint -->
        <div id="scroll-hint"
            class="absolute bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center gap-3 opacity-40 cursor-pointer animate-bounce">
            <span class="text-[10px] font-bold tracking-[0.3em] text-white uppercase">AŞAĞI DİYİRLƏYİN</span>
            <div class="w-6 h-10 border-2 border-white/30 rounded-full flex justify-center p-1.5">
                <div class="w-1 h-2 bg-white rounded-full"></div>
            </div>
        </div>
    </section>

    <!-- LESSONS & SCHEDULE SECTION (2 Column Layout) -->
    <section id="schedule" class="py-24 bg-secondary relative reveal-item">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8 items-start">

                <!-- 1st Column: Today's Lessons -->
                <div
                    class="flex-1 min-w-0 bg-white/5 backdrop-blur-xl border border-white/10 rounded-[3rem] overflow-hidden flex flex-col h-full self-stretch">
                    <div class="p-8 border-b border-white/10 flex items-center justify-between shrink-0">
                        <h2 class="text-xl font-extrabold flex items-center gap-3 text-white">
                            <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                            Bu Günün Dərsləri - <span id="current-day"><?php echo $todayName; ?></span>
                        </h2>
                    </div>
                    <!-- Fixed Height Container for exactly ~3 smaller cards -->
                    <div class="p-6 overflow-y-auto space-y-4 h-[580px] scrollbar-hide" data-lenis-prevent>
                        <?php if (empty($todayLessons)): ?>
                            <div class="flex flex-col items-center justify-center h-full text-center py-20">
                                <div
                                    class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6 border border-white/10">
                                    <i data-lucide="calendar-off" class="w-10 h-10 text-white/20"></i>
                                </div>
                                <p class="text-blue-100/40 font-medium">Bu gün üçün hələ ki, heç bir dərs planlaşdırılmayıb.
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($todayLessons as $lesson):
                                $isLive = ($lesson['status'] === 'live');
                                $startTime = date('H:i', strtotime($lesson['start_time']));
                                $endTime = date('H:i', strtotime($lesson['start_time'] . ' +90 minutes'));
                                ?>
                                <div
                                    class="p-6 bg-white/5 border-l-4 <?php echo $isLive ? 'border-l-red-500' : 'border-l-blue-500/30'; ?> border border-white/10 rounded-[2rem] relative group mb-2 transition-all hover:bg-white/10">
                                    <?php if ($isLive): ?>
                                        <div class="absolute top-5 right-5">
                                            <span
                                                class="px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-lg animate-pulse uppercase tracking-wider">CANLI</span>
                                        </div>
                                    <?php endif; ?>
                                    <h3
                                        class="text-lg font-bold text-white mb-4 line-clamp-1 group-hover:text-blue-400 transition-colors">
                                        <?php echo e($lesson['topic_name'] ?: $lesson['course_title']); ?>
                                    </h3>
                                    <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-[11px] text-white/40">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="clock" class="w-3.5 h-3.5 text-blue-400"></i>
                                            <span class="text-white/80"><?php echo $startTime; ?> -
                                                <?php echo $endTime; ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="user" class="w-3.5 h-3.5 text-blue-400"></i>
                                            <span
                                                class="text-white/80 line-clamp-1"><?php echo e($lesson['instructor_display_name'] ?: 'Müəllim təyin edilməyib'); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="graduation-cap" class="w-3.5 h-3.5 text-blue-400"></i>
                                            <span class="text-white/80">Kurs: <?php echo $lesson['course_level_val']; ?>-ci
                                                kurs</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-blue-400"></i>
                                            <span class="text-white/80 line-clamp-1">İxtisas:
                                                <?php echo e($lesson['specialization_name']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 col-span-2">
                                            <i data-lucide="monitor" class="w-3.5 h-3.5 text-blue-400"></i>
                                            <span class="text-white/80 line-clamp-1">Fənn:
                                                <?php echo e($lesson['course_title']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2nd Column: Past Lessons -->
                <div
                    class="flex-1 min-w-0 bg-white/5 backdrop-blur-xl border border-white/10 rounded-[3rem] overflow-hidden flex flex-col h-full self-stretch">
                    <div class="p-8 border-b border-white/10 shrink-0">
                        <h2 class="text-xl font-extrabold flex items-center gap-3 text-white">
                            <i data-lucide="archive" class="w-5 h-5 text-blue-400"></i>
                            Keçirilmiş Dərslər
                        </h2>
                    </div>
                    <!-- Fixed Height Container for exactly ~3 cards -->
                    <div class="p-6 overflow-y-auto space-y-4 h-[580px] scrollbar-hide" data-lenis-prevent>
                        <?php if (empty($archivedLessons)): ?>
                            <div class="flex flex-col items-center justify-center h-full text-center py-20">
                                <div
                                    class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6 border border-white/10">
                                    <i data-lucide="video-off" class="w-10 h-10 text-white/20"></i>
                                </div>
                                <p class="text-blue-100/40 font-medium">Hələ ki, heç bir keçirilmiş dərs yazısı yoxdur.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($archivedLessons as $archive): ?>
                                <div
                                    class="p-6 bg-white/5 border-l-4 border-l-blue-900 border border-white/10 rounded-[2rem] hover:bg-white/10 transition-all group">
                                    <h4
                                        class="text-lg font-bold text-white mb-4 group-hover:text-blue-400 transition-colors line-clamp-1">
                                        <?php echo e($archive['topic_name'] ?: $archive['course_title']); ?>
                                    </h4>
                                    <div class="space-y-2 mb-6 text-xs font-medium">
                                        <div class="flex gap-2"><span
                                                class="text-white/40 w-28 shrink-0 uppercase tracking-tighter">Fənn:</span>
                                            <span
                                                class="text-blue-400 line-clamp-1"><?php echo e($archive['course_title']); ?></span>
                                        </div>
                                        <div class="flex gap-2"><span
                                                class="text-white/40 w-28 shrink-0 uppercase tracking-tighter">İxtisas:</span>
                                            <span
                                                class="text-white/90 line-clamp-1"><?php echo e($archive['specialization_name']); ?></span>
                                        </div>
                                        <div class="flex gap-2"><span
                                                class="text-white/40 w-28 shrink-0 uppercase tracking-tighter">Kurs:</span>
                                            <span class="text-white/90"><?php echo $archive['course_level_val']; ?>-cü
                                                kurs</span>
                                        </div>
                                        <div class="flex gap-2"><span
                                                class="text-white/40 w-28 shrink-0 uppercase tracking-tighter">Müddət:</span>
                                            <span class="text-white/90">
                                                <?php echo is_numeric($archive['duration']) ? $archive['duration'] . ' dəq' : ($archive['duration'] ?: '0 dəq'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between pt-4 border-t border-white/5">
                                        <div
                                            class="flex items-center gap-2 text-[10px] text-white/40 font-bold uppercase tracking-wider">
                                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                            <?php echo date('d.m.Y', strtotime($archive['activity_date'])); ?>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ABOUT SECTION (Refocused on Platform) -->
    <section id="about" class="py-24 bg-secondary relative overflow-hidden reveal-item">
        <div class="container mx-auto px-4 lg:px-8 relative z-10">

            <!-- Section Header (Statistics Removed) -->
            <div class="text-center mb-20 animate-fadeInUp">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600/10 border border-blue-500/20 rounded-full text-blue-400 text-xs font-bold tracking-[0.2em] uppercase mb-6">
                    <i data-lucide="layers" class="w-4 h-4 text-blue-400"></i> Platforma Haqqında
                </div>
                <h2 class="text-4xl lg:text-5xl font-extrabold text-white mb-6">NDU Distant Təhsil Ekosistemi</h2>
                <p class="text-blue-100/40 max-w-3xl mx-auto text-lg leading-relaxed">
                    Müasir texnologiyalarla təchiz olunmuş bu platforma, təhsilin hər kəs üçün və hər yerdə əlçatan
                    olması üçün yaradılmışdır.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Mission -->
                <div
                    class="p-10 bg-white/5 backdrop-blur-xl border border-white/10 rounded-[3rem] hover:bg-white/10 transition-all text-center group">
                    <div
                        class="w-20 h-20 bg-blue-600/10 rounded-3xl flex items-center justify-center text-blue-400 mx-auto mb-8 group-hover:bg-blue-600 group-hover:text-white transition-all duration-500">
                        <i data-lucide="zap" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-6">Platformanın Məqsədi</h3>
                    <p class="text-blue-100/60 leading-relaxed text-sm">
                        Tələbələrin məkan asılılığı olmadan dərslərə davamlılığını təmin etmək və bütün tədris
                        materiallarını vahid rəqəmsal mərkəzdə mütəşəkkil formada toplamaqdır.
                    </p>
                </div>

                <!-- Vision -->
                <div
                    class="p-10 bg-white/5 backdrop-blur-xl border border-white/10 rounded-[3rem] hover:bg-white/10 transition-all text-center group">
                    <div
                        class="w-20 h-20 bg-emerald-600/10 rounded-3xl flex items-center justify-center text-emerald-400 mx-auto mb-8 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-500">
                        <i data-lucide="monitor" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-6">Texniki İmkanlar</h3>
                    <p class="text-blue-100/60 leading-relaxed text-sm">
                        Stabil canlı dərs bağlantısı, fənn resurslarının onlayn idarəedilməsi və dərslərin
                        arxivləşdirilməsi vasitəsilə keyfiyyətli distant tədris mühiti təqdim edirik.
                    </p>
                </div>

                <!-- Values -->
                <div
                    class="p-10 bg-white/5 backdrop-blur-xl border border-white/10 rounded-[3rem] hover:bg-white/10 transition-all text-center group">
                    <div
                        class="w-20 h-20 bg-purple-600/10 rounded-3xl flex items-center justify-center text-purple-400 mx-auto mb-8 group-hover:bg-purple-600 group-hover:text-white transition-all duration-500">
                        <i data-lucide="shield-check" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-6">Prinsiplərimiz</h3>
                    <p class="text-blue-100/60 leading-relaxed text-sm">
                        Məlumatların tam təhlükəsizliyi, akademik şəffaflıq və hər bir istifadəçiyə (tələbə/müəllim)
                        fərdi yanaşma bizim əsas iş prinsipimizdir.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Platform Features (Platforma) Section -->
    <section id="features" class="py-24 bg-[#0a1f44] relative overflow-hidden reveal-item">

        <div class="container mx-auto px-4 lg:px-8 relative z-10">
            <div class="text-center mb-20 opacity-0 animate-fade-in">
                <div class="inline-flex items-center gap-3 px-6 py-2 bg-white/5 border border-white/10 rounded-full mb-8 animate-fadeInUp"
                    style="animation-delay: 0.1s;">
                    <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                    <span class="text-blue-200/60 text-sm font-bold tracking-wider uppercase">Naxçıvan Dövlət
                        Universiteti — Distant Təhsil Mərkəzi</span>
                </div>
                <h2 class="text-white mb-6 font-extrabold leading-tight text-3xl lg:text-5xl">Vahid Tədris Mühiti</h2>
                <p class="text-blue-200/60 max-w-2xl mx-auto text-lg leading-relaxed font-medium">NDU Distant Təhsil
                    platforması akademik mükəmməllik üçün lazım olan bütün texnoloji alətləri bir pəncərədə birləşdirir.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">
                <!-- Feature 1: Canlı Dərslər -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-rose-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="video" class="w-6 h-6 text-rose-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">Canlı Dərslər</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed font-medium">Müəllimlə real vaxtda yüksək
                        keyfiyyətdə əlaqə, ekran paylaşımı və interaktiv sual-cavab imkanı.</p>
                </div>

                <!-- Feature 2: Video Arxiv -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-blue-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="archive" class="w-6 h-6 text-blue-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">Video Arxiv</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed font-medium">Keçirilmiş bütün dərslərin tam
                        arxivini istənilən vaxt, istənilən cihazdan yenidən izləyin.</p>
                </div>

                <!-- Feature 3: Akademik Monitorinq -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="bar-chart-2" class="w-6 h-6 text-emerald-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">Akademik Monitorinq</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed mb-4 font-medium">Şəxsi akademik irəliləyişinizi,
                        davamiyyəti və qiymətlərinizi rəqəmsal paneldə izləyin.</p>
                </div>

                <!-- Feature 4: Dinamik Cədvəl -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-purple-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="calendar" class="w-6 h-6 text-purple-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">Onlayn Cədvəl</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed font-medium">Onlayn dərslərin gününü və vaxtını
                        bu cədvəl vasitəsilə rahatlıqla izləyin.</p>
                </div>

                <!-- Feature 5: Elektron Resurslar -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-amber-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="book-open" class="w-6 h-6 text-amber-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">Elektron Resurslar</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed font-medium">Tədris materialları, mühazirə
                        mətnləri və fənn üzrə rəqəmsal vəsaitlərin mərkəzləşdirilmiş bazası.</p>
                </div>

                <!-- Feature 6: Ani Bildirişlər -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-sky-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="bell" class="w-6 h-6 text-sky-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">Ani Bildirişlər</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed mb-4 font-medium">Vacib elanlar, yeni dərslər və
                        akademik yeniliklər haqqında anlıq məlumat alın.</p>
                </div>

                <!-- Feature 7: E-Davamiyyət -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-teal-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="user-check" class="w-6 h-6 text-teal-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">E-Davamiyyət</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed mb-4 font-medium">Avtomatik davamiyyət uçotu və
                        iştirak hesabatlarının real vaxtda generasiyası.</p>
                </div>

                <!-- Feature 8: İnteraktiv Forum -->
                <div
                    class="group relative bg-[#0e2652] border border-white/10 rounded-3xl p-8 transition-all duration-300 hover:bg-[#143269] hover:-translate-y-2 opacity-0 animate-fade-in shadow-lg">
                    <div
                        class="w-14 h-14 rounded-2xl bg-indigo-500/10 flex items-center justify-center border border-white/5 shadow-inner mb-6 transition-transform duration-300 group-hover:scale-110">
                        <i data-lucide="message-square" class="w-6 h-6 text-indigo-400"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">İnteraktiv Forum</h3>
                    <p class="text-blue-100/40 text-sm leading-relaxed mb-4 font-medium">Müəllim-tələbə birbaşa
                        mesajlaşma, qrup müzakirələri və fənn üzrə forumlar.</p>
                </div>
            </div>
        </div>
    </section>
    <section id="portals" class="py-24 bg-primary relative overflow-hidden reveal-item">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="text-center mb-20">
                <h2 class="text-white text-4xl lg:text-5xl font-extrabold mb-6">İstifadəçi Portalları</h2>
                <p class="text-blue-100/40 text-lg max-w-2xl mx-auto">Sizin üçün uyğun olan bölməni seçərək tədris
                    prosesinə dərhal başlayın.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 max-w-6xl mx-auto">
                <!-- Student Portal Card -->
                <div
                    class="relative group bg-[#0e2652] border border-white/10 rounded-[3.5rem] p-12 transition-all duration-500 hover:bg-[#143269] hover:-translate-y-2 shadow-2xl">
                    <div class="flex items-start justify-between mb-10">
                        <div
                            class="w-24 h-24 rounded-[2rem] bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-[0_20px_40px_rgba(37,99,235,0.3)] transition-transform duration-500 group-hover:scale-110">
                            <i data-lucide="graduation-cap" class="w-12 h-12"></i>
                        </div>
                        <div class="px-4 py-1.5 bg-blue-500/10 border border-blue-400/20 rounded-full">
                            <span class="text-blue-400 text-[10px] font-bold uppercase tracking-widest">Tələbə
                                Portalı</span>
                        </div>
                    </div>

                    <h2 class="text-3xl font-extrabold text-white mb-6">Tələbə Girişi</h2>
                    <p class="text-blue-100/60 text-lg mb-8 leading-relaxed">Şəxsi kabinetinizə daxil olaraq bütün
                        təhsil resurslarından yararlanın.</p>

                    <div class="space-y-4 mb-10">
                        <div class="flex items-center gap-3 text-sm text-blue-100/80">
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-blue-400"></i>
                            <span>Canlı dərslərə və video arxivə birbaşa giriş</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-blue-100/80">
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-blue-400"></i>
                            <span>Distant dərslərdə interaktiv iştirak və akademik təqvim</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-blue-100/80">
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-blue-400"></i>
                            <span>Fənn materiallarının rəqəmsal bazası</span>
                        </div>
                    </div>

                    <a href="student/login.php"
                        class="flex w-full items-center justify-center gap-3 py-5 bg-white text-[#0a1f44] rounded-2xl font-bold text-lg hover:bg-blue-50 hover:scale-[1.02] transition-all shadow-xl">
                        SİSTEMƏ GİRİŞ <i data-lucide="log-in" class="w-5 h-5"></i>
                    </a>
                </div>

                <!-- Admin Card -->
                <div
                    class="relative group bg-[#0e2652] border border-white/10 rounded-[3.5rem] p-12 transition-all duration-500 hover:bg-[#143269] hover:-translate-y-2 shadow-2xl">
                    <div class="flex items-start justify-between mb-10">
                        <div
                            class="w-24 h-24 rounded-[2rem] bg-gradient-to-br from-slate-700 to-slate-900 border border-white/10 flex items-center justify-center text-white shadow-2xl transition-transform duration-500 group-hover:scale-110">
                            <i data-lucide="user-circle" class="w-12 h-12 text-slate-200"></i>
                        </div>
                        <div class="px-4 py-1.5 bg-white/5 border border-white/10 rounded-full">
                            <span class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Müəllim
                                Portalı</span>
                        </div>
                    </div>

                    <h2 class="text-3xl font-extrabold text-white mb-6">Müəllim Girişi</h2>
                    <p class="text-blue-100/60 text-lg mb-8 leading-relaxed">Tədris prosesini və tələbə irəliləyişini
                        rəqəmsal mühitdə idarə edin.</p>

                    <div class="space-y-4 mb-10">
                        <div class="flex items-center gap-3 text-sm text-blue-100/80">
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-slate-400"></i>
                            <span>Onlayn mühazirələrin başladılması və idarəsi</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-blue-100/80">
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-slate-400"></i>
                            <span>Davamiyyət və akademik göstəricilərin uçotu</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-blue-100/80">
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-slate-400"></i>
                            <span>Tədris materiallarının mütəşəkkil idarəedilməsi</span>
                        </div>
                    </div>
                    <a href="teacher/login.php"
                        class="flex w-full items-center justify-center gap-3 py-5 bg-white text-[#0a1f44] rounded-2xl font-bold text-lg hover:bg-blue-50 hover:scale-[1.02] transition-all shadow-xl">
                        SİSTEMƏ GİRİŞ <i data-lucide="log-in" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer id="contact" class="bg-[#060f23] pt-20 pb-10 border-t border-white/5 reveal-item">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 lg:gap-16 mb-20">

                <!-- Left Column: Branding -->
                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center p-2 shadow-2xl border border-white/10">
                            <img src="assets/logo.png" alt="NDU Logo" class="w-full h-full object-contain">
                        </div>
                        <div>
                            <h3 class="text-white font-black text-xl tracking-tighter uppercase leading-tight">NAXÇIVAN
                                DÖVLƏT<br>UNİVERSİTETİ</h3>
                        </div>
                    </div>

                    <p class="text-blue-100/40 text-sm leading-relaxed font-medium">
                        Naxçıvan Dövlət Universiteti Distant Təhsil Mərkəzi — akademik gələcəyinizi ən müasir texnoloji
                        həllərlə bu gündən qururuq. Naxçıvan Dövlət Universitetinin köklü akademik ənənələrini rəqəmsal
                        dünyanın sonsuz imkanları ilə kəşf edin. Zaman və məkan sərhədlərini aşaraq, NDU-nun
                        intellektual gücünə ən müasir texnologiyalarla hər yerdən qoşulun. Universitetimiz tələbələrə
                        çevik, əlçatan və keyfiyyətli təhsil təqdim edərək rəqəmsal transformasiyanı hədəfləyir.
                    </p>
                </div>

                <!-- Column 2: Platforma -->
                <div class="space-y-8 lg:pl-10">
                    <h4 class="text-white font-extrabold text-sm uppercase tracking-[0.2em] mb-8">PLATFORMA</h4>
                    <ul class="space-y-4">
                        <li><a href="#home"
                                class="text-blue-100/40 hover:text-blue-400 text-sm font-bold transition-colors">Ana
                                Səhifə</a></li>
                        <li><a href="#about"
                                class="text-blue-100/40 hover:text-blue-400 text-sm font-bold transition-colors">Haqqımızda</a>
                        </li>
                        <li><a href="#features"
                                class="text-blue-100/40 hover:text-blue-400 text-sm font-bold transition-colors">Platforma
                                Xüsusiyyətləri</a></li>
                        <li><a href="#portals"
                                class="text-blue-100/40 hover:text-blue-400 text-sm font-bold transition-colors">Portal
                                Girişi</a></li>
                        <li><a href="#"
                                class="text-blue-100/40 hover:text-blue-400 text-sm font-bold transition-colors">Necə
                                İşləyir?</a></li>
                    </ul>
                </div>

                <!-- Column 3: Əlaqə Kanalları -->
                <div class="space-y-8">
                    <h4 class="text-white font-extrabold text-sm uppercase tracking-[0.2em] mb-8">ƏLAQƏ KANALLARI</h4>
                    <div class="space-y-4">
                        <div
                            class="flex items-center gap-5 p-5 bg-white/5 border border-white/5 rounded-3xl group hover:border-blue-500/20 transition-all">
                            <div
                                class="w-12 h-12 rounded-2xl bg-blue-600/10 flex items-center justify-center text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition-all">
                                <i data-lucide="mail" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-white/20 text-[9px] font-bold uppercase tracking-[0.2em] mb-1">E-POÇT
                                    ÜNVANI</p>
                                <p class="text-white font-bold text-sm">distant@ndu.edu.az</p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-5 p-5 bg-white/5 border border-white/5 rounded-3xl group hover:border-emerald-500/20 transition-all">
                            <div
                                class="w-12 h-12 rounded-2xl bg-emerald-600/10 flex items-center justify-center text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                                <i data-lucide="phone" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-white/20 text-[9px] font-bold uppercase tracking-[0.2em] mb-1">QAYNAR
                                    XƏTT</p>
                                <p class="text-white font-bold text-sm">+994 (36) 544 08 61</p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-5 p-5 bg-white/5 border border-white/5 rounded-3xl group hover:border-purple-500/20 transition-all">
                            <div
                                class="w-12 h-12 rounded-2xl bg-purple-600/10 flex items-center justify-center text-purple-400 group-hover:bg-purple-600 group-hover:text-white transition-all">
                                <i data-lucide="map-pin" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-white/20 text-[9px] font-bold uppercase tracking-[0.2em] mb-1">MƏRKƏZİ
                                    ÜNVAN</p>
                                <p class="text-white font-bold text-sm">Naxçıvan ş., Universitet şəhərciyi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Sub-footer -->
            <div class="pt-10 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                <p class="text-white/20 text-[10px] font-bold uppercase tracking-[0.1em]">
                    &copy; 2026 Naxçıvan Dövlət Universiteti • Distant Təhsil Mərkəzi
                </p>
                <div
                    class="flex items-center gap-3 bg-white/5 border border-white/5 pl-5 pr-1.5 py-1.5 rounded-2xl group hover:border-blue-500/20 transition-all">
                    <span class="text-white/20 text-[9px] font-bold uppercase tracking-[0.2em]">Developed by</span>
                    <span
                        class="px-4 py-2 bg-blue-600/10 rounded-xl text-blue-400 text-[10px] font-extrabold tracking-widest border border-blue-500/10">
                        NSU IT DEPARTMENT
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Init Lucide Icons
        lucide.createIcons();

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('main-header');
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Dropdown toggle
        const loginBtn = document.getElementById('login-btn');
        const loginDropdown = document.getElementById('login-dropdown');
        loginBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            loginDropdown.classList.toggle('hidden');
            setTimeout(() => {
                loginDropdown.classList.toggle('opacity-0');
                loginDropdown.classList.toggle('scale-95');
                loginBtn.querySelector('i:last-child').classList.toggle('rotate-180');
            }, 10);
        });

        document.addEventListener('click', () => {
            loginDropdown.classList.add('hidden', 'opacity-0', 'scale-95');
            loginBtn.querySelector('i:last-child').classList.remove('rotate-180');
        });

        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobile-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            const icon = mobileToggle.querySelector('i');
            if (mobileMenu.classList.contains('hidden')) {
                icon.setAttribute('data-lucide', 'menu');
            } else {
                icon.setAttribute('data-lucide', 'x');
            }
            lucide.createIcons();
        });

        // Set current day
        const days = ['Bazar', 'Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə'];
        document.getElementById('current-day').textContent = days[new Date().getDay()];

        // Scroll Reveal Implementation
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.reveal-item').forEach(el => revealObserver.observe(el));

        // Lenis Smooth Scroll Init
        const lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            gestureDirection: 'vertical',
            smoothWheel: true,
            wheelMultiplier: 1,
            smoothTouch: false,
            touchMultiplier: 2,
            infinite: false,
        })

        function raf(time) {
            lenis.raf(time)
            requestAnimationFrame(raf)
        }

        requestAnimationFrame(raf)
    </script>
</body>

</html>