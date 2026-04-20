<?php

/**
 * Teacher Live Studio - (V8.0 - PREMIUM REDESIGN)
 * - Sidebar Integrated Layout
 * - Independent Scrolling
 * - Modern Glassmorphism UI
 */
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$auth = new Auth();
requireInstructor();
$db = Database::getInstance();
$lessonId = $_GET['id'] ?? null;
$subjectId = $_GET['subject_id'] ?? null;

// Bazanın adını tapmaq və dərsi dürüst axtarmaq (Support both Local ID and TMİS ID)
$lesson = $db->fetch(
    "SELECT lc.*, c.title as course_title 
     FROM live_classes lc 
     LEFT JOIN courses c ON lc.course_id = c.id 
     WHERE lc.id = ? OR lc.tmis_session_id = ?",
    [$lessonId, $lessonId]
);

// Fallback: Əgər id/tmis_session_id ilə tapılmadısa, course_id ilə aktiv dərsi axtar
if (!$lesson && $subjectId) {
    $lesson = $db->fetch(
        "SELECT lc.*, c.title as course_title 
         FROM live_classes lc 
         LEFT JOIN courses c ON lc.course_id = c.id 
         WHERE lc.course_id = ? AND lc.status = 'live' 
         ORDER BY lc.id DESC LIMIT 1",
        [$subjectId]
    );
    // Real DB id-ni istifadə et ki, bütün API çağırışları düzgün işləsin
    if ($lesson) {
        $lessonId = $lesson['id'];
    }
}

if (!$lesson) {
    if (!isset($_SESSION['mock_lesson_start_' . $lessonId])) {
        $_SESSION['mock_lesson_start_' . $lessonId] = date('Y-m-d H:i:s');
    }
    // Mock the lesson array if DB returns null
    $lesson = [
        'id' => $lessonId,
        'course_id' => $subjectId ?? $lessonId,
        'course_title' => "Canlı Studio",
        'started_at' => $_SESSION['mock_lesson_start_' . $lessonId],
        'status' => 'live'
    ];
}

// ============================================================
// Artıq bitmiş dərsi Studioda açmağın qarşısını al
// ============================================================
if (isset($lesson['status']) && in_array($lesson['status'], ['ended', 'completed'])) {
    header('Location: live-lessons.php?error=' . urlencode('Bu dərs artıq bitirilmişdir.'));
    exit;
}

// Global safety check
if (!isset($lesson['course_id']) || $lesson['course_id'] == $lessonId)
    $lesson['course_id'] = $_GET['subject_id'] ?? $lesson['course_id'] ?? $lessonId ?? 0;
if (!isset($lesson['course_title']))
    $lesson['course_title'] = "Canlı Studio";
if (!isset($lesson['started_at'])) {
    if (!isset($_SESSION['mock_lesson_start_' . $lessonId])) {
        $_SESSION['mock_lesson_start_' . $lessonId] = date('Y-m-d H:i:s');
    }
    $lesson['started_at'] = $_SESSION['mock_lesson_start_' . $lessonId];
}

if (empty($lesson['course_title'])) {
    $lesson['course_title'] = "Fənn " . $lesson['course_id'];
}

// Set to true for portrait (vertical) camera on phones, false for landscape (horizontal)
$portraitCameraOnPhone = false;

require_once 'includes/header.php';
?>
<style>
    /* Studio tam ekran: sidebar və top header gizlə */
    .sidebar,
    aside.sidebar,
    .top-header,
    #chatbot-container {
        display: none !important;
    }

    .main-wrapper {
        padding-left: 0 !important;
        margin-left: 0 !important;
    }

    body,
    html {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
        background: #0f172a;
        font-family: 'Inter', sans-serif;
    }

    @keyframes blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    @keyframes pulse-red {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }

    #chatMessages::-webkit-scrollbar,
    #liveAttendanceList::-webkit-scrollbar,
    #studentsGrid::-webkit-scrollbar,
    #logBox::-webkit-scrollbar {
        width: 5px;
    }

    #chatMessages::-webkit-scrollbar-track,
    #liveAttendanceList::-webkit-scrollbar-track,
    #studentsGrid::-webkit-scrollbar-track,
    #logBox::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatMessages::-webkit-scrollbar-thumb,
    #liveAttendanceList::-webkit-scrollbar-thumb,
    #studentsGrid::-webkit-scrollbar-thumb,
    #logBox::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .control-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(14px);
    }

    .control-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-3px);
    }

    .control-btn.active-green {
        background: #10b981 !important;
        border-color: #10b981;
    }

    .control-btn.active-red {
        background: #ef4444 !important;
        border-color: #ef4444;
    }

    .sidebar-section {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 15px;
        display: flex;
        flex-direction: column;
    }

    #whiteboardOverlay {
        position: fixed;
        inset: 0;
        background: #fdfdfd;
        z-index: 2000;
        display: none;
        flex-direction: column;
        font-family: 'Inter', sans-serif;
        opacity: 0;
        transition: opacity 0.3s ease, transform 0.3s ease;
        transform: scale(0.98);
    }
    
    #whiteboardOverlay.is-visible {
        opacity: 1;
        transform: scale(1);
    }

    .wb-controls-floating {
        position: absolute;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(24px);
        padding: 12px 24px;
        border-radius: 100px;
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 15px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.15);
        z-index: 2010;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
        max-width: 95vw;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .wb-controls-floating::-webkit-scrollbar {
        display: none;
    }

    /* Collapsed: slide down */
    .wb-controls-floating.wb-collapsed {
        transform: translateX(-50%) translateY(calc(100% + 40px));
        opacity: 0;
        pointer-events: none;
    }

    /* Floating re-open tab (visible only when toolbar is collapsed) */
    #wbToolbarOpenTab {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 2015;
        display: none;
        flex-direction: row;
        align-items: center;
        gap: 8px;
        background: rgba(15, 23, 42, 0.98);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 100px;
        padding: 10px 20px;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        transition: background 0.2s;
    }

    #wbToolbarOpenTab:hover {
        background: rgba(59, 130, 246, 0.25);
        border-color: #3b82f6;
    }

    #wbToolbarOpenTab.visible {
        display: flex;
    }

    .wb-group {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 8px;
    }

    /* Vertical Divider instead of Bottom Border */
    .wb-divider {
        width: 1px;
        height: 30px;
        background: rgba(255, 255, 255, 0.15);
        margin: 0 5px;
    }

    .wb-tool-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #e2e8f0;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .wb-tool-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-3px);
        color: white;
    }

    .wb-tool-btn:active {
        transform: translateY(0) scale(0.95);
    }

    .wb-tool-btn.active {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border-color: #3b82f6;
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
    }

    .wb-color-grid {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 6px;
    }

    .wb-color {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid rgba(255, 255, 255, 0.2);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .wb-color:hover {
        transform: scale(1.2);
        border-color: rgba(255, 255, 255, 0.8);
    }

    .wb-color.active {
        transform: scale(1.2);
        border-color: white;
        box-shadow: 0 0 12px currentColor;
    }

    #laserCursor {
        position: absolute;
        width: 12px;
        height: 12px;
        background: radial-gradient(circle, #ef4444 0%, transparent 70%);
        border: 2px solid white;
        border-radius: 50%;
        box-shadow: 0 0 10px #ef4444;
        pointer-events: none;
        display: none;
        z-index: 2020;
    }

    @keyframes zoomIn {
        from {
            opacity: 0;
            transform: translate(-50%, -20%) scale(0.9);
        }

        to {
            opacity: 1;
            transform: translate(-50%, 0) scale(1);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translate(-50%, 40px);
        }

        to {
            opacity: 1;
            transform: translate(-50%, 0);
        }
    }

    @keyframes slideIn {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Responsive Studio Layout */
    .studio-main-grid {
        display: grid;
        grid-template-columns: 280px 1fr 380px;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    /* Mobile toggle buttons — hidden on desktop */
    .mobile-toggle-btn {
        display: none;
    }

    @media (max-width: 1200px) {
        .studio-main-grid {
            grid-template-columns: 240px 1fr 300px;
        }
    }

    @media (max-width: 1024px) {
        .mobile-toggle-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .mobile-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .mobile-toggle-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.4);
        }

        .studio-main-grid {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr;
            height: calc(100vh - 65px);
            /* Header height fallback */
            overflow: hidden;
            position: relative;
        }

        @supports (height: 100dvh) {
            .studio-main-grid {
                height: calc(100dvh - 65px);
            }
        }

        .sidebar-left,
        .sidebar-right {
            display: none !important;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 600;
            height: 72vh !important;
            max-height: 72vh;
            border: none !important;
            border-top: 2px solid #334155 !important;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.7);
            overflow: hidden !important;
            flex-direction: column !important;
        }

        .sidebar-left.mobile-open,
        .sidebar-right.mobile-open {
            display: flex !important;
            animation: mobileSlideUp 0.3s ease-out;
        }

        /* Backdrop behind mobile panels */
        #mobilePanelBackdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 599;
            backdrop-filter: blur(2px);
        }

        #mobilePanelBackdrop.visible {
            display: block;
        }

        @keyframes mobileSlideUp {
            from {
                transform: translateY(100%);
            }

            to {
                transform: translateY(0);
            }
        }

        .sidebar-left.mobile-open,
        .sidebar-right.mobile-open {
            animation: mobileSlideUp 0.3s ease-out;
        }

        .mobile-panel-close {
            display: flex !important;
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            z-index: 510;
        }

        .studio-center {
            padding: 8px !important;
            min-height: 0;
            flex: 1;
        }

        .studio-center>div:first-child {
            padding: 8px !important;
        }

        #mainVideoWrapper {
            border-radius: 12px !important;
            max-width: 100% !important;
        }

        .studio-header {
            padding: 8px 10px !important;
            height: auto !important;
            min-height: 55px !important;
            gap: 6px;
            flex-wrap: wrap;
        }

        .studio-header h1 {
            font-size: 13px !important;
        }

        .studio-header .live-status-badge {
            padding: 5px 10px !important;
        }

        .studio-header .live-text {
            font-size: 10px !important;
        }

        /* Compact action buttons in header on tablet/mobile */
        .studio-header>div:last-child>button:not(.mobile-toggle-btn) {
            padding: 6px 12px !important;
            font-size: 11px !important;
        }

        /* Compact controls bar on mobile */
        .control-btn {
            width: 42px !important;
            height: 42px !important;
        }

        .studio-center>div:last-child {
            height: 90px !important;
            padding: 0 10px !important;
            gap: 15px !important;
            background: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .studio-center>div:last-child span {
            font-size: 8px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            opacity: 0.7;
        }

        /* Hide the divider line in controls on mobile to save space */
        .studio-center>div:last-child>div[style*="width: 1px"] {
            height: 30px !important;
            background: rgba(255,255,255,0.05) !important;
        }
    }

    @media (max-width: 600px) {
        .studio-header h1 {
            display: none !important;
        }

        .studio-header .live-status-badge span.live-text {
            display: none;
        }

        .control-btn {
            width: min(13vw, 46px) !important;
            height: min(13vw, 46px) !important;
        }
        
        .control-btn i[data-lucide] {
            width: min(6vw, 22px) !important;
            height: min(6vw, 22px) !important;
        }

        /* Even more compact toggle and action buttons on small phones */
        .mobile-toggle-btn {
            padding: 5px 8px !important;
            font-size: 10px !important;
            gap: 3px !important;
        }

        .studio-header>div:last-child>button:not(.mobile-toggle-btn) {
            padding: 5px 10px !important;
            font-size: 10px !important;
        }

        <?php if ($portraitCameraOnPhone): ?>

            /* Portrait (vertical) camera on phones */
            #mainVideoWrapper {
                aspect-ratio: 9/16 !important;
                width: auto !important;
                height: min(62vh, 400px) !important;
                max-width: 100% !important;
            }

        <?php endif; ?>

        /* Controls bar: fluid width distributing buttons evenly across the screen */
        #mainControlsBar {
            width: 100% !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            padding: 0 10px !important;
            box-sizing: border-box;
            background: rgba(15, 23, 42, 0.98) !important;
            scrollbar-width: none;
        }

        #mainControlsInner {
            margin: 0 auto !important;
            padding: 0 10px !important;
            gap: 15px !important;
            width: auto !important;
            min-width: max-content !important;
            justify-content: center !important;
        }

        #mainControlsInner > div {
            flex: 0 0 auto !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px !important;
        }

        #mainControlsInner span {
            font-size: 8px !important;
            white-space: nowrap;
            letter-spacing: 0.2px !important;
            font-weight: 800 !important;
            color: #94a3b8 !important;
            margin-top: 2px;
        }

        /* Log wrapper: position above controls bar */
        #logWrapper {
            bottom: 90px !important;
            left: 8px !important;
            right: 8px !important;
        }
    }

    /* Panel close button - hidden on desktop */
    .mobile-panel-close {
        display: none;
    }

    /* === Whiteboard controls: 2-Row Horizontal Scrollable Bottom Panel === */
    @media (max-width: 900px), (max-height: 700px) {
        .wb-controls-floating {
            top: auto !important;
            bottom: 15px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            
            /* Magic flex properties to create a perfect 2-row horizontal scroller */
            display: flex !important;
            flex-direction: column !important;
            flex-wrap: wrap !important;
            
            /* Total height = 2 buttons (38*2) + gap (8) + padding (20) + scrollbar (6) + safety margin = 115px */
            height: 115px !important;
            max-height: 115px !important;
            width: calc(100% - 20px) !important;
            max-width: 600px !important;
            
            padding: 8px 12px !important;
            padding-bottom: 10px !important;
            border-radius: 20px !important;
            
            align-content: flex-start !important;
            gap: 8px !important;
            
            overflow-x: auto !important; 
            overflow-y: hidden !important;
            
            /* Explicitly stylize scrollbar so users know it scrolls! */
            scrollbar-width: thin !important;
            scrollbar-color: rgba(255,255,255,0.4) rgba(0,0,0,0.1) !important;
        }

        .wb-controls-floating::-webkit-scrollbar {
            height: 6px !important;
            display: block !important;
        }
        .wb-controls-floating::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.2) !important;
            border-radius: 10px !important;
            margin: 0 10px;
        }
        .wb-controls-floating::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.5) !important;
            border-radius: 10px !important;
        }

        /* Flatten groupings so elements flow freely into the 2-row layout */
        .wb-group, .wb-color-grid {
            display: contents !important;
        }

        /* Dividers become tall columns to separate tools logically */
        .wb-divider {
            display: block !important;
            width: 1px !important;
            height: 80px !important; /* Will take up a whole column */
            margin: 5px 6px !important;
            background: rgba(255, 255, 255, 0.15) !important;
        }

        .wb-tool-btn {
            width: 38px !important;
            height: 38px !important;
        }
        .wb-tool-btn i[data-lucide] {
            width: 18px !important;
            height: 18px !important;
        }

        /* Vertically center text like '1/1' or '3px' */
        #pageIndicator, #sizeDisplay {
            display: flex !important;
            align-items: center !important;
            height: 38px !important;
            margin: 0 !important;
        }

        .wb-color {
            width: 22px !important;
            height: 22px !important;
            margin: 8px 4px !important; /* Vertically align smaller color dots */
        }

        /* Toggle panel animation */
        .wb-controls-floating.wb-collapsed {
            transform: translate(-50%, calc(100% + 20px)) !important;
        }

        /* Re-open Tab centered */
        #wbToolbarOpenTab {
            top: auto !important;
            bottom: 20px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            border-radius: 100px !important;
            padding: 10px 20px !important;
        }
    }

    /* === Modals: full-width on small screens === */
    @media (max-width: 540px) {
        #wbRequestModal>div {
            width: calc(100% - 30px) !important;
            max-width: none !important;
            padding: 25px 20px !important;
        }

        #micRequestModal>div {
            width: calc(100% - 30px) !important;
            max-width: none !important;
            padding: 25px 20px !important;
        }

        /* Start production overlay: scale down for small screens */
        #startProductionOverlay {
            padding: 20px !important;
        }

        #startProductionOverlay h2 {
            font-size: 20px !important;
        }

        #startProductionOverlay p {
            font-size: 14px !important;
            margin-bottom: 24px !important;
        }

        #startProductionOverlay>div>button[onclick] {
            font-size: 15px !important;
            padding: 14px 20px !important;
            width: 100%;
        }
    }

    /* === Ultra-small screens (≤ 400px) === */
    @media (max-width: 400px) {
        .control-btn {
            width: 40px !important;
            height: 40px !important;
        }

        .control-btn i[data-lucide] {
            width: 18px !important;
            height: 18px !important;
        }

        .studio-center>div:last-child {
            gap: 6px !important;
        }

        .studio-header .live-status-badge {
            padding: 4px 8px !important;
        }

        .mobile-toggle-btn {
            padding: 4px 6px !important;
            font-size: 10px !important;
        }
    }
</style>

