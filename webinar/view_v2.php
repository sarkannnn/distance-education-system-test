<?php
// webinar/view_v2.php
require_once 'config/auth.php';
require_once 'config/database.php';

WebinarAuth::requireLogin();
$user = WebinarAuth::getCurrentUser();
$db = WebinarDatabase::getInstance();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ../student/live-classes.php');
    exit;
}

$webinar = $db->fetch(
    "SELECT w.*, d.name as dept_name, u.full_name as teacher_name 
     FROM webinars w 
     LEFT JOIN webinar_departments d ON w.department_id = d.id 
     JOIN webinar_users u ON w.teacher_id = u.id
     WHERE w.id = ? AND (w.faculty_id = ? OR w.department_id = ?)",
    [$id, $user['faculty_id'] ?? 0, $user['department_id'] ?? 0]
);

if (!$webinar) {
    die("Vebinar tapılmadı və ya giriş icazəniz yoxdur.");
}

$pageTitle = "Canlı İzle V2: " . $webinar['title'];
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <link rel="stylesheet" href="assets/css/v2/studio.css?v=<?php echo time(); ?>">
</head>

<body class="bg-[#060f23]">
    <!-- Header (Old Structure with V2 Design) -->
    <header
        class="h-16 lg:h-20 border-b border-white/5 bg-[#0a1f44]/80 backdrop-blur-md flex items-center justify-between px-6 z-50">
        <div class="flex items-center gap-4">
            <div
                class="w-10 h-10 lg:w-12 lg:h-12 rounded-2xl bg-blue-500/20 flex items-center justify-center border border-blue-500/20">
                <i data-lucide="play" class="w-6 h-6 text-blue-400 fill-current"></i>
            </div>
            <div>
                <h1 class="text-sm lg:text-base font-black text-white leading-none mb-1">
                    <?php echo e($webinar['title']); ?>
                </h1>
                <p class="text-[10px] text-white/40 font-bold uppercase tracking-widest">
                    Mühazirəçi: <?php echo e($webinar['teacher_name']); ?>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div id="connectionStatus"
                class="flex items-center gap-2 bg-white/5 px-4 py-2 rounded-2xl border border-white/5">
                <div class="w-2 h-2 rounded-full bg-yellow-400 shadow-[0_0_10px_rgba(250,204,21,0.4)]"></div>
                <span class="text-[9px] font-black text-white/40 uppercase tracking-widest">Bağlanılır...</span>
            </div>

            <button id="btnJoinStage" onclick="toggleStudentStage()"
                class="hidden md:flex items-center gap-2 px-6 py-2.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all active:scale-95">
                <i data-lucide="video" class="w-4 h-4"></i>
                Səhnəyə Qoşul
            </button>

            <div class="w-px h-8 bg-white/5 mx-2"></div>

            <a href="dashboard.php"
                class="px-6 py-2.5 bg-white/5 hover:bg-white/10 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all border border-white/5 active:scale-95">
                Ayrıl
            </a>
        </div>
    </header>

    <div class="studio-container">
        <main class="main-stage">
            <div class="live-badge">
                <div class="badge-dot"></div>
                <span>CANLI</span>
                <span class="mx-2 w-px h-3 bg-white/20"></span>
                <span id="webinarTimer">00:00:00</span>
            </div>

            <div class="video-section">
                <div class="video-grid grid-1" id="mainVideoContainer">
                    <div class="participant-box" id="teacherBox">
                        <video id="remoteVid" autoplay playsinline class="w-full h-full object-contain"></video>
                        <div class="participant-name"><?php echo e($webinar['teacher_name']); ?> (Mühazirəçi)</div>
                        
                        <!-- Swap Button -->
                        <button onclick="window.studioMedia.swapViews()" class="absolute top-6 right-6 w-12 h-12 bg-black/40 backdrop-blur-md rounded-2xl flex items-center justify-center text-white/70 hover:text-white hover:bg-black/60 transition-all z-30 group" title="Görünüşü Dəyiş">
                            <i data-lucide="refresh-cw" class="w-5 h-5 group-active:rotate-180 transition-transform duration-500"></i>
                        </button>

                        <div id="unmuteOverlay"
                            class="absolute inset-0 bg-black/60 flex items-center justify-center z-20 cursor-pointer hidden"
                            onclick="unmute()">
                            <div
                                class="bg-blue-600 px-6 py-3 rounded-2xl flex items-center gap-3 active:scale-95 transition-all">
                                <i data-lucide="volume-2" class="w-5 h-5 text-white"></i>
                                <span class="text-white text-[10px] font-black uppercase tracking-widest">Səsi
                                    Aktivləşdir</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Whiteboard Layer (Overlay) -->
                <div id="whiteboardLayer" class="whiteboard-container">
                    <!-- Whiteboard Toolbar (Vertical Left) -->
                    <div id="wbToolbarWrapper" class="absolute left-6 top-1/2 -translate-y-1/2 flex flex-col items-center gap-4 z-50">
                        <div id="wbToolbarContent" class="p-4 bg-[#0a1f44]/90 backdrop-blur-3xl border border-white/10 rounded-[2.5rem] shadow-2xl flex flex-col gap-6">
                            
                            <!-- Navigation Module -->
                            <div class="flex flex-col items-center gap-2 p-2 bg-white/5 rounded-3xl border border-white/5">
                                <button onclick="window.studioWhiteboard.prevPage()" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40">
                                    <i data-lucide="chevron-up" class="w-4 h-4"></i>
                                </button>
                                <div class="bg-emerald-500/10 px-2 py-1 rounded-lg border border-emerald-500/20">
                                    <span id="pageIndicator" class="text-[9px] font-black text-emerald-400">1/1</span>
                                </div>
                                <button onclick="window.studioWhiteboard.nextPage()" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </button>
                                <div class="w-full h-px bg-white/5 my-1"></div>
                                <button onclick="window.studioWhiteboard.addNewPage()" class="w-8 h-8 rounded-xl flex items-center justify-center bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>

                            <!-- Tools Grid -->
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="setWBTool('pencil', this)" class="wb-tool-btn active w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Qələm">
                                    <i data-lucide="pencil" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('eraser', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Pozan">
                                    <i data-lucide="eraser" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('text', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Mətn">
                                    <i data-lucide="type" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('laser', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Lazer">
                                    <i data-lucide="pointer" class="w-4.5 h-4.5"></i>
                                </button>
                                <div class="col-span-2 h-px bg-white/5 my-0.5"></div>
                                <button onclick="setWBTool('line', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Xətt">
                                    <i data-lucide="minus" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('rect', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Dördbucaqlı">
                                    <i data-lucide="square" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('circle', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Dairə">
                                    <i data-lucide="circle" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="setWBTool('arrow', this)" class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Ox">
                                    <i data-lucide="arrow-up-right" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="alert('Tezliklə...')" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Şəkil">
                                    <i data-lucide="image" class="w-4.5 h-4.5"></i>
                                </button>
                                <button onclick="toggleWBGrid(this)" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white" title="Dama Fon">
                                    <i data-lucide="grid" class="w-4.5 h-4.5"></i>
                                </button>
                            </div>

                            <!-- Properties -->
                            <div class="space-y-4 pt-4 border-t border-white/5">
                                <div class="flex items-center justify-between p-1 bg-black/40 rounded-2xl border border-white/5">
                                    <button onclick="changeWBSize(-2)" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40"><i data-lucide="minus" class="w-3.5 h-3.5"></i></button>
                                    <span id="wbSizeDisplay" class="text-[10px] font-black text-white/60 w-5 text-center">4</span>
                                    <button onclick="changeWBSize(2)" class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                                </div>
                                <div class="grid grid-cols-4 gap-2">
                                    <button onclick="setWBColor('#000000', this)" class="wb-color-dot active w-6 h-6 rounded-full bg-black border border-white/20"></button>
                                    <button onclick="setWBColor('#ef4444', this)" class="wb-color-dot w-6 h-6 rounded-full bg-rose-500 border border-transparent"></button>
                                    <button onclick="setWBColor('#3b82f6', this)" class="wb-color-dot w-6 h-6 rounded-full bg-blue-500 border border-transparent"></button>
                                    <button onclick="setWBColor('#10b981', this)" class="wb-color-dot w-6 h-6 rounded-full bg-emerald-500 border border-transparent"></button>
                                    <button onclick="document.getElementById('customColorPicker').click()" class="col-span-4 h-6 rounded-full bg-gradient-to-r from-rose-500 via-emerald-500 to-blue-500 border border-white/10 relative overflow-hidden">
                                        <input type="color" id="customColorPicker" class="opacity-0 w-full h-full cursor-pointer" onchange="setWBColor(this.value, this.parentElement)">
                                    </button>
                                </div>
                            </div>

                            <!-- Bottom Actions -->
                            <div class="flex items-center gap-2 pt-4 border-t border-white/5">
                                <button onclick="window.studioWhiteboard.undo()" class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-white/5 hover:bg-white/10 text-white/20 hover:text-white" title="Geri Al">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>
                                <button onclick="clearWhiteboard()" class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20" title="Təmizlə">
                                    <i data-lucide="home" class="w-4 h-4"></i>
                                </button>
                                <button onclick="toggleWhiteboard()" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-rose-500/10 text-rose-400 hover:bg-rose-500/20" title="Bağla">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <canvas id="wbCanvasInternal"></canvas>
                </div>

                <!-- Laser Cursor -->
                <div id="laserCursor" class="fixed w-4 h-4 bg-rose-500 rounded-full blur-[2px] border-2 border-white pointer-events-none hidden z-[200] shadow-[0_0_15px_rgba(239,68,68,0.8)]"></div>
                
                <!-- Hidden Source Elements for Compositing -->
                <canvas id="studentOutputCanvas" style="display:none"></canvas>
                <video id="camSource" autoplay playsinline muted style="display:none"></video>
                <video id="screenSource" autoplay playsinline muted style="display:none"></video>
            </div>


            <div class="controls-bar">
                <!-- Media Controls -->
                <div class="control-item">
                    <button id="btnMic" onclick="toggleMic()" class="btn-control active-red">
                        <i data-lucide="mic"></i>
                    </button>
                    <span class="control-label">Səs</span>
                </div>

                <div class="control-item">
                    <button id="btnCam" onclick="toggleCam()" class="btn-control active-red">
                        <i data-lucide="video"></i>
                    </button>
                    <span class="control-label">Video</span>
                </div>

                <div class="w-px h-8 bg-white/10 mx-2"></div>

                <!-- Action Controls -->
                <div class="control-item">
                    <button id="btnScreen" onclick="toggleScreen()" class="btn-control">
                        <i data-lucide="monitor"></i>
                    </button>
                    <span class="control-label">Ekran</span>
                </div>

                <div class="control-item">
                    <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="btn-control">
                        <i data-lucide="edit-3"></i>
                    </button>
                    <span class="control-label">Lövhə</span>
                </div>

                <div class="control-item">
                    <button id="btnPiP" onclick="togglePiP()" class="btn-control">
                        <i data-lucide="external-link"></i>
                    </button>
                    <span class="control-label">PiP</span>
                </div>

                <div class="control-item">
                    <button id="btnSwap" onclick="window.studioMedia.swapViews()" class="btn-control">
                        <i data-lucide="refresh-cw"></i>
                    </button>
                    <span class="control-label">Dəyiş</span>
                </div>
            </div>
        </main>

        <?php require_once 'includes/v2/sidebar.php'; ?>
    </div>

    <!-- Student Lobby Modal -->
    <div id="studentLobbyModal"
        class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-[#060f23]/90 backdrop-blur-md p-4 transition-opacity duration-300">
        <div class="w-full max-w-3xl bg-[#0a1f44] rounded-[2rem] overflow-hidden shadow-[0_30px_60px_rgba(0,0,0,0.6)] border border-white/10 flex flex-col md:flex-row transform transition-transform duration-300 scale-95 opacity-0"
            id="studentLobbyContent">
            <!-- Video Preview -->
            <div class="w-full md:w-[55%] bg-black relative aspect-video md:aspect-auto md:min-h-[360px]">
                <video id="studentLobbyVideo" autoplay playsinline muted
                    class="w-full h-full object-cover scale-x-[-1]"></video>
                <div
                    class="absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-black/90 via-black/40 to-transparent flex flex-col items-center gap-3">

                    <!-- Audio Visualizer Wave -->
                    <div id="studentMicVisualizer"
                        class="flex items-center justify-center gap-1.5 h-8 w-24 bg-black/40 backdrop-blur-md rounded-full px-4 border border-white/10">
                        <div class="w-1.5 h-1 bg-emerald-400 rounded-full transition-all duration-75"></div>
                        <div class="w-1.5 h-2 bg-emerald-400 rounded-full transition-all duration-75"></div>
                        <div class="w-1.5 h-1 bg-emerald-400 rounded-full transition-all duration-75"></div>
                        <div class="w-1.5 h-3 bg-emerald-400 rounded-full transition-all duration-75"></div>
                        <div class="w-1.5 h-1 bg-emerald-400 rounded-full transition-all duration-75"></div>
                    </div>

                    <div class="flex justify-center gap-4">
                        <button onclick="window.studioMedia.toggleMic()" id="lobbyMicBtn"
                            class="btn-control w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-lg active-green transition-all hover:scale-110 active:scale-95 border-none">
                            <i data-lucide="mic" class="w-5 h-5"></i>
                        </button>
                        <button onclick="window.studioMedia.toggleCam()" id="lobbyCamBtn"
                            class="btn-control w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-lg active-green transition-all hover:scale-110 active:scale-95 border-none">
                            <i data-lucide="video" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Info & Actions -->
            <div
                class="w-full md:w-[45%] p-8 md:p-10 flex flex-col justify-center bg-gradient-to-br from-[#0a1f44] to-[#06112a]">
                <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.25em] mb-3 block">Səhnəyə
                    Hazırlıq</span>
                <h3 class="text-3xl font-black text-white mb-4 leading-tight tracking-tight">Studiyaya<br>qoşulursunuz
                </h3>
                <p class="text-sm text-white/50 mb-8 font-medium leading-relaxed">Yayına başlamazdan öncə səs və
                    görüntünüzü yoxlayın. Hazır olduqda aşağıdakı düyməni sıxın.</p>
                <div class="space-y-3 mt-auto">
                    <button onclick="confirmStudentStage()"
                        class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-white rounded-2xl font-black text-sm tracking-[0.15em] uppercase transition-all shadow-[0_15px_30px_rgba(16,185,129,0.25)] hover:shadow-[0_20px_40px_rgba(16,185,129,0.35)] hover:-translate-y-1 active:translate-y-0 active:scale-95 flex items-center justify-center gap-3">
                        Səhnəyə Daxil Ol <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                    <button onclick="cancelStudentStage()"
                        class="w-full py-4 bg-white/5 hover:bg-white/10 text-white/70 rounded-2xl font-bold text-sm tracking-widest uppercase transition-all hover:text-white active:scale-95">
                        Ləğv Et
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/v2/studio_media.js"></script>
    <script src="assets/js/v2/studio_chat.js"></script>
    <script src="assets/js/v2/studio_whiteboard.js"></script>
    <script>
        lucide.createIcons();

        const studioConfig = {
            wID: <?php echo (int) $id; ?>,
            uName: "<?php echo htmlspecialchars($user['fullname'] ?? $user['full_name'] ?? 'İştirakçı'); ?>",
            role: "student",
            teacherPeerId: "<?php echo $webinar['peer_id']; ?>"
        };

        console.log("StudioConfig:", studioConfig);
        window.initStudioMedia(studioConfig);
        
        // Initialize Whiteboard
        window.initStudioWhiteboard('wbCanvasInternal', 'student');

        function unmute() {
            const vid = document.getElementById('remoteVid');
            vid.muted = false;
            vid.play();
            document.getElementById('unmuteOverlay').classList.add('hidden');
        }

        async function toggleStudentStage() {
            if (!window.studioMedia) return;

            const btn = document.getElementById('btnJoinStage');
            const isCurrentlyOnStage = btn.classList.contains('bg-emerald-500/30');

            if (isCurrentlyOnStage) {
                await window.studioMedia.leaveStage();
                btn.classList.remove('bg-emerald-500/30');
                btn.innerHTML = '<i data-lucide="video" class="w-4 h-4"></i>Səhnəyə Qoşul';
                lucide.createIcons();
            } else {
                // Open Lobby Modal
                const modal = document.getElementById('studentLobbyModal');
                const content = document.getElementById('studentLobbyContent');
                modal.classList.remove('hidden');

                // Trigger reflow for animation
                void modal.offsetWidth;

                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');

                await window.studioMedia.previewStage();
            }
        }

        async function confirmStudentStage() {
            const btn = document.getElementById('btnJoinStage');
            const success = await window.studioMedia.confirmJoinStage();

            if (success) {
                if (window.studioMedia) window.studioMedia.stopAudioVisualizer();
                btn.classList.add('bg-emerald-500/30');
                btn.innerHTML = '<i data-lucide="video-off" class="w-4 h-4"></i>Səhnədən Ayrıl';
                lucide.createIcons();
                closeStudentLobby();
            }
        }

        function cancelStudentStage() {
            // If they cancel, we should stop the local tracks they just got for preview
            if (window.studioMedia && window.studioMedia.camStream) {
                window.studioMedia.stopAudioVisualizer();
                window.studioMedia.camStream.getTracks().forEach(t => t.stop());
                window.studioMedia.camStream = null;
                window.studioMedia.isCamOn = false;
                window.studioMedia.isMicOn = false;
            }
            closeStudentLobby();
        }

        function closeStudentLobby() {
            const modal = document.getElementById('studentLobbyModal');
            const content = document.getElementById('studentLobbyContent');

            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function toggleMic() { 
            if (window.studioMedia) window.studioMedia.toggleMic(); 
        }
        function toggleCam() { 
            if (window.studioMedia) window.studioMedia.toggleCam(); 
        }
        function toggleScreen() { 
            if (window.studioMedia) window.studioMedia.toggleScreen();
        }

        function toggleWhiteboard() { 
            const layer = document.getElementById('whiteboardLayer');
            const isActive = layer.classList.toggle('active');
            
            if (window.studioMedia) {
                window.studioMedia.isWBActive = isActive;
            }

            if (isActive && window.studioWhiteboard) {
                window.studioWhiteboard.resize();
            }

            const btn = document.getElementById('btnWhiteboard');
            if (btn) btn.classList.toggle('active-green', isActive);
        }

        // Whiteboard Helper Functions
        function setWBTool(tool, btn) {
            document.querySelectorAll('.wb-tool-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            if (window.studioWhiteboard) window.studioWhiteboard.setTool(tool);
            
            if (window.studioWhiteboard) {
                document.getElementById('wbSizeDisplay').innerText = (tool === 'eraser') ? window.studioWhiteboard.eraserSize : window.studioWhiteboard.size;
            }
        }

        function setWBColor(color, btn) {
            document.querySelectorAll('.wb-color-dot').forEach(b => b.classList.remove('active', 'border-white'));
            if (btn) btn.classList.add('active', 'border-white');
            if (window.studioWhiteboard) window.studioWhiteboard.setColor(color);
        }

        function changeWBSize(delta) {
            if (window.studioWhiteboard) {
                const current = window.studioWhiteboard.tool === 'eraser' ? window.studioWhiteboard.eraserSize : window.studioWhiteboard.size;
                const next = Math.max(1, Math.min(100, current + delta));
                window.studioWhiteboard.setLineWidth(next);
                document.getElementById('wbSizeDisplay').innerText = next;
            }
        }

        function toggleWBGrid(btn) {
            const active = btn.classList.toggle('bg-emerald-500/20');
            btn.classList.toggle('text-emerald-400', active);
            if (window.studioWhiteboard) window.studioWhiteboard.setGrid(active);
        }

        function clearWhiteboard() {
            if (confirm('Lövhəni təmizləmək istəyirsiniz?')) {
                if (window.studioWhiteboard) window.studioWhiteboard.clear(true);
            }
        }

        function togglePiP() {
            const video = document.getElementById('remoteVid');
            if (document.pictureInPictureElement) {
                document.exitPictureInPicture();
            } else {
                video.requestPictureInPicture();
            }
        }

        function switchTab(tab) {
            document.getElementById('tabChat').classList.toggle('hidden', tab !== 'chat');
            document.getElementById('tabParticipants').classList.toggle('hidden', tab !== 'participants');
            document.getElementById('tabBtnChat').classList.toggle('active', tab === 'chat');
            document.getElementById('tabBtnParticipants').classList.toggle('active', tab === 'participants');
        }

        // Simple Timer
        let seconds = 0;
        setInterval(() => {
            seconds++;
            const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
            const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            const el = document.getElementById('webinarTimer');
            if (el) el.innerText = `${h}:${m}:${s}`;
        }, 1000);
    </script>
</body>

</html>