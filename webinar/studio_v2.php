<?php
// webinar/studio_v2.php
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

// Fetch Webinar/Lesson data (same logic as studio.php)
if ($user['role'] === 'admin' && !isset($user['department_id'])) {
    $webinar = $db->fetch(
        "SELECT w.*, d.name as dept_name, 'webinar' as session_type
         FROM webinars w 
         LEFT JOIN webinar_departments d ON w.department_id = d.id 
         WHERE w.id = ?",
        [$id]
    );
    if (!$webinar) {
        $webinar = $db->fetch("SELECT *, 'live_class' as session_type FROM live_classes WHERE id = ?", [$id]);
    }
} else {
    $webinar = $db->fetch(
        "SELECT w.*, d.name as dept_name, 'webinar' as session_type
         FROM webinars w 
         LEFT JOIN webinar_departments d ON w.department_id = d.id 
         WHERE w.id = ? AND w.department_id = ?",
        [$id, $user['department_id']]
    );
    if (!$webinar) {
        $webinar = $db->fetch(
            "SELECT *, 'live_class' as session_type FROM live_classes 
             WHERE id = ? AND (faculty_id = ? OR instructor_id = ?)",
            [$id, $user['department_id'], $_SESSION['webinar_user_id'] ?? 0]
        );
    }
}

if (!$webinar) {
    die("Vebinar tapılmadı və ya giriş icazəniz yoxdur.");
}

$pageTitle = "Studio V2: " . $webinar['title'];

// Header
require_once 'includes/v2/header.php';
?>

