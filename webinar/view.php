<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: dashboard.php');
    exit;
}

// Admin can view any webinar, others restricted to their department
if ($user['role'] === 'admin' && !isset($user['department_id'])) {
    $webinar = $db->fetch(
        "SELECT w.*, d.name as dept_name, u.full_name as teacher_name 
         FROM webinars w 
         LEFT JOIN webinar_departments d ON w.department_id = d.id 
         JOIN webinar_users u ON w.teacher_id = u.id
         WHERE w.id = ?",
        [$id]
    );
} else {
    $webinar = $db->fetch(
        "SELECT w.*, d.name as dept_name, u.full_name as teacher_name 
         FROM webinars w 
         LEFT JOIN webinar_departments d ON w.department_id = d.id 
         JOIN webinar_users u ON w.teacher_id = u.id
         WHERE w.id = ? AND w.department_id = ?",
        [$id, $user['department_id']]
    );
}

if (!$webinar) {
    die("Vebinar tapılmadı və ya giriş icazəniz yoxdur.");
}

if ($webinar['status'] !== 'live') {
    header('Location: dashboard.php?error=not_live');
    exit;
}

$pageTitle = "Canlı İzle: " . $webinar['title'];
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/studio.css?v=<?php echo time(); ?>">
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .mirrored-canvas {
            transform: scaleX(-1);
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .wb-tool-btn.active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981 !important;
            transform: scale(1.1);
        }

        .wb-color-btn {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .wb-color-btn:hover {
            transform: scale(1.2);
            z-index: 10;
        }

        .wb-color-btn.active {
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
            border-color: white !important;
        }
    </style>
</head>

<body class="bg-[#060f23]">

    <!-- Header -->
    <header
        class="h-16 border-b border-white/5 bg-[#0a1f44]/80 backdrop-blur-md flex items-center justify-between px-3 sm:px-6 z-50 overflow-hidden gap-2">
        <div class="flex items-center gap-2 sm:gap-4 min-w-0 pr-2">
            <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-blue-500/20 flex shrink-0 items-center justify-center">
                <i data-lucide="play" class="w-4 h-4 sm:w-5 sm:h-5 text-blue-400 fill-current"></i>
            </div>
            <div class="min-w-0 truncate">
                <h1 class="text-xs sm:text-sm font-bold leading-tight truncate"><?php echo e($webinar['title']); ?></h1>
                <p class="text-[9px] sm:text-[10px] text-white/40 font-bold uppercase tracking-widest mt-0.5 truncate">Mühazirəçi:
                    <?php echo e($webinar['teacher_name']); ?>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Mobil Üçün Çat Düyməsi -->
            <button onclick="toggleMobileChat()" class="lg:hidden w-8 h-8 flex items-center justify-center bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-full transition-all border border-blue-500/20 shadow-[0_0_10px_rgba(59,130,246,0.3)]">
                <i data-lucide="message-square" class="w-4 h-4"></i>
            </button>

            <div id="connectionStatus"
                class="w-8 h-8 sm:w-auto sm:px-3 sm:py-1 rounded-full bg-white/5 border border-white/10 text-[9px] sm:text-[10px] font-bold text-white/40 uppercase tracking-widest flex items-center justify-center gap-1.5 sm:gap-2">
                <div class="w-2 h-2 rounded-full bg-yellow-400 shrink-0 shadow-[0_0_8px_rgba(250,204,21,0.6)]"></div>
                <span class="hidden sm:inline">Bağlanılır...</span>
            </div>

            <button id="btnJoinStage" onclick="toggleStudentStage()"
                class="flex items-center justify-center px-3 py-1.5 sm:w-auto sm:px-6 sm:py-2 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-xl text-[9px] sm:text-[11px] font-black uppercase tracking-widest transition-all border border-emerald-500/20 gap-1.5 sm:gap-2 shrink-0">
                <i data-lucide="video" class="w-3.5 h-3.5"></i>
                <span class="whitespace-nowrap">Səhnəyə Qoşul</span>
            </button>

            <a href="dashboard.php"
                class="flex items-center justify-center w-8 h-8 sm:w-auto sm:px-6 sm:py-2 bg-rose-500/10 hover:bg-rose-500/20 sm:bg-white/5 sm:hover:bg-white/10 text-rose-400 sm:text-white rounded-xl text-[10px] sm:text-[11px] font-black uppercase tracking-widest transition-all">
                <i data-lucide="log-out" class="w-4 h-4 sm:hidden"></i>
                <span class="hidden sm:inline">Ayrıl</span>
            </a>
        </div>
    </header>

    <div class="studio-grid">
        <!-- Main Stage -->
        <div class="video-section">
            <div class="main-video-container">
                <!-- Hidden Source Elements for Participant Studio -->
                <canvas id="studentOutputCanvas" class="fixed top-[-9999px] left-[-9999px] opacity-0 pointer-events-none"></canvas>
                <video id="camSource" autoplay playsinline muted class="fixed top-[-9999px] left-[-9999px] opacity-0 pointer-events-none"></video>
                <video id="screenSource" autoplay playsinline muted class="fixed top-[-9999px] left-[-9999px] opacity-0 pointer-events-none"></video>

                <video id="remoteVid" autoplay playsinline class="w-full h-full object-cover bg-black"></video>

                <!-- Mobile Edition: Local Stage (PiP) -->
                <div id="mobileLocalStage" class="hidden absolute top-6 right-6 w-[30vw] max-w-[120px] aspect-[4/5] bg-black/60 backdrop-blur-xl rounded-[1.2rem] overflow-hidden shadow-[0_15px_30px_rgba(0,0,0,0.6)] border border-white/10 z-40 transition-all cursor-pointer group" onclick="toggleViewSwap()" title="Yerini dəyişdir">
                    <video id="localVidMobile" autoplay playsinline muted class="w-full h-full object-cover scale-x-[-1]"></video>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent pointer-events-none"></div>
                    <div class="absolute bottom-1.5 right-1.5 bg-white/20 backdrop-blur-md px-1.5 py-1.5 rounded-lg opacity-80 group-hover:opacity-100 transition-opacity">
                        <i data-lucide="repeat" class="w-3 h-3 text-white"></i>
                    </div>
                </div>

                <div class="absolute top-6 left-6 flex flex-col gap-2 z-40">
                    <div class="live-badge !static !m-0 bg-rose-500/90 shadow-lg shadow-rose-500/20">
                        <div class="badge-dot"></div>
                        CANLI <span class="mx-1 h-3 bg-white/20 flex w-px"></span> <span id="webinarTimer">00:00:00</span>
                    </div>
                </div>

                <!-- Whiteboard Overlay (Student Edition) -->
                <div id="whiteboardOverlay"
                    class="absolute inset-0 bg-white hidden z-40 animate-in fade-in duration-300">
                    <canvas id="wbCanvasInternal" class="w-full h-full cursor-crosshair"></canvas>

                    <!-- Whiteboard Toolbar (Premium V3: Professional Broadcast Edition) -->
                    <div id="wbToolbarWrapper" class="absolute left-6 top-1/2 -translate-y-1/2 flex items-center gap-3 z-50 scale-[0.85] origin-left transition-all duration-500">
                        
                        <!-- Master Control Panel -->
                        <div id="wbToolbarContent" class="p-4 bg-[#06112a]/80 backdrop-blur-[40px] border border-white/10 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(0,0,0,0.7)] ring-1 ring-white/5 flex flex-col gap-6 transition-all duration-500">
                            
                            <!-- Navigation Module -->
                            <div class="flex flex-col items-center gap-1.5 p-2 bg-white/5 rounded-[1.5rem] border border-white/5">
                                <button onclick="prevPage()" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40 transition-all active:scale-90" title="Əvvəlki">
                                    <i data-lucide="chevron-up" class="w-4 h-4"></i>
                                </button>
                                <div class="bg-emerald-500/10 px-2 py-0.5 rounded-lg border border-emerald-500/20">
                                    <span id="pageIndicator" class="text-[9px] font-black text-emerald-400 tracking-widest uppercase">1/1</span>
                                </div>
                                <button onclick="nextPage()" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40 transition-all active:scale-90" title="Növbəti">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </button>
                                <div class="w-full h-px bg-white/5 my-1"></div>
                                <button onclick="addNewPage()" class="w-8 h-8 rounded-xl flex items-center justify-center bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 transition-all active:scale-90 shadow-[0_0_15px_rgba(16,185,129,0.2)]" title="Yeni Səhifə">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>

                            <!-- Tools Grid Module -->
                            <div class="grid grid-cols-2 gap-2.5">
                                <button onclick="setWBTool('pencil')" id="toolPencil" class="wb-tool-btn active w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Qələm">
                                    <i data-lucide="pen-tool" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('eraser')" id="toolEraser" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Pozan">
                                    <i data-lucide="eraser" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('text')" id="toolText" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Mətn">
                                    <i data-lucide="type" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('laser')" id="toolLaser" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Lazer">
                                    <i data-lucide="pointer" class="w-4.5 h-4.5"></i>
                                </button>
                                <div class="col-span-2 h-px bg-white/5 my-0.5"></div>
                                <button onclick="setWBTool('line')" id="toolLine" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Xətt">
                                    <i data-lucide="minus" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('rect')" id="toolRect" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Dördbucaqlı">
                                    <i data-lucide="square" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('circle')" id="toolCircle" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Dairə">
                                    <i data-lucide="circle" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('arrow')" id="toolArrow" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300" title="Ox">
                                    <i data-lucide="arrow-up-right" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="document.getElementById('wbImgInput').click()" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white hover:bg-emerald-500/10 hover:border-emerald-500/20 transition-all" title="Şəkil">
                                    <i data-lucide="image" class="w-4.5 h-4.5"></i>
                                    <input type="file" id="wbImgInput" class="hidden" accept="image/*" onchange="wbUploadImage(this)">
                                </button>
                                <button onclick="setWBBackground('grid')" id="bgGrid" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all" title="Dama Fon">
                                    <i data-lucide="grid" class="w-4.5 h-4.5"></i>
                                </button>
                            </div>

                            <!-- Properties Module -->
                            <div class="space-y-4 pt-4 border-t border-white/5">
                                <div class="flex items-center justify-between p-1 bg-black/40 rounded-2xl border border-white/5">
                                    <button onclick="changeWBSize(-2)" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40"><i data-lucide="minus" class="w-3.5 h-3.5"></i></button>
                                    <span id="wbSizeDisplay" class="text-[10px] font-black text-white/60 w-5 text-center">4</span>
                                    <button onclick="changeWBSize(2)" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                                </div>
                                <div class="grid grid-cols-4 gap-3">
                                    <button onclick="setWBColor('#000000', this)" class="wb-color-btn active w-6 h-6 rounded-full bg-black border-2 border-white/10 hover:scale-110 transition-all shadow-lg"></button>
                                    <button onclick="setWBColor('#ef4444', this)" class="wb-color-btn w-6 h-6 rounded-full bg-rose-500 border-2 border-transparent hover:scale-110 transition-all shadow-lg shadow-rose-500/20"></button>
                                    <button onclick="setWBColor('#3b82f6', this)" class="wb-color-btn w-6 h-6 rounded-full bg-blue-500 border-2 border-transparent hover:scale-110 transition-all shadow-lg shadow-blue-500/20"></button>
                                    <button onclick="setWBColor('#10b981', this)" class="wb-color-btn w-6 h-6 rounded-full bg-emerald-500 border-2 border-transparent hover:scale-110 transition-all shadow-lg shadow-emerald-500/20"></button>
                                    <button onclick="openColorPicker()" class="col-span-4 h-6 rounded-full bg-gradient-to-r from-rose-500 via-emerald-500 to-blue-500 border border-white/10 hover:scale-[1.02] transition-all relative overflow-hidden group">
                                        <div class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        <input type="color" id="customColorPicker" class="opacity-0 w-full h-full cursor-pointer" onchange="setWBColor(this.value, this.parentElement)">
                                    </button>
                                </div>
                            </div>

                            <!-- Actions Module -->
                            <div class="flex items-center gap-2 pt-4 border-t border-white/5">
                                <button onclick="undo()" class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-white/5 hover:bg-white/10 text-white/30 hover:text-white transition-all" title="Geri Al">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>
                                <button onclick="clearWhiteboard()" class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500/20 transition-all shadow-[0_0_20px_rgba(244,63,94,0.1)]" title="Təmizlə">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                <button onclick="toggleWhiteboard()" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 hover:bg-white/10 text-white/20 hover:text-white transition-all" title="Bağla">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Toggle Collapse Button -->
                        <button onclick="toggleWBToolbar()" class="w-12 h-12 rounded-2xl bg-[#06112a]/80 backdrop-blur-xl border border-white/10 flex items-center justify-center text-white/40 hover:text-white shadow-2xl transition-all active:scale-90 group" title="Alətləri Gizlə/Göstər">
                            <i id="wbToolbarToggleIcon" data-lucide="chevron-left" class="w-6 h-6 transition-transform duration-500"></i>
                        </button>
                    </div>

                    <!-- Image Placement UI -->
                    <div id="imagePlacementOverlay" class="absolute inset-0 z-50 bg-black/40 hidden">
                        <div id="imagePlacementContainer" class="absolute border-2 border-dashed border-emerald-500 cursor-move">
                            <img id="placementImage" src="" class="w-full h-full pointer-events-none select-none">
                            <div id="resizeHandle" class="absolute -bottom-3 -right-3 w-6 h-6 bg-emerald-500 rounded-full cursor-nwse-resize flex items-center justify-center shadow-lg">
                                <div class="w-2 h-2 border-r-2 border-b-2 border-white"></div>
                            </div>
                            <div class="absolute -top-12 left-1/2 -translate-x-1/2 flex items-center gap-2">
                                <button onclick="confirmImagePlacement()" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-xs font-black uppercase tracking-widest shadow-lg">Təsdiqlə</button>
                                <button onclick="cancelImagePlacement()" class="px-4 py-2 bg-rose-500 text-white rounded-lg text-xs font-black uppercase tracking-widest shadow-lg">Ləğv Et</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="laserCursor" class="fixed w-4 h-4 bg-rose-500 rounded-full blur-[2px] border-2 border-white pointer-events-none hidden z-50 shadow-[0_0_15px_rgba(244,63,94,0.8)]"></div>


                <!-- Unmute Button (Overlay) -->
                <div id="unmuteOverlay"
                    class="absolute inset-0 bg-black/60 flex items-center justify-center z-20 cursor-pointer hidden"
                    onclick="unmute()">
                    <div class="bg-blue-600 px-8 py-4 rounded-3xl flex items-center gap-4 animate-bounce">
                        <i data-lucide="volume-2" class="w-8 h-8 text-white"></i>
                        <span class="text-white font-black uppercase tracking-widest">Səsi Aktivləşdir</span>
                    </div>
                </div>

                <div class="absolute bottom-10 left-10 flex items-center gap-3">
                    <div
                        class="glass-panel px-6 py-3 rounded-2xl flex items-center gap-3 text-xs font-bold uppercase tracking-widest">
                        <div class="w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.5)]"></div>
                        <?php echo e($webinar['dept_name']); ?>
                    </div>
                </div>

                <!-- Control Bar (Visible when on stage) -->
                <div id="studentControls" class="control-bar hidden">
                    <div class="flex flex-col items-center gap-1">
                        <button id="btnMic" onclick="toggleMic()" class="btn-ctrl active-green" title="Mikrofon">
                            <i data-lucide="mic" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[8px] sm:text-[9px] font-semibold text-white/50 uppercase tracking-widest mt-0.5">Səs</span>
                    </div>

                    <div class="flex flex-col items-center gap-1">
                        <button id="btnCam" onclick="toggleCam()" class="btn-ctrl active-green" title="Kamera">
                            <i data-lucide="video" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[8px] sm:text-[9px] font-semibold text-white/50 uppercase tracking-widest mt-0.5">Video</span>
                    </div>

                    <div class="hidden sm:block w-px h-8 bg-white/10 mx-1"></div>

                    <div class="flex flex-col items-center gap-1">
                        <button id="btnScreen" onclick="toggleScreen()" class="btn-ctrl" title="Ekran Paylaş">
                            <i data-lucide="monitor" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[8px] sm:text-[9px] font-semibold text-white/50 uppercase tracking-widest mt-0.5">Ekran</span>
                    </div>

                    <div class="hidden sm:block w-px h-8 bg-white/10 mx-1"></div>

                    <div class="flex flex-col items-center gap-1">
                        <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="btn-ctrl" title="Ağ Lövhə">
                            <i data-lucide="edit-3" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[8px] sm:text-[9px] font-semibold text-white/50 uppercase tracking-widest mt-0.5">Lövhə</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcement Modal -->
        <div id="announcementModal"
            class="absolute inset-0 z-[100] bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-6 text-center">
            <div
                class="bg-[#1e293b] border border-white/10 w-full max-w-md p-8 rounded-[2rem] shadow-[0_50px_100px_-20px_rgba(0,0,0,0.5)] animate-in zoom-in duration-300">
                <div class="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="megaphone" class="w-10 h-10 text-emerald-400"></i>
                </div>
                <h3 class="text-xl font-black text-white mb-2 uppercase tracking-widest">Mühazirəçi Elanı</h3>
                <p id="announcementText" class="text-white/70 leading-relaxed mb-8">Elan mətni bura gələcək...</p>
                <button onclick="document.getElementById('announcementModal').classList.add('hidden')"
                    class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-white font-black text-[10px] uppercase tracking-[0.2em] rounded-2xl transition-all shadow-lg shadow-emerald-500/20">
                    ANLADIM
                </button>
            </div>
        </div>

        <!-- Sidebar (Chat & My Cam) -->
        <aside id="studioAside" class="border-l border-white/5 bg-[#0a1f44]/30 backdrop-blur-xl flex flex-col min-h-0">
            <!-- Local Stage (My Camera) -->
            <div id="localStageContainer"
                class="hidden p-6 border-b border-white/10 bg-emerald-500/5 animate-in slide-in-from-top duration-500">
                <div class="mb-4 flex items-center justify-between">
                    <h3
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span id="localStageLabel">MƏNİM KAMERAM</span>
                    </h3>
                    <div class="flex items-center gap-2">
                        <button onclick="toggleViewSwap()"
                            class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all"
                            title="Yerini dəyişdir">
                            <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
                        </button>
                        <span class="text-[9px] font-bold text-white/20 uppercase tracking-widest">Canlı Yayım</span>
                    </div>
                </div>
                <div class="relative aspect-video rounded-2xl overflow-hidden bg-black ring-1 ring-white/10 shadow-2xl">
                    <video id="localVid" autoplay playsinline muted
                        class="w-full h-full object-cover scale-x-[-1]"></video>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                </div>
            </div>

            <div class="p-6 border-b border-white/5 flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-white/40 flex items-center gap-3">
                    <i data-lucide="message-square" class="w-4 h-4 text-blue-400"></i>
                    Dərs Çatı
                </h3>
                <button onclick="toggleMobileChat()" class="lg:hidden w-8 h-8 flex items-center justify-center text-white/40 hover:text-white bg-white/5 hover:bg-white/10 rounded-xl transition-all">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div id="chatMessages" class="flex-1 min-h-0 overflow-y-auto p-6 space-y-4 custom-scrollbar"></div>

            <div class="p-6 bg-[#060f23]/50 border-t border-white/5">
                <div class="relative">
                    <input type="text" id="chatInput" placeholder="Mesaj yazın..."
                        class="w-full bg-white/5 border border-white/10 rounded-2xl pl-6 pr-14 py-4 text-sm focus:outline-none focus:border-blue-500/50 transition-all font-medium"
                        onkeypress="if(event.key==='Enter') sendChat()">
                    <button onclick="sendChat()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center hover:bg-blue-500 transition-all active:scale-90 shadow-lg shadow-blue-600/20">
                        <i data-lucide="send" class="w-4 h-4 text-white"></i>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <script>
        const wID = <?php echo (int) $id; ?>;
        const uName = "<?php echo e($user['full_name']); ?>";
        const teacherPeerId = "<?php echo $webinar['peer_id']; ?>"; // Initially populated, but we can also poll or use a more dynamic way

        let peer, dataConn, currentCall;
        let localStream = null, screenStream = null, remoteStream = null;
        let isOnStage = false, isViewSwapped = false;
        let isMicOn = true, isCamOn = true, isScreenOn = false;

        // --- Student Studio Engine (Whiteboard & Compositor) ---
        let isWBActive = false, wbCanvas, wbCtx;
        let wbTool = 'pencil', wbColor = '#000000', wbSize = 4, wbEraserSize = 30;
        let isDrawing = false, lastX = 0, lastY = 0, startX = 0, startY = 0;
        let wbSnapshot, wbHistory = [], wbUndoStack = [], wbPages = [], currentWBPage = 0;
        let wbBgType = 'none';
        let laserActive = false, laserX = 0, laserY = 0;
        let placementImg, imgAspectRatio, isDraggingImg = false, isResizingImg = false;
        let imgDragStartX, imgDragStartY, imgStartLeft, imgStartTop, imgStartWidth;
        let studentStream = null; // The final broadcast stream (Canvas + Audio)

        function toggleMobileChat() {
            const aside = document.getElementById('studioAside');
            if(aside) aside.classList.toggle('mobile-open');
        }

        function LOG(msg, color = "white") {
            console.log(`%c[Webinar] ${msg}`, `color: ${color}`);
        }

        function init() {
            initWebinarTimer();
            if (!teacherPeerId) {
                alert("Mühazirəçi hələ yayıma başlamayıb.");
                window.location.href = 'dashboard.php';
                return;
            }

            const uniqueID = `ndu-webinar-student-${Math.floor(Math.random() * 100000)}`;
            peer = new Peer(uniqueID, {
                host: '0.peerjs.com',
                port: 443,
                secure: true
            });

            peer.on('open', (id) => {
                document.getElementById('connectionStatus').innerHTML = '<div class="w-2 h-2 rounded-full bg-blue-400 shrink-0 shadow-[0_0_8px_rgba(96,165,250,0.6)]"></div><span class="hidden sm:inline">Qoşuldu</span>';
                connectToTeacher();
            });

            peer.on('error', (err) => {
                LOG("❌ Qoşulma Xətası: " + err.type, "#ef4444");
                console.error("PeerJS Error:", err);
                if (err.type === 'peer-unavailable') {
                    LOG("⚠️ Mühazirəçi tapılmadı. Gözləyin, yenidən yoxlanılır...");
                    setTimeout(checkTeacherPeer, 3000);
                }
            });
        }

        function connectToTeacher() {
            // Data connection for chat
            dataConn = peer.connect(teacherPeerId, {
                metadata: { name: uName, id: <?php echo $user['id']; ?> }
            });

            dataConn.on('open', () => {
                appendChat('Sistem', 'Mühazirəçiyə qoşuldu!', '#94a3b8');
            });

            dataConn.on('data', (data) => {
                if (data.type === 'stage_request') {
                    LOG("🔔 Səhnə paylaşımı tələbi göndərildi");
                }
                if (data.type === 'stage_ended') {
                    LOG("🔌 İştirakçı səhnədən çıxdı");
                }
                if (data.type === 'chat') {
                    appendChat(data.sender, data.message);
                } else if (data.type === 'stage_ended') {
                    if (isOnStage) toggleStudentStage(true); // Forced stop
                } else if (data.type === 'whiteboard_state') {
                    const badge = document.getElementById('wbBadge');
                    if (badge) {
                        if (data.active) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                } else if (data.type === 'announcement') {
                    const modal = document.getElementById('announcementModal');
                    if (modal) {
                        document.getElementById('announcementText').innerText = data.message;
                        modal.classList.remove('hidden');
                    }
                    appendChat('Mühazirəçi Elanı', data.message, '#f59e0b');
                } else if (data.type === 'end_webinar') {
                    alert("Vebinar mühazirəçi tərəfindən bitirildi.");
                    window.location.href = 'dashboard.php';
                }
            });

            dataConn.on('close', () => {
                LOG("Bağlantı kəsildi.", "red");
                alert("Vebinar başa çatdı və ya mühazirəçi ayrıldı.");
                window.location.href = 'dashboard.php';
            });

            // Call teacher with a silent audio track to establish media channel
            // PeerJS needs at least one real track for proper negotiation
            const silentCtx = new AudioContext();
            const silentDest = silentCtx.createMediaStreamDestination();
            const silentStream = silentDest.stream;

            currentCall = peer.call(teacherPeerId, silentStream);
            handleCall(currentCall);
        }

        function handleCall(call) {
            call.on('stream', (stream) => {
                // VERIFICATION: Ensure we aren't receiving our own stream back 
                // (can happen in some loopback scenarios or signaling overlaps)
                if (studentStream && stream.id === studentStream.id) {
                    LOG("⚠️ Loopback stream rədd edildi", "#f59e0b");
                    return;
                }

                LOG("📡 Mühazirəçi stream-i alındı! Tracks: " + stream.getTracks().length);
                remoteStream = stream;
                updateVideoElements();

                const vid = document.getElementById('remoteVid');
                vid.play().catch(e => {
                    console.warn("Autoplay blocked, showing unmute overlay.");
                    document.getElementById('unmuteOverlay').classList.remove('hidden');
                });
            });

            call.on('close', () => {
                LOG("Zəng bağlandı.");
            });

            call.on('error', (err) => {
                LOG("Zəng xətası: " + err, "#ef4444");
                console.error("Call error:", err);
            });
        }

        async function toggleStudentStage(forced = false) {
            const btn = document.getElementById('btnJoinStage');

            if (!isOnStage && !forced) {
                try {
                    LOG("Media cihazları yoxlanılır...", "#60a5fa");

                    try {
                        // Attempt full Video + Audio first
                        localStream = await navigator.mediaDevices.getUserMedia({
                            video: true,
                            audio: true
                        });
                        LOG("✅ Kamera və Mikrofon aktivdir", "#10b981");
                    } catch (e) {
                        console.warn("Camera failed, trying Audio Only:", e);
                        // Fallback: Audio Only
                        try {
                            localStream = await navigator.mediaDevices.getUserMedia({
                                video: false,
                                audio: true
                            });
                            LOG("⚠️ Kamera məşğuldur, Səs rejiminə keçildi", "#f59e0b");
                            alert("Kameraya giriş mümkün deyil (məşğul və ya bloklanmış ola bilər). Yalnız səs ilə qoşulursunuz.");
                        } catch (e2) {
                            throw e2; // Bubble up if both fail
                        }
                    }

                    isOnStage = true;
                    btn.innerHTML = '<i data-lucide="video-off" class="w-4 h-4 sm:w-3.5 sm:h-3.5"></i> <span class="hidden sm:inline">Tərk Et</span>';
                    btn.classList.replace('text-emerald-400', 'text-rose-400');
                    btn.classList.replace('bg-emerald-500/10', 'bg-rose-500/10');
                    btn.classList.replace('border-emerald-500/20', 'border-rose-500/20');

                    // Show Controls
                    document.getElementById('studentControls').classList.remove('hidden');

                    // Show local camera PiP
                    showLocalVideo(localStream);

                    // Start Student Studio Engine
                    startStudentCompositing();

                    // Re-negotiate with Canvas Stream
                    if (currentCall) {
                        currentCall.close();
                        currentCall = null;
                    }

                    const canvas = document.getElementById('studentOutputCanvas');
                    const canvasStream = canvas.captureStream(30); // 30 FPS

                    // Combine Canvas Video with Local Audio
                    const tracks = [...canvasStream.getVideoTracks(), ...localStream.getAudioTracks()];
                    studentStream = new MediaStream(tracks);

                    // Delay call slightly to let teacher's side cleanup old connection
                    setTimeout(() => {
                        currentCall = peer.call(teacherPeerId, studentStream);
                        handleCall(currentCall);
                    }, 500);

                } catch (err) {
                    console.error("Media Error Full:", err);
                    let errMsg = "Media cihazlarına giriş tapılmadı.";
                    if (err.name === 'NotAllowedError') errMsg = "Kamera/Mikrofon icazəsi brauzer tərəfindən bloklanıb. Zəhmət olmasa URL çubuğundakı 'Kilid' (🔒) ikonuna basaraq icazə verin.";
                    else if (err.name === 'NotFoundError') errMsg = "Kamera və ya Mikrofon tapılmadı (Cihaz qoşulmayıb).";
                    else if (err.name === 'NotReadableError') errMsg = "Kamera/Mikrofon başqa proqram tərəfindən istifadə edilir.";

                    alert(errMsg + "\n\n(Xəta: " + err.name + ")");
                }
            } else {
                LOG("Səhnədən çıxılır...", "#f43f5e");
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                    localStream = null;
                }

                // Hide Controls
                document.getElementById('studentControls').classList.add('hidden');

                // Hide local camera PiP
                hideLocalVideo();

                isViewSwapped = false;
                updateVideoElements();

                isOnStage = false;
                btn.innerHTML = '<i data-lucide="video" class="w-4 h-4 sm:w-3.5 sm:h-3.5"></i> <span class="hidden sm:inline">Səhnəyə Qoşul</span>';
                btn.classList.replace('text-rose-400', 'text-emerald-400');
                btn.classList.replace('bg-rose-500/10', 'bg-emerald-500/10');
                btn.classList.replace('border-rose-500/20', 'border-emerald-500/20');

                // Return to receive-only state with silent audio track
                if (currentCall) currentCall.close();
                const silentCtx = new AudioContext();
                const silentDest = silentCtx.createMediaStreamDestination();
                currentCall = peer.call(teacherPeerId, silentDest.stream);
                handleCall(currentCall);

                lucide.createIcons();
            }
        }

        function unmute() {
            const vid = document.getElementById('remoteVid');
            vid.muted = false;
            vid.play();
            document.getElementById('unmuteOverlay').classList.add('hidden');
        }

        // === LOCAL CAMERA STAGE ===
        function showLocalVideo(stream) {
            const container = document.getElementById('localStageContainer');
            const mobileContainer = document.getElementById('mobileLocalStage');
            localStream = stream;
            updateVideoElements();
            container.classList.remove('hidden');
            if(mobileContainer) mobileContainer.classList.remove('hidden');
            LOG("📸 Lokal kamera aktivləşdirildi", "#10b981");
        }

        function hideLocalVideo() {
            const container = document.getElementById('localStageContainer');
            const mobileContainer = document.getElementById('mobileLocalStage');
            container.classList.add('hidden');
            if(mobileContainer) mobileContainer.classList.add('hidden');
            // We don't null localStream here yet as toggleStudentStage handles it
        }

        function updateVideoElements() {
            const mainVid = document.getElementById('remoteVid');
            const sideVid = document.getElementById('localVid');
            const sideVidMobile = document.getElementById('localVidMobile');
            const sideLabel = document.getElementById('localStageLabel');

            const currentLocal = isScreenOn && screenStream ? screenStream : localStream;
            const applyMirror = !isScreenOn; // Don't mirror screen shares

            if (isViewSwapped) {
                mainVid.srcObject = currentLocal;
                if(applyMirror) mainVid.classList.add('scale-x-[-1]'); else mainVid.classList.remove('scale-x-[-1]');

                sideVid.srcObject = remoteStream;
                sideVid.classList.remove('scale-x-[-1]');
                
                if (sideVidMobile) {
                    sideVidMobile.srcObject = remoteStream;
                    sideVidMobile.classList.remove('scale-x-[-1]');
                }
                
                sideLabel.innerText = "MÜHAZİRƏÇİ";
            } else {
                mainVid.srcObject = remoteStream;
                mainVid.classList.remove('scale-x-[-1]');

                sideVid.srcObject = currentLocal;
                if(applyMirror) sideVid.classList.add('scale-x-[-1]'); else sideVid.classList.remove('scale-x-[-1]');
                
                if (sideVidMobile) {
                    sideVidMobile.srcObject = currentLocal;
                    if(applyMirror) sideVidMobile.classList.add('scale-x-[-1]'); else sideVidMobile.classList.remove('scale-x-[-1]');
                }
                
                sideLabel.innerText = isScreenOn ? "EKRAN PAYLAŞIMI" : "MƏNİM KAMERAM";
            }
        }

        function toggleViewSwap() {
            if (!isOnStage) return;
            isViewSwapped = !isViewSwapped;
            updateVideoElements();
            LOG(`🔄 Ekran yerləri dəyişdirildi: ${isViewSwapped ? 'Lokal Əsasda' : 'Mühazirəçi Əsasda'}`);
        }

        // === STUDENT STUDIO ENGINE (Symmetric with Teacher) ===
        function startStudentCompositing() {
            const canvas = document.getElementById('studentOutputCanvas');
            const ctx = canvas.getContext('2d');
            const camVid = document.getElementById('camSource');
            const screenVid = document.getElementById('screenSource');

            canvas.width = 1280;
            canvas.height = 720;
            camVid.srcObject = localStream;
            camVid.play().catch(e => console.warn("camVid play xətası:", e));

            function draw() {
                if (!isOnStage) return;

                ctx.fillStyle = "#000";
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                if (isWBActive && wbCanvas) {
                    ctx.fillStyle = "#ffffff";
                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    // Draw backgrounds for stream
                    if (wbBgType === 'grid') {
                        ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = 1; ctx.beginPath();
                        const step = 30 * (canvas.width / wbCanvas.width);
                        for (let x = step; x < canvas.width; x += step) { ctx.moveTo(x, 0); ctx.lineTo(x, canvas.height); }
                        for (let y = step; y < canvas.height; y += step) { ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); }
                        ctx.stroke();
                    } else if (wbBgType === 'lines') {
                        ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = 1; ctx.beginPath();
                        const step = 25 * (canvas.height / wbCanvas.height);
                        for (let y = step; y < canvas.height; y += step) { ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); }
                        ctx.stroke();
                    }
                    drawImageFit(ctx, wbCanvas, 0, 0, canvas.width, canvas.height);
                } else if (isScreenOn && screenVid.readyState >= 2) {
                    drawImageFit(ctx, screenVid, 0, 0, canvas.width, canvas.height);
                } else if (isCamOn && camVid.readyState >= 2) {
                    drawImageCover(ctx, camVid, 0, 0, canvas.width, canvas.height);
                }
            }
            setInterval(draw, 1000 / 30);
        }

        function drawImageCover(ctx, img, x, y, w, h) {
            const imgW = (img instanceof HTMLCanvasElement) ? img.width : img.videoWidth;
            const imgH = (img instanceof HTMLCanvasElement) ? img.height : img.videoHeight;
            if (!imgW || !imgH) return;
            const aspect = w / h; const imgAspect = imgW / imgH;
            let sx, sy, sw, sh;
            if (imgAspect > aspect) { sh = imgH; sw = imgH * aspect; sx = (imgW - sw) / 2; sy = 0; }
            else { sw = imgW; sh = imgW / aspect; sx = 0; sy = (imgH - sh) / 2; }
            ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
        }

        function drawImageFit(ctx, img, x, y, w, h) {
            const imgW = (img instanceof HTMLCanvasElement) ? img.width : img.videoWidth;
            const imgH = (img instanceof HTMLCanvasElement) ? img.height : img.videoHeight;
            if (!imgW || !imgH) return;

            const targetAspect = w / h;
            const imgAspect = imgW / imgH;
            let dw, dh, dx, dy;

            if (imgAspect > targetAspect) {
                dw = w;
                dh = w / imgAspect;
                dx = x;
                dy = y + (h - dh) / 2;
            } else {
                dh = h;
                dw = h * imgAspect;
                dx = x + (w - dw) / 2;
                dy = y;
            }
            ctx.drawImage(img, 0, 0, imgW, imgH, dx, dy, dw, dh);
        }

        // === WHITEBOARD ENGINE ===
        function toggleWhiteboard() {
            if (!isOnStage) return;
            const overlay = document.getElementById('whiteboardOverlay');
            const btn = document.getElementById('btnWhiteboard');
            isWBActive = !isWBActive;

            if (isWBActive) {
                isScreenOn = false; // Screen off when WB on
                overlay.classList.remove('hidden');
                btn.classList.add('active-green');
                initWBCanvas();
                setTimeout(() => { if(window.wbResize) window.wbResize(); }, 50);
                LOG("🎨 İştirakçı Lövhəsi aktivdir", "#10b981");
            } else {
                overlay.classList.add('hidden');
                btn.classList.remove('active-green');
                LOG("🎥 Kamera görüntüsünə qayıdıldı");
            }
        }

        function initWBCanvas() {
            if (wbCanvas) return;
            wbCanvas = document.getElementById('wbCanvasInternal');
            wbCtx = wbCanvas.getContext('2d');
            window.wbResize = () => {
                const rect = wbCanvas.parentElement.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0) return;
                
                let temp = null;
                if (wbCanvas.width > 0 && wbCanvas.height > 0) {
                    temp = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
                }
                
                wbCanvas.width = rect.width;
                wbCanvas.height = rect.height;
                
                if (temp) {
                    wbCtx.putImageData(temp, 0, 0);
                }
                
                wbCtx.lineCap = 'round';
                wbCtx.lineJoin = 'round';
            };
            window.addEventListener('resize', window.wbResize);
            window.wbResize();
            if (wbPages.length === 0) wbPages.push(null);
            updatePageIndicator();

            function getXY(e) {
                const rect = wbCanvas.getBoundingClientRect();
                let clientX, clientY;
                if (e.touches && e.touches.length > 0) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            const startDraw = (e) => {
                const { x, y } = getXY(e);
                if (wbTool === 'laser') return;
                if (wbTool === 'text') { drawText(x, y); return; }
                saveState(); isDrawing = true;
                [startX, startY] = [x, y]; [lastX, lastY] = [x, y];
                wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
                if (e.type === 'touchstart') e.preventDefault();
            };

            const moveDraw = (e) => {
                const { x, y } = getXY(e);
                const laser = document.getElementById('laserCursor');
                if (wbTool === 'laser') {
                    laser.style.display = 'block'; 
                    laser.style.left = (e.touches ? e.touches[0].clientX : e.clientX) + 'px'; 
                    laser.style.top = (e.touches ? e.touches[0].clientY : e.clientY) + 'px';
                    laserActive = true; laserX = x; laserY = y;
                } else { laser.style.display = 'none'; laserActive = false; }
                if (!isDrawing) return;
                if (wbTool === 'pencil' || wbTool === 'eraser') drawFreehand(x, y);
                else { wbCtx.putImageData(wbSnapshot, 0, 0); drawShape(x, y); }
                if (e.type === 'touchmove') e.preventDefault();
            };

            const endDraw = () => { isDrawing = false; };

            wbCanvas.addEventListener('mousedown', startDraw);
            wbCanvas.addEventListener('mousemove', moveDraw);
            wbCanvas.addEventListener('mouseup', endDraw);
            wbCanvas.addEventListener('mouseout', endDraw);
            wbCanvas.addEventListener('touchstart', startDraw, { passive: false });
            wbCanvas.addEventListener('touchmove', moveDraw, { passive: false });
            wbCanvas.addEventListener('touchend', endDraw);
        }

        function saveState() {
            wbHistory.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
            if (wbHistory.length > 30) wbHistory.shift();
            wbUndoStack = [];
        }

        function undo() {
            if (wbHistory.length === 0) return;
            wbUndoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
            wbCtx.putImageData(wbHistory.pop(), 0, 0);
        }

        function drawFreehand(x, y) {
            wbCtx.beginPath(); wbCtx.moveTo(lastX, lastY); wbCtx.lineTo(x, y);
            if (wbTool === 'eraser') { wbCtx.globalCompositeOperation = 'destination-out'; wbCtx.lineWidth = wbEraserSize; }
            else { wbCtx.globalCompositeOperation = 'source-over'; wbCtx.strokeStyle = wbColor; wbCtx.lineWidth = wbSize; }
            wbCtx.stroke(); wbCtx.globalCompositeOperation = 'source-over';[lastX, lastY] = [x, y];
        }

        function drawShape(x, y) {
            wbCtx.beginPath(); wbCtx.strokeStyle = wbColor; wbCtx.lineWidth = wbSize;
            if (wbTool === 'line') { wbCtx.moveTo(startX, startY); wbCtx.lineTo(x, y); }
            else if (wbTool === 'rect') wbCtx.strokeRect(startX, startY, x - startX, y - startY);
            else if (wbTool === 'circle') { let r = Math.sqrt(Math.pow(x - startX, 2) + Math.pow(y - startY, 2)); wbCtx.arc(startX, startY, r, 0, 2 * Math.PI); }
            wbCtx.stroke();
        }

        function drawText(x, y) {
            const txt = prompt("Mətn daxil edin:");
            if (txt) { saveState(); wbCtx.font = "bold 24px 'Inter', sans-serif"; wbCtx.fillStyle = wbColor; wbCtx.fillText(txt, x, y); }
        }

        function setWBTool(tool) {
            wbTool = tool;
            document.querySelectorAll('.wb-tool-btn').forEach(b => { if (b.id && b.id.startsWith('tool')) b.classList.remove('active', 'text-emerald-400'); });
            document.getElementById('tool' + tool.charAt(0).toUpperCase() + tool.slice(1)).classList.add('active', 'text-emerald-400');
            document.getElementById('wbSizeDisplay').innerText = (tool === 'eraser') ? wbEraserSize : wbSize;
        }

        function setWBColor(color, el) {
            wbColor = color;
            document.querySelectorAll('.wb-color-btn').forEach(b => b.classList.remove('active', 'border-white'));
            el.classList.add('active', 'border-white');
        }

        function openColorPicker() { document.getElementById('customColorPicker').click(); }

        function changeWBSize(delta) {
            if (wbTool === 'eraser') {
                wbEraserSize = Math.max(5, Math.min(100, Math.floor((typeof wbEraserSize === 'undefined' ? 20 : wbEraserSize)) + delta));
                document.getElementById('wbSizeDisplay').innerText = wbEraserSize;
            } else {
                wbSize = Math.max(1, Math.min(20, Math.floor((typeof wbSize === 'undefined' ? 4 : wbSize)) + delta));
                document.getElementById('wbSizeDisplay').innerText = wbSize;
            }
        }

        function setWBBackground(type) {
            if (typeof wbBgType !== 'undefined') wbBgType = type;
            document.querySelectorAll('.wb-tool-btn').forEach(b => { if (b.id && b.id.startsWith('bg')) b.classList.remove('active', 'text-emerald-400'); });
            document.getElementById('bg' + type.charAt(0).toUpperCase() + type.slice(1)).classList.add('active', 'text-emerald-400');
            
            const canvasEl = document.getElementById('wbCanvasInternal');
            if (type === 'grid') {
                canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px), linear-gradient(90deg, #94a3b8 1px, transparent 1px)';
                canvasEl.style.backgroundSize = '30px 30px';
            } else if (type === 'lines') {
                canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px)';
                canvasEl.style.backgroundSize = '100% 25px';
            } else {
                canvasEl.style.backgroundImage = 'none';
            }
        }

        let isWBToolbarCollapsed = false;
        function toggleWBToolbar() {
            isWBToolbarCollapsed = !isWBToolbarCollapsed;
            const content = document.getElementById('wbToolbarContent');
            const icon = document.getElementById('wbToolbarToggleIcon');
            const wrapper = document.getElementById('wbToolbarWrapper');
            
            if (isWBToolbarCollapsed) {
                content.style.display = 'none';
                icon.style.transform = 'rotate(180deg)';
                wrapper.style.left = '0';
                wrapper.style.top = '50%';
                wrapper.style.transform = 'translateY(-50%) scale(0.75)';
            } else {
                content.style.display = 'flex';
                icon.style.transform = 'rotate(0deg)';
                wrapper.style.left = '24px';
                wrapper.style.top = '50%';
                wrapper.style.transform = 'translateY(-50%) scale(0.85)';
            }
        }

        function wbUploadImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    placementImg = new Image();
                    placementImg.onload = () => {
                        const overlay = document.getElementById('imagePlacementOverlay');
                        const container = document.getElementById('imagePlacementContainer');
                        document.getElementById('placementImage').src = placementImg.src;
                        imgAspectRatio = placementImg.width / placementImg.height;
                        container.style.width = '300px'; container.style.height = (300 / imgAspectRatio) + 'px';
                        overlay.classList.remove('hidden');
                        overlay.style.display = 'block';
                    };
                    placementImg.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        function confirmImagePlacement() {
            const container = document.getElementById('imagePlacementContainer');
            saveState();
            wbCtx.drawImage(placementImg, parseInt(container.style.left || 0), parseInt(container.style.top || 0), parseInt(container.style.width), parseInt(container.style.height));
            document.getElementById('imagePlacementOverlay').style.display = 'none';
        }
        function cancelImagePlacement() { document.getElementById('imagePlacementOverlay').style.display = 'none'; }
        function clearWhiteboard() { if (confirm('Tömizlənsin?')) { saveState(); wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); } }
        function addNewPage() { wbPages.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height)); currentWBPage++; wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); updatePageIndicator(); }
        function prevPage() { if (currentWBPage > 0) { currentWBPage--; wbCtx.putImageData(wbPages[currentWBPage], 0, 0); updatePageIndicator(); } }
        function nextPage() { if (currentWBPage < wbPages.length - 1) { currentWBPage++; wbCtx.putImageData(wbPages[currentWBPage], 0, 0); updatePageIndicator(); } }
        function updatePageIndicator() { document.getElementById('pageIndicator').innerText = `${currentWBPage + 1} / ${wbPages.length + (wbPages[currentWBPage] ? 0 : 0)}`; }

        function toggleMic() {
            if (!localStream) return;
            const btn = document.getElementById('btnMic');
            const audioTracks = localStream.getAudioTracks();
            if (audioTracks.length > 0) {
                isMicOn = !isMicOn;
                audioTracks.forEach(t => t.enabled = isMicOn);

                if (isMicOn) {
                    btn.classList.add('active-green');
                    btn.classList.remove('bg-rose-500/20', 'border-rose-500/50');
                    btn.innerHTML = '<i data-lucide="mic" class="w-5 h-5 text-white"></i>';
                } else {
                    btn.classList.remove('active-green');
                    btn.classList.add('bg-rose-500/20', 'border-rose-500/50');
                    btn.innerHTML = '<i data-lucide="mic-off" class="w-5 h-5 text-rose-400"></i>';
                }
                lucide.createIcons();
            }
        }

        function toggleCam() {
            if (!localStream) return;
            const btn = document.getElementById('btnCam');
            const videoTracks = localStream.getVideoTracks();
            if (videoTracks.length > 0) {
                isCamOn = !isCamOn;
                videoTracks.forEach(t => t.enabled = isCamOn);

                if (isCamOn) {
                    btn.classList.add('active-green');
                    btn.classList.remove('bg-rose-500/20', 'border-rose-500/50');
                    btn.innerHTML = '<i data-lucide="video" class="w-5 h-5 text-white"></i>';
                } else {
                    btn.classList.remove('active-green');
                    btn.classList.add('bg-rose-500/20', 'border-rose-500/50');
                    btn.innerHTML = '<i data-lucide="video-off" class="w-5 h-5 text-rose-400"></i>';
                }
                lucide.createIcons();
            }
        }

        async function toggleScreen() {
            if (!isOnStage) return;
            const btn = document.getElementById('btnScreen');
            if (!isScreenOn) {
                try {
                    screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                    isScreenOn = true;
                    isWBActive = false; // WB off when screen on
                    document.getElementById('whiteboardOverlay').classList.add('hidden');
                    document.getElementById('btnWhiteboard').classList.remove('active-green');

                    document.getElementById('screenSource').srcObject = screenStream;
                    document.getElementById('screenSource').play().catch(e => console.warn(e));
                    btn.classList.add('active-green');
                    LOG("🖥️ Ekran paylaşımı aktivdir", "#60a5fa");

                    updateVideoElements();

                    screenStream.getVideoTracks()[0].onended = () => { if (isScreenOn) toggleScreen(); };
                } catch (err) { console.error(err); }
            } else {
                if (screenStream) screenStream.getTracks().forEach(t => t.stop());
                screenStream = null;
                isScreenOn = false;
                btn.classList.remove('active-green');
                LOG("🎥 Kamera görüntüsünə qayıdıldı");
                
                updateVideoElements();
            }
        }

        function sendChat() {
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (!msg || !dataConn || !dataConn.open) return;

            const data = { type: 'chat', sender: uName, message: msg };
            dataConn.send(data);
            appendChat('Mən', msg, '#3b82f6');
            input.value = '';
        }

        function appendChat(sender, msg, color = '#3b82f6') {
            const box = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = 'bg-white/5 p-4 rounded-2xl border border-white/5 animate-in slide-in-from-right duration-300';
            div.innerHTML = `
                <div class="text-[10px] font-bold uppercase tracking-widest mb-1" style="color: ${color}">${sender}</div>
                <div class="text-sm text-white/80">${msg}</div>
            `;
            box.appendChild(div);
            box.scrollTop = box.scrollHeight;
        }

        function initWebinarTimer() {
            <?php
            $startedAt = !empty($webinar['started_at']) ? strtotime($webinar['started_at']) : time();
            $elapsedSeconds = time() - $startedAt;
            if ($elapsedSeconds < 0)
                $elapsedSeconds = 0;
            ?>
            let secondsElapsed = <?php echo (int) $elapsedSeconds; ?>;

            setInterval(() => {
                secondsElapsed++;

                const hours = Math.floor(secondsElapsed / 3600);
                const minutes = Math.floor((secondsElapsed % 3600) / 60);
                const seconds = secondsElapsed % 60;

                const display = [hours, minutes, seconds]
                    .map(v => v.toString().padStart(2, '0'))
                    .join(':');

                const el = document.getElementById('webinarTimer');
                if (el) el.innerText = display;
            }, 1000);
        }

        async function checkTeacherPeer() {
            try {
                const resp = await fetch(`api/get_webinar_peer.php?id=${wID}`);
                const data = await resp.json();
                if (data.success && data.peer_id && data.peer_id !== teacherPeerId) {
                    LOG("🔄 Yeni mühazirəçi ID-si tapıldı. Səhifə yenilənir...");
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (e) { }
        }

        window.onload = () => {
            init();
            lucide.createIcons();
        };
    </script>
</body>

</html>