<div class="main-wrapper" style="height: 100vh; display: flex; flex-direction: column; overflow: hidden; color: white;">
    <!-- STUDIO HEADER -->
    <div class="studio-header"
        style="min-height: 65px; padding: 10px 30px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #334155; z-index: 100;">
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div class="live-status-badge"
                style="display: flex; align-items: center; gap: 10px; background: rgba(239, 68, 68, 0.1); padding: 8px 15px; border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.2);">
                <div
                    style="width: 10px; height: 10px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; box-shadow: 0 0 10px #ef4444;">
                </div>
                <span class="live-text"
                    style="color: #ef4444; font-weight: 800; font-size: 12px; letter-spacing: 1px;">STUDİO
                    CANLI</span>
            </div>
            <h1 style="font-size: 16px; margin: 0; font-weight: 700; color: #f8fafc;">
                <?php echo e($lesson['course_title'] ?? ("Dərs #" . $lessonId)); ?> <span
                    style="opacity: 0.4; margin-left: 8px; font-weight: 400;">#<?php echo $lessonId; ?></span>
            </h1>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <!-- Mobile toggle buttons -->
            <button class="mobile-toggle-btn" onclick="toggleMobilePanel('left')" id="mobileStudentsBtn">
                📋 Tələbələr
            </button>
            <button class="mobile-toggle-btn" onclick="toggleMobilePanel('right')" id="mobileChatBtn">
                💬 Çat
            </button>
            <?php if (($_SESSION['user_role'] ?? '') !== 'admin'): ?>
            <button
                onclick="window.open('attendance_report.php?id=<?php echo $lessonId; ?>&minimal=1', 'AttendanceReport', 'width=1000,height=800,scrollbars=yes,resizable=yes')"
                style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer;">
                📋 İştirakçı Jurnalı
            </button>
            <?php endif; ?>
            <button onclick="stopAndUpload()"
                style="background: #ef4444; color: white; border: none; padding: 8px 22px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">
                Dərsi Bitir
            </button>
        </div>
    </div>

    <!-- Mobile panel backdrop -->
    <div id="mobilePanelBackdrop" onclick="closeMobilePanels()"></div>

    <!-- MAIN GRID -->
    <div class="studio-main-grid">

        <!-- LEFT SIDEBAR: ATTENDANCE -->
        <div class="sidebar-left" id="sidebarLeft"
            style="background: #1e293b; border-right: 2px solid #334155; display: flex; flex-direction: column; padding: 20px; gap: 20px; overflow: hidden; min-height: 0;">
            <button class="mobile-panel-close" onclick="toggleMobilePanel('left')">&times;</button>
            <div class="sidebar-section" style="flex: 1; min-height: 0; display: flex; flex-direction: column;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-shrink: 0;">
                    <h3
                        style="font-size: 11px; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                        Canlı İştirak
                    </h3>
                    <span id="liveAttendanceCount"
                        style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 900;">0/0</span>
                </div>
                <div id="liveAttendanceList" style="flex: 1; overflow-y: auto; padding-right: 5px;">
                    <div
                        style="padding: 20px; text-align: center; color: #64748b; font-size: 12px; font-style: italic;">
                        Yüklənir...
                    </div>
                </div>
            </div>
        </div>

        <!-- CENTER: PRODUCTION AREA -->
        <div class="studio-center"
            style="position: relative; background: #020617; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; padding: 20px; min-height: 0;">

            <!-- MAIN VIEWER -->
            <div
                style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; position: relative;">
                <div id="mainVideoWrapper"
                    style="width: 100%; max-width: 1100px; aspect-ratio: 16/9; position: relative; background: #000; border-radius: 24px; overflow: hidden; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.8); border: 1px solid rgba(255,255,255,0.05);">
                    <video id="localVid" autoplay playsinline muted
                        style="width: 100%; height: 100%; object-fit: contain; background: #000;"></video>

                    <!-- SOURCE VIDEOS (HIDDEN) -->
                    <video id="camSource" autoplay playsinline muted style="display:none;"></video>
                    <video id="screenSource" autoplay playsinline muted style="display:none;"></video>

                    <!-- SPOTLIGHT OVERLAY -->
                    <div id="spotlightOverlay"
                        style="position: absolute; top: 20px; right: 20px; display: none; z-index: 50;">
                        <button onclick="resetMainVideo()"
                            style="background: #ef4444; color: white; border: none; padding: 8px 20px; border-radius: 50px; cursor: pointer; font-size: 12px; font-weight: 800; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);">
                            KAMERAMA QAYIT
                        </button>
                    </div>

                    <!-- TEACHER NAME BADGE -->
                    <!-- <div
                        style="position: absolute; top: 25px; left: 25px; background: rgba(0,0,0,0.5); padding: 8px 15px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #fff; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px); z-index: 50; display: flex; align-items: center; gap: 8px;">
                        <div
                            style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;">
                        </div>
                        Müəllim: <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                    </div> -->
                    <!-- DURATION TIMER -->
                    <div id="lessonTimer"
                        style="position: absolute; top: 25px; right: 25px; background: rgba(0,0,0,0.7); padding: 6px 12px; border-radius: 10px; font-size: 13px; font-weight: 800; color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: flex; align-items: center; gap: 8px; font-family: 'JetBrains Mono', monospace;">
                        <i data-lucide="clock" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                        <span id="timerDisplay">00:00</span>
                    </div>
                </div>
            </div>

            <!-- CONTROLS BAR -->
            <div id="mainControlsBar"
                style="height: 120px; width: 100%; display: flex; align-items: center; background: linear-gradient(to top, rgba(15,23,42,1), rgba(15,23,42,0)); z-index: 20; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; flex-shrink: 0;">
                <div id="mainControlsInner"
                    style="display: flex; align-items: center; justify-content: center; gap: 30px; padding: 0 30px; margin: 0 auto; flex-shrink: 0; min-width: max-content;">

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button id="btnMic" onclick="toggleMic()" class="control-btn" title="Mikrofon">
                            <i data-lucide="mic" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span
                            style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">MİKROFON</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button id="btnCam" onclick="toggleCam()" class="control-btn" title="Kamera">
                            <i data-lucide="video" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span
                            style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">KAMERA</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button id="btnScreen" onclick="toggleScreenShare()" class="control-btn" title="Ekran Paylaş">
                            <i data-lucide="monitor" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span
                            style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">EKRAN</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="control-btn" title="Ağ Lövhə">
                            <i data-lucide="pen-tool" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span
                            style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">LÖVHƏ</span>
                    </div>

                    <div style="width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 10px;"></div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button id="btnModeNormal" onclick="setQualityMode('normal')" class="control-btn active-green" style="width: 50px; border-radius: 50%;" title="Standart Keyfiyyət">
                            <i data-lucide="zap" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">NORMAL</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button id="btnModeEco" onclick="setQualityMode('eco')" class="control-btn" style="width: 50px; border-radius: 50%;" title="Eco Mode (Zəif İnternet)">
                            <i data-lucide="leaf" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">ECO</span>
                    </div>

                    <div style="width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 10px;"></div>


                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <button
                            onclick="document.getElementById('logWrapper').style.display = document.getElementById('logWrapper').style.display === 'none' ? 'block' : 'none'"
                            class="control-btn" style="width: 50px; border-radius: 50%;">
                            <i data-lucide="activity" style="width: 22px; height: 22px;"></i>
                        </button>
                        <span
                            style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">LOGLAR</span>
                    </div>
                </div><!-- end inner flex wrapper -->
            </div>

            <!-- LOGS WRAPPER (HIDDEN BY DEFAULT) -->
            <div id="logWrapper"
                style="display: none; position: absolute; bottom: 110px; left: 30px; right: 30px; z-index: 100;">
                <div id="logBox"
                    style="height: 150px; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px; font-family: 'JetBrains Mono', monospace; font-size: 11px; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                    <div
                        style="color: #60a5fa; font-weight: 800; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px;">
                        SİSTEM HADİSƏLƏRİ</div>
                </div>
            </div>
        </div>

        <!-- RIGHT: SIDEBAR CORE -->
        <div class="sidebar-right" id="sidebarRight"
            style="background: #1e293b; border-left: 2px solid #334155; display: flex; flex-direction: column; overflow: hidden; padding: 25px;">
            <button class="mobile-panel-close" onclick="toggleMobilePanel('right')">&times;</button>

            <!-- STUDENTS VIDEO GRID -->
            <!-- STUDENTS VIDEO GRID -->
            <div class="sidebar-section"
                style="flex: 2; display: flex; flex-direction: column; min-height: 0; padding-bottom: 0;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-shrink: 0;">
                    <h3
                        style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                        Tələbə Kameraları</h3>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button onclick="refreshAllStudentVideos()"
                            style="background: rgba(253, 224, 71, 0.1); border: 1px solid #fde047; color: #fde047; font-size: 10px; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-weight: 700;"
                            title="Bütün kameraları yenidən başlat">🔄 Yenilə</button>
                        <span id="activeVideoCount"
                            style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 900;">0</span>
                    </div>
                </div>



                <div id="studentsGrid"
                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; overflow-y: auto; overflow-x: hidden; padding-right: 5px; flex: 1; align-content: start;">
                    <div id="waitingMsg"
                        style="grid-column: 1/-1; padding: 30px 10px; text-align: center; border: 2px dashed rgba(255,255,255,0.05); border-radius: 12px; color: #64748b; font-size: 12px; align-self: center;">
                        Gözlənilir...
                    </div>
                </div>
            </div>


            <!-- CHAT AREA -->
            <div class="sidebar-section" style="flex: 1; min-height: 0; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3
                        style="font-size: 11px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                        Dərs Çatı</h3>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="openBulkNotificationModal()"
                            style="background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; color: #3b82f6; font-size: 10px; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-weight: 700;">📣
                            Toplu Bildiriş</button>
                        <i data-lucide="message-square" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                    </div>
                </div>

                <!-- PRIVATE CHAT TARGET INDICATOR -->
                <div id="privateTargetIndicator"
                    style="display: none; background: rgba(59, 130, 246, 0.2); padding: 5px 12px; border-radius: 8px; font-size: 11px; margin-bottom: 8px; align-items: center; justify-content: space-between;">
                    <span style="color: #3b82f6; font-weight: 700;">🔒 Özəl: <span id="targetName">Tələbə</span></span>
                    <button onclick="clearPrivateTarget()"
                        style="background: none; border: none; color: #ef4444; font-size: 14px; cursor: pointer; padding: 0;">&times;</button>
                </div>

                <div id="chatMessages"
                    style="flex: 1; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 12px; margin-bottom: 12px; font-size: 13px; border: 1px solid rgba(255,255,255,0.02);">
                    <div
                        style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.2; text-align: center;">
                        <p style="font-size: 11px;">Hələ ki, mesaj yoxdur.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 8px;">
                    <label for="fileInput"
                        style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;">
                        📎
                    </label>
                    <input type="file" id="fileInput" style="display: none;" onchange="handleFileSelect(this)">

                    <input type="text" id="chatInput" placeholder="Mesaj yazın..."
                        style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 0 15px; color: white; font-size: 13px; outline: none;"
                        onkeypress="if(event.key==='Enter') sendChatMessage()">

                    <button onclick="sendChatMessage()"
                        style="background: #3b82f6; border: none; border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        🚀
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Start Production Overlay (Mandatory User Interaction for Fullscreen/Media) -->
<div id="startProductionOverlay"
    style="position: fixed; inset: 0; background: #0f172a; z-index: 30000; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px;">
    <div style="max-width: 500px;">
        <div
            style="width: 100px; height: 100px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px;">
            <i data-lucide="play-circle" style="width: 50px; height: 50px; color: #3b82f6;"></i>
        </div>
        <h2 style="font-size: 28px; font-weight: 800; color: white; margin-bottom: 15px;">Dərs Yayımına Başlayın</h2>
        <p style="color: #94a3b8; margin-bottom: 40px; line-height: 1.6; font-size: 16px;">Video qeydiyyatın
            təhlükəsizliyi və tam ekran rejimi üçün yayımı aşağıdakı düymə ilə başladın.</p>
        <button onclick="startProductionNow()"
            style="background: #3b82f6; color: white; border: none; padding: 18px 40px; border-radius: 12px; font-size: 18px; font-weight: 800; cursor: pointer; box-shadow: 0 15px 35px rgba(59, 130, 246, 0.4); transition: all 0.2s;">🚀
            YAYIMI BAŞLAT (TAM EKRAN)</button>
        <p style="color: #64748b; font-size: 12px; margin-top: 20px;">Qeyd: Dərs əsnasında səhifəni yeniləməyin.</p>
    </div>
</div>

<!-- Whiteboard Request Modal -->
<div id="wbRequestModal"
    style="display: none; position: fixed; inset: 0; background: rgba(2,6,23,0.8); backdrop-filter: blur(10px); z-index: 10000; align-items: center; justify-content: center;">
    <div
        style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1); width: 450px; padding: 40px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); text-align: center; animation: zoomIn 0.3s ease-out;">
        <div
            style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px;">
            <i data-lucide="pen-tool" style="width: 40px; height: 40px; color: #3b82f6;"></i>
        </div>
        <h3 style="font-size: 22px; font-weight: 850; margin-bottom: 10px; color: white;">Lövhə İstəyi</h3>
        <p style="color: #94a3b8; margin-bottom: 30px; line-height: 1.6;"><b><span id="wbRequesterName"
                    style="color: #60a5fa;"></span></b> lövhədən istifadə icazəsi istəyir. Təsdiqləyirsiniz?</p>
        <div style="display: flex; gap: 15px;">
            <button id="wbRejectBtn"
                style="flex: 1; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer;">RƏDD
                ET</button>
            <button id="wbApproveBtn"
                style="flex: 1; background: #3b82f6; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);">İCAZƏ
                VER</button>
        </div>
    </div>
</div>

<!-- Mic Request Modal -->
<div id="micRequestModal"
    style="display: none; position: fixed; inset: 0; background: rgba(2,6,23,0.85); z-index: 1000; backdrop-filter: blur(10px); justify-content: center; align-items: center;">
    <div
        style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 40px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 30px 60px rgba(0,0,0,0.5);">
        <div
            style="width: 80px; height: 80px; background: rgba(34, 197, 94, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; font-size: 40px;">
            🎤</div>
        <h3 id="micRequestTitle" style="margin: 0 0 10px 0; font-size: 20px; font-weight: 800;">Tələbə Söz İstəyir</h3>
        <p id="micRequestText" style="color: #94a3b8; font-size: 14px; margin-bottom: 30px; line-height: 1.6;">Tələbənin
            mikrofonunu aktivləşdirmək istəyirsiniz?</p>
        <div style="display: flex; gap: 15px;">
            <button id="micRejectBtn"
                style="flex: 1; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer;">RƏDD
                ET</button>
            <button id="micApproveBtn"
                style="flex: 1; background: #22c55e; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);">İCAZƏ
                VER</button>
        </div>
    </div>
</div>