<div class="studio-container">
    <!-- Main Content Area -->
    <main class="main-stage">
        <!-- Status Badge -->
        <div class="live-badge">
            <div class="badge-dot"></div>
            <span>CANLI</span>
            <span class="mx-2 w-px h-3 bg-white/20"></span>
            <span id="webinarTimer">00:00:00</span>
        </div>

        <!-- Video Grid Area -->
        <div class="video-section">
            <!-- Featured Student View (Hidden by default) -->
            <div id="featuredVideoContainer"
                class="hidden absolute inset-0 z-10 bg-black rounded-[2rem] overflow-hidden border-2 border-emerald-500/50 shadow-2xl">
                <video id="featuredVideo" autoplay playsinline class="w-full h-full object-contain"></video>
                <div
                    class="absolute bottom-6 left-6 px-4 py-2 bg-black/60 backdrop-blur-md rounded-xl border border-white/10 text-white font-bold flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span id="featuredName">Tələbənin Lövhəsi</span>
                </div>
                <button onclick="window.studioMedia.closeFeatured()"
                    class="absolute top-6 right-6 w-12 h-12 bg-black/60 backdrop-blur-md rounded-2xl border border-white/10 text-white flex items-center justify-center hover:bg-rose-500/20 hover:text-rose-400 transition-all">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="video-grid grid-1" id="mainVideoContainer">
                <!-- Teacher View (Primary) -->
                <div class="participant-box" id="teacherBox">
                    <video id="localPreviewVid" autoplay playsinline muted class="mirrored-video"></video>
                    <div class="participant-name">Mən (Mühazirəçi)</div>
                </div>

                <!-- Student Views will be appended here dynamically -->

                <!-- Canvas for legacy support/compositing -->
                <canvas id="outputCanvas" style="display:none"></canvas>

                <!-- Hidden Source Elements for Compositing -->
                <video id="camSource" autoplay playsinline muted style="display:none"></video>
                <video id="screenSource" autoplay playsinline muted style="display:none"></video>
                <video id="studentSource" autoplay playsinline muted style="display:none"></video>
            </div>

            <!-- Whiteboard Layer (Overlay) -->
            <div id="whiteboardLayer" class="whiteboard-container">
                <!-- Whiteboard Toolbar (Vertical Left) -->
                <div id="wbToolbarWrapper"
                    class="absolute left-6 top-1/2 -translate-y-1/2 flex flex-col items-center gap-4 z-50">
                    <div id="wbToolbarContent"
                        class="p-4 bg-[#0a1f44]/90 backdrop-blur-3xl border border-white/10 rounded-[2.5rem] shadow-2xl flex flex-col gap-6">

                        <!-- Navigation Module -->
                        <div class="flex flex-col items-center gap-2 p-2 bg-white/5 rounded-3xl border border-white/5">
                            <button onclick="window.studioWhiteboard.prevPage()"
                                class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40">
                                <i data-lucide="chevron-up" class="w-4 h-4"></i>
                            </button>
                            <div class="bg-emerald-500/10 px-2 py-1 rounded-lg border border-emerald-500/20">
                                <span id="pageIndicator" class="text-[9px] font-black text-emerald-400">1/1</span>
                            </div>
                            <button onclick="window.studioWhiteboard.nextPage()"
                                class="w-8 h-8 rounded-xl flex items-center justify-center hover:bg-white/10 text-white/40">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </button>
                            <div class="w-full h-px bg-white/5 my-1"></div>
                            <button onclick="window.studioWhiteboard.addNewPage()"
                                class="w-8 h-8 rounded-xl flex items-center justify-center bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <!-- Tools Grid -->
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick="setWBTool('pencil', this)"
                                class="wb-tool-btn active w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Qələm">
                                <i data-lucide="pencil" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="setWBTool('eraser', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Pozan">
                                <i data-lucide="eraser" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="setWBTool('text', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Mətn">
                                <i data-lucide="type" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="setWBTool('laser', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Lazer">
                                <i data-lucide="pointer" class="w-4.5 h-4.5"></i>
                            </button>
                            <div class="col-span-2 h-px bg-white/5 my-0.5"></div>
                            <button onclick="setWBTool('line', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Xətt">
                                <i data-lucide="minus" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="setWBTool('rect', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Dördbucaqlı">
                                <i data-lucide="square" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="setWBTool('circle', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Dairə">
                                <i data-lucide="circle" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="setWBTool('arrow', this)"
                                class="wb-tool-btn w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Ox">
                                <i data-lucide="arrow-up-right" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="alert('Tezliklə...')"
                                class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Şəkil">
                                <i data-lucide="image" class="w-4.5 h-4.5"></i>
                            </button>
                            <button onclick="toggleWBGrid(this)"
                                class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/5 border border-white/5 text-white/30 hover:text-white"
                                title="Dama Fon">
                                <i data-lucide="grid" class="w-4.5 h-4.5"></i>
                            </button>
                        </div>

                        <!-- Properties -->
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
                            <div class="grid grid-cols-4 gap-2">
                                <button onclick="setWBColor('#000000', this)"
                                    class="wb-color-dot active w-6 h-6 rounded-full bg-black border border-white/20"></button>
                                <button onclick="setWBColor('#ef4444', this)"
                                    class="wb-color-dot w-6 h-6 rounded-full bg-rose-500 border border-transparent"></button>
                                <button onclick="setWBColor('#3b82f6', this)"
                                    class="wb-color-dot w-6 h-6 rounded-full bg-blue-500 border border-transparent"></button>
                                <button onclick="setWBColor('#10b981', this)"
                                    class="wb-color-dot w-6 h-6 rounded-full bg-emerald-500 border border-transparent"></button>
                                <button onclick="document.getElementById('customColorPicker').click()"
                                    class="col-span-4 h-6 rounded-full bg-gradient-to-r from-rose-500 via-emerald-500 to-blue-500 border border-white/10 relative overflow-hidden">
                                    <input type="color" id="customColorPicker"
                                        class="opacity-0 w-full h-full cursor-pointer"
                                        onchange="setWBColor(this.value, this.parentElement)">
                                </button>
                            </div>
                        </div>

                        <!-- Bottom Actions -->
                        <div class="flex items-center gap-2 pt-4 border-t border-white/5">
                            <button onclick="window.studioWhiteboard.undo()"
                                class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-white/5 hover:bg-white/10 text-white/20 hover:text-white"
                                title="Geri Al">
                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                            </button>
                            <button onclick="clearWhiteboard()"
                                class="flex-1 h-10 rounded-2xl flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20"
                                title="Təmizlə">
                                <i data-lucide="home" class="w-4 h-4"></i>
                            </button>
                            <button onclick="toggleWhiteboard()"
                                class="w-10 h-10 rounded-2xl flex items-center justify-center bg-rose-500/10 text-rose-400 hover:bg-rose-500/20"
                                title="Bağla">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <canvas id="wbCanvasInternal"></canvas>
            </div>

            <!-- Laser Cursor -->
            <div id="laserCursor"
                class="fixed w-4 h-4 bg-rose-500 rounded-full blur-[2px] border-2 border-white pointer-events-none hidden z-[200] shadow-[0_0_15px_rgba(239,68,68,0.8)]">
            </div>
        </div>

        <!-- Controls (Bottom Center) -->
        <?php require_once 'includes/v2/controls.php'; ?>
    </main>

    <!-- Sidebar (Right) -->
    <?php require_once 'includes/v2/sidebar.php'; ?>
