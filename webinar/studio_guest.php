<?php
// webinar/studio_guest.php
require_once 'config/database.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    die("Giriş üçün keçərli link yoxdur.");
}

$db = WebinarDatabase::getInstance();

// Check if a webinar exists with this guest_token (or secret_id)
// For now, let's look for a webinar by a hypothetical guest_token column
// If it doesn't exist, we'll fallback to a regular ID for demo purposes
$webinar = $db->fetch(
    "SELECT w.*, d.name as dept_name 
     FROM webinars w 
     LEFT JOIN webinar_departments d ON w.department_id = d.id 
     WHERE w.guest_token = ?",
    [$token]
);

if (!$webinar) {
    die("Qonaq linki yanlışdır və ya vaxtı bitib.");
}

$user = [
    'full_name' => "Qonaq Mühazirəçi",
    'role' => 'guest'
];

$id = $webinar['id'];
$pageTitle = "Qonaq Studio: " . $webinar['title'];

// Header
require_once 'includes/v2/header.php';
?>

<div class="studio-container">
    <!-- Main Content Area -->
    <main class="main-stage">
        <!-- Status Badge -->
        <div class="live-badge">
            <div class="badge-dot"></div>
            <span>QONAQ YAYIMI</span>
            <span class="mx-2 w-px h-3 bg-white/20"></span>
            <span id="webinarTimer">00:00:00</span>
        </div>

        <!-- Video Grid Area -->
        <div class="video-section">
            <div class="video-grid grid-1" id="mainVideoContainer">
                <div class="participant-box" id="teacherBox">
                    <video id="localPreviewVid" autoplay playsinline muted class="mirrored-video"></video>
                    <div class="participant-name">Mən (Qonaq Mühazirəçi)</div>
                </div>
            </div>
        </div>

        <!-- Controls (Bottom Center) -->
        <?php require_once 'includes/v2/controls.php'; ?>
    </main>

    <!-- Sidebar (Right) -->
    <?php require_once 'includes/v2/sidebar.php'; ?>
</div>

<!-- Lobby Screen -->
<?php require_once 'includes/v2/lobby.php'; ?>

<!-- Scripts -->
<script src="assets/js/v2/studio_media.js"></script>
<script src="assets/js/v2/studio_chat.js"></script>
<script>
    lucide.createIcons();

    const studioConfig = {
        wID: <?php echo (int)$id; ?>,
        uName: "<?php echo e($user['full_name']); ?>"
    };

    window.initStudioMedia(studioConfig);
    
    function switchTab(tab) {
        document.getElementById('tabChat').classList.toggle('hidden', tab !== 'chat');
        document.getElementById('tabParticipants').classList.toggle('hidden', tab !== 'participants');
        document.getElementById('tabBtnChat').classList.toggle('active', tab === 'chat');
        document.getElementById('tabBtnParticipants').classList.toggle('active', tab === 'participants');
    }

    function startWebinarNow() {
        document.getElementById('lobbyOverlay').classList.add('hidden');
    }

    function toggleMic() { if (window.studioMedia) window.studioMedia.toggleMic(); }
    function toggleCam() { if (window.studioMedia) window.studioMedia.toggleCam(); }
    function togglePiP() { if (window.studioMedia) window.studioMedia.togglePiP(); }
    function toggleScreen() { console.log("Toggle Screen"); }
    function toggleWhiteboard() { console.log("Whiteboard not available in guest mode"); }
</script>

</body>
</html>