<!-- Pure Whiteboard Overlay -->
<div id="whiteboardOverlay">
    <!-- Floating Toolbar (Horizontal bottom) -->
    <div class="wb-controls-floating">

        <!-- SƏHİFƏLƏR - Çoxsəhifəli sistem -->
        <div class="wb-group">
            <button class="wb-tool-btn" onclick="prevPage()" title="Əvvəlki Səhifə"
                style="width: 32px; height: 32px;"><i data-lucide="chevron-left"
                    style="width:16px;height:16px;"></i></button>
            <div id="pageIndicator"
                style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; min-width: 40px; text-align: center; color: white;">
                1/1</div>
            <button class="wb-tool-btn" onclick="nextPage()" title="Növbəti Səhifə"
                style="width: 32px; height: 32px;"><i data-lucide="chevron-right"
                    style="width:16px;height:16px;"></i></button>
            <button class="wb-tool-btn" onclick="addNewPage()" title="Yeni Səhifə"
                style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.15); color: #10b981;"><i
                    data-lucide="plus" style="width:16px;height:16px;"></i></button>
            <button class="wb-tool-btn" onclick="deletePage()" title="Səhifəni Sil"
                style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.15); color: #ef4444;"><i
                    data-lucide="minus" style="width:16px;height:16px;"></i></button>
        </div>

        <div class="wb-divider"></div>

        <!-- ALƏTLƏR - Əsas alətlər -->
        <div class="wb-group">
            <button id="toolPencil" class="wb-tool-btn active" onclick="setWBTool('pencil')" title="Qələm">
                <i data-lucide="pen-tool" style="width:20px;height:20px;"></i>
            </button>
            <button id="toolEraser" class="wb-tool-btn" onclick="setWBTool('eraser')" title="Silgi">
                <i data-lucide="eraser" style="width:20px;height:20px;"></i>
            </button>
            <button id="toolText" class="wb-tool-btn" onclick="setWBTool('text')" title="Mətn Yaz">
                <i data-lucide="type" style="width:20px;height:20px;"></i>
            </button>
            <button id="toolLaser" class="wb-tool-btn" onclick="setWBTool('laser')" title="Lazer Göstərici">
                <i data-lucide="mouse-pointer-2" style="width:20px;height:20px;"></i>
            </button>
            <button class="wb-tool-btn" onclick="document.getElementById('wbImgInput').click()" title="Şəkil Əlavə Et">
                <i data-lucide="image" style="width:20px;height:20px;"></i>
            </button>

            <input type="file" id="wbImgInput" style="display:none" accept="image/*" onchange="wbUploadImage(this)">
        </div>

        <!-- Size control (small slider or +/-) -->
        <div class="wb-group" style="background: rgba(0,0,0,0.2); padding: 4px 8px; border-radius: 100px;">
            <button class="wb-tool-btn" onclick="changeSize(-5)" title="Kiçilt"
                style="width: 24px; height: 24px; background: transparent; border: none; color: #94a3b8;"><i
                    data-lucide="minus" style="width:14px;height:14px;"></i></button>
            <div id="sizeDisplay"
                style="font-size: 11px; font-weight: 800; min-width: 24px; text-align: center; color: white;">3px</div>
            <button class="wb-tool-btn" onclick="changeSize(5)" title="Böyüt"
                style="width: 24px; height: 24px; background: transparent; border: none; color: #94a3b8;"><i
                    data-lucide="plus" style="width:14px;height:14px;"></i></button>
        </div>

        <div class="wb-divider"></div>

        <!-- FİQURLAR - Həndəsi formalar -->
        <div class="wb-group">
            <button id="toolLine" class="wb-tool-btn" onclick="setWBTool('line')" title="Düz Xətt">
                <i data-lucide="minus" style="width:20px;height:20px;"></i>
            </button>
            <button id="toolRect" class="wb-tool-btn" onclick="setWBTool('rect')" title="Dördbucaqlı">
                <i data-lucide="square" style="width:20px;height:20px;"></i>
            </button>
            <button id="toolCircle" class="wb-tool-btn" onclick="setWBTool('circle')" title="Dairə">
                <i data-lucide="circle" style="width:20px;height:20px;"></i>
            </button>
            <button id="toolArrow" class="wb-tool-btn" onclick="setWBTool('arrow')" title="Ox İşarəsi">
                <i data-lucide="arrow-up-right" style="width:20px;height:20px;"></i>
            </button>
        </div>

        <div class="wb-divider"></div>

        <!-- RƏNG - Rəng seçimi -->
        <div class="wb-color-grid">
            <div class="wb-color active" style="background: #000000;" onclick="setWBColor('#000000', this)"
                title="Qara"></div>
            <div class="wb-color" style="background: #ef4444;" onclick="setWBColor('#ef4444', this)" title="Qırmızı">
            </div>
            <div class="wb-color" style="background: #3b82f6;" onclick="setWBColor('#3b82f6', this)" title="Mavi"></div>
            <div class="wb-color" style="background: #10b981;" onclick="setWBColor('#10b981', this)" title="Yaşıl">
            </div>
            <div class="wb-color" style="background: #f59e0b;" onclick="setWBColor('#f59e0b', this)" title="Narıncı">
            </div>
            <div class="wb-color" style="background: #8b5cf6;" onclick="setWBColor('#8b5cf6', this)" title="Bənövşəyi">
            </div>
            <div class="wb-color" style="background: #ec4899;" onclick="setWBColor('#ec4899', this)" title="Çəhrayı">
            </div>
            <div class="wb-color"
                style="background: linear-gradient(135deg, #fff, #ddd); border:2px dashed #94a3b8; display: flex; align-items: center; justify-content: center; position: relative;"
                onclick="openColorPicker()" title="Xüsusi Rəng">
                <i data-lucide="palette" style="width:12px;height:12px;color:#64748b;"></i>
                <input type="color" id="customColorPicker"
                    style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;"
                    onchange="setCustomColor(this.value)">
            </div>
        </div>

        <div class="wb-divider"></div>

        <!-- NÖV / FON Seçimi -->
        <div class="wb-group" style="background: rgba(0,0,0,0.2); padding: 4px; border-radius: 100px;">
            <button id="bgPlain" class="wb-tool-btn active" onclick="setWBBackground('plain')" title="Ağ Fon"
                style="width: 34px; height: 34px;">
                <div style="width:16px;height:16px;background:white;border-radius:2px;border:1px solid #cbd5e1;"></div>
            </button>
            <button id="bgGrid" class="wb-tool-btn" onclick="setWBBackground('grid')" title="Riyaziyyat (Dama)"
                style="width: 34px; height: 34px;">
                <i data-lucide="grid" style="width:18px;height:18px;"></i>
            </button>
            <button id="bgLines" class="wb-tool-btn" onclick="setWBBackground('lines')" title="Dil (Xətli)"
                style="width: 34px; height: 34px;">
                <i data-lucide="align-justify" style="width:18px;height:18px;"></i>
            </button>
        </div>

        <div class="wb-divider"></div>

        <!-- ADDIM VƏ ƏMƏLİYYATLAR -->
        <div class="wb-group">
            <button class="wb-tool-btn" onclick="undo()" title="Geri Al (Undo)">
                <i data-lucide="undo-2" style="width:20px;height:20px;"></i>
            </button>
            <button class="wb-tool-btn" onclick="redo()" title="Yenidən (Redo)">
                <i data-lucide="redo-2" style="width:20px;height:20px;"></i>
            </button>
            <button class="wb-tool-btn" onclick="clearWhiteboard()" title="Təmizlə"
                style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                <i data-lucide="trash-2" style="width:20px;height:20px;"></i>
            </button>
            <button class="wb-tool-btn" onclick="exportWhiteboard()" title="Yadda Saxla lövhəni"
                style="background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3);">
                <i data-lucide="download" style="width:20px;height:20px;"></i>
            </button>

            <div style="width: 1px; height: 20px; background: rgba(255, 255, 255, 0.1); margin: 0 4px;"></div>

            <button class="wb-tool-btn" onclick="toggleWBToolbar()" title="Paneli Gizlət"
                style="background: rgba(255, 255, 255, 0.1); color: white; width: auto !important; padding: 0 14px; border-radius: 12px; font-weight: bold; font-size: 11px;">
                <i data-lucide="chevron-down" style="width:16px;height:16px; margin-right: 4px;"></i> GİZLƏT
            </button>

        </div>
    </div>

    <!-- Re-open toolbar tab (shown when toolbar is collapsed) -->
    <div id="wbToolbarOpenTab" onclick="toggleWBToolbar()" title="Paneli Aç">
        <i data-lucide="grid-3x3" style="width:20px;height:20px;color:white;"></i>
        <span style="font-size: 11px; font-weight: 800; color: white; letter-spacing: 0.5px;">ALƏTLƏRİ AÇ</span>
    </div>

    <!-- Top Bar Overlay (Info & Exit) -->
    <div style="position: absolute; top: 15px; left: 15px; right: 15px; display: flex; justify-content: space-between; align-items: flex-start; z-index: 2010; pointer-events: none;">
        
        <!-- Info Badge (Top Left) -->
        <div style="background: rgba(15, 23, 42, 0.65); color: white; border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 100px; font-weight: 600; font-size: 11px; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); pointer-events: auto;">
            <div style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; box-shadow: 0 0 8px rgba(239, 68, 68, 0.8);"></div>
            STUDİO WHITEBOARD PRO
        </div>

        <!-- Exit Button (Top Right) -->
        <button onclick="toggleWhiteboard()" title="Lövhəni Bağla & Kameraya Qayıt"
            style="background: rgba(15, 23, 42, 0.65); color: white; border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 100px; font-weight: 600; font-size: 12px; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(10px); cursor: pointer; pointer-events: auto; transition: all 0.2s ease;" 
            onmouseover="this.style.background='#ef4444'; this.style.borderColor='#ef4444'; this.style.transform='scale(1.05)'" 
            onmouseout="this.style.background='rgba(15, 23, 42, 0.65)'; this.style.borderColor='rgba(255, 255, 255, 0.1)'; this.style.transform='scale(1)'">
            <i data-lucide="x" style="width:16px;height:16px;"></i> BAĞLA
        </button>

    </div>

    <div id="laserCursor"></div>

    <!-- Image Placement Overlay -->
    <div id="imagePlacementOverlay"
        style="display: none; position: absolute; inset: 0; z-index: 3000; background: rgba(0,0,0,0.3);">
        <div id="imagePlacementContainer"
            style="position: absolute; cursor: move; border: 2px dashed #3b82f6; box-shadow: 0 10px 30px rgba(0,0,0,0.3); touch-action: none;">
            <img id="placementImage" style="width: 100%; height: 100%; object-fit: contain; pointer-events: none;">
            <!-- Resize handle -->
            <div id="resizeHandle"
                style="position: absolute; bottom: -12px; right: -12px; width: 28px; height: 28px; background: #3b82f6; border: 2px solid white; border-radius: 50%; cursor: se-resize; display: flex; align-items: center; justify-content: center; font-size: 11px; color: white; user-select: none; touch-action: none;">
                ↘</div>
        </div>
        <div
            style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px;">
            <button onclick="confirmImagePlacement()"
                style="background: #22c55e; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);">✓
                Yerləşdir</button>
            <button onclick="cancelImagePlacement()"
                style="background: #ef4444; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);">✕
                Ləğv Et</button>
        </div>
        <div
            style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;">
            🖼️ Şəkli sürükləyin. Küncündən tutub ölçüsünü dəyişin.
        </div>
    </div>

    <div style="flex: 1; position: relative; background: #ffffff; cursor: crosshair; overflow: hidden;">
        <canvas id="wbCanvasInternal" style="display: block; touch-action: none;"></canvas>
    </div>
</div>