</div>

<!-- Lobby Screen (Always on top initially) -->
<?php require_once 'includes/v2/lobby.php'; ?>

<!-- Hidden Source Elements for Compositing -->
<video id="camSource" autoplay playsinline muted style="display:none"></video>
<video id="screenSource" autoplay playsinline muted style="display:none"></video>
<video id="studentSource" autoplay playsinline muted style="display:none"></video>

<!-- Scripts -->
<script src="assets/js/v2/studio_media.js"></script>
<script src="assets/js/v2/studio_chat.js"></script>
<script src="assets/js/v2/studio_whiteboard.js"></script>
<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Global configurations
    const studioConfig = {
        wID: <?php echo (int) $id; ?>,
        uName: "<?php echo e($user['full_name']); ?>"
    };

    // Initialize Media Manager
    window.initStudioMedia(studioConfig);

    // Initialize Whiteboard
    window.initStudioWhiteboard('wbCanvasInternal', 'teacher');

    // TAB Switching logic
    function switchTab(tab) {
        document.getElementById('tabChat').classList.toggle('hidden', tab !== 'chat');
        document.getElementById('tabParticipants').classList.toggle('hidden', tab !== 'participants');
        document.getElementById('tabBtnChat').classList.toggle('active', tab === 'chat');
        document.getElementById('tabBtnParticipants').classList.toggle('active', tab === 'participants');
    }

    // Lobby Logic
    function startWebinarNow() {
        document.getElementById('lobbyOverlay').classList.add('hidden');
        if (window.studioMedia) window.studioMedia.stopAudioVisualizer();
        console.log("Webinar starting...");
    }

    function toggleLobbyMic() { if (window.studioMedia) window.studioMedia.toggleMic(); }
    function toggleLobbyCam() { if (window.studioMedia) window.studioMedia.toggleCam(); }

    // Control functions mapping to studioMedia instance
    function toggleMic() {
        if (window.studioMedia) window.studioMedia.toggleMic();
    }
    function toggleCam() {
        if (window.studioMedia) window.studioMedia.toggleCam();
    }
    function togglePiP() {
        if (window.studioMedia) window.studioMedia.togglePiP();
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
            // Force a resize when opening to ensure canvas has dimensions
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

        // Update size display
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

    function openSettings() {
        alert("Parametrlər tezliklə aktiv olacaq.");
    }

    async function endWebinar() {
        if (confirm('Vebinarı bitirmək istədiyinizə əminsiniz?')) {
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = 'GÖZLƏYİN...';

            try {
                const resp = await fetch('api/end_webinar.php?id=' + studioConfig.wID);
                const d = await resp.json();
                if (d.success) {
                    window.location.href = 'dashboard.php?success=webinar_ended';
                } else {
                    alert(d.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Yayıma Yekun Vur';
                }
            } catch (err) {
                console.error("End webinar error:", err);
                alert("Xəta baş verdi.");
                btn.disabled = false;
            }
        }
    }

    // Timer Logic
    function initTimer() {
        let seconds = 0;
        const el = document.getElementById('webinarTimer');
        setInterval(() => {
            seconds++;
            const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
            const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            if (el) el.innerText = `${h}:${m}:${s}`;
        }, 1000);
    }

    initTimer();
</script>

</body>

</html>