<?php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireRole('teacher');
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: dashboard.php');
    exit;
}

$webinar = $db->fetch(
    "SELECT w.*, f.name as faculty_name 
         FROM webinars w 
         JOIN webinar_faculties f ON w.faculty_id = f.id 
         WHERE w.id = ? AND w.faculty_id = ?",
    [$id, $user['faculty_id']]
);

if (!$webinar) {
    die("Vebinar tapılmadı və ya giriş icazəniz yoxdur.");
}

$pageTitle = "Studio: " . $webinar['title'];
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
    </style>
</head>

<body class="bg-[#060f23] overflow-hidden">
    <!-- Production Start Overlay -->
    <div id="startProductionOverlay"
        class="fixed inset-0 z-[1000] bg-[#06112a] flex flex-col items-center justify-center p-6 text-center">
        <div class="absolute inset-0 opacity-20 bg-grid pointer-events-none"></div>
        <div
            class="relative w-24 h-24 bg-emerald-500/20 rounded-[2rem] flex items-center justify-center mb-8 animate-pulse shadow-[0_0_50px_rgba(16,185,129,0.2)] border border-emerald-500/20">
            <i data-lucide="video" class="w-10 h-10 text-emerald-400"></i>
        </div>
        <h2 class="text-3xl font-black text-white mb-4 tracking-tight">Studio Hazırdır</h2>
        <p class="text-blue-100/40 text-lg max-w-md mb-12 font-medium">Yayıma başlamaq üçün aşağıdakı düyməni sıxın.
            Sistem avtomatik olaraq tam ekran rejiminə keçəcək.</p>

        <button onclick="startProductionNow()"
            class="px-10 py-5 bg-emerald-500 hover:bg-emerald-400 text-white rounded-[2rem] font-black text-lg tracking-widest uppercase transition-all shadow-[0_20px_40px_rgba(16,185,129,0.3)] hover:scale-105 active:scale-95 flex items-center gap-4">
            Yayıma Başla <i data-lucide="play" class="w-6 h-6"></i>
        </button>

        <p class="mt-12 text-[10px] text-white/20 font-bold uppercase tracking-[0.3em] flex items-center gap-3">
            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500/40"></i>
            TƏHLÜKƏSİZ YAYIM SİSTEMİ V3.5
        </p>
    </div>

    <header
        class="h-14 lg:h-16 border-b border-white/5 bg-[#0a1f44]/80 backdrop-blur-md flex items-center justify-between px-4 lg:px-6 z-50">
        <!-- Desktop Header Title -->
        <div class="hidden lg:flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                <i data-lucide="video" class="w-6 h-6 text-emerald-400"></i>
            </div>
            <div>
                <h1 class="text-sm font-bold leading-none"><?php echo e($webinar['title']); ?></h1>
                <p class="text-[10px] text-white/40 font-bold uppercase tracking-widest mt-1">
                    <?php echo e($webinar['faculty_name']); ?></p>
            </div>
        </div>

        <!-- Mobile Header (Legacy Style) -->
        <div class="flex lg:hidden items-center">
            <div class="w-2.5 h-2.5 bg-rose-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(244,63,94,0.5)]"></div>
        </div>

        <div class="flex lg:hidden items-center justify-center">
            <div class="flex bg-white/5 border border-white/10 rounded-full p-1 gap-1">
                <button onclick="toggleMobilePanel('left')"
                    class="px-3 py-1.5 rounded-full text-[10px] font-bold text-white/60 hover:text-white transition-all flex items-center gap-1.5">
                    <i data-lucide="users" class="w-3 h-3"></i> İştirakçılar
                </button>
                <button onclick="toggleMobilePanel('right')"
                    class="px-3 py-1.5 rounded-full bg-white/10 text-[10px] font-bold text-white transition-all flex items-center gap-1.5">
                    <i data-lucide="message-square" class="w-3 h-3"></i> Çat
                </button>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="endWebinar()"
                class="px-5 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap">
                Bitir
            </button>
        </div>
    </header>

    <div class="studio-grid">
        <!-- Main Stage -->
        <div class="video-section">
            <div class="main-video-container">
                <!-- Main Feed -->
                <canvas id="outputCanvas" class="w-full h-full object-contain"></canvas>
                <video id="localMainVid" autoplay playsinline class="hidden w-full h-full object-contain"></video>

                <!-- Whiteboard Overlay -->
                <div id="whiteboardOverlay"
                    class="absolute inset-0 bg-white hidden z-40 animate-in fade-in duration-300">
                    <canvas id="wbCanvasInternal" class="w-full h-full cursor-crosshair"></canvas>
                    <!-- Whiteboard Toolbar (Premium V3: Professional Broadcast Edition) -->
                    <div id="wbToolbarWrapper"
                        class="absolute left-6 top-1/2 -translate-y-1/2 flex items-center gap-3 z-50 scale-[0.85] origin-left transition-all duration-500">

                        <!-- Master Control Panel -->
                        <div id="wbToolbarContent"
                            class="p-4 bg-[#06112a]/80 backdrop-blur-[40px] border border-white/10 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(0,0,0,0.7)] ring-1 ring-white/5 flex flex-col gap-6 transition-all duration-500">

                            <!-- Navigation Module -->
                            <div
                                class="flex flex-col items-center gap-1.5 p-2 bg-white/5 rounded-[1.5rem] border border-white/5">
                                <button onclick="prevPage()"
                                    class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40 transition-all active:scale-90"
                                    title="Əvvəlki">
                                    <i data-lucide="chevron-up" class="w-4 h-4"></i>
                                </button>
                                <div class="bg-emerald-500/10 px-2 py-0.5 rounded-lg border border-emerald-500/20">
                                    <span id="pageIndicator"
                                        class="text-[9px] font-black text-emerald-400 tracking-widest uppercase">1/1</span>
                                </div>
                                <button onclick="nextPage()"
                                    class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40 transition-all active:scale-90"
                                    title="Növbəti">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </button>
                                <div class="w-full h-px bg-white/5 my-1"></div>
                                <button onclick="addNewPage()"
                                    class="w-8 h-8 rounded-xl flex items-center justify-center bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 transition-all active:scale-90 shadow-[0_0_15px_rgba(16,185,129,0.2)]"
                                    title="Yeni Səhifə">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>

                            <!-- Tools Grid Module -->
                            <div class="grid grid-cols-2 gap-2.5">
                                <button onclick="setWBTool('pencil')" id="toolPencil"
                                    class="wb-tool-btn active w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Qələm">
                                    <i data-lucide="pen-tool" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('eraser')" id="toolEraser"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Pozan">
                                    <i data-lucide="eraser" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('text')" id="toolText"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Mətn">
                                    <i data-lucide="type" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('laser')" id="toolLaser"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Lazer">
                                    <i data-lucide="pointer" class="w-4.5 h-4.5"></i>
                                </button>
                                <div class="col-span-2 h-px bg-white/5 my-0.5"></div>
                                <button onclick="setWBTool('line')" id="toolLine"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Xətt">
                                    <i data-lucide="minus" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('rect')" id="toolRect"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Dördbucaqlı">
                                    <i data-lucide="square" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('circle')" id="toolCircle"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Dairə">
                                    <i data-lucide="circle" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('arrow')" id="toolArrow"
                                    class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all duration-300"
                                    title="Ox">
                                    <i data-lucide="arrow-up-right" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="document.getElementById('wbImgInput').click()"
                                    class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white hover:bg-emerald-500/10 hover:border-emerald-500/20 transition-all"
                                    title="Şəkil">
                                    <i data-lucide="image" class="w-4.5 h-4.5"></i>
                                    <input type="file" id="wbImgInput" class="hidden" accept="image/*"
                                        onchange="wbUploadImage(this)">
                                </button>
                                <button onclick="setWBBackground('grid')" id="bgGrid"
                                    class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white transition-all"
                                    title="Dama Fon">
                                    <i data-lucide="grid" class="w-4.5 h-4.5"></i>
                                </button>
                            </div>

                            <!-- Properties Module -->
                            <div class="space-y-4 pt-4 border-t border-white/5">
                                <div
                                    class="flex items-center justify-between p-1 bg-black/40 rounded-2xl border border-white/5">
                                    <button onclick="changeWBSize(-2)"
                                        class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40"><i
                                            data-lucide="minus" class="w-3.5 h-3.5"></i></button>
                                    <span id="wbSizeDisplay"
                                        class="text-[10px] font-black text-white/60 w-5 text-center">4</span>
                                    <button onclick="changeWBSize(2)"
                                        class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40"><i
                                            data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                                </div>
                                <div class="grid grid-cols-4 gap-3">
                                    <button onclick="setWBColor('#000000', this)"
                                        class="wb-color-btn active w-6 h-6 rounded-full bg-black border-2 border-white/10 hover:scale-110 transition-all shadow-lg"></button>
                                    <button onclick="setWBColor('#ef4444', this)"
                                        class="wb-color-btn w-6 h-6 rounded-full bg-rose-500 border-2 border-transparent hover:scale-110 transition-all shadow-lg shadow-rose-500/20"></button>
                                    <button onclick="setWBColor('#3b82f6', this)"
                                        class="wb-color-btn w-6 h-6 rounded-full bg-blue-500 border-2 border-transparent hover:scale-110 transition-all shadow-lg shadow-blue-500/20"></button>
                                    <button onclick="setWBColor('#10b981', this)"
                                        class="wb-color-btn w-6 h-6 rounded-full bg-emerald-500 border-2 border-transparent hover:scale-110 transition-all shadow-lg shadow-emerald-500/20"></button>
                                    <button onclick="openColorPicker()"
                                        class="col-span-4 h-6 rounded-full bg-gradient-to-r from-rose-500 via-emerald-500 to-blue-500 border border-white/10 hover:scale-[1.02] transition-all relative overflow-hidden group">
                                        <div
                                            class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity">
                                        </div>
                                        <input type="color" id="customColorPicker"
                                            class="opacity-0 w-full h-full cursor-pointer"
                                            onchange="setWBColor(this.value, this.parentElement)">
                                    </button>
                                </div>
                            </div>

                            <!-- Actions Module -->
                            <div class="flex items-center gap-2 pt-4 border-t border-white/5">
                                <button onclick="undo()"
                                    class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-white/5 hover:bg-white/10 text-white/30 hover:text-white transition-all"
                                    title="Geri Al">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>
                                <button onclick="clearWhiteboard()"
                                    class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500/20 transition-all shadow-[0_0_20px_rgba(244,63,94,0.1)]"
                                    title="Təmizlə">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                <button onclick="toggleWhiteboard()"
                                    class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 hover:bg-white/10 text-white/20 hover:text-white transition-all"
                                    title="Bağla">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Toggle Collapse Button -->
                        <button onclick="toggleWBToolbar()"
                            class="w-12 h-12 rounded-2xl bg-[#06112a]/80 backdrop-blur-xl border border-white/10 flex items-center justify-center text-white/40 hover:text-white shadow-2xl transition-all active:scale-90 group"
                            title="Alətləri Gizlə/Göstər">
                            <i id="wbToolbarToggleIcon" data-lucide="chevron-left"
                                class="w-6 h-6 transition-transform duration-500"></i>
                        </button>
                    </div>

                    <!-- Image Placement UI -->
                    <div id="imagePlacementOverlay" class="absolute inset-0 z-50 bg-black/40 hidden">
                        <div id="imagePlacementContainer"
                            class="absolute border-2 border-dashed border-emerald-500 cursor-move">
                            <img id="placementImage" src="" class="w-full h-full pointer-events-none select-none">
                            <div id="resizeHandle"
                                class="absolute -bottom-3 -right-3 w-6 h-6 bg-emerald-500 rounded-full cursor-nwse-resize flex items-center justify-center shadow-lg">
                                <div class="w-2 h-2 border-r-2 border-b-2 border-white"></div>
                            </div>
                            <div class="absolute -top-12 left-1/2 -translate-x-1/2 flex items-center gap-2">
                                <button onclick="confirmImagePlacement()"
                                    class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-xs font-black uppercase tracking-widest shadow-lg">Təsdiqlə</button>
                                <button onclick="cancelImagePlacement()"
                                    class="px-4 py-2 bg-rose-500 text-white rounded-lg text-xs font-black uppercase tracking-widest shadow-lg">Ləğv
                                    Et</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="laserCursor"
                    class="fixed w-4 h-4 bg-rose-500 rounded-full blur-[2px] border-2 border-white pointer-events-none hidden z-50 shadow-[0_0_15px_rgba(244,63,94,0.8)]">
                </div>

                <!-- Hidden Source Elements for Compositing -->
                <video id="camSource" autoplay playsinline muted
                    style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;"></video>
                <video id="screenSource" autoplay playsinline muted
                    style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;"></video>
                <video id="studentSource" autoplay playsinline muted
                    style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;"></video>

                <!-- Status Badges -->
                <div class="live-badge">
                    <div class="badge-dot"></div>
                    CANLI <span class="mx-2 w-px h-3 bg-white/20"></span> <span id="webinarTimer">00:00:00</span>
                </div>

                <div class="absolute top-6 right-6 flex items-center gap-3">
                    <div class="glass-panel px-4 py-2 rounded-2xl flex items-center gap-3 text-sm font-bold">
                        <i data-lucide="users" class="w-4 h-4 text-emerald-400"></i>
                        <span id="viewerCount">0</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="control-bar">
                    <div class="flex flex-col items-center gap-2">
                        <button id="btnMic" onclick="toggleMic()" class="btn-ctrl active-green" title="Mikrofon">
                            <i data-lucide="mic" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Səs</span>
                    </div>

                    <div class="flex flex-col items-center gap-2">
                        <button id="btnCam" onclick="toggleCam()" class="btn-ctrl active-green" title="Kamera">
                            <i data-lucide="video" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Video</span>
                    </div>

                    <div class="w-px h-8 bg-white/10 mx-2"></div>

                    <div class="flex flex-col items-center gap-2">
                        <button id="btnScreen" onclick="toggleScreen()" class="btn-ctrl" title="Ekran Paylaş">
                            <i data-lucide="monitor" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Ekran</span>
                    </div>

                    <div class="w-px h-8 bg-white/10 mx-2"></div>

                    <div class="flex flex-col items-center gap-2">
                        <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="btn-ctrl" title="Ağ Lövhə">
                            <i data-lucide="edit-3" class="w-5 h-5 text-white"></i>
                        </button>
                        <span class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Lövhə</span>
                    </div>
                </div>
            </div>



            <!-- Debug Logs (Hidden by default, useful for dev) -->
            <div id="logBox"
                class="glass-panel p-4 rounded-2xl hidden h-32 overflow-y-auto custom-scrollbar text-[10px] font-mono text-white/40">
            </div>
        </div>

        <!-- Sidebar (Tabs & Chat) -->
        <aside id="studioAside"
            class="w-[380px] border-l border-white/5 bg-[#0a1f44]/30 backdrop-blur-xl flex flex-col h-full overflow-hidden transition-all duration-300">
            <button onclick="toggleMobileChat()"
                class="lg:hidden absolute top-4 right-4 w-10 h-10 rounded-full bg-rose-500/10 text-rose-500 border border-rose-500/20 flex items-center justify-center z-[100]">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <!-- Side Stage Container (Unified) -->
            <div id="sideStageContainer" class="p-4 border-b border-white/10 bg-blue-500/5 transition-all duration-500">
                <div class="mb-3 flex items-center justify-between">
                    <h3
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400 flex items-center gap-2">
                        <span id="sideStageDot" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span id="sideStageLabel">İŞTİRAKÇI CANLIDA</span>
                    </h3>
                    <div class="flex items-center gap-2">
                        <button id="btnSideSwap" onclick="toggleStudentMain()"
                            class="hidden w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all"
                            title="Yayımı Dəyişdir">
                            <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
                        </button>
                        <span class="text-[9px] font-bold text-white/20 uppercase tracking-widest">Studio</span>
                    </div>
                </div>
                <div class="relative aspect-video rounded-2xl overflow-hidden bg-black ring-1 ring-white/10 shadow-xl group cursor-pointer"
                    onclick="toggleStudentMain()">
                    <video id="sideStageVid" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>

                    <div
                        class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/20">
                        <div class="bg-white/20 backdrop-blur-md p-2 rounded-full border border-white/30">
                            <i data-lucide="repeat" class="w-5 h-5 text-white"></i>
                        </div>
                    </div>

                    <!-- Overlay for Student Controls when they are in sidebar -->
                    <div id="sideStageOverlay"
                        class="absolute inset-x-0 bottom-0 p-3 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="event.stopPropagation(); toggleStudentExpand()"
                            class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-all">
                            <i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex border-b border-white/5">
                <button onclick="switchSidebarTab('chat')" id="tabBtnChat"
                    class="flex-1 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400 border-b-2 border-emerald-500 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="message-square" class="w-3.5 h-3.5"></i> Çat
                </button>
                <button onclick="switchSidebarTab('students')" id="tabBtnStudents"
                    class="flex-1 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/30 border-b-2 border-transparent hover:text-white/60 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="users" class="w-3.5 h-3.5"></i> İştirakçılar
                </button>
            </div>

            <!-- Chat Tab Content -->
            <div id="tabContentChat" class="flex-1 flex flex-col min-h-0 overflow-hidden">
                <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar min-h-0">
                    <div class="text-center py-10 opacity-20">
                        <i data-lucide="messages-square" class="mx-auto w-10 h-10 mb-4"></i>
                        <p class="text-xs font-bold uppercase tracking-widest">Çat boşdur</p>
                    </div>
                </div>

                <div class="p-6 bg-[#060f23]/50 border-t border-white/5 flex-shrink-0">
                    <div class="flex items-center gap-3 mb-4">
                        <button onclick="sendAnnouncement()"
                            class="flex-1 py-2 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border border-emerald-500/20 flex items-center justify-center gap-2">
                            <i data-lucide="megaphone" class="w-3 h-3"></i> Elan Göndər
                        </button>
                    </div>
                    <div class="relative">
                        <input type="text" id="chatInput" placeholder="Mesaj yazın..."
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-6 pr-14 py-4 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/10"
                            onkeypress="if(event.key==='Enter') sendChat()">
                        <button onclick="sendChat()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center hover:bg-emerald-400 transition-all active:scale-90 shadow-lg shadow-emerald-500/20">
                            <i data-lucide="send" class="w-4 h-4 text-white"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Students Tab Content -->
            <div id="tabContentStudents" class="hidden flex-1 flex flex-col min-h-0">
                <div class="p-6 border-b border-white/5 flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-400">Online
                        İştirakçılar</span>
                    <span id="studentCountBadge"
                        class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-[10px] font-bold rounded-full">0</span>
                </div>
                <div id="studentListContainer" class="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar">
                    <div class="text-center py-10 opacity-20 text-[10px] font-bold uppercase tracking-widest italic">
                        İştirakçı yoxdur</div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Student Large View Overlay -->
    <div id="largeStudentStageOverlay"
        class="fixed inset-0 z-[100] bg-[#060f23]/95 backdrop-blur-2xl hidden flex flex-col items-center justify-center p-10 animate-in fade-in zoom-in duration-300">
        <div
            class="relative w-full max-w-5xl aspect-video rounded-[2.5rem] overflow-hidden bg-black shadow-[0_0_100px_rgba(16,185,129,0.2)] ring-1 ring-white/10">
            <video id="studentRemoteVidLarge" autoplay playsinline class="w-full h-full object-contain"></video>

            <div class="absolute top-8 right-8 flex items-center gap-4">
                <div
                    class="flex items-center gap-3 px-6 py-3 bg-black/60 backdrop-blur-xl rounded-2xl border border-white/10 shadow-2xl">
                    <div
                        class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_10px_rgba(16,185,129,0.8)]">
                    </div>
                    <span class="text-[11px] font-black text-white uppercase tracking-[0.2em]">İştirakçı ilə Fərdi
                        Seans</span>
                </div>
                <button onclick="toggleStudentExpand()"
                    class="w-12 h-12 rounded-2xl bg-black/60 hover:bg-black/80 backdrop-blur-xl text-white flex items-center justify-center transition-all group shadow-2xl border border-white/10">
                    <i data-lucide="minimize-2"
                        class="w-5 h-5 group-hover:scale-110 transition-transform text-white/80 group-hover:text-white"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- JS Logic -->
    <script>
        const wID = <?php echo (int) $id; ?>;
        const uName = "<?php echo e($user['full_name']); ?>";
        let peer, stream, camStream, screenStream, destStream;
        let allDataConns = [];
        let isCamOn = true, isMicOn = true, isScreenOn = false;
        let activeStudentCall = null, isStudentExpanded = false, isStudentMain = false;

        // --- WHITEBOARD STATE (V2.1) ---
        let isWBActive = false, isDrawing = false;
        let wbCanvas, wbCtx, wbTool = 'pencil', wbColor = '#000000';
        let wbSize = 4, wbEraserSize = 30;
        let lastX = 0, lastY = 0, startX = 0, startY = 0;
        let laserActive = false, laserX = 0, laserY = 0;

        // History & Pages
        let wbHistory = [], wbUndoStack = [];
        let wbPages = [], currentWBPage = 0;
        let wbBgType = 'plain', wbSnapshot = null;

        // Image Placement
        let placementImg = null, isDraggingImg = false, isResizingImg = false;
        let imgDragStartX = 0, imgDragStartY = 0, imgStartLeft = 0, imgStartTop = 0;
        let imgStartWidth = 0, imgStartHeight = 0, imgAspectRatio = 1;

        function LOG(msg, color = "#94a3b8") {
            const box = document.getElementById('logBox');
            if (!box) return;
            const entry = document.createElement('div');
            entry.style.color = color;
            entry.innerText = `[${new Date().toLocaleTimeString()}] ${msg}`;
            box.appendChild(entry);
            box.scrollTop = box.scrollHeight;
        }

        // --- RECORDING LOGIC ---
        let mediaRecorder;
        let recordedChunks = [];
        let recordingStartTime = 0;
        let recordingMimeType = 'video/webm;codecs=vp8,opus';
        let isFirstChunkRecorded = true; // Tracks if this is the start of a recorder session

        function getBestMimeType() {
            const types = [
                'video/mp4;codecs=avc1,mp4a.40.2',
                'video/webm;codecs=vp9,opus',
                'video/webm;codecs=vp8,opus',
                'video/webm'
            ];
            for (let t of types) {
                if (MediaRecorder.isTypeSupported(t)) return t;
            }
            return '';
        }

        function startRecording() {
            if (!stream) return;
            isFirstChunkRecorded = true; // New recorder session
            try {
                const bestType = getBestMimeType();
                recordingMimeType = bestType;

                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: bestType,
                    videoBitsPerSecond: 1500000 // 1.5 Mbps
                });

                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        flushChunk(event.data);
                    }
                };

                recordingStartTime = Date.now();
                mediaRecorder.start(10000); // Trigger dataavailable every 10 seconds
                LOG(`🔴 Video qeydiyyat başladı (${bestType.split(';')[0]})`, "#ef4444");
            } catch (e) {
                console.warn("MediaRecorder Error:", e);
                // Fallback
                try {
                    mediaRecorder = new MediaRecorder(stream);
                    mediaRecorder.ondataavailable = (event) => {
                        if (event.data.size > 0) flushChunk(event.data);
                    };
                    recordingStartTime = Date.now();
                    mediaRecorder.start(10000);
                    LOG("🔴 Video qeydiyyat başladı (fallback).", "#ef4444");
                } catch (err) {
                    LOG("❌ Qeydiyyat mümkün olmadı.", "red");
                }
            }
        }

        let lastFlushPromise = Promise.resolve();

        async function finalizeRecording() {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') return;

            const durationMs = Date.now() - recordingStartTime;
            LOG("🏁 Yayım yekunlaşdırılır, video emal edilir...", "#60a5fa");

            return new Promise((resolve) => {
                mediaRecorder.onstop = async () => {
                    // Wait for the very last flush triggered by stop()
                    await lastFlushPromise;

                    try {
                        const formData = new FormData();
                        formData.append('webinar_id', wID);
                        formData.append('duration_ms', durationMs);
                        formData.append('mime_type', recordingMimeType);

                        const resp = await fetch('api/finalize_recording.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await resp.json();
                        if (data.success) {
                            LOG("✅ Video uğurla arxivləşdirildi.", "#10b981");
                        }
                    } catch (err) {
                        console.error("Finalize error:", err);
                    }
                    resolve();
                };
                mediaRecorder.stop();
            });
        }

        async function flushChunk(blob) {
            const formData = new FormData();
            formData.append('webinar_id', wID);
            formData.append('video_blob', blob);
            formData.append('mime_type', recordingMimeType);
            formData.append('is_first_chunk', isFirstChunkRecorded ? '1' : '0');

            if (isFirstChunkRecorded) isFirstChunkRecorded = false;

            lastFlushPromise = fetch('api/upload_recording.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    LOG(`💾 Parça saxlanıldı: ${Math.round(data.size / 1024 / 1024 * 10) / 10} MB`, "#94a3b8");
                }
                return data;
            }).catch(err => {
                console.error("Flush error:", err);
            });

            return lastFlushPromise;
        }

        async function init() {
            initWebinarTimer();
            try {
                LOG("Media cihazları yoxlanılır...", "#3b82f6");
                // Get Camera & Michel
                camStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 1280, height: 720 },
                    audio: { echoCancellation: true }
                });

                document.getElementById('camSource').srcObject = camStream;
                updateSideStage();
                startCompositing();

                // START RECORDING
                startRecording();

                // Initialize PeerJS
                const uniqueID = `ndu-webinar-${wID}-${Math.floor(Math.random() * 1000)}`;
                peer = new Peer(uniqueID, {
                    debug: 1,
                    host: '0.peerjs.com',
                    port: 443,
                    secure: true
                });

                peer.on('error', (err) => {
                    LOG("❌ PeerJS Sistemsəl Xəta: " + err.type, "#ef4444");
                    console.error("PeerJS Error:", err);
                });

                peer.on('open', (id) => {
                    LOG("🚀 Yayım serverinə qoşuldu!", "#10b981");
                    const statusEl = document.getElementById('connectionStatus');
                    if (statusEl) {
                        statusEl.innerHTML = '<div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div>AKTİV';
                    }

                    fetch(`api/update_peer_id.php?id=${wID}&peer_id=${id}`)
                        .then(r => r.json())
                        .then(data => data.success ? LOG("✅ DB yeniləndi") : LOG("❌ DB xətası", "red"));
                });

                peer.on('connection', (conn) => {
                    allDataConns.push(conn);
                    updateViewerCount();
                    renderStudentList();
                    LOG(`👤 İştirakçı qoşuldu: ${conn.metadata.name || 'Naməlum'}`, "#60a5fa");

                    conn.on('data', (data) => {
                        if (data.type === 'chat') {
                            appendChat(data.sender, data.message);
                            broadcast(data, conn.peer);
                        }
                    });

                    conn.on('close', () => {
                        allDataConns = allDataConns.filter(c => c.peer !== conn.peer);
                        updateViewerCount();
                        renderStudentList();
                        LOG("👤 İştirakçı ayrıldı", "#94a3b8");
                    });
                });

                peer.on('call', (call) => {
                    // Detect if the call is a student sharing camera (has video tracks)
                    // In PeerJS, we can check the metadata or simply observe the incoming stream
                    LOG(`📞 Giriş zəngi qəbul edilir...`, "#fbbf24");

                    // We have the globally composed 'stream' (Canvas + Audio)
                    if (!stream || stream.getTracks().length === 0) {
                        LOG("⚠️ Yayım hələ hazır deyil, cəhd edilir...", "#f59e0b");
                        // Fallback: If not ready, just answer with an empty stream to maintain the signaling
                        call.answer(new MediaStream());
                    } else {
                        call.answer(stream);
                    }

                    call.on('stream', (remoteStream) => {
                        // If the stream has video, it's a student joining the stage
                        if (remoteStream.getVideoTracks().length > 0) {
                            LOG("👤 İştirakçı səhnəyə qoşuldu!", "#10b981");
                            activeStudentCall = call;

                            const vSource = document.getElementById('studentSource');
                            vSource.srcObject = remoteStream;
                            vSource.play();

                            const v2 = document.getElementById('studentRemoteVidLarge');
                            v2.srcObject = remoteStream;
                            v2.play();

                            updateSideStage();
                            lucide.createIcons();
                        }
                    });

                    call.on('close', () => {
                        if (activeStudentCall === call) {
                            closeStudentCall(false);
                        }
                    });
                });

            } catch (err) {
                LOG("❌ Media xətası: " + err.message, "#ef4444");
                alert("Kamera və ya mikrofon tapılmadı.");
            }
        }

        function startCompositing() {
            const canvas = document.getElementById('outputCanvas');
            const ctx = canvas.getContext('2d');
            const camVid = document.getElementById('camSource');
            const screenVid = document.getElementById('screenSource');

            function draw() {
                const canvasEl = document.getElementById('outputCanvas');

                // Dynamic Canvas Resolution
                let targetW = 1280;
                let targetH = 720;

                if (isScreenOn && screenVid && screenVid.videoWidth) {
                    targetW = screenVid.videoWidth;
                    targetH = screenVid.videoHeight;
                } else if (isStudentMain && activeStudentCall) {
                    const studentVid = document.getElementById('studentSource');
                    if (studentVid && studentVid.videoWidth) {
                        targetW = studentVid.videoWidth;
                        targetH = studentVid.videoHeight;
                    }
                }

                if (canvas.width !== targetW || canvas.height !== targetH) {
                    canvas.width = targetW;
                    canvas.height = targetH;
                }

                if (isScreenOn || isWBActive || (isStudentMain && activeStudentCall)) {
                    canvasEl.classList.remove('mirrored-canvas');
                } else {
                    canvasEl.classList.add('mirrored-canvas');
                }

                ctx.fillStyle = "#000";
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                let isMainDrawn = false;

                if (isWBActive && wbCanvas) {
                    ctx.fillStyle = "#ffffff";
                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    if (wbBgType === 'grid') {
                        ctx.strokeStyle = '#94a3b8';
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        const step = 30 * (canvas.width / wbCanvas.width);
                        for (let x = step; x < canvas.width; x += step) { ctx.moveTo(x, 0); ctx.lineTo(x, canvas.height); }
                        for (let y = step; y < canvas.height; y += step) { ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); }
                        ctx.stroke();
                    } else if (wbBgType === 'lines') {
                        ctx.strokeStyle = '#94a3b8';
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        const step = 25 * (canvas.height / wbCanvas.height);
                        for (let y = step; y < canvas.height; y += step) { ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); }
                        ctx.stroke();
                    }

                    drawImageFit(ctx, wbCanvas, 0, 0, canvas.width, canvas.height);

                    if (laserActive && wbTool === 'laser') {
                        const imgW = wbCanvas.width;
                        const imgH = wbCanvas.height;
                        const targetAspect = canvas.width / canvas.height;
                        const imgAspect = imgW / imgH;
                        let dw, dh, dx, dy;

                        if (imgAspect > targetAspect) {
                            dw = canvas.width;
                            dh = canvas.width / imgAspect;
                            dx = 0;
                            dy = (canvas.height - dh) / 2;
                        } else {
                            dh = canvas.height;
                            dw = canvas.height * imgAspect;
                            dx = (canvas.width - dw) / 2;
                            dy = 0;
                        }

                        // Map laser coordinates from wbCanvas to fitted outputCanvas
                        const lx = dx + (laserX / imgW) * dw;
                        const ly = dy + (laserY / imgH) * dh;

                        ctx.save();
                        const grad = ctx.createRadialGradient(lx, ly, 0, lx, ly, 20);
                        grad.addColorStop(0, 'rgba(239, 68, 68, 0.8)');
                        grad.addColorStop(1, 'rgba(239, 68, 68, 0)');
                        ctx.fillStyle = grad;
                        ctx.beginPath();
                        ctx.arc(lx, ly, 20, 0, Math.PI * 2);
                        ctx.fill();

                        ctx.beginPath();
                        ctx.arc(lx, ly, 6, 0, Math.PI * 2);
                        ctx.fillStyle = '#ef4444';
                        ctx.fill();
                        ctx.strokeStyle = 'white';
                        ctx.lineWidth = 2;
                        ctx.stroke();
                        ctx.restore();
                    }
                    isMainDrawn = true;
                } else if (isScreenOn) {
                    drawImageFit(ctx, screenVid, 0, 0, canvas.width, canvas.height);
                    isMainDrawn = true;
                }

                if (!isMainDrawn && isCamOn && camVid.readyState >= 2) {
                    drawImageCover(ctx, camVid, 0, 0, canvas.width, canvas.height);
                }

                // PIP removed entirely per user request
            }

            function drawImageCover(ctx, img, x, y, w, h) {
                const imgW = (img instanceof HTMLCanvasElement) ? img.width : img.videoWidth;
                const imgH = (img instanceof HTMLCanvasElement) ? img.height : img.videoHeight;
                if (!imgW || !imgH) return;

                const aspect = w / h;
                const imgAspect = imgW / imgH;
                let sx, sy, sw, sh;

                if (imgAspect > aspect) {
                    sh = imgH;
                    sw = imgH * aspect;
                    sx = (imgW - sw) / 2;
                    sy = 0;
                } else {
                    sw = imgW;
                    sh = imgW / aspect;
                    sx = 0;
                    sy = (imgH - sh) / 2;
                }
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

            // Use setInterval instead of requestAnimationFrame so the broadcast 
            // continues even if the teacher switches tabs (prevents black screen for students)
            setInterval(draw, 1000 / 30);

            const canvasStream = canvas.captureStream(30);
            const audioTracks = (camStream && camStream.getAudioTracks().length > 0) ? camStream.getAudioTracks() : [];

            // Construct a pristine MediaStream with consolidated tracks
            stream = new MediaStream([...canvasStream.getVideoTracks(), ...audioTracks]);

            LOG("📡 Yayım axını hazırlandı (V3)", "#10b981");
        }

        function toggleCam() {
            isCamOn = !isCamOn;
            const track = camStream.getVideoTracks()[0];
            if (track) track.enabled = isCamOn;
            document.getElementById('btnCam').className = isCamOn ? 'btn-ctrl active-green' : 'btn-ctrl active-red';
            LOG(`📷 Kamera: ${isCamOn ? 'Aktiv' : 'Deaktiv'}`);
        }

        function toggleMic() {
            isMicOn = !isMicOn;
            const track = camStream.getAudioTracks()[0];
            if (track) track.enabled = isMicOn;
            document.getElementById('btnMic').className = isMicOn ? 'btn-ctrl active-green' : 'btn-ctrl active-red';
            LOG(`🎤 Mikrofon: ${isMicOn ? 'Aktiv' : 'Deaktiv'}`);
        }

        async function toggleScreen() {
            if (!isScreenOn) {
                try {
                    screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                    document.getElementById('screenSource').srcObject = screenStream;
                    isScreenOn = true;
                    document.getElementById('btnScreen').classList.add('active-green');
                    screenStream.getVideoTracks()[0].onended = () => {
                        isScreenOn = false;
                        document.getElementById('btnScreen').classList.remove('active-green');
                    };
                } catch (e) {
                    LOG("⚠️ Ekran paylaşımı ləğv edildi");
                }
            } else {
                screenStream.getTracks().forEach(t => t.stop());
                isScreenOn = false;
                document.getElementById('btnScreen').classList.remove('active-green');
            }
        }

        function sendChat() {
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (!msg) return;

            const data = { type: 'chat', sender: 'Mühazirəçi', message: msg };
            broadcast(data);
            appendChat('Mən', msg, '#10b981');
            input.value = '';
        }

        function broadcast(data, exclude = null) {
            allDataConns.forEach(c => {
                if (c.open && c.peer !== exclude) c.send(data);
            });
        }

        function switchSidebarTab(tab) {
            const isChat = tab === 'chat';
            document.getElementById('tabBtnChat').className = isChat ? 'flex-1 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400 border-b-2 border-emerald-500 transition-all flex items-center justify-center gap-2' : 'flex-1 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/30 border-b-2 border-transparent hover:text-white/60 transition-all flex items-center justify-center gap-2';
            document.getElementById('tabBtnStudents').className = !isChat ? 'flex-1 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-emerald-400 border-b-2 border-emerald-500 transition-all flex items-center justify-center gap-2' : 'flex-1 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/30 border-b-2 border-transparent hover:text-white/60 transition-all flex items-center justify-center gap-2';
            document.getElementById('tabContentChat').classList.toggle('hidden', !isChat);
            document.getElementById('tabContentStudents').classList.toggle('hidden', isChat);
            if (!isChat) renderStudentList();
            lucide.createIcons();
        }

        function renderStudentList() {
            const container = document.getElementById('studentListContainer');
            const countBadge = document.getElementById('studentCountBadge');
            container.innerHTML = '';
            countBadge.innerText = allDataConns.length;

            if (allDataConns.length === 0) {
                container.innerHTML = '<div class="text-center py-10 opacity-20 text-[10px] font-bold uppercase tracking-widest">İştirakçı yoxdur</div>';
                return;
            }

            allDataConns.forEach(conn => {
                const sName = (conn.metadata && conn.metadata.name) ? conn.metadata.name : 'Naməlum İştirakçı';
                const initial = sName.charAt(0).toUpperCase();
                const div = document.createElement('div');
                div.className = 'group flex items-center justify-between p-3 rounded-2xl hover:bg-white/5 border border-transparent hover:border-white/5 transition-all';
                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-black text-xs">${initial}</div>
                        <div>
                            <div class="text-[11px] font-bold text-white/80">${sName}</div>
                            <div class="text-[9px] font-bold text-white/20 uppercase tracking-widest">İştirakçı • Online</div>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            });
            lucide.createIcons();
        }

        function sendAnnouncement() {
            const msg = prompt("Bütün iştirakçılara elan göndərin:");
            if (msg) {
                broadcast({ type: 'announcement', message: msg });
                LOG("📣 Elan göndərildi: " + msg, "#10b981");
                appendChat('SİSTEM ELANI', msg, '#f59e0b');
            }
        }

        function changeWBSize(delta) {
            if (wbTool === 'eraser') {
                wbEraserSize = Math.max(5, Math.min(100, wbEraserSize + delta));
                document.getElementById('wbSizeDisplay').innerText = wbEraserSize;
            } else {
                wbSize = Math.max(1, Math.min(20, wbSize + delta));
                document.getElementById('wbSizeDisplay').innerText = wbSize;
            }
        }

        function appendChat(sender, msg, color = '#60a5fa') {
            const box = document.getElementById('chatMessages');
            if (box.innerHTML.includes('Çat boşdur')) box.innerHTML = '';

            const div = document.createElement('div');
            div.className = 'bg-white/5 p-4 rounded-2xl border border-white/5 animate-in slide-in-from-right duration-300';
            div.innerHTML = `
                <div class="text-[10px] font-bold uppercase tracking-widest mb-1" style="color: ${color}">${sender}</div>
                <div class="text-sm text-white/80">${msg}</div>
            `;
            box.appendChild(div);
            box.scrollTop = box.scrollHeight;
        }

        function updateViewerCount() {
            document.getElementById('viewerCount').innerText = allDataConns.length;
        }

        // --- WHITEBOARD FUNCTIONS (V2: Professional) ---
        function toggleWhiteboard() {
            isWBActive = !isWBActive;
            const overlay = document.getElementById('whiteboardOverlay');
            const btn = document.getElementById('btnWhiteboard');

            if (isWBActive) {
                overlay.classList.remove('hidden');
                btn.classList.add('active-green');
                initWBCanvas();
                // Force a resize check after showing
                setTimeout(() => { if (window.wbResize) window.wbResize(); }, 50);
                LOG("🎨 Professional Lövhə aktivdir", "#10b981");
                broadcast({ type: 'whiteboard_state', active: true });
            } else {
                overlay.classList.add('hidden');
                btn.classList.remove('active-green');
                laserActive = false;
                document.getElementById('laserCursor').style.display = 'none';
                LOG("🎥 Kamera görüntüsünə qayıdıldı");
                broadcast({ type: 'whiteboard_state', active: false });
            }
        }

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

        // --- WB Toolbar Management ---
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

        function initWBCanvas() {
            if (wbCanvas) return;
            wbCanvas = document.getElementById('wbCanvasInternal');
            wbCtx = wbCanvas.getContext('2d');

            window.wbResize = () => {
                const rect = wbCanvas.parentElement.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0) return;

                // Save current content
                let temp = null;
                if (wbCanvas.width > 0 && wbCanvas.height > 0) {
                    temp = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
                }

                wbCanvas.width = rect.width;
                wbCanvas.height = rect.height;

                if (temp) wbCtx.putImageData(temp, 0, 0);

                // Restore context settings
                wbCtx.lineCap = 'round';
                wbCtx.lineJoin = 'round';
                wbCtx.strokeStyle = wbColor;
                wbCtx.lineWidth = wbSize;
            };

            window.addEventListener('resize', window.wbResize);
            window.wbResize();

            // Init pages
            if (wbPages.length === 0) wbPages.push(null);
            updatePageIndicator();

            const startDraw = (e) => {
                const { x, y } = getXY(e);
                if (wbTool === 'laser') return;
                if (wbTool === 'text') { drawText(x, y); return; }

                if (wbCanvas.width === 0 || wbCanvas.height === 0) return;

                saveState();
                isDrawing = true;
                [startX, startY] = [x, y];
                [lastX, lastY] = [x, y];
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
                    laserActive = true;
                    laserX = x;
                    laserY = y;
                } else {
                    laser.style.display = 'none';
                    laserActive = false;
                }

                if (!isDrawing) return;

                if (wbTool === 'pencil' || wbTool === 'eraser') {
                    drawFreehand(x, y);
                } else {
                    wbCtx.putImageData(wbSnapshot, 0, 0);
                    drawShape(x, y);
                }
                if (e.type === 'touchmove') e.preventDefault();
            };

            const endDraw = () => {
                isDrawing = false;
                wbSnapshot = null;
            };

            // Mouse Events
            wbCanvas.addEventListener('mousedown', startDraw);
            wbCanvas.addEventListener('mousemove', moveDraw);
            wbCanvas.addEventListener('mouseup', endDraw);
            wbCanvas.addEventListener('mouseout', () => {
                isDrawing = false;
                document.getElementById('laserCursor').style.display = 'none';
                laserActive = false;
            });

            // Touch Events (Mobile)
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
            wbCtx.beginPath();
            wbCtx.moveTo(lastX, lastY);
            wbCtx.lineTo(x, y);

            if (wbTool === 'eraser') {
                wbCtx.globalCompositeOperation = 'destination-out';
                wbCtx.lineWidth = wbEraserSize;
            } else {
                wbCtx.globalCompositeOperation = 'source-over';
                wbCtx.strokeStyle = wbColor;
                wbCtx.lineWidth = wbSize;
            }

            wbCtx.stroke();
            wbCtx.globalCompositeOperation = 'source-over';
            [lastX, lastY] = [x, y];
        }

        function drawShape(x, y) {
            wbCtx.beginPath();
            wbCtx.strokeStyle = wbColor;
            wbCtx.lineWidth = wbSize;
            if (wbTool === 'line') {
                wbCtx.moveTo(startX, startY);
                wbCtx.lineTo(x, y);
            } else if (wbTool === 'rect') {
                wbCtx.strokeRect(startX, startY, x - startX, y - startY);
            } else if (wbTool === 'circle') {
                let r = Math.sqrt(Math.pow(x - startX, 2) + Math.pow(y - startY, 2));
                wbCtx.arc(startX, startY, r, 0, 2 * Math.PI);
            } else if (wbTool === 'arrow') {
                const headlen = 15;
                const angle = Math.atan2(y - startY, x - startX);
                wbCtx.moveTo(startX, startY);
                wbCtx.lineTo(x, y);
                wbCtx.lineTo(x - headlen * Math.cos(angle - Math.PI / 6), y - headlen * Math.sin(angle - Math.PI / 6));
                wbCtx.moveTo(x, y);
                wbCtx.lineTo(x - headlen * Math.cos(angle + Math.PI / 6), y - headlen * Math.sin(angle + Math.PI / 6));
            }
            wbCtx.stroke();
        }

        function drawText(x, y) {
            const txt = prompt("Mətn daxil edin:");
            if (txt) {
                saveState();
                wbCtx.font = "bold 24px 'Inter', sans-serif";
                wbCtx.fillStyle = wbColor;
                wbCtx.fillText(txt, x, y);
            }
        }

        // Image Management
        function wbUploadImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    placementImg = new Image();
                    placementImg.onload = () => showImagePlacement(placementImg);
                    placementImg.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function showImagePlacement(img) {
            const overlay = document.getElementById('imagePlacementOverlay');
            const container = document.getElementById('imagePlacementContainer');
            const imgEl = document.getElementById('placementImage');
            imgEl.src = img.src;
            imgAspectRatio = img.width / img.height;

            const w = 400, h = 400 / imgAspectRatio;
            container.style.width = w + 'px';
            container.style.height = h + 'px';
            container.style.left = '100px';
            container.style.top = '100px';
            overlay.style.display = 'block';

            container.onmousedown = (e) => {
                if (e.target.id === 'resizeHandle') return;
                isDraggingImg = true;
                [imgDragStartX, imgDragStartY] = [e.clientX, e.clientY];
                [imgStartLeft, imgStartTop] = [parseInt(container.style.left), parseInt(container.style.top)];
            };

            document.getElementById('resizeHandle').onmousedown = (e) => {
                isResizingImg = true;
                imgDragStartX = e.clientX;
                imgStartWidth = parseInt(container.style.width);
                e.stopPropagation();
            };

            window.onmousemove = (e) => {
                if (isDraggingImg) {
                    container.style.left = (imgStartLeft + (e.clientX - imgDragStartX)) + 'px';
                    container.style.top = (imgStartTop + (e.clientY - imgDragStartY)) + 'px';
                } else if (isResizingImg) {
                    const nw = Math.max(50, imgStartWidth + (e.clientX - imgDragStartX));
                    container.style.width = nw + 'px';
                    container.style.height = (nw / imgAspectRatio) + 'px';
                }
            };

            window.onmouseup = () => { isDraggingImg = isResizingImg = false; };
        }

        function confirmImagePlacement() {
            const container = document.getElementById('imagePlacementContainer');
            saveState();
            wbCtx.drawImage(placementImg, parseInt(container.style.left), parseInt(container.style.top), parseInt(container.style.width), parseInt(container.style.height));
            document.getElementById('imagePlacementOverlay').style.display = 'none';
        }

        function cancelImagePlacement() {
            document.getElementById('imagePlacementOverlay').style.display = 'none';
        }

        // Page System
        function addNewPage() {
            wbPages[currentWBPage] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
            wbPages.push(null);
            currentWBPage = wbPages.length - 1;
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
            updatePageIndicator();
            LOG("📄 Yeni səhifə əlavə edildi");
        }

        function prevPage() {
            if (currentWBPage > 0) {
                wbPages[currentWBPage] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
                currentWBPage--;
                wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
                if (wbPages[currentWBPage]) wbCtx.putImageData(wbPages[currentWBPage], 0, 0);
                updatePageIndicator();
            }
        }

        function nextPage() {
            if (currentWBPage < wbPages.length - 1) {
                wbPages[currentWBPage] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
                currentWBPage++;
                wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
                if (wbPages[currentWBPage]) wbCtx.putImageData(wbPages[currentWBPage], 0, 0);
                updatePageIndicator();
            }
        }

        function updatePageIndicator() {
            document.getElementById('pageIndicator').innerText = `${currentWBPage + 1} / ${wbPages.length}`;
        }

        function setWBBackground(type) {
            wbBgType = type;
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

        function setWBTool(tool) {
            wbTool = tool;
            document.querySelectorAll('.wb-tool-btn').forEach(b => { if (b.id && b.id.startsWith('tool')) b.classList.remove('active', 'text-emerald-400'); });
            document.getElementById('tool' + tool.charAt(0).toUpperCase() + tool.slice(1)).classList.add('active', 'text-emerald-400');

            // Update size display
            document.getElementById('wbSizeDisplay').innerText = (tool === 'eraser') ? wbEraserSize : wbSize;
        }

        function setWBColor(color, el) {
            wbColor = color;
            document.querySelectorAll('.wb-color-btn').forEach(b => b.classList.remove('active', 'border-white'));
            el.classList.add('active', 'border-white');
        }

        function openColorPicker() { document.getElementById('customColorPicker').click(); }

        function clearWhiteboard() {
            if (confirm('Lövhə tamamilə təmizlənsin?')) {
                saveState();
                wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
                LOG("🧹 Lövhə təmizləndi", "#f59e0b");
            }
        }

        function exportWhiteboard() {
            const link = document.createElement('a');
            link.download = `Webinar_Whiteboard_${wID}_${Date.now()}.png`;
            link.href = wbCanvas.toDataURL("image/png");
            link.click();
            LOG("📸 Lövhə qeydi yadda saxlanıldı.");
        }

        async function endWebinar() {
            if (confirm('Vebinarı bitirmək istədiyinizə əminsiniz?')) {
                const btn = event.currentTarget;
                const oldText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="animate-spin mr-2">...</i> EMAL EDİLİR...';

                LOG("⌛ Yayım dayandırılır və son görüntülər saxlanılır...", "#f59e0b");

                // Allow user to leave without warning now that we are finalizing
                window.onbeforeunload = null;

                try {
                    await finalizeRecording();

                    const resp = await fetch('api/end_webinar.php?id=' + wID);
                    const d = await resp.json();

                    if (d.success) {
                        window.location.href = 'dashboard.php?success=webinar_ended';
                    } else {
                        alert(d.message);
                        btn.disabled = false;
                        btn.innerHTML = oldText;
                    }
                } catch (err) {
                    console.error("End webinar error:", err);
                    alert("Xəta baş verdi. Yenidən cəhd edin.");
                    btn.disabled = false;
                    btn.innerHTML = oldText;
                }
            }
        }

        // --- Prevent Accidental Refresh ---
        window.onbeforeunload = function () {
            return "Canlı yayım davam edir. Səhifəni yeniləsəniz yayım kəsiləcək. Davam etmək istəyirsiniz?";
        };

        // --- Student Stage Control ---
        // --- Student Stage Control ---
        function updateSideStage() {
            const container = document.getElementById('sideStageContainer');
            const video = document.getElementById('sideStageVid');
            const label = document.getElementById('sideStageLabel');
            const dot = document.getElementById('sideStageDot');
            const btnSwap = document.getElementById('btnSideSwap');
            const overlay = document.getElementById('sideStageOverlay');
            const mainCanvas = document.getElementById('outputCanvas');

            const localMainVid = document.getElementById('localMainVid');
            const camVid = document.getElementById('camSource');
            const studentVid = document.getElementById('studentSource');

            // If no student is on stage, hide the sidebar stage to avoid redundancy
            if (!activeStudentCall) {
                container.classList.add('hidden');
                localMainVid.classList.add('hidden');
                mainCanvas.classList.remove('hidden');
                mainCanvas.classList.add('mirrored-canvas');
                return;
            }

            container.classList.remove('hidden');

            // Reset classes
            container.classList.remove('bg-blue-500/5', 'bg-emerald-500/5');
            label.classList.remove('text-blue-400', 'text-emerald-400');
            dot.classList.remove('bg-blue-500', 'bg-emerald-500');

            if (isStudentMain && activeStudentCall) {
                // LOCAL SWAP: Showing STUDENT in MAIN stage, TEACHER in SIDEBAR
                // (But broadcast canvas still shows Teacher in background)

                // Sidebar: Show Lecturer
                video.srcObject = camStream;
                video.classList.add('scale-x-[-1]');
                label.innerText = "MƏNİM KAMERAM";
                container.classList.add('bg-blue-500/5');
                label.classList.add('text-blue-400');
                dot.classList.add('bg-blue-500');
                btnSwap.classList.add('bg-emerald-500/80');
                overlay.classList.add('hidden');

                // Main Stage: Show Participant (Locally)
                mainCanvas.classList.add('hidden'); // Hide broadcast preview
                localMainVid.classList.remove('hidden'); // Show student direct feed
                localMainVid.srcObject = studentVid.srcObject;
                localMainVid.classList.remove('scale-x-[-1]');
            } else if (activeStudentCall) {
                // NORMAL: Showing LECTURER in MAIN stage, PARTICIPANT in SIDEBAR

                // Sidebar: Show Participant
                video.srcObject = studentVid.srcObject;
                video.classList.remove('scale-x-[-1]');
                label.innerText = "İŞTİRAKÇI CANLIDA";
                container.classList.add('bg-emerald-500/5');
                label.classList.add('text-emerald-400');
                dot.classList.add('bg-emerald-500', 'animate-pulse');
                btnSwap.classList.remove('bg-emerald-500/80');
                overlay.classList.remove('hidden');

                // Main Stage: Show Lecturer (Broadcast Preview)
                mainCanvas.classList.remove('hidden');
                mainCanvas.classList.add('mirrored-canvas');
                localMainVid.classList.add('hidden');
                localMainVid.srcObject = null;
            } else {
                mainCanvas.classList.remove('hidden');
                mainCanvas.classList.add('mirrored-canvas');
                container.classList.add('hidden');
                localMainVid.classList.add('hidden');
            }

            // Show Swap button only if student is present
            if (activeStudentCall) {
                btnSwap.classList.remove('hidden');
            } else {
                btnSwap.classList.add('hidden');
            }
        }

        function toggleStudentExpand() {
            isStudentExpanded = !isStudentExpanded;
            const overlay = document.getElementById('largeStudentStageOverlay');
            if (isStudentExpanded) {
                overlay.classList.remove('hidden');
                LOG("🔍 İştirakçı görüntüsü böyüdüldü");
            } else {
                overlay.classList.add('hidden');
            }
        }

        function toggleStudentMain() {
            if (!activeStudentCall) return;
            isStudentMain = !isStudentMain;
            updateSideStage();

            if (isStudentMain) {
                LOG("🌟 İştirakçı əsas yayım səhnəsinə çıxarıldı!", "#10b981");
            } else {
                LOG("📺 Mühazirəçi kamerasına qayıdıldı");
            }
        }

        function closeStudentCall(notify = true) {
            if (activeStudentCall) {
                activeStudentCall.close();
                activeStudentCall = null;
            }

            isStudentMain = false;
            updateSideStage();

            document.getElementById('largeStudentStageOverlay').classList.add('hidden');
            isStudentExpanded = false;

            document.getElementById('studentSource').srcObject = null;
            document.getElementById('studentRemoteVidLarge').srcObject = null;

            if (notify) {
                LOG("🔌 İştirakçı səhnədən çıxarıldı", "#ef4444");
                broadcast({ type: 'stage_ended' });
            }
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

        function toggleMobilePanel(side) {
            const aside = document.getElementById('studioAside');
            if (!aside) return; // Defensive check

            const studentsTab = document.querySelector('[data-tab="students"]');
            const chatTab = document.querySelector('[data-tab="chat"]');

            const isCurrentlyOpen = aside.classList.contains('mobile-open');
            const targetTab = side === 'left' ? studentsTab : chatTab;

            if (isCurrentlyOpen) {
                // Determine if we click identical tab
                const isActive = targetTab && targetTab.classList.contains('active-tab-class');
            }

            if (!isCurrentlyOpen) {
                if (side === 'left' && studentsTab) studentsTab.click();
                if (side === 'right' && chatTab) chatTab.click();
                aside.classList.add('mobile-open');
            } else {
                aside.classList.remove('mobile-open');
            }
        }

        function toggleMobileChat() {
            toggleMobilePanel('right');
        }

        function startProductionNow() {
            // 1. Request Fullscreen
            try {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(() => { });
                }
            } catch (e) { }

            // 2. Hide Overlay
            const overlay = document.getElementById('startProductionOverlay');
            overlay.style.transition = 'opacity 0.8s, transform 0.8s';
            overlay.style.opacity = '0';
            overlay.style.transform = 'scale(1.1)';
            setTimeout(() => {
                overlay.style.display = 'none';
                document.body.classList.remove('overflow-hidden');
            }, 800);

            // 3. Init
            init();
            LOG("🎬 Yayım və tam ekran rejimi başladıldı.", "#10b981");
        }

        // --- PROTECTION LOGIC ---
        // Block F5, F12, Ctrl+R, Ctrl+F5
        window.addEventListener('keydown', (e) => {
            // 116 = F5, 123 = F12, 82 = R
            if (e.keyCode === 116 || e.keyCode === 123 || (e.ctrlKey && e.keyCode === 82)) {
                e.preventDefault();
                LOG("⚠️ Diqqət: Yayım zamanı bu əməliyyat bloklanıb!", "#f59e0b");
                return false;
            }
        });

        // Warn before leaving
        window.addEventListener('beforeunload', (e) => {
            const msg = "Vebinar davam edir. Çıxsanız yayım kəsiləcək və hər kəs sistemdən atılacaq!";
            e.preventDefault();
            e.returnValue = msg;
            return msg;
        });

        window.onload = () => {
            // Don't call init here, wait for click
            lucide.createIcons();
        };

        const oldBeforeUnload = window.onbeforeunload;
        window.onbeforeunload = () => {
            if (peer) peer.destroy();
            if (oldBeforeUnload) oldBeforeUnload();
        };
    </script>
</body>

</html>