<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
<script>
    // === Mobile Panel Toggle ===
    function toggleMobilePanel(side) {
        const panel = side === 'left' ? document.getElementById('sidebarLeft') : document.getElementById('sidebarRight');
        const btn = side === 'left' ? document.getElementById('mobileStudentsBtn') : document.getElementById('mobileChatBtn');
        const otherPanel = side === 'left' ? document.getElementById('sidebarRight') : document.getElementById('sidebarLeft');
        const otherBtn = side === 'left' ? document.getElementById('mobileChatBtn') : document.getElementById('mobileStudentsBtn');
        const backdrop = document.getElementById('mobilePanelBackdrop');

        // Close the other panel
        if (otherPanel) otherPanel.classList.remove('mobile-open');
        if (otherBtn) otherBtn.classList.remove('active');

        // Toggle current panel
        if (panel) {
            panel.classList.toggle('mobile-open');
            const isOpen = panel.classList.contains('mobile-open');
            if (btn) btn.classList.toggle('active', isOpen);
            if (backdrop) backdrop.classList.toggle('visible', isOpen);
        }
    }

    function closeMobilePanels() {
        ['sidebarLeft', 'sidebarRight'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.remove('mobile-open');
        });
        ['mobileStudentsBtn', 'mobileChatBtn'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        });
        const backdrop = document.getElementById('mobilePanelBackdrop');
        if (backdrop) backdrop.classList.remove('visible');
    }
    const LOG = (msg, color = "#a5f3fc") => {
        const d = document.getElementById('logBox');
        if (!d) {
            console.log("LOG:", msg);
            return;
        }
        d.innerHTML += `<div style="color: ${color}; margin-bottom: 5px;">[${new Date().toLocaleTimeString()}] ${msg}</div>`;
        d.scrollTop = d.scrollHeight;
    };

    window.onerror = function (msg, url, line) {
        LOG(`🚨 JS ERROR: ${msg} (Line: ${line})`, "#ef4444");
        console.error(msg, url, line);
        return false;
    };

    var stream, peer;
    var camStream = null;
    var screenStream = null;
    var allDataConns = [];
    const activePeerCalls = new Map();
    const lID = "<?php echo $lessonId ?? '0'; ?>";
    const courseId = "<?php echo $lesson['course_id'] ?? '0'; ?>";
    var isScreenSharing = false;
    var isWhiteboardActive = false;
    var wbCanvas = null;
    var wbCtx = null;
    var isDrawing = false;
    var startX = 0;
    var startY = 0;
    var lastX = 0;
    var lastY = 0;
    var wbColor = '#000000';
    var wbTool = 'pencil';
    var wbSnapshot = null;
    var wbBgType = 'plain';
    var eraserSize = 30; // Default eraser size
    var pencilSize = 3; // Default pencil size

    // Laser pointer position (for streaming to students)
    var laserX = 0;
    var laserY = 0;
    var laserActive = false;

    // Student Spotlight / Screen Share State
    var isStudentSpotlight = false;
    var spotlightPeerId = null;
    var spotlightName = "";
    var studentScreenVidElement = null;
    var studentCamVidElement = null;

    // Multi-page system
    let wbPages = []; // Array of page data (ImageData)
    let currentPageIndex = 0;

    function saveCurrentPage() {
        if (wbCanvas && wbCtx) {
            wbPages[currentPageIndex] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        }
    }

    function loadPage(index) {
        if (wbPages[index]) {
            wbCtx.putImageData(wbPages[index], 0, 0);
        } else {
            // New blank page
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
        }
        updatePageIndicator();
    }

    function addNewPage() {
        saveCurrentPage(); // Save current page first
        currentPageIndex = wbPages.length; // Go to new page
        wbPages.push(null); // Placeholder for new page
        wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); // Clear canvas
        updatePageIndicator();
        LOG("📄 Yeni səhifə əlavə edildi: " + (currentPageIndex + 1), "#3b82f6");
    }

    function prevPage() {
        if (currentPageIndex > 0) {
            saveCurrentPage();
            currentPageIndex--;
            loadPage(currentPageIndex);
            LOG("◀️ Səhifə: " + (currentPageIndex + 1) + "/" + wbPages.length, "#64748b");
        }
    }

    function nextPage() {
        if (currentPageIndex < wbPages.length - 1) {
            saveCurrentPage();
            currentPageIndex++;
            loadPage(currentPageIndex);
            LOG("▶️ Səhifə: " + (currentPageIndex + 1) + "/" + wbPages.length, "#64748b");
        }
    }

    function deletePage() {
        if (wbPages.length <= 1) {
            LOG("⚠️ Son səhifəni silə bilməzsiniz!", "#f59e0b");
            return;
        }
        if (confirm("Bu səhifəni silmək istəyirsiniz?")) {
            wbPages.splice(currentPageIndex, 1);
            if (currentPageIndex >= wbPages.length) {
                currentPageIndex = wbPages.length - 1;
            }
            loadPage(currentPageIndex);
            LOG("🗑️ Səhifə silindi. Qalan: " + wbPages.length, "#ef4444");
        }
    }

    function updatePageIndicator() {
        const indicator = document.getElementById('pageIndicator');
        if (indicator) {
            indicator.innerText = (currentPageIndex + 1) + '/' + wbPages.length;
        }
    }

    // Undo/Redo Stacks
    let undoStack = [];
    let redoStack = [];
    const MAX_HISTORY = 30;

    function saveState() {
        if (!wbCanvas || wbCanvas.width === 0 || wbCanvas.height === 0) return;
        if (undoStack.length >= MAX_HISTORY) undoStack.shift();
        undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
        redoStack = []; // Clear redo on new action
    }

    function undo() {
        if (undoStack.length > 0) {
            redoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
            const state = undoStack.pop();
            wbCtx.putImageData(state, 0, 0);
            LOG("⏪ Geri qaytarıldı.");
        }
    }

    function redo() {
        if (redoStack.length > 0) {
            undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
            const state = redoStack.pop();
            wbCtx.putImageData(state, 0, 0);
            LOG("⏩ İrəli qaytarıldı.");
        }
    }

    // Whiteboard Functions
    function toggleWBToolbar() {
        const toolbar = document.querySelector('.wb-controls-floating');
        const tab = document.getElementById('wbToolbarOpenTab');
        const isCollapsed = toolbar.classList.toggle('wb-collapsed');
        tab.classList.toggle('visible', isCollapsed);
    }

    function toggleWhiteboard() {
        isWhiteboardActive = !isWhiteboardActive;
        const overlay = document.getElementById('whiteboardOverlay');
        const btn = document.getElementById('btnWhiteboard');

        if (isWhiteboardActive) {
            overlay.style.display = 'flex';
            setTimeout(() => overlay.classList.add('is-visible'), 10);
            btn.classList.add('active-blue');
            initWBCanvas();
            LOG("🎨 Advanced Whiteboard aktivdir.", "#3b82f6");
        } else {
            // Close instantly on student side without waiting video stream teardown.
            broadcastData({ type: 'whiteboard_force_stop' });
            overlay.classList.remove('is-visible');
            btn.classList.remove('active-blue');
            document.getElementById('laserCursor').style.display = 'none';
            // Reset toolbar to visible state for next open
            document.querySelector('.wb-controls-floating').classList.remove('wb-collapsed');
            document.getElementById('wbToolbarOpenTab').classList.remove('visible');
            setTimeout(() => {
                overlay.style.display = 'none';
                LOG("🎥 Normal görünüşə qayıtdı.");
            }, 300);
        }
    }

    function initWBCanvas() {
        if (wbCanvas) return;
        wbCanvas = document.getElementById('wbCanvasInternal');
        wbCtx = wbCanvas.getContext('2d');

        const resize = () => {
            const container = wbCanvas.parentElement;
            if (!container || container.clientWidth === 0 || container.clientHeight === 0) return;
            // Capture existing content if any
            let tempImg = null;
            if (wbCanvas.width > 0 && wbCanvas.height > 0) {
                tempImg = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
            }

            wbCanvas.width = container.clientWidth;
            wbCanvas.height = container.clientHeight;

            // Clear to transparent (CSS background shows through)
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);

            // Restore previous content
            if (tempImg) wbCtx.putImageData(tempImg, 0, 0);
            else saveState(); // First state

            // Apply CSS background based on current type
            setWBBackground(wbBgType);
        };

        window.addEventListener('resize', resize);
        resize();

        // Initialize first page
        if (wbPages.length === 0) {
            wbPages.push(null); // First page placeholder
            updatePageIndicator();
        }

        wbCanvas.onmousedown = (e) => {
            if (wbTool === 'laser') return;
            if (wbTool === 'text') {
                drawText(e.offsetX, e.offsetY);
                return;
            }

            saveState(); // Record state BEFORE action
            isDrawing = true;
            [startX, startY] = [e.offsetX, e.offsetY];
            [lastX, lastY] = [e.offsetX, e.offsetY];
            wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        };

        wbCanvas.onmousemove = (e) => {
            // Handle Laser Pointer
            const laser = document.getElementById('laserCursor');
            if (wbTool === 'laser') {
                laser.style.display = 'block';
                laser.style.left = (e.clientX - 6) + 'px';
                laser.style.top = (e.clientY - 6) + 'px';

                // Store laser position relative to canvas for streaming
                laserX = e.offsetX;
                laserY = e.offsetY;
                laserActive = true;
            } else {
                laser.style.display = 'none';
                laserActive = false;
            }

            if (!isDrawing) return;

            if (wbTool === 'pencil' || wbTool === 'eraser') {
                drawFreehand(e.offsetX, e.offsetY);
            } else if (wbTool !== 'text' && wbTool !== 'laser') {
                wbCtx.putImageData(wbSnapshot, 0, 0);
                drawShape(e.offsetX, e.offsetY);
            }
        };

        wbCanvas.onmouseup = () => {
            isDrawing = false;
            wbSnapshot = null;
        };
        wbCanvas.onmouseout = () => {
            isDrawing = false;
            laserCursor.style.display = 'none';
            laserActive = false; // Hide laser when mouse leaves canvas
        };

        // ── Touch support ─────────────────────────────────────────────
        // Helper: convert a Touch into canvas-relative {x, y} (same as offsetX/Y)
        function getTouchPos(touch) {
            const rect = wbCanvas.getBoundingClientRect();
            return {
                x: (touch.clientX - rect.left) * (wbCanvas.width / rect.width),
                y: (touch.clientY - rect.top) * (wbCanvas.height / rect.height)
            };
        }

        wbCanvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const pos = getTouchPos(e.touches[0]);
            if (wbTool === 'laser') return;
            if (wbTool === 'text') {
                drawText(pos.x, pos.y);
                return;
            }
            saveState();
            isDrawing = true;
            startX = pos.x;
            startY = pos.y;
            lastX = pos.x;
            lastY = pos.y;
            wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        }, {
            passive: false
        });

        wbCanvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (!isDrawing) return;
            const pos = getTouchPos(e.touches[0]);
            if (wbTool === 'pencil' || wbTool === 'eraser') {
                drawFreehand(pos.x, pos.y);
            } else if (wbTool !== 'text' && wbTool !== 'laser') {
                wbCtx.putImageData(wbSnapshot, 0, 0);
                drawShape(pos.x, pos.y);
            }
        }, {
            passive: false
        });

        wbCanvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            isDrawing = false;
            wbSnapshot = null;
        }, {
            passive: false
        });
    }

    function drawBackground() {
        if (!wbCanvas || !wbCtx) {
            console.warn("drawBackground: Canvas not ready yet");
            return;
        }

        console.log("drawBackground called with type:", wbBgType, "canvas size:", wbCanvas.width, "x", wbCanvas.height);

        // Clear everything first
        wbCtx.fillStyle = '#ffffff';
        wbCtx.fillRect(0, 0, wbCanvas.width, wbCanvas.height);

        if (wbBgType === 'plain') return;

        wbCtx.beginPath();
        wbCtx.strokeStyle = '#94a3b8'; // More visible gray color
        wbCtx.lineWidth = 1;

        if (wbBgType === 'grid') {
            const step = 30;
            for (let x = step; x < wbCanvas.width; x += step) {
                wbCtx.moveTo(x, 0);
                wbCtx.lineTo(x, wbCanvas.height);
            }
            for (let y = step; y < wbCanvas.height; y += step) {
                wbCtx.moveTo(0, y);
                wbCtx.lineTo(wbCanvas.width, y);
            }
            console.log("Grid drawn:", Math.floor(wbCanvas.width / step), "x", Math.floor(wbCanvas.height / step), "lines");
        } else if (wbBgType === 'lines') {
            const step = 25;
            for (let y = step; y < wbCanvas.height; y += step) {
                wbCtx.moveTo(0, y);
                wbCtx.lineTo(wbCanvas.width, y);
            }
            console.log("Lines drawn:", Math.floor(wbCanvas.height / step), "horizontal lines");
        }
        wbCtx.stroke();
    }

    function setWBBackground(type) {
        wbBgType = type;
        document.querySelectorAll('[id^="bg"]').forEach(b => b.classList.remove('active'));
        document.getElementById('bg' + type.charAt(0).toUpperCase() + type.slice(1)).classList.add('active');

        // Apply background via CSS - doesn't affect canvas content
        const container = wbCanvas ? wbCanvas.parentElement : document.getElementById('whiteboardOverlay');
        const canvasEl = wbCanvas || document.getElementById('wbCanvasInternal');

        if (type === 'grid') {
            // Create grid pattern
            canvasEl.style.backgroundImage = `
                linear-gradient(#94a3b8 1px, transparent 1px),
                linear-gradient(90deg, #94a3b8 1px, transparent 1px)
            `;
            canvasEl.style.backgroundSize = '30px 30px';
            canvasEl.style.backgroundColor = 'white';
        } else if (type === 'lines') {
            // Create horizontal lines pattern
            canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px)';
            canvasEl.style.backgroundSize = '100% 25px';
            canvasEl.style.backgroundColor = 'white';
        } else {
            // Plain white
            canvasEl.style.backgroundImage = 'none';
            canvasEl.style.backgroundColor = 'white';
        }

        const bgLabels = {
            'plain': 'Ağ Fon',
            'grid': 'Riyaziyyat (Dama)',
            'lines': 'Dil (Xətli)'
        };
        LOG("📋 Fon dəyişdirildi: " + bgLabels[type], "#3b82f6");
    }

    function drawText(x, y) {
        const text = prompt("Mətn daxil edin:");
        if (text) {
            saveState();
            wbCtx.font = "24px 'Inter', sans-serif";
            wbCtx.fillStyle = wbColor;
            wbCtx.fillText(text, x, y);
        }
    }

    // Image placement variables
    var placementImg = null;
    var isDraggingImage = false;
    var isResizingImage = false;
    var imgDragStartX = 0;
    var imgDragStartY = 0;
    var imgStartLeft = 0;
    var imgStartTop = 0;
    var imgStartWidth = 0;
    var imgStartHeight = 0;
    var imgAspectRatio = 1;

    function wbUploadImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            LOG("🖼️ Şəkil yüklənir: " + file.name, "#3b82f6");

            const reader = new FileReader();
            reader.onload = (e) => {
                placementImg = new Image();
                placementImg.onload = () => {
                    showImagePlacement(placementImg);
                };
                placementImg.onerror = () => {
                    LOG("❌ Şəkil yüklənə bilmədi!", "#ef4444");
                };
                placementImg.src = e.target.result;
            };
            reader.onerror = () => {
                LOG("❌ Fayl oxuna bilmədi!", "#ef4444");
            };
            reader.readAsDataURL(file);

            // Reset input so same file can be selected again
            input.value = '';
        }
    }

    function showImagePlacement(img) {
        const overlay = document.getElementById('imagePlacementOverlay');
        const container = document.getElementById('imagePlacementContainer');
        const imgEl = document.getElementById('placementImage');

        imgEl.src = img.src;
        imgAspectRatio = img.width / img.height;

        // Calculate initial size (50% of canvas)
        const maxW = wbCanvas.width * 0.5;
        const maxH = wbCanvas.height * 0.5;
        let w, h;

        if (img.width / img.height > maxW / maxH) {
            w = maxW;
            h = w / imgAspectRatio;
        } else {
            h = maxH;
            w = h * imgAspectRatio;
        }

        // Center the image
        const left = (wbCanvas.width - w) / 2;
        const top = (wbCanvas.height - h) / 2;

        container.style.left = left + 'px';
        container.style.top = top + 'px';
        container.style.width = w + 'px';
        container.style.height = h + 'px';

        overlay.style.display = 'block';

        // Setup drag events (mouse)
        container.onmousedown = startImageDrag;
        document.getElementById('resizeHandle').onmousedown = startImageResize;

        // Setup drag events (touch)
        container.addEventListener('touchstart', startImageDragTouch, {
            passive: false
        });
        document.getElementById('resizeHandle').addEventListener('touchstart', startImageResizeTouch, {
            passive: false
        });
    }

    function startImageDrag(e) {
        if (e.target.id === 'resizeHandle') return;
        e.preventDefault();
        isDraggingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = e.clientX;
        imgDragStartY = e.clientY;
        imgStartLeft = parseInt(container.style.left);
        imgStartTop = parseInt(container.style.top);

        document.onmousemove = dragImage;
        document.onmouseup = stopImageDrag;
    }

    function dragImage(e) {
        if (!isDraggingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        const dx = e.clientX - imgDragStartX;
        const dy = e.clientY - imgDragStartY;
        container.style.left = (imgStartLeft + dx) + 'px';
        container.style.top = (imgStartTop + dy) + 'px';
    }

    function stopImageDrag() {
        isDraggingImage = false;
        document.onmousemove = null;
        document.onmouseup = null;
    }

    function startImageResize(e) {
        e.preventDefault();
        e.stopPropagation();
        isResizingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = e.clientX;
        imgDragStartY = e.clientY;
        imgStartWidth = parseInt(container.style.width);
        imgStartHeight = parseInt(container.style.height);

        document.onmousemove = resizeImage;
        document.onmouseup = stopImageResize;
    }

    function resizeImage(e) {
        if (!isResizingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        const dx = e.clientX - imgDragStartX;

        // Maintain aspect ratio
        let newW = Math.max(50, imgStartWidth + dx);
        let newH = newW / imgAspectRatio;

        container.style.width = newW + 'px';
        container.style.height = newH + 'px';
    }

    function stopImageResize() {
        isResizingImage = false;
        document.onmousemove = null;
        document.onmouseup = null;
    }

    // ── Touch equivalents for image drag ────────────────────────────────────
    function startImageDragTouch(e) {
        if (e.target.id === 'resizeHandle') return;
        e.preventDefault();
        const t = e.touches[0];
        isDraggingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = t.clientX;
        imgDragStartY = t.clientY;
        imgStartLeft = parseInt(container.style.left);
        imgStartTop = parseInt(container.style.top);
        document.addEventListener('touchmove', dragImageTouch, {
            passive: false
        });
        document.addEventListener('touchend', stopImageDragTouch);
    }

    function dragImageTouch(e) {
        e.preventDefault();
        if (!isDraggingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        const t = e.touches[0];
        container.style.left = (imgStartLeft + (t.clientX - imgDragStartX)) + 'px';
        container.style.top = (imgStartTop + (t.clientY - imgDragStartY)) + 'px';
    }

    function stopImageDragTouch() {
        isDraggingImage = false;
        document.removeEventListener('touchmove', dragImageTouch);
        document.removeEventListener('touchend', stopImageDragTouch);
    }

    // ── Touch equivalents for image resize ──────────────────────────────────
    function startImageResizeTouch(e) {
        e.preventDefault();
        e.stopPropagation();
        const t = e.touches[0];
        isResizingImage = true;
        const container = document.getElementById('imagePlacementContainer');
        imgDragStartX = t.clientX;
        imgDragStartY = t.clientY;
        imgStartWidth = parseInt(container.style.width);
        imgStartHeight = parseInt(container.style.height);
        document.addEventListener('touchmove', resizeImageTouch, {
            passive: false
        });
        document.addEventListener('touchend', stopImageResizeTouch);
    }

    function resizeImageTouch(e) {
        e.preventDefault();
        if (!isResizingImage) return;
        const container = document.getElementById('imagePlacementContainer');
        const t = e.touches[0];
        const dx = t.clientX - imgDragStartX;
        const newW = Math.max(50, imgStartWidth + dx);
        container.style.width = newW + 'px';
        container.style.height = (newW / imgAspectRatio) + 'px';
    }

    function stopImageResizeTouch() {
        isResizingImage = false;
        document.removeEventListener('touchmove', resizeImageTouch);
        document.removeEventListener('touchend', stopImageResizeTouch);
    }

    function confirmImagePlacement() {
        const container = document.getElementById('imagePlacementContainer');
        const overlay = document.getElementById('imagePlacementOverlay');

        const x = parseInt(container.style.left);
        const y = parseInt(container.style.top);
        const w = parseInt(container.style.width);
        const h = parseInt(container.style.height);

        saveState();
        wbCtx.drawImage(placementImg, x, y, w, h);

        overlay.style.display = 'none';
        cleanupImagePlacementTouchListeners();
        placementImg = null;

        LOG("✅ Şəkil yerləşdirildi: " + w + "x" + h + "px", "#10b981");
    }

    function cancelImagePlacement() {
        document.getElementById('imagePlacementOverlay').style.display = 'none';
        cleanupImagePlacementTouchListeners();
        placementImg = null;
        LOG("❌ Şəkil yerləşdirmə ləğv edildi", "#f59e0b");
    }

    function cleanupImagePlacementTouchListeners() {
        const container = document.getElementById('imagePlacementContainer');
        const handle = document.getElementById('resizeHandle');
        if (container) container.removeEventListener('touchstart', startImageDragTouch);
        if (handle) handle.removeEventListener('touchstart', startImageResizeTouch);
    }

    function drawFreehand(currX, currY) {
        wbCtx.beginPath();
        wbCtx.moveTo(lastX, lastY);
        wbCtx.lineTo(currX, currY);

        if (wbTool === 'eraser') {
            // Use destination-out to make pixels transparent
            // This way the CSS background (grid/lines) shows through
            wbCtx.globalCompositeOperation = 'destination-out';
            wbCtx.strokeStyle = 'rgba(0,0,0,1)';
            wbCtx.lineWidth = eraserSize;
        } else {
            wbCtx.globalCompositeOperation = 'source-over';
            wbCtx.strokeStyle = wbColor;
            wbCtx.lineWidth = pencilSize;
        }

        wbCtx.lineCap = 'round';
        wbCtx.lineJoin = 'round';
        wbCtx.stroke();

        // Reset composite operation for other tools
        wbCtx.globalCompositeOperation = 'source-over';

        [lastX, lastY] = [currX, currY];
    }

    function drawShape(currX, currY) {
        wbCtx.beginPath();
        wbCtx.strokeStyle = wbColor;
        wbCtx.lineWidth = 3;
        wbCtx.lineCap = 'round';

        if (wbTool === 'line') {
            wbCtx.moveTo(startX, startY);
            wbCtx.lineTo(currX, currY);
        } else if (wbTool === 'rect') {
            wbCtx.strokeRect(startX, startY, currX - startX, currY - startY);
        } else if (wbTool === 'circle') {
            let radius = Math.sqrt(Math.pow(currX - startX, 2) + Math.pow(currY - startY, 2));
            wbCtx.arc(startX, startY, radius, 0, 2 * Math.PI);
        } else if (wbTool === 'arrow') {
            drawArrow(startX, startY, currX, currY);
        }
        wbCtx.stroke();
    }

    function drawArrow(fromx, fromy, tox, toy) {
        const headlen = 15;
        const angle = Math.atan2(toy - fromy, tox - fromx);
        wbCtx.moveTo(fromx, fromy);
        wbCtx.lineTo(tox, toy);
        wbCtx.lineTo(tox - headlen * Math.cos(angle - Math.PI / 6), toy - headlen * Math.sin(angle - Math.PI / 6));
        wbCtx.moveTo(tox, toy);
        wbCtx.lineTo(tox - headlen * Math.cos(angle + Math.PI / 6), toy - headlen * Math.sin(angle + Math.PI / 6));
    }

    function setWBTool(tool) {
        wbTool = tool;
        document.querySelectorAll('.wb-tool-btn').forEach(btn => btn.classList.remove('active'));
        const btnId = 'tool' + tool.charAt(0).toUpperCase() + tool.slice(1);
        const btn = document.getElementById(btnId);
        if (btn) btn.classList.add('active');

        // Show/hide size control based on tool
        const sizeControl = document.getElementById('sizeControl');
        if (sizeControl) {
            if (tool === 'eraser' || tool === 'pencil') {
                sizeControl.style.display = 'flex';
                updateSizeDisplay();
            } else {
                sizeControl.style.display = 'none';
            }
        }
    }

    function changeSize(delta) {
        if (wbTool === 'eraser') {
            eraserSize = Math.max(10, Math.min(100, eraserSize + delta));
        } else {
            pencilSize = Math.max(1, Math.min(20, pencilSize + delta));
        }
        updateSizeDisplay();
    }

    function updateSizeDisplay() {
        const display = document.getElementById('sizeDisplay');
        if (display) {
            const size = wbTool === 'eraser' ? eraserSize : pencilSize;
            display.innerText = size + 'px';
        }
    }

    function setWBColor(color, el) {
        wbColor = color;
        document.querySelectorAll('.wb_color_item').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
    }

    function openColorPicker() {
        document.getElementById('customColorPicker').click();
    }

    function setCustomColor(color) {
        wbColor = color;
        document.querySelectorAll('.wb_color_item').forEach(c => c.classList.remove('active'));
        // Highlight the custom color button
        const customBtn = document.querySelector('.wb_color_btn');
        if (customBtn) {
            customBtn.style.background = color;
            customBtn.classList.add('active');
        }
        LOG("🎨 Xüsusi rəng seçildi: " + color, color);
    }

    function clearWhiteboard() {
        if (confirm("Lövhə təmizlənsin?")) {
            saveState();
            // Clear canvas to transparent - CSS background will show through
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
            LOG("🧹 Lövhə təmizləndi.", "#f59e0b");
        }
    }

    function exportWhiteboard() {
        const link = document.createElement('a');
        link.download = `Whiteboard_Arxiv_${lID}_${Date.now()}.png`;
        link.href = wbCanvas.toDataURL("image/png");
        link.click();
        LOG("📸 Lövhə şəkli yadda saxlanıldı (Arxiv).", "#10b981");
    }

    // Compositing (Canvas)
    let mediaRecorder;
    let recordedChunks = [];
    let recordingStartTime = 0;
    let recordingDurationMs = 0;
    let canvas, ctx;
    let canvasLoopId;
    let destStream;
    let chunkFlushInterval = null;
    let isFlushingChunks = false;
    let isFirstChunkSent = false;
    let isFirstChunkRecorded = true; // Tracks if this is the very first chunk of a recorder session
    let recordingSessionId = sessionStorage.getItem('active_recording_session_' + lID);
    if (!recordingSessionId) {
        recordingSessionId = "sess_" + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('active_recording_session_' + lID, recordingSessionId);
    }

    // ============================================================
    // Periodic Chunk Flush — hər 30 saniyədən bir parçaları serverə göndər
    // Refresh zamanı recording itirilməsin deyə
    // ============================================================
    function flushChunksToServer() {
        if (isFlushingChunks || recordedChunks.length === 0) return Promise.resolve();

        // Kiçik parçaları yığmaq üçün limit (məsələn 10KB-dan azdırsa gözləsin)
        const currentSize = recordedChunks.reduce((acc, c) => acc + c.size, 0);
        if (currentSize < 10240 && !mediaRecorder.state === 'inactive') return Promise.resolve();

        isFlushingChunks = true;
        const chunksToSend = recordedChunks.slice();
        recordedChunks = [];

        const blob = new Blob(chunksToSend, {
            type: 'video/webm'
        });
        const fd = new FormData();
        fd.append('lesson_id', lID);
        // Track if this is the first chunk of a recorder session (to help server strip redundant headers)
        fd.append('is_first_chunk', isFirstChunkRecorded ? '1' : '0');
        if (isFirstChunkRecorded) {
            isFirstChunkRecorded = false; 
            console.log("🎬 Session Header Chunk sent");
        }
        fd.append('video_blob', blob);
        fd.append('session_id', recordingSessionId);

        // Dinamik URL təyini
        const chunkUrl = window.location.pathname.includes('/teacher/') ? '../api/live/upload_chunk.php' : '/api/live/upload_chunk.php';

        return fetch(chunkUrl, {
            method: 'POST',
            body: fd,
            credentials: 'include'
        })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error("Server cavabı JSON deyil: " + text.substring(0, 100));
                }
            })
            .then(data => {
                if (data.success) {
                    LOG(`💾 Video parçası serverə yazıldı (${(blob.size / 1024).toFixed(0)} KB)`, "#10b981");
                } else {
                    recordedChunks = chunksToSend.concat(recordedChunks);
                    LOG(`⚠️ Parça yazılmadı: ${data.message}`, "#f59e0b");
                    if (data.message.toLowerCase().includes('authorized') || data.message.toLowerCase().includes('login')) {
                        console.warn("Session lost during chunk upload. User may need to re-login.");
                    }
                    console.error('Server error upload_chunk:', data);
                }
                isFlushingChunks = false;
            })
            .catch(err => {
                recordedChunks = chunksToSend.concat(recordedChunks);
                isFlushingChunks = false;
                LOG(`❌ Bağlantı xətası (Chunk): ${err.message}`, "#ef4444");
                console.error('Chunk flush error:', err);
            });
    }

    function startProductionNow() {
        const overlay = document.getElementById('startProductionOverlay');

        // 1. Request Fullscreen
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(e => {
                LOG("⚠️ Tam ekran rejimi aktivləşdirilə bilmədi.", "#f59e0b");
            });
        }

        // 2. Hide Overlay
        overlay.style.transition = 'opacity 0.5s';
        overlay.style.opacity = '0';
        setTimeout(() => overlay.style.display = 'none', 500);

        // 3. Initialize Media if not already done or restart
        init();

        LOG("🎬 Yayım və tam ekran rejimi başladıldı.", "#10b981");

        // 4. Monitoring Fullscreen Exit
        document.onfullscreenchange = () => {
            if (!document.fullscreenElement) {
                LOG("⚠️ Diqqət: Tam ekran rejimi dayandırıldı!", "#ef4444");
            }
        };
    }

    function flushChunksBeacon() {
        if (!recordedChunks || recordedChunks.length === 0) return;
        const blob = new Blob(recordedChunks, {
            type: 'video/webm'
        });
        const fd = new FormData();
        fd.append('lesson_id', lID);
        fd.append('video_blob', blob);
        fd.append('session_id', recordingSessionId);
        fd.append('is_first_chunk', isFirstChunkRecorded ? '1' : '0');
        
        const chunkUrl = window.location.pathname.includes('/teacher/') ? '../api/live/upload_chunk.php' : '/api/live/upload_chunk.php';
        navigator.sendBeacon(chunkUrl, fd);
        recordedChunks = [];
    }

    // CHAT & FILE
    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        // 5MB Limit Check
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            LOG("⚠️ Fayl çox böyükdür! Maksimum limit 5MB-dır.", "#ef4444");
            alert("Xəta: Faylın ölçüsü 5MB-dan çox ola bilməz.");
            input.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        const progressId = "up-" + Date.now();
        LOG(`<div id="${progressId}" style="display:inline-block;">📤 Fayl yüklənir: 0%</div>`, "#3b82f6");

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                const el = document.getElementById(progressId);
                if (el) el.innerHTML = `📤 Fayl yüklənir: ${percent}%`;
            }
        };

        xhr.onload = () => {
            const el = document.getElementById(progressId);
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    if (el) el.innerHTML = `✅ Yükləndi: ${file.name}`;
                    const msgObj = {
                        type: 'file',
                        fileData: data.url,
                        fileName: data.fileName,
                        sender: 'Müəllim'
                    };
                    broadcastData(msgObj);
                    appendFileMessage('Mən', data.fileName, data.url, '#3b82f6');
                } else {
                    if (el) el.innerHTML = `❌ Xəta: ${data.message}`;
                    LOG("Yükləmə xətası: " + data.message, "#ef4444");
                }
            } catch (e) {
                if (el) el.innerHTML = `❌ Server xətası`;
            }
        };

        xhr.onerror = () => {
            const el = document.getElementById(progressId);
            if (el) el.innerHTML = `❌ Bağlantı xətası`;
        };

        xhr.open('POST', '../api/upload_chat_file.php');
        xhr.send(formData);
        input.value = '';
    }

    let privateTarget = null; // { peer: '...', name: '...' }

    function setPrivateTarget(peerId, name) {
        privateTarget = {
            peer: peerId,
            name: name
        };
        document.getElementById('targetName').innerText = name;
        document.getElementById('privateTargetIndicator').style.display = 'flex';
        document.getElementById('chatInput').placeholder = `${name} üçün özəl mesaj...`;
        document.getElementById('chatInput').focus();
    }

    function clearPrivateTarget() {
        privateTarget = null;
        document.getElementById('privateTargetIndicator').style.display = 'none';
        document.getElementById('chatInput').placeholder = "Mesaj yazın...";
    }

    function sendChatMessage() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg) return;

        if (privateTarget) {
            const conn = allDataConns.find(c => c.peer === privateTarget.peer);
            if (conn && conn.open) {
                const msgObj = {
                    type: 'chat',
                    message: msg,
                    sender: 'Müəllim (Özəl)',
                    isPrivate: true
                };
                conn.send(msgObj);
                appendChatMessage(`Mən -> ${privateTarget.name}`, msg, '#3b82f6');
                LOG(`🔒 ${privateTarget.name} üçün özəl mesaj göndərildi.`, "#3b82f6");
            } else {
                LOG("⚠️ Seçilmiş tələbə artıq canlı dərsi tərk edib.", "#ef4444");
                clearPrivateTarget();
            }
        } else {
            const msgObj = {
                type: 'chat',
                message: msg,
                sender: 'Müəllim'
            };
            broadcastData(msgObj);
            appendChatMessage('Mən', msg, '#3b82f6');
        }
        input.value = '';
    }

    function broadcastData(data, excludePeerId = null) {
        const alive = [];
        allDataConns.forEach(conn => {
            if (!conn || !conn.open) return;
            alive.push(conn);
            if (conn.peer === excludePeerId) return;
            try {
                conn.send(data);
            } catch (e) {
                // connection may close between open-check and send
            }
        });
        allDataConns = alive;
    }

    function showMicRequestModal(senderName, conn) {
        const modal = document.getElementById('micRequestModal');
        document.getElementById('micRequestTitle').textContent = `${senderName} söz istəyir`;
        modal.style.display = 'flex';

        const aprove = document.getElementById('micApproveBtn');
        const reject = document.getElementById('micRejectBtn');

        const cleanup = () => {
            modal.style.display = 'none';
            aprove.onclick = null;
            reject.onclick = null;
        };

        aprove.onclick = () => {
            conn.send({
                type: 'mic_approved'
            });
            LOG(`${senderName} mikrofonu açıldı.`, "#22c55e");
            cleanup();
        };
        reject.onclick = () => {
            conn.send({
                type: 'mic_rejected'
            });
            LOG(`${senderName} rədd edildi.`, "#fde047");
            cleanup();
        };
    }

    function appendFileMessage(sender, fileName, fileData, color = "#fff") {
        const box = document.getElementById('chatMessages');
        if (box.innerText.includes('Hələ ki')) box.innerHTML = '';
        box.innerHTML += `<div style="margin-bottom: 12px; line-height: 1.4;"><strong style="color: ${color}; font-size: 11px; display: block; margin-bottom: 2px;">${sender}</strong><a href="${fileData}" target="_blank" style="color: #60a5fa; text-decoration: none; padding: 5px 10px; background: rgba(255,255,255,0.05); border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">📎 ${fileName}</a></div>`;
        box.scrollTop = box.scrollHeight;
    }

    function appendChatMessage(sender, msg, color = "#fff") {
        const box = document.getElementById('chatMessages');
        if (box.innerText.includes('Hələ ki')) box.innerHTML = '';
        box.innerHTML += `<div style="margin-bottom: 12px; line-height: 1.4;"><strong style="color: ${color}; font-size: 11px; display: block; margin-bottom: 2px;">${sender}</strong><span style="color: #e2e8f0;">${msg}</span></div>`;
        box.scrollTop = box.scrollHeight;
    }

    // ─── Dynamic ICE/TURN Configuration ───
    // Default fallback (STUN only — works on LAN, fails on mobile)
    let iceServers = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ],
        sdpSemantics: 'unified-plan',
        iceCandidatePoolSize: 10
    };

    let turnCredentialsFetched = false;

    async function fetchTurnCredentials() {
        try {
            LOG("🔑 TURN server məlumatları yüklənir...", "#3b82f6");
            const resp = await fetch('../api/get_turn_credentials.php?t=' + Date.now(), { credentials: 'include' });
            const data = await resp.json();

            if (data.success && data.iceServers && data.iceServers.length > 0) {
                iceServers = {
                    iceServers: data.iceServers,
                    sdpSemantics: 'unified-plan',
                    iceCandidatePoolSize: 10
                };
                turnCredentialsFetched = true;

                const hasTurn = data.iceServers.some(s =>
                    (typeof s.urls === 'string' && s.urls.startsWith('turn:')) ||
                    (typeof s.urls === 'string' && s.urls.startsWith('turns:'))
                );

                if (hasTurn) {
                    LOG("✅ TURN server hazırdır (mobil bağlantı dəstəklənir)", "#10b981");
                } else {
                    LOG("⚠️ TURN server tapılmadı! Yalnız lokal şəbəkə işləyəcək.", "#f59e0b");
                    LOG("ℹ️ METERED_API_KEY və ya METERED_DOMAIN yoxlayın.", "#94a3b8");
                    console.error("No TURN servers returned from API. Config:", data);
                }

                if (data.source === 'fallback_stun_only') {
                    if (data.warning) LOG("⚠️ Xəta: " + data.warning, "#ef4444");
                    console.warn('TURN fallback active:', data);
                }
            } else {
                LOG("❌ API xətası (TURN yüklənmədi)", "#ef4444");
                console.error("API response was not successful:", data);
            }
        } catch (err) {
            LOG("❌ Bağlantı xətası: TURN məlumatları alınmadı.", "#ef4444");
            console.error("TURN credential fetch exception:", err);
        }
    }

    const peerConfig = {
        debug: 1,
        get config() { return iceServers; },
        host: window.location.hostname,
        port: 9000,
        secure: false,
        path: '/myapp'
    };

    async function init() {
        try {
            LOG("Sistem yoxlanılır...", "#3b82f6");

            // 0. Fetch TURN credentials first (critical for mobile/LTE)
            await fetchTurnCredentials();

            // 1. Secure Context Warning
            if (!window.isSecureContext && window.location.hostname !== 'localhost') {
                LOG("⚠️ HTTPS TƏLƏB OLUNUR: Kamera/Mikrofon bloklana bilər!", "#ef4444");
                alert("DİQQƏT: Təhlükəsiz bağlantı (HTTPS) yoxdur. Kamera və mikrofon brauzer tərəfindən bloklana bilər.");
            }

            // 2. Try Media Access
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                LOG("Media cihazları yoxlanılır...", "#3b82f6");
                const tryGetMedia = async (constraints) => {
                    try {
                        return await navigator.mediaDevices.getUserMedia(constraints);
                    } catch (e) {
                        return null;
                    }
                };

                // HD -> SD -> Any
                camStream = await tryGetMedia({
                    video: {
                        width: {
                            ideal: 1280
                        },
                        height: {
                            ideal: 720
                        }
                    },
                    audio: {
                        echoCancellation: true
                    }
                });
                if (!camStream) camStream = await tryGetMedia({
                    video: true,
                    audio: {
                        echoCancellation: true
                    }
                });

                if (camStream) {
                    const camVid = document.getElementById('camSource');
                    camVid.srcObject = camStream;
                    camVid.play().catch(e => LOG("⚠️ Kamera avtomatik başlamadı. Ekrana klikləyin.", "#f59e0b"));
                    LOG("✅ Kamera aktivdir.", "#10b981");
                }
            }

            // 3. Fallback to dummy if failed
            if (!camStream) {
                LOG("⚠️ Kamera tapılmadı. Görüntüsüz davam edilir.", "#f59e0b");
                const dummyCanvas = document.createElement('canvas');
                dummyCanvas.width = 640;
                dummyCanvas.height = 480;
                const dummyCtx = dummyCanvas.getContext('2d');
                dummyCtx.fillStyle = "black";
                dummyCtx.fillRect(0, 0, 640, 480);
                camStream = dummyCanvas.captureStream();
                try {
                    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = audioCtx.createOscillator();
                    const dst = osc.connect(audioCtx.createMediaStreamDestination());
                    osc.start();
                    camStream.addTrack(dst.stream.getAudioTracks()[0]);
                } catch (e) { }
                document.getElementById('camSource').srcObject = camStream;
            }

            function showWhiteboardRequestModal(name, conn) {
                document.getElementById('wbRequesterName').innerText = name;
                const modal = document.getElementById('wbRequestModal');
                modal.style.display = 'flex';

                document.getElementById('wbApproveBtn').onclick = () => {
                    conn.send({
                        type: 'whiteboard_approved'
                    });
                    modal.style.display = 'none';
                };
                document.getElementById('wbRejectBtn').onclick = () => {
                    conn.send({
                        type: 'whiteboard_rejected'
                    });
                    modal.style.display = 'none';
                };
            }

            // 4. Start Production
            startCanvasCompositing();
            stream = destStream;
            document.getElementById('localVid').srcObject = stream;

            // 5. Connection Setup
            let sessionUnique = Math.floor(Math.random() * 100000);
            let uniqueID = 'ndu-live-' + lID + '-' + sessionUnique;

            function startPeer(useCloud = false) {
                const config = useCloud ? {
                    debug: 1,
                    host: '0.peerjs.com',
                    port: 443,
                    secure: true,
                    config: iceServers
                } : peerConfig;

                if (peer) peer.destroy();
                peer = new Peer(uniqueID, config);

                peer.on('open', (id) => {
                    LOG(useCloud ? "🚀 Bulud serverinə qoşuldu!" : "🚀 Lokal server hazır!", "#10b981");
                    const serverType = useCloud ? 'cloud' : 'local';
                    fetch(`api/update_peer_id.php?live_class_id=${lID}&peer_id=${id}&server=${serverType}&t=${Date.now()}`, { credentials: 'include' })
                        .then(r => r.json()).catch(e => console.error("DB Update Error"));
                    trackAttendance('join');
                });

                peer.on('error', (err) => {
                    LOG("⚠️ Peer xətası: " + err.type, "#f59e0b");
                    if (err.type === 'id-taken') {
                        sessionUnique = Math.floor(Math.random() * 1000000);
                        uniqueID = 'ndu-live-' + lID + '-' + sessionUnique;
                        setTimeout(() => startPeer(useCloud), 1000);
                    } else if (!useCloud && (['socket-error', 'network', 'server-error'].includes(err.type))) {
                        LOG("⚠️ Buluda keçid edilir...", "#f59e0b");
                        setTimeout(() => startPeer(true), 1500);
                    }
                });

                peer.on('connection', (conn) => {
                    if (conn.metadata && conn.metadata.type === 'rejoin_request') {
                        showRejoinRequestModal(conn.metadata.name, conn, conn.metadata.userId);
                        return;
                    }
                    allDataConns.push(conn);
                    const removeConn = () => {
                        const idx = allDataConns.findIndex(c => c.peer === conn.peer);
                        if (idx > -1) allDataConns.splice(idx, 1);
                    };
                    conn.on('close', removeConn);
                    conn.on('error', removeConn);
                    conn.on('data', (d) => {
                        if (d.type === 'chat' || d.type === 'file') {
                            const isPrivate = d.isPrivate || false;
                            const color = isPrivate ? '#3b82f6' : '#10b981';
                            if (d.type === 'chat') {
                                appendChatMessage(d.sender, d.message, color);
                                if (!isPrivate) broadcastData(d, conn.peer);
                            } else {
                                appendFileMessage(d.sender, d.fileName, d.fileData, color);
                                broadcastData(d, conn.peer);
                            }
                        } else if (d.type === 'mic_request') {
                            showMicRequestModal(d.sender, conn);
                        } else if (d.type === 'whiteboard_request') {
                            showWhiteboardRequestModal(d.sender, conn);
                        } else if (d.type === 'whiteboard_ended') {
                            if (conn.peer === spotlightPeerId) stopStudentSpotlight();
                        } else if (d.type === 'screen_share_request') {
                            showScreenShareRequestModal(d.sender, conn);
                        } else if (d.type === 'screen_share_ended') {
                            if (conn.peer === spotlightPeerId) stopStudentSpotlight();
                        }
                    });
                });

                peer.on('call', (call) => {
                    const meta = call.metadata || {};
                    const name = meta.name || "Tələbə";
                    const sid = meta.userId || meta.id || meta.student_id;
                    const isScreenShare = meta.type === 'screen_share';
                    let incomingHandled = false;

                    // Force stop spotlight if student calling again with normal stream
                    if (!isScreenShare && call.peer === spotlightPeerId) {
                        stopStudentSpotlight();
                    }

                    if (stream) {
                        const vtr = stream.getVideoTracks().length;
                        const atr = stream.getAudioTracks().length;
                        LOG(`📡 Zəng cavablandırılır: ${vtr} video, ${atr} audio track göndərilir.`);
                    } else {
                        LOG("⚠️ DİQQƏT: Zəngə cavab verilərkən stream tapılmadı!", "#ef4444");
                    }
                    call.answer(stream);

                    // --- PERFORMANCE: Apply Bitrate Limit ---
                    setTimeout(() => {
                        if (call.peerConnection) {
                            applyBitrateLimit(call.peerConnection, currentBitrateLimit);
                        }
                    }, 1000);
                    trackActiveCall(call);

                    const handleIncomingStream = (rem) => {
                        if (!rem || incomingHandled) return;
                        incomingHandled = true;
                        if (isScreenShare) {
                            LOG(`🖥️ ${name} ekran paylaşımına başladı.`, "#3b82f6");
                            startStudentSpotlight(call.peer, rem, name);
                        } else {
                            addStudentVideo(call.peer, rem, name, sid);
                        }
                    };


                    if (sid) {
                        const existing = document.querySelector(`.student-card[data-student-id='${sid}']`);
                        if (existing && !isScreenShare) {
                            const oldId = existing.getAttribute('data-peer-id');
                            if (oldId && oldId !== call.peer) {
                                const idx = allDataConns.findIndex(c => c.peer === oldId);
                                if (idx > -1) {
                                    allDataConns[idx].close();
                                    allDataConns.splice(idx, 1);
                                }
                                removeStudentVideo(oldId);
                            }
                        }
                    }

                    call.on('stream', handleIncomingStream);
                    if (call.peerConnection) {
                        const trackStream = new MediaStream();
                        call.peerConnection.ontrack = (ev) => {
                            if (ev.track) trackStream.addTrack(ev.track);
                            if (trackStream.getTracks().length > 0) {
                                handleIncomingStream(trackStream);
                            }
                        };
                    }
                    call.on('close', () => {
                        untrackActiveCall(call.peer);
                        incomingHandled = false;
                        if (isScreenShare) {
                            if (call.peer === spotlightPeerId) stopStudentSpotlight();
                            LOG(`${name} ekran paylaşımını bitirdi.`, "#3b82f6");
                        } else {
                            removeStudentVideo(call.peer);
                            LOG(`${name} yayımı dayandırdı.`, "#f59e0b");
                        }
                    });
                    call.on('error', () => {
                        untrackActiveCall(call.peer);
                    });
                });
            }

            startPeer(true); // Default to cloud for stability
            startAdaptiveQualityMonitor();
            startLessonTimer();

        } catch (e) {
            LOG("❌ Studiya Xətası: " + e.message, "#ef4444");
            console.error(e);
        }
    }

    // --- NEW PERFORMANCE HELPERS ---
    let currentBitrateLimit = 1500; // Default 1.5 Mbps
    let adaptiveQualityInterval = null;
    let poorNetworkStreak = 0;
    let goodNetworkStreak = 0;

    function trackActiveCall(call) {
        if (!call || !call.peer) return;
        activePeerCalls.set(call.peer, call);
    }

    function untrackActiveCall(peerId) {
        if (!peerId) return;
        activePeerCalls.delete(peerId);
    }

    function applyBitrateLimit(pc, maxKbps) {
        if (!pc || !pc.getSenders) return;
        pc.getSenders().forEach(sender => {
            if (sender.track && sender.track.kind === 'video') {
                const parameters = sender.getParameters();
                if (!parameters.encodings || parameters.encodings.length === 0) {
                    parameters.encodings = [{}];
                }
                parameters.encodings[0].maxBitrate = maxKbps * 1000;
                sender.setParameters(parameters).catch(e => console.warn("Bitrate control not supported/blocked:", e));
            }
        });
    }

    function applyBitrateLimitToActiveCalls(maxKbps) {
        activePeerCalls.forEach((call) => {
            if (call && call.peerConnection) {
                applyBitrateLimit(call.peerConnection, maxKbps);
            }
        });
    }

    async function getCallNetworkMetrics(call) {
        try {
            if (!call || !call.peerConnection || !call.peerConnection.getStats) return null;
            const stats = await call.peerConnection.getStats();
            let rttMs = null;
            let packetsLost = 0;
            let packetsSent = 0;

            stats.forEach(report => {
                if (report.type === 'candidate-pair' && report.state === 'succeeded' && report.nominated && typeof report.currentRoundTripTime === 'number') {
                    rttMs = Math.round(report.currentRoundTripTime * 1000);
                }
                if (report.type === 'outbound-rtp' && report.kind === 'video') {
                    packetsLost += report.packetsLost || 0;
                    packetsSent += report.packetsSent || 0;
                }
            });

            const lossPercent = packetsSent > 0 ? (packetsLost / packetsSent) * 100 : 0;
            return { rttMs, lossPercent };
        } catch (e) {
            return null;
        }
    }

    async function evaluateAdaptiveQuality() {
        if (activePeerCalls.size === 0) return;

        const checks = await Promise.all(Array.from(activePeerCalls.values()).map(getCallNetworkMetrics));
        const valid = checks.filter(Boolean);
        if (valid.length === 0) return;

        const avgRtt = valid.reduce((sum, item) => sum + (item.rttMs || 0), 0) / valid.length;
        const maxLoss = Math.max(...valid.map(item => item.lossPercent || 0));
        const hasPoorSignal = avgRtt >= 450 || maxLoss >= 8;
        const hasGoodSignal = avgRtt > 0 && avgRtt <= 220 && maxLoss < 3;

        if (hasPoorSignal) {
            poorNetworkStreak++;
            goodNetworkStreak = 0;
            if (poorNetworkStreak >= 2 && currentBitrateLimit !== 800) {
                setQualityMode('eco', true);
                LOG(`📉 Auto Eco: RTT ${Math.round(avgRtt)}ms, itki ${maxLoss.toFixed(1)}%`, "#f59e0b");
            }
            return;
        }

        if (hasGoodSignal) {
            goodNetworkStreak++;
            poorNetworkStreak = 0;
            if (goodNetworkStreak >= 3 && currentBitrateLimit !== 1500) {
                setQualityMode('normal', true);
                LOG(`📈 Auto Normal: RTT ${Math.round(avgRtt)}ms, itki ${maxLoss.toFixed(1)}%`, "#10b981");
            }
            return;
        }

        poorNetworkStreak = 0;
        goodNetworkStreak = 0;
    }

    function startAdaptiveQualityMonitor() {
        if (adaptiveQualityInterval) clearInterval(adaptiveQualityInterval);
        adaptiveQualityInterval = setInterval(() => {
            evaluateAdaptiveQuality().catch(() => { });
        }, 8000);
    }

    function setQualityMode(mode, isAutomatic = false) {
        const btnNormal = document.getElementById('btnModeNormal');
        const btnEco = document.getElementById('btnModeEco');

        if (mode === 'eco') {
            currentBitrateLimit = 800; // 800 kbps
            if (btnEco) btnEco.classList.add('active-green');
            if (btnNormal) btnNormal.classList.remove('active-green');
            if (!isAutomatic) LOG("🍃 Eco rejim aktivləşdirildi (Aşağı trafik)", "#10b981");
        } else {
            currentBitrateLimit = 1500; // 1.5 mbps
            if (btnNormal) btnNormal.classList.add('active-green');
            if (btnEco) btnEco.classList.remove('active-green');
            if (!isAutomatic) LOG("📺 Standart rejim aktivləşdirildi", "#3b82f6");
        }

        applyBitrateLimitToActiveCalls(currentBitrateLimit);
    }


    function startCanvasCompositing() {
        canvas = document.createElement('canvas');
        canvas.width = 1280;
        canvas.height = 720;
        ctx = canvas.getContext('2d');
        drawToCanvas();

        const canvasStream = canvas.captureStream(20); // Reduced from 30 to 20 for performance

        // Add audio track from camera stream
        if (camStream && camStream.getAudioTracks().length > 0) {
            canvasStream.addTrack(camStream.getAudioTracks()[0]);
        }
        destStream = canvasStream;

        const types = ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
        let supportedType = '';
        for (let t of types) {
            if (MediaRecorder.isTypeSupported(t)) {
                supportedType = t;
                break;
            }
        }

        try {
            mediaRecorder = new MediaRecorder(destStream, {
                mimeType: supportedType
            });

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    recordedChunks.push(e.data);

                    // İlk parçanı (WebM Header) dərhal göndər
                    if (!isFirstChunkSent) {
                        isFirstChunkSent = true;
                        setTimeout(flushChunksToServer, 100);
                    }

                    // Əgər recordedChunks həcmi 256KB-dan çoxdursa, dərhal göndər (Limit 1MB-dan 256KB-a düşürüldü)
                    const currentSize = recordedChunks.reduce((acc, c) => acc + c.size, 0);
                    if (currentSize > 256 * 1024) {
                        flushChunksToServer();
                    }
                }
            };

            mediaRecorder.start(1000);
            recordingStartTime = Date.now();
            LOG(`Arxiv qeydiyyatı aktivdir. 🔴`, "#ef4444");

            // Start the rendering loop only after canvas/ctx are ready
            if (canvasLoopId) clearInterval(canvasLoopId);
            canvasLoopId = setInterval(drawToCanvas, 50); // 20 FPS (50ms) instead of 30 FPS (33ms)

        } catch (e) {
            LOG("MediaRecorder Xətası", "#ef4444");
        }
    }

    function drawToCanvas() {
        // --- MODE: STUDENT SPOTLIGHT (Priority) ---
        if (isStudentSpotlight && studentScreenVidElement && studentScreenVidElement.readyState >= 2) {
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const sW = studentScreenVidElement.videoWidth;
            const sH = studentScreenVidElement.videoHeight;
            const sRatio = sW / sH;
            const canvasRatio = canvas.width / canvas.height;

            let dW, dH, dx, dy;
            if (sRatio > canvasRatio) {
                dW = canvas.width;
                dH = canvas.width / sRatio;
                dx = 0;
                dy = (canvas.height - dH) / 2;
            } else {
                dH = canvas.height;
                dW = canvas.height * sRatio;
                dx = (canvas.width - dW) / 2;
                dy = 0;
            }

            ctx.save();
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(studentScreenVidElement, dx, dy, dW, dH);
            ctx.restore();

            // Draw student's name badge
            const badgeText = `TƏLƏBƏ EKRANI: ${spotlightName.toUpperCase()}`;
            ctx.save();
            ctx.font = 'bold 14px Inter, sans-serif';
            const tw = ctx.measureText(badgeText).width;
            ctx.fillStyle = 'rgba(0,0,0,0.6)';
            ctx.fillRect(20, 20, tw + 30, 30);
            ctx.fillStyle = '#3b82f6';
            ctx.fillRect(20, 20, 4, 30);
            ctx.fillStyle = '#fff';
            ctx.fillText(badgeText, 35, 41);
            ctx.restore();

            // Draw sharer's camera in corner if available
            if (studentCamVidElement && studentCamVidElement.readyState >= 2) {
                const cW = studentCamVidElement.videoWidth;
                const cH = studentCamVidElement.videoHeight;
                const cRatio = cW / cH;
                const camDrawW = 220;
                const camDrawH = camDrawW / cRatio;

                ctx.save();
                ctx.shadowBlur = 20;
                ctx.shadowColor = 'rgba(0,0,0,0.5)';
                ctx.strokeStyle = '#3b82f6';
                ctx.lineWidth = 3;
                // Clip round rect
                const cx = canvas.width - camDrawW - 30;
                const cy = canvas.height - camDrawH - 30;
                if (ctx.roundRect) {
                    ctx.beginPath();
                    ctx.roundRect(cx, cy, camDrawW, camDrawH, 12);
                    ctx.stroke();
                    ctx.clip();
                }
                ctx.drawImage(studentCamVidElement, cx, cy, camDrawW, camDrawH);
                ctx.restore();
            }

            // Draw "Stop Spotlight" button indicator for teacher (UI only, composite gets a badge)
            return;
        }

        const camVid = document.getElementById('camSource');
        const screenVid = document.getElementById('screenSource');
        const camTrack = camStream ? camStream.getVideoTracks()[0] : null;

        // Diagnostic log: Only log if camera is expected but not ready
        if (camTrack && camTrack.enabled && (!camVid || camVid.readyState < 2)) {
            if (!window.lastCamWarn || Date.now() - window.lastCamWarn > 5000) {
                LOG("⚠️ Kamera hazır deyil (State: " + (camVid ? camVid.readyState : 'null') + "). Gözlənilir...", "#f59e0b");
                window.lastCamWarn = Date.now();
            }
        }

        const isCamActive = camVid && camVid.readyState >= 2 && camTrack && camTrack.enabled;

        // 1. Fill Background
        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // 1.5 WHITEBOARD MODE: 
        if (isWhiteboardActive && wbCanvas && wbCanvas.width > 0 && wbCanvas.height > 0) {
            // First draw white background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Draw grid or lines pattern for stream (CSS background doesn't transfer to video)
            if (wbBgType === 'grid') {
                ctx.strokeStyle = '#94a3b8';
                ctx.lineWidth = 1;
                ctx.beginPath();
                const step = 30 * (canvas.width / wbCanvas.width); // Scale step size
                for (let x = step; x < canvas.width; x += step) {
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, canvas.height);
                }
                for (let y = step; y < canvas.height; y += step) {
                    ctx.moveTo(0, y);
                    ctx.lineTo(canvas.width, y);
                }
                ctx.stroke();
            } else if (wbBgType === 'lines') {
                ctx.strokeStyle = '#94a3b8';
                ctx.lineWidth = 1;
                ctx.beginPath();
                const step = 25 * (canvas.height / wbCanvas.height);
                for (let y = step; y < canvas.height; y += step) {
                    ctx.moveTo(0, y);
                    ctx.lineTo(canvas.width, y);
                }
                ctx.stroke();
            }

            // Draw whiteboard canvas content on top of background
            ctx.drawImage(wbCanvas, 0, 0, canvas.width, canvas.height);

            // Draw laser pointer on stream for students to see
            if (laserActive && wbTool === 'laser') {
                // Calculate scaled position
                const scaleX = canvas.width / wbCanvas.width;
                const scaleY = canvas.height / wbCanvas.height;
                const scaledLaserX = laserX * scaleX;
                const scaledLaserY = laserY * scaleY;

                // Draw glowing laser effect
                ctx.save();

                // Outer glow
                const gradient = ctx.createRadialGradient(scaledLaserX, scaledLaserY, 0, scaledLaserX, scaledLaserY, 25);
                gradient.addColorStop(0, 'rgba(239, 68, 68, 0.8)');
                gradient.addColorStop(0.3, 'rgba(239, 68, 68, 0.4)');
                gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.arc(scaledLaserX, scaledLaserY, 25, 0, Math.PI * 2);
                ctx.fill();

                // Inner bright dot
                ctx.beginPath();
                ctx.arc(scaledLaserX, scaledLaserY, 8, 0, Math.PI * 2);
                ctx.fillStyle = '#ef4444';
                ctx.fill();
                ctx.strokeStyle = 'white';
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.restore();
            }

            // Draw small camera in corner during whiteboard
            if (isCamActive) {
                const cW = camVid.videoWidth;
                const cH = camVid.videoHeight;
                const cRatio = cW / cH;
                const camDrawW = 200;
                const camDrawH = camDrawW / cRatio;

                ctx.save();
                ctx.shadowBlur = 10;
                ctx.shadowColor = 'rgba(0,0,0,0.3)';
                ctx.drawImage(camVid, canvas.width - camDrawW - 20, canvas.height - camDrawH - 20, camDrawW, camDrawH);
                ctx.restore();
            }
            return;
        }

        const canvasRatio = canvas.width / canvas.height;

        // 2. SCREEN SHARING: Maximize Readability (80/20)
        if (isScreenSharing && screenVid && screenVid.readyState >= 2) {

            let screenSlotW, camSlotW;
            if (isCamActive) {
                // 80% for Screen to maximize text size
                screenSlotW = canvas.width * 0.82;
                camSlotW = canvas.width * 0.18;
            } else {
                screenSlotW = canvas.width;
                camSlotW = 0;
            }

            // --- DRAW SCREEN (Maximize Height) ---
            const sW = screenVid.videoWidth;
            const sH = screenVid.videoHeight;
            const sRatio = sW / sH;

            let dW, dH, dx, dy;
            // Fill height primarily to keep text large
            dH = canvas.height;
            dW = dH * sRatio;

            if (dW > screenSlotW) {
                dW = screenSlotW;
                dH = dW / sRatio;
            }

            dx = (screenSlotW - dW) / 2;
            dy = (canvas.height - dH) / 2;

            ctx.save();
            // Sharp rendering
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(screenVid, dx, dy, dW, dH);
            ctx.restore();

            // --- DRAW CAMERA (Top Align in Slot) ---
            if (isCamActive) {
                const cW = camVid.videoWidth;
                const cH = camVid.videoHeight;
                const cRatio = cW / cH;

                let cDrawW = camSlotW - 10;
                let cDrawH = cDrawW / cRatio;

                const cx = screenSlotW + (camSlotW - cDrawW) / 2;
                const cy = 20;
                const radius = 12;

                ctx.save();
                // Shadow
                ctx.shadowBlur = 15;
                ctx.shadowColor = 'rgba(0,0,0,0.5)';
                ctx.fillStyle = '#1e293b';
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(cx - 3, cy - 3, cDrawW + 6, cDrawH + 6, radius + 2);
                else ctx.rect(cx - 3, cy - 3, cDrawW + 6, cDrawH + 6);
                ctx.fill();

                // Clip Cam
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(cx, cy, cDrawW, cDrawH, radius);
                else ctx.rect(cx, cy, cDrawW, cDrawH);
                ctx.clip();
                ctx.drawImage(camVid, cx, cy, cDrawW, cDrawH);
                ctx.restore();

                // Compact Badge
                const badgeW = 70;
                const badgeH = 18;
                const bx = cx + (cDrawW / 2) - (badgeW / 2);
                const by = cy + cDrawH - 9;

                ctx.save();
                ctx.fillStyle = '#3b82f6';
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(bx, by, badgeW, badgeH, 4);
                else ctx.rect(bx, by, badgeW, badgeH);
                ctx.fill();
                ctx.fillStyle = '#fff';
                ctx.font = '900 9px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('MÜƏLLİM', bx + (badgeW / 2), by + (badgeH / 2));
                ctx.restore();
            }

        }
        // 3. CAMERA ONLY: Full frame
        else if (camVid && camVid.readyState >= 2) {
            const cW = camVid.videoWidth;
            const cH = camVid.videoHeight;
            const cRatio = cW / cH;

            let dW, dH, dx, dy;
            if (cRatio > canvasRatio) {
                dW = canvas.width;
                dH = canvas.width / cRatio;
                dx = 0;
                dy = (canvas.height - dH) / 2;
            } else {
                dH = canvas.height;
                dW = canvas.height * cRatio;
                dx = (canvas.width - dW) / 2;
                dy = 0;
            }
            ctx.drawImage(camVid, dx, dy, dW, dH);
        }
    }

    const activeStudents = new Set();

    function addStudentVideo(peerId, stream, name, studentId = null) {
        const existingCard = document.getElementById('card-' + peerId);
        if (existingCard) {
            LOG(`${name} yayımı yeniləndi.`, "#3b82f6");
            const vid = document.getElementById('vid-' + peerId);
            if (vid) {
                vid.srcObject = stream;
                vid.play().catch(e => console.warn("Video play failed on update:", e));
            }
            return;
        }
        document.getElementById('waitingMsg').style.display = 'none';
        const grid = document.getElementById('studentsGrid');

        const card = document.createElement('div');
        card.id = 'card-' + peerId;
        card.className = 'student-card'; // Identify as student card
        if (studentId) card.setAttribute('data-student-id', studentId);
        else {
            // Fallback: Try to find studentId in the sidebar attendance list by peerId
            const sidebarAtt = document.querySelector(`[onclick*="${peerId}"]`);
            if (sidebarAtt) {
                // Extract uID from the onclick attribute if possible, or wait for refresh
                console.log("Fallback studentId discovery for:", peerId);
            }
        }
        card.setAttribute('data-peer-id', peerId);

        // CSS HACK: Use padding-top for aspect ratio to force correct height in Grid
        card.style.cssText = "background:#000; border-radius:12px; overflow:hidden; width:100%; padding-top:56.25%; position:relative; border:1px solid rgba(255,255,255,0.1); cursor:pointer; transition: 0.2s;";

        const vid = document.createElement('video');
        vid.id = 'vid-' + peerId;
        vid.autoplay = true;
        vid.playsInline = true;
        vid.muted = true; // Essential for autoplay
        // Absolute position to fill the padded area
        vid.style.cssText = "position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; background:#000;";
        vid.srcObject = stream;

        // Monitoring tracks
        const checkTracks = () => {
            const vTracks = stream.getVideoTracks();
            console.log(`Student ${name} (${peerId}) tracks at start:`, stream.getTracks().map(t => t.kind));
            if (vTracks.length === 0) {
                console.warn(`Student ${name} sent NO video tracks yet.`);
            } else {
                vTracks.forEach(t => {
                    t.onunmute = () => console.log(`Track unmuted for ${name}`);
                    t.onmute = () => console.warn(`Track muted for ${name}`);
                });
            }
        };
        checkTracks();

        // Handle late arriving tracks
        stream.onaddtrack = (e) => {
            console.log(`Late track arrived for ${name}:`, e.track.kind);
            vid.srcObject = stream; // Refresh
            vid.play().catch(() => { });
        };

        vid.onloadedmetadata = () => {
            vid.play().catch(err => console.warn("Student video play failed:", err));
        };

        // Name Badge
        const badge = document.createElement('div');
        badge.style.cssText = "position:absolute; bottom:8px; left:8px; background:rgba(0,0,0,0.7); color:white; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:700; backdrop-filter:blur(4px); display:flex; align-items:center; gap:6px;";
        badge.innerHTML = `<span style="width:6px; height:6px; background:#10b981; border-radius:50%; box-shadow:0 0 6px #10b981;"></span>${name}`;

        // 3-Dot Menu Button
        const menuBtn = document.createElement('button');
        menuBtn.innerHTML = '⋮';
        menuBtn.title = "Tələbə Əməliyyatları";
        menuBtn.style.cssText = "position:absolute; top:8px; right:8px; background:rgba(0,0,0,0.5); border:none; border-radius:8px; width:28px; height:28px; font-size:16px; cursor:pointer; color:white; display:flex; align-items:center; justify-content:center; z-index:20; backdrop-filter:blur(4px); transition:0.2s;";

        // Create dropdown and append to BODY (fixed positioning)
        const dropdown = document.createElement('div');
        dropdown.className = 'student-dropdown';
        dropdown.id = 'dropdown-' + peerId;
        dropdown.style.cssText = "position:fixed; background:#1e293b; border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:8px 0; min-width:180px; z-index:99999; display:none; box-shadow:0 15px 40px rgba(0,0,0,0.8); backdrop-filter:blur(10px);";
        document.body.appendChild(dropdown);

        // Dropdown Items
        const createMenuItem = (icon, text, color, onClick) => {
            const item = document.createElement('button');
            item.innerHTML = `<span style="font-size:14px;">${icon}</span><span>${text}</span>`;
            item.style.cssText = `display:flex; align-items:center; gap:10px; width:100%; padding:12px 18px; background:none; border:none; color:${color}; font-size:13px; font-weight:600; cursor:pointer; text-align:left; transition:0.15s;`;
            item.onmouseenter = () => item.style.background = 'rgba(255,255,255,0.08)';
            item.onmouseleave = () => item.style.background = 'none';
            item.onclick = (e) => {
                e.stopPropagation();
                onClick();
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
                menuBtn.style.background = 'rgba(0,0,0,0.5)';
            };
            return item;
        };

        // Sound Toggle
        let isMuted = true;
        const soundItem = createMenuItem('🔇', 'Səsi Aç', '#10b981', () => {
            vid.muted = !vid.muted;
            isMuted = vid.muted;
            soundItem.innerHTML = vid.muted ?
                '<span style="font-size:14px;">🔇</span><span>Səsi Aç</span>' :
                '<span style="font-size:14px;">🔊</span><span>Səsi Bağla</span>';
            soundItem.querySelector('span:last-child').style.color = vid.muted ? '#10b981' : '#f59e0b';
        });

        // Spotlight
        const spotlightItem = createMenuItem('🎯', 'Böyüt (Spotlight)', '#3b82f6', () => {
            focusStudent(stream, name);
        });

        // Private Message
        const chatItem = createMenuItem('💬', 'Özəl Mesaj', '#8b5cf6', () => {
            setPrivateTarget(peerId, name);
        });

        // Divider
        const divider = document.createElement('div');
        divider.style.cssText = "height:1px; background:rgba(255,255,255,0.1); margin:8px 0;";

        // Kick
        const kickItem = createMenuItem('🚫', 'Dərsdən Uzaqlaşdır', '#ef4444', () => {
            kickStudent(peerId, name, studentId);
        });

        dropdown.append(soundItem, spotlightItem, chatItem, divider, kickItem);

        // Menu Toggle with fixed positioning
        menuBtn.onclick = (e) => {
            e.stopPropagation();

            // Close all other dropdowns
            document.querySelectorAll('.student-dropdown').forEach(d => {
                if (d !== dropdown) {
                    d.style.display = 'none';
                    d.classList.remove('open');
                }
            });

            const isOpen = dropdown.style.display === 'block';

            if (!isOpen) {
                // Calculate position based on button location
                const rect = menuBtn.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = (rect.left - 150) + 'px'; // Position to the left of button
                dropdown.style.display = 'block';
                dropdown.classList.add('open');
                menuBtn.style.background = 'rgba(59, 130, 246, 0.8)';
            } else {
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
                menuBtn.style.background = 'rgba(0,0,0,0.5)';
            }
        };

        menuBtn.onmouseenter = () => menuBtn.style.background = 'rgba(59, 130, 246, 0.8)';
        menuBtn.onmouseleave = () => {
            if (!dropdown.classList.contains('open')) menuBtn.style.background = 'rgba(0,0,0,0.5)';
        };

        // Close dropdown when clicking anywhere
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== menuBtn) {
                dropdown.style.display = 'none';
                dropdown.classList.remove('open');
            }
        });

        card.append(vid, badge, menuBtn);
        grid.append(card);
        document.getElementById('activeVideoCount').innerText = grid.children.length - (document.getElementById('waitingMsg').style.display === 'none' ? 0 : 1);
    }

    function muteStudent(peerId, name) {
        if (confirm(`${name} adlı tələbənin səsini kəsmək istəyirsiniz?`)) {
            const conn = allDataConns.find(c => c.peer === peerId);
            if (conn) {
                conn.send({
                    type: 'mute_force'
                });
                LOG(`${name} səsi kəsildi.`, "#ef4444");
            }
        }
    }

    function refreshAllStudentVideos() {
        LOG("🔄 Bütün tələbə yayımları yenilənir...", "#fde047");
        allDataConns.forEach(conn => {
            if (conn.open) conn.send({
                type: 'refresh_stream'
            });
        });
    }

    function removeStudentVideo(peerId) {
        const card = document.getElementById('card-' + peerId);
        if (card) card.remove();
        const grid = document.getElementById('studentsGrid');
        const count = grid.children.length - (document.getElementById('waitingMsg').style.display === 'none' ? 0 : 1);
        document.getElementById('activeVideoCount').innerText = Math.max(0, count);
        if (grid.children.length === 1) document.getElementById('waitingMsg').style.display = 'block';
    }


    function focusStudent(studentStream, name) {
        document.getElementById('localVid').srcObject = studentStream;
        document.getElementById('localVid').muted = false;
        document.getElementById('spotlightOverlay').style.display = 'block';
        LOG("Spotlight: " + name, "#3b82f6");
    }

    function resetMainVideo() {
        document.getElementById('localVid').srcObject = stream;
        document.getElementById('localVid').muted = true;
        document.getElementById('spotlightOverlay').style.display = 'none';
    }

    function toggleMic() {
        const t = camStream.getAudioTracks()[0];
        if (t) {
            t.enabled = !t.enabled;
            document.getElementById('btnMic').classList.toggle('active-red', !t.enabled);
        }
    }

    function toggleCam() {
        const t = camStream.getVideoTracks()[0];
        if (t) {
            t.enabled = !t.enabled;
            document.getElementById('btnCam').classList.toggle('active-red', !t.enabled);
        }
    }
    async function toggleScreenShare() {
        const btn = document.getElementById('btnScreen');
        if (isScreenSharing) {
            if (screenStream) screenStream.getTracks().forEach(t => t.stop());
            screenStream = null;
            isScreenSharing = false;
            btn.classList.remove('active-green');
            LOG("Ekran paylaşımı dayandırıldı.", "#f59e0b");
        } else {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: true
                });
                document.getElementById('screenSource').srcObject = screenStream;
                isScreenSharing = true;
                btn.classList.add('active-green');
                LOG("Ekran paylaşımı aktivdir.", "#10b981");

                screenStream.getVideoTracks()[0].onended = () => {
                    if (isScreenSharing) toggleScreenShare();
                };
            } catch (e) {
                LOG("Ekran paylaşımı ləğv edildi.", "#f59e0b");
            }
        }
    }

    function showScreenShareRequestModal(name, conn) {
        const modal = document.createElement('div');
        modal.id = 'ss-request-modal';
        modal.style = "position:fixed; top:80px; left:50%; transform:translateX(-50%); background:#1e293b; border:2px solid #10b981; border-radius:20px; padding:30px; z-index:10000; box-shadow:0 20px 50px rgba(0,0,0,0.7); color:white; min-width:350px; text-align:center; animation: zoomIn 0.3s ease-out;";
        modal.innerHTML = `
            <div style="font-size:50px; margin-bottom:20px;">🖥️</div>
            <div style="font-size:14px; font-weight:700; margin-bottom:10px; color:#10b981; text-transform:uppercase; letter-spacing:1px;">Ekran Paylaşımı Sorğusu</div>
            <div style="font-size:18px; margin-bottom:25px;"><b>${name}</b> öz ekranını dərslə paylaşmaq istəyir.</div>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button id="btnRejectSS" style="background:rgba(244, 63, 94, 0.1); border:1px solid #f43f5e; color:#f43f5e; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">Rədd et</button>
                <button id="btnApproveSS" style="background:#10b981; border:none; color:white; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">İcazə ver</button>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('btnApproveSS').onclick = () => {
            conn.send({
                type: 'screen_share_approved'
            });
            LOG(`✅ ${name} üçün ekran paylaşımına icazə verildi.`, "#10b981");
            modal.remove();
        };
        document.getElementById('btnRejectSS').onclick = () => {
            conn.send({
                type: 'screen_share_rejected'
            });
            LOG(`❌ ${name} üçün ekran paylaşım sorğusu rədd edildi.`, "#f43f5e");
            modal.remove();
        };
    }

    function startStudentSpotlight(peerId, screenStream, name) {
        isStudentSpotlight = true;
        spotlightPeerId = peerId;
        spotlightName = name;

        // Ensure we have a hidden container for spotlight videos
        let hiddenContainer = document.getElementById('hiddenSpotlightContainer');
        if (!hiddenContainer) {
            hiddenContainer = document.createElement('div');
            hiddenContainer.id = 'hiddenSpotlightContainer';
            hiddenContainer.style.display = 'none';
            document.body.appendChild(hiddenContainer);
        }

        // Create a video element to feed the canvas
        studentScreenVidElement = document.createElement('video');
        studentScreenVidElement.srcObject = screenStream;
        studentScreenVidElement.muted = true;
        studentScreenVidElement.playsInline = true;
        hiddenContainer.appendChild(studentScreenVidElement); // MUST BE IN DOM for some browsers to update state


        studentScreenVidElement.onloadedmetadata = () => {
            console.log(`[SPOTLIGHT] Video Loaded: ${studentScreenVidElement.videoWidth}x${studentScreenVidElement.videoHeight}`);
            studentScreenVidElement.play()
                .then(() => LOG(`✨ ${name} ekranı/lövhəsi başladı.`, "#3b82f6"))
                .catch(e => LOG("❌ Video Play Xətası: " + e.message, "#ef4444"));
        };

        // If metadata doesn't load for some reason, try playing anyway
        setTimeout(() => {
            if (studentScreenVidElement.paused) {
                studentScreenVidElement.play().catch(e => console.error("Force play failed", e));
            }
        }, 1000);

        // Also try to find their camera stream among active cards
        const studentCard = document.getElementById('card-' + peerId);
        if (studentCard) {
            const camVid = studentCard.querySelector('video');
            if (camVid) studentCamVidElement = camVid;
        }

        // Display a control button to stop spotlight
        let stopBtn = document.getElementById('btnStopSpotlight');
        if (!stopBtn) {
            stopBtn = document.createElement('button');
            stopBtn.id = 'btnStopSpotlight';
            stopBtn.innerHTML = "⏹️ Tələbə Paylaşımını Dayandır";
            stopBtn.style = "position:fixed; bottom:100px; left:50%; transform:translateX(-50%); background:#ef4444; color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:800; z-index:5000; cursor:pointer; box-shadow:0 10px 30px rgba(239, 68, 68, 0.4); animation: slideUp 0.3s ease-out;";
            stopBtn.onclick = () => stopStudentSpotlight('teacher');
            document.body.appendChild(stopBtn);
        }

        LOG(`✨ ${name} spotlight sorğusu qəbul edildi.`, "#3b82f6");
    }

    function stopStudentSpotlight(reason = 'auto') {
        if (spotlightPeerId) {
            const conn = allDataConns.find(c => c.peer === spotlightPeerId && c.open);
            if (conn) {
                conn.send({
                    type: 'whiteboard_force_stop',
                    reason: reason
                });
                if (reason === 'teacher') {
                    LOG("🛑 Tələbəyə lövhəni dayandırmaq əmri göndərildi.", "#f59e0b");
                }
            }
        }

        isStudentSpotlight = false;
        spotlightPeerId = null;
        if (studentScreenVidElement) {
            studentScreenVidElement.srcObject = null;
            if (studentScreenVidElement.parentNode) {
                studentScreenVidElement.parentNode.removeChild(studentScreenVidElement);
            }
            studentScreenVidElement = null;
        }
        studentCamVidElement = null;

        const stopBtn = document.getElementById('btnStopSpotlight');
        if (stopBtn) stopBtn.remove();

        LOG("⏹️ Spotlight dayandırıldı, müəllim kamerasını qayıdırıq.", "#f59e0b");
    }

    function showMicRequestModal(name, conn) {
        const modal = document.createElement('div');
        modal.style = "position:fixed; top:20px; right:20px; background:#1e293b; border:1px solid #3b82f6; border-radius:12px; padding:20px; z-index:10000; box-shadow:0 10px 30px rgba(0,0,0,0.5); color:white; min-width:300px; animation: slideIn 0.3s ease-out;";
        modal.innerHTML = `
            <div style="font-size:14px; font-weight:700; margin-bottom:15px; color:#3b82f6;">🎙️ MİKROFON SORĞUSU</div>
            <div style="font-size:16px; margin-bottom:20px;"><b>${name}</b> söz istəyir. Mikrofonu aktiv edilsin?</div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button id="btnRejectMic" style="background:none; border:none; color:#f43f5e; cursor:pointer; font-weight:700;">Rədd et</button>
                <button id="btnApproveMic" style="background:#3b82f6; border:none; color:white; padding:8px 15px; border-radius:8px; cursor:pointer; font-weight:700;">İcazə ver</button>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('btnApproveMic').onclick = () => {
            conn.send({
                type: 'mic_approved'
            });
            modal.remove();
        };
        document.getElementById('btnRejectMic').onclick = () => {
            conn.send({
                type: 'mic_rejected'
            });
            modal.remove();
        };
    }

    function showRejoinRequestModal(name, conn, userId) {
        if (document.getElementById('rejoin-modal-' + userId)) return;

        const modal = document.createElement('div');
        modal.id = 'rejoin-modal-' + userId;
        modal.style = "position:fixed; top:100px; left:50%; transform:translateX(-50%); background:#1e293b; border:2px solid #f59e0b; border-radius:20px; padding:30px; z-index:10000; box-shadow:0 20px 50px rgba(0,0,0,0.7); color:white; min-width:350px; text-align:center; animation: zoomIn 0.3s ease-out;";
        modal.innerHTML = `
            <div style="font-size:50px; margin-bottom:20px;">✋</div>
            <div style="font-size:14px; font-weight:700; margin-bottom:10px; color:#f59e0b; text-transform:uppercase;">Giriş İstəyi</div>
            <div style="font-size:18px; margin-bottom:25px;">Uzaqlaşdırılmış tələbə <b>${name}</b> yenidən dərsə girmək üçün icazə istəyir.</div>
            <div style="display:flex; gap:15px; justify-content:center;">
                <button id="btnRejectRejoin-${userId}" style="background:rgba(244, 63, 94, 0.1); border:1px solid #f43f5e; color:#f43f5e; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">Rədd et</button>
                <button id="btnApproveRejoin-${userId}" style="background:#f59e0b; border:none; color:#000; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:700;">İcazə ver</button>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('btnApproveRejoin-' + userId).onclick = () => {
            LOG(`🔄 ${name} (ID: ${userId}) üçün giriş icazəsi bazaya ötürülür...`, "#f59e0b");

            // Use the teacher API endpoint properly
            const formData = new FormData();
            formData.append('live_class_id', lID);
            formData.append('user_id', userId);

            fetch('api/approve_rejoin.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        conn.send({
                            type: 'entry_approved'
                        });
                        LOG(`✅ ${name} üçün yenidən giriş icazəsi verildi`, "#10b981");
                        modal.remove();
                    } else {
                        alert("Xəta: " + (data.message || 'Naməlum xəta'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Sorğu zamanı xəta yarandı.");
                });
        };
        document.getElementById('btnRejectRejoin-' + userId).onclick = () => {
            conn.send({
                type: 'entry_rejected'
            });
            LOG(`❌ ${name} üçün giriş sorğusu rədd edildi`, "#f43f5e");
            modal.remove();
        };
    }

    function replaceTrack(newTrack) {
        Object.values(peer.connections).forEach(cs => cs.forEach(c => {
            if (c.peerConnection) {
                const s = c.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
                if (s) s.replaceTrack(newTrack);
            }
        }));
    }

    <?php
    $lessonStartTime = 'Date.now()';
    if ($lesson && isset($lesson['started_at']) && $lesson['started_at']) {
        $lessonStartTime = strtotime($lesson['started_at']) * 1000;
    }
    ?>
    let lessonStartedAt = <?php echo $lessonStartTime; ?>;

    function startLessonTimer() {
        if (!document.getElementById('timerDisplay')) return;

        const MAX_DURATION = 3 * 3600; // 3 hours in seconds
        let hasWarned = false;

        setInterval(() => {
            const now = Date.now();
            const diff = Math.floor((now - lessonStartedAt) / 1000);
            const totalSeconds = Math.max(0, diff);

            // AUTO-TERMINATE CHECK
            if (totalSeconds >= MAX_DURATION) {
                LOG("⌛ Maksimum dərs müddəti (3 saat) tamamlandı. Dərs avtomatik bitirilir.", "#ef4444");
                stopAndUpload(true); // Call with isAuto = true
                return;
            }

            // 5-MINUTE WARNING
            if (totalSeconds >= (MAX_DURATION - 300) && !hasWarned) {
                hasWarned = true;
                LOG("⚠️ Diqqət: Dərsin bitməsinə 5 dəqiqə qalıb (Maksimum 3 saat).", "#f59e0b");
                alert("DİQQƏT: Maksimum dərs müddəti (3 saat) dolmaq üzrədir! 5 dəqiqə sonra dərs avtomatik kəsiləcək. Xahiş olunur dərsi yekunlaşdırın.");
            }

            const h = Math.floor(totalSeconds / 3600);
            const m = Math.floor((totalSeconds % 3600) / 60);
            const s = totalSeconds % 60;

            let timeStr = "";
            if (h > 0) timeStr += (h < 10 ? '0' + h : h) + ':';
            timeStr += (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);

            document.getElementById('timerDisplay').innerText = timeStr;
        }, 1000);
    }

    function stopAndUpload(isAuto = false) {
        if (!isAuto && !confirm("Dərsi bitirmək və arxivləmək istəyirsiniz?")) return;

        LOG("🏁 Dərs bitirilir...", "#f59e0b");
        broadcastData({
            type: 'lesson_ended'
        });

        // Periodic flush-u dayandır
        if (chunkFlushInterval) {
            clearInterval(chunkFlushInterval);
            chunkFlushInterval = null;
        }

        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            LOG("🎥 Yazı dayandırılır...", "#f59e0b");
            mediaRecorder.stop();
            recordingDurationMs = Date.now() - recordingStartTime;
        }

        LOG("⏳ Video emal olunur, xahiş olunur gözləyin...", "#3b82f6");

        // Əvvəlcə qalan parçaları serverə göndər, sonra final upload et
        setTimeout(() => {
            const finalFlush = (recordedChunks.length > 0) ? flushChunksToServer() : Promise.resolve();

            finalFlush.then(() => {
                const fd = new FormData();
                fd.append('lesson_id', lID);
                fd.append('course_id', '<?php echo $lesson['course_id']; ?>');
                fd.append('has_chunks', '1'); // Serverə pre-saved parçalar olduğunu bildir
                fd.append('duration_ms', recordingDurationMs);

                if (recordedChunks.length === 0) {
                    // Bütün parçalar artıq serverə göndərilib, əlavə video yoxdur
                    fd.append('no_video', '1');
                } else {
                    const blob = new Blob(recordedChunks, {
                        type: 'video/webm'
                    });
                    LOG(`📦 Video Blobu yaradıldı: ${(blob.size / 1024 / 1024).toFixed(2)} MB`, "#10b981");
                    fd.append('video', blob);
                }

                LOG("🚀 Serverə göndərilir...", "#3b82f6");

                fetch('api/upload_recording.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'include'
                })
                    .then(r => r.text())
                    .then(text => {
                        LOG("📥 Server cavabı alındı.", "#10b981");
                        try {
                            const d = JSON.parse(text);
                            if (d.success) {
                                LOG("✅ Dərs uğurla tamamlandı!", "#10b981");
                                sessionStorage.removeItem('active_recording_session_' + lID);
                                alert(d.message || "Dərs uğurla bitirildi!");
                                window.location.href = 'live-lessons.php';
                            } else {
                                LOG("❌ Server xətası: " + d.message, "#ef4444");
                                alert("Xəta: " + d.message);
                                window.location.href = 'live-lessons.php';
                            }
                        } catch (e) {
                            console.error("Non-JSON response:", text);
                            LOG("❌ Cavab oxunarkən xəta yarandı.", "#ef4444");
                            alert("Server Xətası: Məlumat yadda saxlanıla bilmədi.");
                            window.location.href = 'live-lessons.php';
                        }
                    })
                    .catch(err => {
                        LOG("❌ Şəbəkə xətası: " + err.message, "#ef4444");
                        alert("Şəbəkə Xətası: Video yüklənə bilmədi.");
                        window.location.href = 'live-lessons.php';
                    });
            });
        }, 2000);
    }

    function kickStudent(pId, name, uId) {
        // Fallback: If uId is missing, try to get it from the card attribute
        if (!uId || uId === 'undefined' || uId === 'null') {
            const card = document.getElementById('card-' + pId);
            if (card && card.getAttribute('data-student-id')) {
                uId = card.getAttribute('data-student-id');
                console.log(`Found uId from card: ${uId}`);
            }
        }

        // Second Fallback: Try to find in the global attendees list if possible
        if (!uId || uId === 'undefined' || uId === 'null') {
            LOG(`❌ Xəta: Bu tələbənin ID-si tapılmadı (${name}). ID: ${uId}`, "#ef4444");
            console.error("Kick Error: uId is invalid", {
                pId,
                name,
                uId
            });
            return;
        }

        if (!confirm(`${name} uzaqlaşdırılsın?`)) return;

        LOG(`🚀 ${name} uzaqlaşdırılır...`, "#f59e0b");

        const fd = new FormData();
        fd.append('live_class_id', lID);
        fd.append('user_id', uId);

        fetch('api/kick_student.php', {
            method: 'POST',
            body: fd,
            credentials: 'include'
        })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    LOG(`✅ Bazada qeyd edildi: ${name}`, "#22c55e");

                    // Notify via WebRTC
                    let notified = false;
                    if (pId && peer && peer.connections && peer.connections[pId]) {
                        peer.connections[pId].forEach(conn => {
                            if (conn.type === 'data' && conn.open) {
                                conn.send({
                                    type: 'kick_user'
                                });
                                notified = true;
                            }
                        });
                    }

                    if (notified) {
                        LOG(`📡 Canlı siqnal göndərildi: ${name}`, "#22c55e");
                    } else {
                        LOG(`⚠️ Canlı bağlantı yoxdur, tələbə yenilənmədə uzaqlaşacaq.`, "#f59e0b");
                    }

                    if (pId) removeStudentVideo(pId);

                    // Refresh UI
                    refreshLiveAttendance();
                } else {
                    LOG(`❌ Xəta: ${data.message}`, "#ef4444");
                    alert("Xəta: " + data.message);
                }
            })
            .catch(err => {
                LOG(`❌ Şəbəkə xətası: ${err.message}`, "#ef4444");
                console.error("Kick Error:", err);
            });
    }

    function approveStudentRejoin(uId, name) {
        LOG(`✅ ${name} (ID: ${uId}) üçün yenidən giriş icazəsi təsdiqlənir...`, "#f59e0b");

        fetch(`../student/api/unkick_student.php?live_class_id=${lID}&user_id=${uId}&t=${Date.now()}`, { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    LOG(`✨ ${name} artıq daxil ola bilər.`, "#10b981");
                    refreshLiveAttendance();
                } else {
                    alert("Xəta: " + (data.message || 'Parametr xətası'));
                }
            })
            .catch(err => {
                LOG(`❌ API Xətası: ${err.message}`, "#ef4444");
            });
    }

    // --- ATTENDANCE MENU SYSTEM ---
    let attendanceMenuEl = null;

    function openAttendanceMenu(e, userId, name, isOnline, isKicked, peerId) {
        e.stopPropagation();

        // Create menu if not exists
        if (!attendanceMenuEl) {
            attendanceMenuEl = document.createElement('div');
            attendanceMenuEl.className = 'student-dropdown';
            attendanceMenuEl.style.cssText = "position:fixed; background:#1e293b; border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:6px 0; min-width:180px; z-index:99999; display:none; box-shadow:0 15px 40px rgba(0,0,0,0.8); backdrop-filter:blur(10px);";
            document.body.appendChild(attendanceMenuEl);

            // Close on outside click
            document.addEventListener('click', (event) => {
                if (attendanceMenuEl && attendanceMenuEl.style.display === 'block' && !attendanceMenuEl.contains(event.target)) {
                    attendanceMenuEl.style.display = 'none';
                }
            });
        }

        // Helper to create items
        const createItem = (icon, text, color, onClick) => {
            return `
                <button onclick="${onClick}" style="display:flex; align-items:center; gap:10px; width:100%; padding:10px 16px; background:none; border:none; color:${color}; font-size:13px; font-weight:600; cursor:pointer; text-align:left; transition:0.15s;" onmouseenter="this.style.background='rgba(255,255,255,0.08)'" onmouseleave="this.style.background='none'">
                    <span style="font-size:16px;">${icon}</span>
                    <span>${text}</span>
                </button>
            `;
        };

        let menuHtml = '';

        // Header
        menuHtml += `<div style="padding:8px 16px; font-size:11px; color:#64748b; font-weight:700; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:4px;">${name}</div>`;

        if (isKicked) {
            // UNKICK - approveStudentRejoin now uses the correct API
            menuHtml += createItem('🔓', 'Girişə İcazə Ver', '#f59e0b', `approveStudentRejoin('${userId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}')`);
        } else if (isOnline) {
            // ONLINE ACTIONS
            menuHtml += createItem('💬', 'Özəl Mesaj', '#3b82f6', `setPrivateTarget('${peerId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}')`);
            menuHtml += createItem('🚫', 'Dərsdən Uzaqlaşdır', '#ef4444', `kickStudent('${peerId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}', '${userId}')`);
        } else {
            // OFFLINE ACTIONS
            menuHtml += createItem('🔔', 'Bildiriş Göndər', '#94a3b8', `openBulkNotificationModal('${userId}', '${name.replace(/'/g, "\\'").replace(/"/g, '\\"')}')`);
        }

        attendanceMenuEl.innerHTML = menuHtml;

        // Position and Show
        const rect = e.target.getBoundingClientRect();
        attendanceMenuEl.style.top = (rect.bottom + 5) + 'px';
        attendanceMenuEl.style.left = (rect.left - 150) + 'px'; // Shift left a bit
        attendanceMenuEl.style.display = 'block';
    }

    function refreshLiveAttendance() {
        LOG("🔄 Canlı iştirak yenilənir...", "#60a5fa");
        fetch('api/get_live_attendance.php?id=' + lID + '&subject_id=' + courseId, { credentials: 'include' })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    LOG(`✅ İştirak məlumatı alındı: ${data.online_count}/${data.total_count}`, "#10b981");
                    document.getElementById('liveAttendanceCount').innerText = data.online_count + ' / ' + data.total_count;
                    const list = document.getElementById('liveAttendanceList');
                    if (data.attendees.length === 0) {
                        list.innerHTML = '<div style="color: #64748b; font-style: italic; text-align:center; padding:20px; font-size:12px;">Hələ ki, qoşulub yoxdur.</div>';
                    } else {
                        list.innerHTML = data.attendees.map(att => {
                            const uID = att.userId || att.user_id || att.id || 'N/A';

                            // SYNC: If student is online and has a peer_id, update their video card's student-id
                            if (att.is_online && att.peer_id) {
                                const card = document.getElementById('card-' + att.peer_id);
                                if (card && !card.getAttribute('data-student-id')) {
                                    card.setAttribute('data-student-id', uID);
                                    console.log(`Synced ID ${uID} for peer ${att.peer_id}`);
                                }
                            }

                            return `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: rgba(255,255,255,0.03); border-radius: 8px; margin-bottom: 6px; border: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; background: ${att.is_online ? 'rgba(16, 185, 129, 0.15)' : 'rgba(255,255,255,0.05)'}; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: ${att.is_online ? '#10b981' : '#64748b'};">
                                        ${att.role === 'instructor' ? '🎓' : '👤'}
                                    </div>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 600; color: #f8fafc; line-height: 1.2;">${att.name}</div>
                                        <div style="font-size: 10px; color: ${att.is_kicked ? '#ef4444' : (att.is_online ? '#10b981' : '#64748b')}; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                            <span style="width: 4px; height: 4px; background: currentColor; border-radius: 50%;"></span>
                                            ${att.is_kicked ? 'UZAQLAŞDIRILIB' : (att.is_online ? 'CANLI' : 'OFFLINE')}
                                        </div>
                                    </div>
                                </div>
                                
                                <button onclick="openAttendanceMenu(event, '${uID}', '${att.name.replace(/'/g, "\\'").replace(/"/g, '\\"')}', ${att.is_online}, ${att.is_kicked}, '${att.peer_id || ''}')" 
                                    style="width:28px; height:28px; border:none; background:transparent; color:#94a3b8; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:0.2s;" 
                                    onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.color='white'" 
                                    onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'">
                                    ⋮
                                </button>
                            </div>`;
                        }).join('');
                    }
                } else {
                    LOG(`⚠️ API xətası: ${data.message}`, "#facc15");
                    if (data.message.toLowerCase().includes('unauthorized')) {
                        document.getElementById('liveAttendanceList').innerHTML = 
                            '<div style="padding:20px; text-align:center; color:#ef4444; font-size:12px;">' +
                            'Sessiya itirilib. Lütfən yenidən daxil olun.</div>';
                    }
                }
            })
            .catch(err => {
                LOG("❌ Canlı iştirak xətası: " + err.message, "#ef4444");
                console.error("Attendance Error:", err);
            });
    }

    // --- NOTIFICATION SYSTEM ---
    function openBulkNotificationModal(userId = null, name = null) {
        const modal = document.createElement('div');
        modal.id = 'bulkNotifyModal';
        modal.style = "position:fixed; inset:0; background:rgba(2,6,23,0.85); z-index:20000; backdrop-filter:blur(10px); display:flex; justify-content:center; align-items:center;";

        const isIndividual = userId != null;
        const targetLabel = isIndividual ? `Tələbə: ${name}` : "Bütün Kurs Tələbələrinə Toplu Mesaj";

        modal.innerHTML = `
            <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:24px; padding:30px; width:100%; max-width:450px; box-shadow:0 30px 60px rgba(0,0,0,0.5);">
                <div style="font-size:18px; font-weight:800; color:white; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                    ${isIndividual ? '📧 Fərdi Mesaj' : '📣 Toplu Bildiriş'}
                    <button onclick="this.closest('#bulkNotifyModal').remove()" style="background:none; border:none; color:#64748b; font-size:24px; cursor:pointer;">&times;</button>
                </div>
                <div style="font-size:12px; color:#94a3b8; margin-bottom:15px; background:rgba(59, 130, 246, 0.1); padding:8px 12px; border-radius:8px; border-left:3px solid #3b82f6;">
                    🎯 ${targetLabel}
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px;">Başlıq</label>
                    <input id="notifyTitle" type="text" value="Müəllim Bildirişi" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:10px; color:white; outline:none; font-size:14px;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px;">Mesaj</label>
                    <textarea id="notifyMessage" placeholder="Tələbələrə nə demək istəyirsiniz?" style="width:100%; height:120px; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:10px; color:white; outline:none; font-size:14px; resize:none;"></textarea>
                </div>
                <div style="display:flex; gap:12px;">
                    <button onclick="this.closest('#bulkNotifyModal').remove()" style="flex:1; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:12px; cursor:pointer; font-weight:700;">Ləğv et</button>
                    <button id="sendNotifyBtn" onclick="sendNotification(${isIndividual ? `'individual', '${userId}'` : `'bulk', '${<?php echo $lesson['course_id']; ?>}'`})" style="flex:2; background:#3b82f6; border:none; color:white; padding:12px; border-radius:12px; cursor:pointer; font-weight:800; display:flex; align-items:center; justify-content:center; gap:8px;">
                        Göndər 🚀
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function sendNotification(type, targetId) {
        const titleEl = document.getElementById('notifyTitle');
        const messageEl = document.getElementById('notifyMessage');
        const title = titleEl ? titleEl.value : '';
        const message = messageEl ? messageEl.value : '';
        const btn = document.getElementById('sendNotifyBtn');
        if (!message) {
            alert("Mesaj boş ola bilməz!");
            return;
        }
        btn.disabled = true;
        btn.innerHTML = 'Göndərilir...';
        fetch('api/send_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                target_type: type,
                target_id: targetId,
                title: title,
                message: message,
                type: 'info'
            }),
            credentials: 'include'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    LOG(`✅ Bildiriş uğurla göndərildi.`, "#10b981");

                    // Broadcast via WebRTC for real-time delivery to students in class
                    const msgObj = {
                        type: 'notification',
                        title: title,
                        message: message,
                        style: 'info'
                    };

                    if (type === 'bulk') {
                        broadcastData(msgObj);
                        LOG(`📡 Toplu bildiriş bütün tələbələrə yayımlandı.`, "#3b82f6");
                    } else {
                        // Send to specific student (find their peer connection)
                        let sent = false;
                        if (peer && peer.connections) {
                            Object.values(peer.connections).forEach(conns => {
                                conns.forEach(conn => {
                                    if (conn.type === 'data' && conn.metadata && String(conn.metadata.userId) === String(targetId)) {
                                        conn.send(msgObj);
                                        sent = true;
                                    }
                                });
                            });
                        }
                        if (sent) {
                            LOG(`📡 Fərdi bildiriş tələbəyə çatdırıldı.`, "#3b82f6");
                        } else {
                            LOG(`⚠️ Tələbə canlı deyilsə, bildirişi girişdə görəcək.`, "#f59e0b");
                        }
                    }

                    document.getElementById('bulkNotifyModal').remove();
                } else {
                    alert("Xəta: " + data.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Göndər 🚀';
                }
            });
    }

    // --- ATTENDANCE TRACKING ---
    function trackAttendance(type) {
        const params = {
            type: type,
            live_class_id: lID
        };
        if (peer && peer.id) params.peer_id = peer.id;
        if (type === 'leave') {
            const blob = new Blob([JSON.stringify(params)], {
                type: 'application/json'
            });
            navigator.sendBeacon('api/track_attendance.php', blob);
            return;
        }
        fetch('api/track_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(params),
            credentials: 'include'
        }).catch(err => console.error("Attendance Track Error:", err));
    }

    setInterval(() => {
        if (peer && peer.open) trackAttendance('heartbeat');
    }, 30000);

    // Start everything correctly on load
    window.onload = () => {
        // init(); // Now called via startProductionNow() for user gesture compliance
        setTimeout(() => {
            LOG("📊 İlk iştirak məlumatı yüklənir...", "#3b82f6");
            refreshLiveAttendance();
        }, 1000);
        setInterval(refreshLiveAttendance, 5000);

        // Hər 5 saniyədən bir recording parçalarını serverə göndər (Daha sürətli yaddaş)
        chunkFlushInterval = setInterval(flushChunksToServer, 5000);
    };

    window.addEventListener('beforeunload', (e) => {
        trackAttendance('leave');
        // Refresh/bağlanma zamanı qalan parçaları sendBeacon ilə göndər
        flushChunksBeacon();

        // Refresh qadağan etmək üçün brauzer xəbərdarlığı
        e.preventDefault();
        e.returnValue = 'Dərs gedir, səhifəni yeniləmək qeydiyyatın itməsinə səbəb ola bilər. Çıxmaq istədiyinizə əminsiniz?';
    });

    // Klaviatura qısayollarını (F5, Ctrl+R) blokla
    window.addEventListener('keydown', function (e) {
        if ((e.which || e.keyCode) == 116 || (e.ctrlKey && (e.which || e.keyCode) == 82)) {
            e.preventDefault();
            LOG("⚠️ Səhifəni yeniləmək qadağandır!", "#ef4444");
        }
    });
</script>
<?php require_once 'includes/footer.php'; ?>