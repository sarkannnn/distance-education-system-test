<?php

/**
 * Student Live View - (V8.0 - PREMIUM REDESIGN)
 * - Sidebar Chat
 * - Large Main Screen
 * - Consistent with Teacher UI
 */
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$uName = e(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
$lessonId = $_GET['id'] ?? null;
$lesson = $db->fetch("
    SELECT lc.*, c.title as course_title, u.first_name, u.last_name 
    FROM live_classes lc 
    JOIN courses c ON lc.course_id = c.id 
    LEFT JOIN instructors i ON lc.instructor_id = i.id 
    LEFT JOIN users u ON i.user_id = u.id 
    WHERE lc.id = ?
", [$lessonId]);

if (!$lesson) {
    die("Dərs tapılmadı.");
}

// CHECK IF STUDENT IS ENROLLED IN THIS COURSE
if ($currentUser['role'] === 'student') {
    $courseId = (int)$lesson['course_id'];
    $tmisSubjectId = isset($lesson['tmis_subject_id']) ? (int)$lesson['tmis_subject_id'] : 0;
    
    $isEnrolled = false;

    // 1. Check local enrollments
    // student_id is TMIS ID; local ID might be used in enrollments
    $localUser = $db->fetch("SELECT id FROM users WHERE student_id = ?", [$currentUser['id']]);
    $localId = $localUser ? $localUser['id'] : 0;

    $checkLocal = $db->fetch(
        "SELECT id FROM enrollments WHERE (user_id = ? OR user_id = ?) AND course_id = ?",
        [$currentUser['id'], $localId, $courseId]
    );

    if ($checkLocal) {
        $isEnrolled = true;
    } else {
        // 2. Fallback: Check TMIS subjects list
        $studentSubjects = tmis_get('/student/subjects');
        if ($studentSubjects && is_array($studentSubjects)) {
            foreach ($studentSubjects as $subj) {
                $subjId = (int)($subj['id'] ?? 0);
                if ($subjId > 0 && ($subjId === $courseId || $subjId === $tmisSubjectId)) {
                    $isEnrolled = true;
                    break;
                }
            }
        }
    }

    // 3. Check Stream (If it's a broadcast to multiple courses)
    if (!$isEnrolled && !empty($lesson['stream_course_ids'])) {
        $targets = explode(',', $lesson['stream_course_ids']);
        // Check if any of the target courses match the student's subjects
        $studentSubjectIds = [];
        $studentSubjects = tmis_get('/student/subjects');
        if ($studentSubjects && is_array($studentSubjects)) {
            foreach ($studentSubjects as $s) $studentSubjectIds[] = (int)($s['id'] ?? 0);
        }
        
        foreach ($targets as $t) {
            if (in_array((int)trim($t), $studentSubjectIds)) {
                $isEnrolled = true;
                break;
            }
        }
    }
    
    if (!$isEnrolled) {
        die("
            <div style='background:#0f172a; color:white; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif;'>
                <div style='background:#1e293b; padding:40px; border-radius:20px; text-align:center; border:1px solid rgba(255,255,255,0.1);'>
                    <h2 style='color:#ef4444; margin-bottom:10px;'>Giriş Qadağandır</h2>
                    <p style='color:#94a3b8;'>Siz bu fənn üzrə qeydiyyatda deyilsiniz.<br>Yalnız müəllimin tələbələri dərslərə qoşula bilər.</p>
                    <a href='index.php' style='display:inline-block; margin-top:20px; background:#3b82f6; color:white; padding:10px 25px; border-radius:10px; text-decoration:none;'>Panelə Qayıt</a>
                </div>
            </div>
        ");
    }
}


require_once 'includes/header.php';
?>
<!-- Meta refresh removed - using JavaScript heartbeat instead to preserve WebRTC connections -->
<style>
    body,
    html {
        margin: 0;
        padding: 0;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
        background: #0f172a;
    }

    .control-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        width: 45px;
        height: 45px;
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
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
    }

    .control-btn.active-red {
        background: #ef4444 !important;
        border-color: #ef4444;
        box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
    }

    .control-btn.active-orange {
        background: #f59e0b !important;
        border-color: #f59e0b;
        box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);
    }

    .main-wrapper {
        padding-left: 0 !important;
    }

    #chatbot-container {
        display: none !important;
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

    #chatMessages::-webkit-scrollbar {
        width: 6px;
    }

    #chatMessages::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatMessages::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 10px;
    }

    #chatMessages::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    #chatInput:focus {
        border-color: #3b82f6 !important;
    }

    .msg-mən {
        background: #3b82f6;
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px !important;
    }

    .msg-müəllim {
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        margin-right: auto;
        border-bottom-left-radius: 4px !important;
    }

    .msg-özəl {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
        margin-right: auto;
        border-bottom-left-radius: 4px !important;
        font-weight: 600;
    }

    @keyframes fadeInRight {
        from {
            opacity: 0;
            transform: translateX(40px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes scaleUp {
        from {
            opacity: 0;
            transform: scale(0.8);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes heartbeat {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
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

    /* Redesigned TOP HEADER */
    .wb-top-bar {
        position: absolute;
        top: 20px;
        left: 20px;
        right: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 2100;
        pointer-events: none;
    }

    .wb-badge-whiteboard {
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(8px);
        padding: 8px 16px;
        border-radius: 100px;
        color: white;
        font-size: 11px;
        font-weight: 850;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .wb-badge-whiteboard .status-dot {
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        animation: blink 1s infinite;
        box-shadow: 0 0 8px #ef4444;
    }

    .wb-close-whiteboard {
        background: #1e293b;
        color: white;
        padding: 8px 20px;
        border-radius: 100px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        pointer-events: auto;
        transition: all 0.2s;
    }

    .wb-close-whiteboard:hover {
        background: #ef4444;
        border-color: #ef4444;
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
        flex-wrap: nowrap; /* No wrapping, use scrolling instead */
        justify-content: flex-start; /* Start from left to allow scrolling */
        align-items: center;
        gap: 15px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.15);
        z-index: 2010;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
        max-width: 90vw;
        overflow-x: auto;
        padding-bottom: 8px; /* Space for scrollbar */
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.3) transparent;
    }
    .wb-controls-floating::-webkit-scrollbar {
        height: 4px;
    }
    .wb-controls-floating::-webkit-scrollbar-track {
        background: transparent;
    }
    .wb-controls-floating::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }
    .wb-controls-floating::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.4);
    }
    /* Mobile optimization */
    @media (max-width: 900px) {
        .wb-controls-floating {
            gap: 10px;
            padding: 10px 16px;
            bottom: 15px;
            border-radius: 24px;
            max-width: 98vw;
        }
        .wb-group {
            gap: 6px;
        }
        .wb-tool-btn {
            width: 36px;
            height: 36px;
        }
        .wb-divider {
            height: 20px;
            margin: 0 2px;
        }
        .wb-color {
            width: 20px;
            height: 20px;
        }
        .wb-top-bar {
            top: 10px;
            left: 10px;
            right: 10px;
        }
    }

    @media (max-width: 600px) {
        .wb-controls-floating {
            gap: 6px;
            padding: 8px 12px;
        }
        .wb-tool-btn {
            width: 32px;
            height: 32px;
        }
        .wb-tool-btn i {
            width: 16px !important;
            height: 16px !important;
        }
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

    /* Responsive Studio Layout */
    .studio-container {
        display: grid;
        grid-template-columns: 1fr 380px;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    #mobilePanelBackdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 499;
        backdrop-filter: blur(2px);
    }

    #mobilePanelBackdrop.visible {
        display: block;
    }

    #videoContainer {
        width: 100%;
        max-width: 100%;
        aspect-ratio: 16/9;
        position: relative;
        background: #000;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Mobile chat toggle — hidden on desktop */
    .mobile-chat-toggle {
        display: none;
    }

    .mobile-panel-close-student {
        display: none;
    }

    @media (max-width: 1024px) {
        .mobile-chat-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .mobile-chat-toggle.active {
            background: #3b82f6;
            color: white;
        }

        .studio-container {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr;
            overflow: hidden;
            position: relative;
        }

        .studio-sidebar {
            display: none !important;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 500;
            height: 70vh !important;
            height: 70dvh !important;
            max-height: 70vh;
            border-left: none !important;
            border-top: 2px solid #334155 !important;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.5);
            overflow: hidden !important;
            min-height: unset !important;
        }

        .studio-sidebar.mobile-open {
            display: flex !important;
            animation: mobileSlideUpStudent 0.3s ease-out;
        }

        @keyframes mobileSlideUpStudent {
            from {
                transform: translateY(100%);
            }

            to {
                transform: translateY(0);
            }
        }

        .mobile-panel-close-student {
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

        #videoContainer {
            border-radius: 12px !important;
            height: auto !important;
            min-height: 200px;
        }

        .video-screen-area {
            padding: 8px !important;
        }

        #overlay h2 {
            font-size: 20px !important;
        }

        #joinBtn {
            padding: 15px 40px !important;
            font-size: 18px !important;
        }

        #localControlsWrapper {
            position: absolute !important;
            bottom: 20px !important;
            left: 50% !important;
            transform: translateX(-50%);
            width: 100% !important;
            max-width: 100vw;
            display: flex !important;
            flex-direction: column;
            align-items: center;
            padding: 0 10px;
        }

        #localPreview {
            width: 120px !important;
            height: 90px !important;
            border-radius: 12px !important;
        }

        /* Compact local control buttons */
        #localControlsWrapper > div:last-child {
            padding: 12px 20px !important;
            gap: 15px !important;
            background: rgba(15, 23, 42, 0.9) !important;
            backdrop-filter: blur(12px) !important;
            border-radius: 50px !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
        }

        #localControlsWrapper button {
            width: 44px !important;
            height: 44px !important;
        }

        #localControlsWrapper span {
            font-size: 8px !important;
            margin-top: 2px;
        }

        /* Keep teacher label away from face area on mobile/tablet */
        #mainVidLabel {
            top: auto !important;
            bottom: 118px !important;
            left: 12px !important;
            max-width: 68vw !important;
            padding: 6px 10px !important;
            font-size: 11px !important;
            gap: 6px !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            pointer-events: none;
        }
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

    @media (max-width: 600px) {
        #localPreview {
            width: 90px !important;
            height: 65px !important;
        }
        
        /* Compact action buttons in header on mobile */
        .btn-sm {
            padding: 6px 12px !important;
            font-size: 11px !important;
        }

        .main-wrapper > div:first-child h1 {
            font-size: 12px !important;
        }

        #mainVidLabel {
            bottom: 98px !important;
            max-width: 72vw !important;
            font-size: 10px !important;
            padding: 5px 9px !important;
        }
    }
</style>

    <!-- MOBILE BACKDROP -->
    <div id="mobilePanelBackdrop" onclick="closeAllMobilePanels()"></div>

    <div class="main-wrapper"
    style="margin: 0; padding: 0; background: #0f172a; height: 100vh; color: white; display: flex; flex-direction: column; overflow: hidden;">
    <!-- STUDIO HEADER -->
    <div
        style="height: 55px; min-height: 55px; padding: 0 15px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; z-index: 100; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 12px; min-width: 0; flex-shrink: 1;">
            <div
                style="width: 10px; height: 10px; min-width: 10px; background: #ef4444; border-radius: 50%; box-shadow: 0 0 10px #ef4444; animation: blink 1s infinite;">
            </div>
            <h1
                style="font-size: 14px; margin: 0; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?php echo e($lesson['course_title']); ?> <span
                    style="opacity: 0.5; margin-left: 6px; font-weight: 400;">#<?php echo $lessonId; ?></span>
            </h1>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
            <span id="connectionStatus"
                style="font-size: 11px; color: #94a3b8; font-family: monospace; display: none;">Gözlənilir...</span>
            <button class="mobile-chat-toggle" onclick="toggleStudentChat()" id="mobileChatToggle">
                💬 Çat
            </button>
            <button onclick="leaveLesson()" class="btn btn-danger btn-sm"
                style="background: #ef4444; border: none; color: white; font-weight: 700; padding: 7px 16px; border-radius: 10px; font-size: 12px; white-space: nowrap;">Ayrıl</button>
        </div>
    </div>

    <!-- MAIN STUDIO AREA -->
    <div class="studio-container">
        <!-- LEFT: VIDEO SCREEN -->
        <div class="video-screen-area"
            style="position: relative; background: #020617; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; padding: 20px; min-height: 0;">

            <div id="videoContainer">

                <!-- REMOTE VIDEO (Müəllim) -->
                <video id="remVid" autoplay playsinline webkit-playsinline muted
                    style="width: 100%; height: 100%; object-fit: contain; background: #000;"></video>

                <!-- UNMUTE FALLBACK -->
                <button id="unmuteBtn" onclick="unmuteRemoteVideo()"
                    style="display: none; position: absolute; bottom: 30px; right: 30px; background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 50px; font-weight: bold; z-index: 100; cursor: pointer; align-items: center; gap: 8px; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.4);">
                    <i data-lucide="volume-2"></i> SƏSİ AKTİVLƏŞDİR
                </button>

                <!-- STATUS ALERTS -->
                <div id="alertBox"
                    style="position: absolute; top: 30px; left: 50%; transform: translateX(-50%); background: rgba(239, 68, 68, 0.9); color: white; padding: 12px 25px; border-radius: 12px; font-size: 14px; font-weight: bold; display: none; z-index: 110; backdrop-filter: blur(5px);">
                </div>

                <!-- DURATION TIMER -->
                <div id="lessonTimer"
                    style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.7); padding: 6px 12px; border-radius: 10px; font-size: 13px; font-weight: 800; color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: none; align-items: center; gap: 8px; font-family: 'JetBrains Mono', monospace; z-index: 110;">
                    <i data-lucide="clock" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                    <span id="timerDisplay">00:00</span>
                </div>

                <div id="mainVidLabel"
                    style="position: absolute; top: 75px; left: 25px; background: rgba(0,0,0,0.5); padding: 8px 15px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #fff; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px); z-index: 110; display: flex; align-items: center; gap: 8px;">
                    <div
                        style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; box-shadow: 0 0 8px #3b82f6;">
                    </div>
                    Müəllim: <?php echo e($lesson['first_name'] . ' ' . $lesson['last_name']); ?>
                </div>

                <!-- OVERLAY (JOIN SCREEN) -->
                <div id="overlay"
                    style="position: absolute; inset: 0; background: rgba(15,23,42,0.98); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 150; backdrop-filter: blur(10px);">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h2 style="font-size: 28px; font-weight: 850; color: white; margin-bottom: 10px;">Dərsə
                            hazırsınız?</h2>
                        <p style="color: #94a3b8;">Müəllim və yoldaşlarınız sizi gözləyir.</p>
                    </div>
                    <button id="joinBtn" onclick="initStudent()"
                        style="background: #ef4444; color: white; border: none; border-radius: 50px; padding: 25px 80px; font-weight: 800; font-size: 22px; cursor: pointer; transition: all 0.3s; box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3);">
                        DƏRSƏ QOŞUL
                    </button>
                    <div id="status"
                        style="color: #64748b; margin-top: 40px; font-size: 14px; text-align: center; font-family: monospace; letter-spacing: 1px;">
                        Səhifə yüklənir...</div>
                </div>

                <!-- LIVE BADGE -->
                <div
                    style="position: absolute; top: 25px; left: 25px; background: rgba(239, 68, 68, 0.9); color: white; padding: 5px 15px; border-radius: 8px; font-size: 11px; font-weight: 900; z-index: 10; display: flex; align-items: center; gap: 8px; backdrop-filter: blur(4px);">
                    <div
                        style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: blink 1s infinite;">
                    </div>
                    CANLI
                </div>

                <!-- SYSTEM LOGS (Optional, hidden by default) -->
                <div id="debugLogWrapper" style="display: none; width: 100%; max-width: 1100px; margin-top: 20px;">
                    <div id="debugLog"
                        style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; height: 100px; overflow-y: auto; font-family: monospace; font-size: 11px; color: #64748b;">
                    </div>
                </div>
            </div>

            <!-- LOCAL PREVIEW & CONTROLS -->
            <div id="localControlsWrapper"
                style="position: absolute; bottom: 30px; left: 30px; z-index: 120; display: none;">
                <div style="position: relative; margin-bottom: 15px;">
                    <video id="localPreview" muted autoplay playsinline onclick="swapVideoSources()"
                        title="Görüntüləri dəyişmək üçün klikləyin"
                        style="width: 180px; height: 120px; object-fit: cover; border-radius: 16px; border: 2px solid rgba(255,255,255,0.2); background: #000; box-shadow: 0 10px 20px rgba(0,0,0,0.5); cursor: pointer; transition: transform 0.2s;"
                        onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    </video>
                    <div id="miniVidLabel"
                        style="position: absolute; bottom: 10px; left: 10px; background: rgba(0,0,0,0.6); padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 700; color: #fff; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 5px; pointer-events: none;">
                        <div style="width: 5px; height: 5px; background: #fff; border-radius: 50%; opacity: 0.8;">
                        </div>
                        Mən
                    </div>
                </div>
                <div
                    style="display: flex; gap: 20px; justify-content: center; background: rgba(15,23,42,0.6); padding: 15px 25px; border-radius: 24px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                        <button id="btnMic" onclick="toggleMicRequest()" class="control-btn active-red" title="Söz İstə">
                            <i data-lucide="hand" style="width: 20px; height: 20px;"></i>
                        </button>
                        <span
                            style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Söz
                            İstə</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                        <button id="btnCam" onclick="toggleCam()" class="control-btn" title="Kamera">
                            <i data-lucide="video" style="width: 20px; height: 20px;"></i>
                        </button>
                        <span
                            style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Kamera</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                        <button id="btnScreen" onclick="toggleScreenShare()" class="control-btn" title="Ekran Paylaş">
                            <i data-lucide="monitor" style="width: 20px; height: 20px;"></i>
                        </button>
                        <span
                            style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Ekran</span>
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                        <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="control-btn" title="Lövhə">
                            <i data-lucide="pen-tool" style="width: 20px; height: 20px;"></i>
                        </button>
                        <span
                            style="font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Lövhə</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: SIDEBAR (CHAT & PARTICIPANTS) -->
        <div class="studio-sidebar" id="studentChatPanel"
            style="background: #1e293b; border-left: 2px solid #334155; display: flex; flex-direction: column; overflow: hidden; padding: 25px; position: relative;">
            <button class="mobile-panel-close-student" onclick="toggleStudentChat()">&times;</button>

            <!-- CHAT AREA -->
            <div style="flex: 1; display: flex; flex-direction: column; min-height: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3
                        style="font-size: 15px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="message-square" style="width: 18px; height: 18px; color: #3b82f6;"></i>
                        Dərs Çatı
                    </h3>
                    <button id="btnPrivateTeacher" onclick="togglePrivateTeacher()"
                        style="background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; color: #3b82f6; font-size: 10px; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-weight: 700;">🔒
                        Müəllimə Özəl</button>
                </div>

                <!-- PRIVATE CHAT TARGET INDICATOR -->
                <div id="privateTargetIndicator"
                    style="display: none; background: rgba(59, 130, 246, 0.2); padding: 5px 12px; border-radius: 8px; font-size: 11px; margin-bottom: 15px; align-items: center; justify-content: space-between;">
                    <span style="color: #3b82f6; font-weight: 700;">🔒 Müəllimə ÖZƏL yazılır...</span>
                    <button onclick="clearPrivateTarget()"
                        style="background: none; border: none; color: #ef4444; font-size: 14px; cursor: pointer; padding: 0;">&times;</button>
                </div>

                <div id="chatMessages"
                    style="flex: 1; overflow-y: auto; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 16px; margin-bottom: 20px; scroll-behavior: smooth; border: 1px solid rgba(255,255,255,0.05);">
                    <div
                        style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.3; text-align: center; padding: 0 20px;">
                        <i data-lucide="messages-square" style="width: 48px; height: 48px; margin-bottom: 10px;"></i>
                        <p style="font-size: 13px;">Hələ ki, mesaj yoxdur. Sualınız varsa yazın!</p>
                    </div>
                </div>

                <div
                    style="display: flex; flex-direction: column; gap: 10px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="chatInput" placeholder="Mesaj yazın..."
                            style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid #334155; border-radius: 12px; padding: 12px 15px; color: white; font-size: 14px; outline: none; transition: border-color 0.2s;"
                            onkeypress="if(event.key==='Enter') sendChatMessage()">
                        <button onclick="sendChatMessage()"
                            style="background: #3b82f6; border: none; border-radius: 12px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s;">
                            <i data-lucide="send"
                                style="width: 18px; height: 18px; color: white; margin-left: 2px;"></i>
                        </button>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label for="fileInput"
                            style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #94a3b8; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: background 0.2s;">
                            <i data-lucide="paperclip" style="width: 14px; height: 14px;"></i> Fayl Əlavə Et
                        </label>
                        <input type="file" id="fileInput" style="display: none;" onchange="handleFileSelect(this)">
                    </div>
                </div>
            </div>

            <!-- FOOTER INFO -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #334155;">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: #64748b;">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                    Uçtan-uca şifrələnmiş bağlantı
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    #chatMessages::-webkit-scrollbar {
        width: 6px;
    }

    #chatMessages::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatMessages::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 10px;
    }

    #chatMessages::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    #chatInput:focus {
        border-color: #3b82f6 !important;
    }

    .msg-mən {
        background: #3b82f6;
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 4px !important;
    }

    .msg-müəllim {
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        margin-right: auto;
        border-bottom-left-radius: 4px !important;
    }

    #localPreview {
        transform: scaleX(-1);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
<script>
    // === Mobile Panels Management ===
    function toggleStudentChat() {
        const panel = document.getElementById('studentChatPanel');
        const btn = document.getElementById('mobileChatToggle');
        const backdrop = document.getElementById('mobilePanelBackdrop');
        if (panel) {
            const isOpen = panel.classList.toggle('mobile-open');
            if (btn) btn.classList.toggle('active', isOpen);
            if (backdrop) backdrop.classList.toggle('visible', isOpen);
        }
    }

    function closeAllMobilePanels() {
        const chatPanel = document.getElementById('studentChatPanel');
        const backdrop = document.getElementById('mobilePanelBackdrop');
        const chatBtn = document.getElementById('mobileChatToggle');

        if (chatPanel) chatPanel.classList.remove('mobile-open');
        if (backdrop) backdrop.classList.remove('visible');
        if (chatBtn) chatBtn.classList.remove('active');
    }

    const lID = "<?php echo $lessonId; ?>";
    const uName = "<?php echo e($uName); ?>";
    const uID = "<?php echo $currentUser['id']; ?>";
    let room = null, discoveryInterval, localMediaStream = null,
        dataConn = null;
    let activeTeacherCall = null;
    let freezeWatchdogInterval = null;
    let lastRemoteTime = 0;
    let lastFreezeRecoveryAt = 0;
    let signalReady = false;
    let isConnecting = false;
    let isDummyStream = false;

    // Media States
    let isScreenSharing = false;
    let camVideoTrack = null;
    let screenStream = null;

    // Mic Mgmt
    let micApproved = false;
    let micRequested = false;

    // Screen Share Mgmt
    let screenRequested = false;
    let screenApproved = false;

    // Whiteboard Mgmt
    let wbRequested = false;
    let wbApproved = false;

    let isViewSwapped = false;
    let teacherStream = null;
    let seenMessages = new Set();

    window.initStudent = async function() {
        if (window.isInitializing) return;
        window.isInitializing = true;
        
        console.log("🚀 initStudent triggered");
        const btn = document.getElementById('joinBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Qoşulur...';
        }
        const overlay = document.getElementById('overlay');
        if (overlay) overlay.style.display = 'none';

        try {
            try {
                // Step 1: Try both Audio and Video
                localMediaStream = await getMediaStream(true, true);
                isDummyStream = false;
            } catch (err) {
                try {
                    // Step 2: Try Video only
                    localMediaStream = await getMediaStream(false, true);
                    isDummyStream = false;
                    DBG("⚠️ Mikrofon tapılmadı, yalnız kamera ilə davam edilir.");
                } catch (err2) {
                    // No video available. Try to at least get real audio or go full dummy.
                    try {
                        const aOnly = await getMediaStream(true, false);
                        localMediaStream = aOnly;
                        isDummyStream = false;
                        DBG("⚠️ Kamera tapılmadı, yalnız səs ilə davam edilir.");
                    } catch (err3) {
                        localMediaStream = createDummyStream();
                        isDummyStream = true;
                        DBG("🔴 Heç bir media cihazı tapılmadı. Placeholder istifadə edilir.");
                    }
                }
            }

            // Preview local
            const preview = document.getElementById('localPreview');
            if (preview) {
                preview.srcObject = localMediaStream;
                preview.play().catch(e => console.warn("Local preview play failed:", e));
                
                const wrapper = document.getElementById('localControlsWrapper');
                if (wrapper) wrapper.style.display = 'block';
                
                updateCamUI(!isDummyStream);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            // Connect to LiveKit
            await startLiveKit();

        } catch (fatal) {
            console.error("Fatal initialization error:", fatal);
            showAlert("Xəta baş verdi: " + fatal.message, "error");
        }
    };

    window.startLiveKit = async function() {
        if (isConnecting) return;
        isConnecting = true;
        
        try {
            DBG("🔑 Token alınır...");
            const response = await fetch(`../api/livekit_token.php?room=${lID}`, { credentials: 'include' });
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || "Token error");
            }

            room = new LivekitClient.Room({
                adaptiveStream: true,
                dynacast: true,
                publishDefaults: {
                    simulcast: true,
                }
            });

            setupRoomEvents();

            DBG("🌐 Otağa qoşulur...");
            await room.connect(data.serverUrl, data.token);
            DBG("✅ Qoşuldu!");

            signalReady = true;
            isConnecting = false;

            // Publish local media
            await publishMedia();
            
            // Start components
            startLessonTimer();
            startHeartbeat();
            initWBCanvas();

        } catch (e) {
            isConnecting = false;
            console.error("LiveKit connection failed:", e);
            DBG("❌ Qoşulma alınmadı: " + e.message);
            showAlert("Canlı yayıma qoşulmaq mümkün olmadı.", "error");
        }
    };

    // --- LOGGING & UI ---
    const DBG = (m) => {
        const d = document.getElementById('debugLog');
        if (d) d.innerHTML += `<div>[${new Date().toLocaleTimeString()}] ${m}</div>`;
        const s = document.getElementById('connectionStatus');
        if (s) s.innerText = m.length > 20 ? m.substring(0, 17) + "..." : m;
        console.log("LOG:", m);
    };

    function LOG(msg, color = '#64748b') {
        const box = document.getElementById('debugLog'); // Using debugLog for consistency
        if (box) {
            const div = document.createElement('div');
            div.style.color = color;
            div.style.marginBottom = '5px';
            div.innerHTML = `<span style="opacity:0.5; font-size:10px;">[${new Date().toLocaleTimeString()}]</span> ${msg}`;
            box.appendChild(div);
            box.scrollTop = box.scrollHeight;
        }
    }

    let lessonStartTime = Date.now();
    <?php if ($lesson && isset($lesson['started_at']) && $lesson['started_at']): ?>
        lessonStartTime = <?php echo strtotime($lesson['started_at']) * 1000; ?>;
    <?php endif; ?>

    function startLessonTimer() {
        const timerEl = document.getElementById('lessonTimer');
        const displayEl = document.getElementById('timerDisplay');
        if (!timerEl || !displayEl) return;
        
        timerEl.style.display = 'flex';
        const MAX_DURATION = 3 * 3600; // 3 hours

        setInterval(() => {
            const now = Date.now();
            const totalSeconds = Math.floor((now - lessonStartTime) / 1000);
            if (totalSeconds < 0) return;

            // 3-HOUR LIMIT CHECK
            if (totalSeconds >= MAX_DURATION) {
                if (localMediaStream) localMediaStream.getTracks().forEach(t => t.stop());
                showAlert("⌛ Maksimum dərs müddəti (3 saat) tamamlandı. Dərs bitmiş sayılır.", "error");
                setTimeout(() => {
                    window.location.href = 'live-classes.php';
                }, 3500);
                return;
            }

            const h = Math.floor(totalSeconds / 3600);
            const m = Math.floor((totalSeconds % 3600) / 60);
            const s = totalSeconds % 60;

            let timeStr = "";
            if (h > 0) timeStr += (h < 10 ? '0' + h : h) + ':';
            timeStr += (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);

            displayEl.innerText = timeStr;
        }, 1000);
    }

    let alertTimeout = null;

    function showAlert(msg, type = 'info', autoHide = true) {
        const a = document.getElementById('alertBox');
        if (!a) return;

        if (alertTimeout) clearTimeout(alertTimeout);

        const colors = {
            'success': '#10b981',
            'error': '#ef4444',
            'info': 'rgba(0,0,0,0.8)'
        };
        a.style.background = colors[type] || colors.info;
        a.innerText = msg;
        a.style.display = 'block';

        if (autoHide) {
            alertTimeout = setTimeout(() => a.style.display = 'none', 4000);
        }
    }

    // --- CONTROL FUNCTIONS ---
    async function toggleMicRequest() {
        if (!room || room.state !== 'connected') return;

        if (micApproved) {
            setMic(!localMediaStream.getAudioTracks()[0].enabled);
            return;
        }

        if (micRequested) {
            showAlert("Sorğu artıq göndərilib.");
            return;
        }

        micRequested = true;
        await broadcastData({
            type: 'mic_request',
            sender: uName
        });
        const btn = document.getElementById('btnMic');
        if (btn) btn.classList.add('active-orange');
        showAlert("Mikrofon üçün icazə istənildi...");
        LOG("🎤 Mikrofon üçün icazə istənildi.", "#f59e0b");
    }

    async function setMic(enabled) {
        if (localMediaStream) {
            localMediaStream.getAudioTracks().forEach(t => t.enabled = enabled);
        }
        
        if (room && room.localParticipant) {
            try {
                await room.localParticipant.setMicrophoneEnabled(enabled);
                DBG(`🎤 Mikrofon statusu serverdə yeniləndi: ${enabled ? 'Aktiv' : 'Səssiz'}`);
            } catch (e) {
                console.error("Mic toggle error:", e);
            }
        }

        const btn = document.getElementById('btnMic');
        if (btn) {
            if (enabled) {
                btn.classList.remove('active-red', 'active-orange');
                btn.classList.add('active-green');
                btn.innerHTML = '<i data-lucide="mic" style="width: 20px; height: 20px;"></i>';
            } else {
                btn.classList.remove('active-green', 'active-orange');
                btn.classList.add('active-red');
                btn.innerHTML = '<i data-lucide="mic-off" style="width: 20px; height: 20px;"></i>';
            }
            if (window.lucide) lucide.createIcons();
        }
    }

    async function toggleCam() {
        if (!localMediaStream) return;

        // If we are on dummy stream, try to get real camera instead of just toggling
        if (isDummyStream) {
            showAlert("Kamera yenidən yoxlanılır...");
            try {
                let stream;
                try {
                    stream = await getMediaStream(true, true);
                } catch (e) {
                    stream = await getMediaStream(false, true);
                    DBG("Kamera aktivləşdi (səssiz).");
                }

                isDummyStream = false;

                const newVideoTrack = stream.getVideoTracks()[0];
                const oldVideoTrack = localMediaStream.getVideoTracks()[0];

                if (oldVideoTrack) localMediaStream.removeTrack(oldVideoTrack);
                localMediaStream.addTrack(newVideoTrack);

                if (stream.getAudioTracks().length > 0) {
                    const newAudioTrack = stream.getAudioTracks()[0];
                    const oldAudioTrack = localMediaStream.getAudioTracks()[0];
                    if (oldAudioTrack) localMediaStream.removeTrack(oldAudioTrack);
                    localMediaStream.addTrack(newAudioTrack);
                    newAudioTrack.enabled = false;
                }

                document.getElementById('localPreview').srcObject = localMediaStream;

                if (room && room.state === 'connected') {
                    DBG("📡 Yeni kamera yayımı müəllimə göndərilir...");
                    
                    const publications = room.localParticipant.getTrackPublications();
                    for (const pub of publications) {
                        if (pub.track && pub.track.kind === 'video' && pub.source === LivekitClient.Track.Source.Camera) {
                            await room.localParticipant.unpublishTrack(pub.track);
                        }
                    }

                    const localTrack = new LivekitClient.LocalVideoTrack(newVideoTrack);
                    await room.localParticipant.publishTrack(localTrack, { source: LivekitClient.Track.Source.Camera });
                }

                newVideoTrack.enabled = true;
                updateCamUI(true);
                showAlert("Kamera uğurla aktivləşdirildi! ✅", "success");
                return;
            } catch (err) {
                console.error("Camera acquisition failed:", err);
                if (err.name === 'NotAllowedError') {
                    showAlert("İcazə rədd edildi! Brauzer ayarlarından kameraya icazə verin.", "error");
                } else if (err.name === 'NotFoundError') {
                    showAlert("Kamera cihazı tapılmadı!", "error");
                } else if (err.name === 'NotReadableError') {
                    showAlert("Kamera başqa proqram tərəfindən istifadə edilir!", "error");
                } else {
                    showAlert("Kamera xətası: " + err.name, "error");
                }
                return;
            }
        }

        const videoTrack = localMediaStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            updateCamUI(videoTrack.enabled);
            
            if (room && room.localParticipant) {
                try {
                    room.localParticipant.setCameraEnabled(videoTrack.enabled);
                    DBG(`📷 Kamera statusu serverdə yeniləndi: ${videoTrack.enabled ? 'Aktiv' : 'Bağlı'}`);
                } catch (e) {
                    console.error("Cam toggle error:", e);
                }
            }
        }
    }

    function updateCamUI(enabled) {
        const btn = document.getElementById('btnCam');
        if (!btn) return;

        if (enabled) {
            btn.classList.add('active-green');
            btn.innerHTML = '<i data-lucide="video" style="width: 20px; height: 20px;"></i>';
            LOG("✅ Kamera aktivdir.", "#10b981");
        } else {
            btn.classList.remove('active-green');
            btn.innerHTML = '<i data-lucide="video-off" style="width: 20px; height: 20px;"></i>';
            LOG("⏹️ Kamera söndürüldü.", "#f59e0b");
        }
        if (window.lucide) lucide.createIcons();
    }

    // --- CHAT LOGIC ---
    function appendChatMessage(sender, msg, type = 'müəllim') {
        const box = document.getElementById('chatMessages');
        if (box.querySelector('p')) box.innerHTML = '';

        const isSelf = type === 'mən';
        const msgHtml = `
            <div style="display: flex; flex-direction: column; margin-bottom: 15px; ${isSelf ? 'align-items: flex-end' : 'align-items: flex-start'}">
                <div style="font-size: 11px; color: #94a3b8; font-weight: 700; margin-bottom: 4px; padding: 0 12px;">${sender}</div>
                <div class="msg-${type}" style="max-width: 80%; padding: 10px 15px; border-radius: 16px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); line-height: 1.4;">
                    ${msg}
                </div>
                <div style="font-size: 10px; color: #475569; margin-top: 4px; padding: 0 12px;">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
            </div>
        `;
        box.innerHTML += msgHtml;
        box.scrollTop = box.scrollHeight;
    }

    function appendFileMessage(sender, fileName, fileData, type = 'müəllim') {
        const box = document.getElementById('chatMessages');
        if (box.querySelector('p')) box.innerHTML = '';

        const isSelf = type === 'mən';
        const linkHTML = `<a href="${fileData}" target="_blank" download="${fileName}" style="color: ${isSelf ? '#fff' : '#3b82f6'}; text-decoration: underline; font-weight: 700; display: flex; align-items: center; gap: 8px;"><i data-lucide="file-text" style="width:16px;"></i> ${fileName}</a>`;

        const msgHtml = `
            <div style="display: flex; flex-direction: column; margin-bottom: 15px; ${isSelf ? 'align-items: flex-end' : 'align-items: flex-start'}">
                <div style="font-size: 11px; color: #94a3b8; font-weight: 700; margin-bottom: 4px; padding: 0 12px;">${sender}</div>
                <div class="msg-${type}" style="max-width: 80%; padding: 10px 15px; border-radius: 16px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    ${linkHTML}
                </div>
            </div>
        `;
        box.innerHTML += msgHtml;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        box.scrollTop = box.scrollHeight;
    }

    let isPrivateMode = false;

    function togglePrivateTeacher() {
        isPrivateMode = true;
        document.getElementById('privateTargetIndicator').style.display = 'flex';
        document.getElementById('chatInput').placeholder = "Müəllimə özəl mesaj...";
        document.getElementById('chatInput').focus();
    }

    function clearPrivateTarget() {
        isPrivateMode = false;
        document.getElementById('privateTargetIndicator').style.display = 'none';
        document.getElementById('chatInput').placeholder = "Mesaj yazın...";
    }

    async function sendChatMessage() {
        if (window.isSendingMessage) return;
        
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (msg === '') return;

        window.isSendingMessage = true;

        const msgId = Date.now() + '-' + Math.random();
        const msgObj = {
            type: 'chat',
            message: msg,
            sender: uName,
            msgId: msgId
        };
        seenMessages.add(msgId);
        await broadcastData(msgObj);
        appendChatMessage('Mən', msg, 'mən');
        input.value = '';
        setTimeout(() => { window.isSendingMessage = false; }, 500);
    }

    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        // 5MB Limit Check
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            showAlert("Fayl çox böyükdür (Max 5MB)!", "error");
            alert("Xəta: Seçdiyiniz faylın ölçüsü 5MB-dan çoxdur. Zəhmət olmasa daha kiçik fayl seçin.");
            input.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        showAlert(`Gözləyin: 0% yüklənir...`, "info", false);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                showAlert(`Yüklənir: ${percent}%...`, "info", false);
            }
        };

        xhr.onload = async () => {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showAlert("Fayl yükləndi! ✅", "success", true);
                    await broadcastData({
                        type: 'file',
                        fileData: data.url,
                        fileName: data.fileName,
                        sender: uName
                    });
                    appendFileMessage("Mən", data.fileName, data.url, "mən");
                } else {
                    showAlert("Yükləmə alınmadı ❌", "error", true);
                    alert("Xəta: " + data.message);
                }
            } catch (e) {
                showAlert("Server xətası ❌", "error", true);
            }
        };

        xhr.onerror = () => {
            showAlert("Bağlantı xətası ❌", "error", true);
        };

        xhr.open('POST', '../api/upload_chat_file.php');
        xhr.send(formData);
        input.value = '';
    }


    // --- WEBRTC LOGIC ---
    async function getMediaStream(withAudio = true, withVideo = true) {
        const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        const constraints = {};
        if (withAudio) constraints.audio = true;
        if (withVideo) constraints.video = isMobile ? {
            facingMode: 'user'
        } : true;

        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            console.error(`Media access failed (A:${withAudio}, V:${withVideo}):`, err.name, err.message);

            // If permission denied, request via alert
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                showAlert("Kamera/Mikrofon icazəsi rədd edildi! URL sətrindən icazə verin.", "error");
                throw err;
            }

            // If camera is busy (NotReadableError) AND we want video, try generic fallbacks
            if (withVideo && (err.name === 'NotReadableError' || err.name === 'TrackStartError')) {
                console.warn("Camera busy/locked. Trying to find alternative camera...");
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(d => d.kind === 'videoinput');
                    // If we have multiple cameras, try the others one by one
                    if (videoDevices.length > 1) {
                        for (const device of videoDevices) {
                            // Try this specific device
                            console.log("Trying alternative device:", device.label);
                            const altConstraints = {
                                ...constraints
                            }; // copy
                            altConstraints.video = {
                                deviceId: {
                                    exact: device.deviceId
                                }
                            };
                            try {
                                const s = await navigator.mediaDevices.getUserMedia(altConstraints);
                                console.log("Success with alternative device:", device.label);
                                return s;
                            } catch (e2) {
                                console.warn("Failed with alternative device:", e2);
                            }
                        }
                    }
                } catch (enumErr) {
                    console.error("Device enumeration failed:", enumErr);
                }
            }

            throw err;
        }
    }

    function createDummyStream(realAudioTrack = null) {
        const canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = 480;
        const ctx = canvas.getContext('2d');

        ctx.fillStyle = "#111827";
        ctx.fillRect(0, 0, 640, 480);
        const grad = ctx.createRadialGradient(320, 240, 50, 320, 240, 300);
        grad.addColorStop(0, "#1f2937");
        grad.addColorStop(1, "#111827");
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, 640, 480);

        ctx.fillStyle = "#ef4444";
        ctx.font = "bold 30px sans-serif";
        ctx.textAlign = "center";
        ctx.fillText("📷 Kamera Tapılmadı / Məşğul", 320, 220);

        ctx.fillStyle = "#94a3b8";
        ctx.font = "14px sans-serif";
        ctx.fillText("Digər tabda (Müəllim) kamera açıqdırsa, onu bağlayın.", 320, 260);

        ctx.fillStyle = "rgba(255,255,255,0.7)";
        ctx.font = "italic 16px sans-serif";
        ctx.fillText("Aşağıdakı kamera düyməsi ilə yenidən yoxlaya bilərsiniz", 320, 300);

        ctx.fillStyle = "#3b82f6";
        ctx.font = "600 18px sans-serif";
        ctx.fillText("Tələbə: <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>", 320, 350);

        const vidStream = canvas.captureStream(10);
        const vTrack = vidStream.getVideoTracks()[0];

        let aTrack = realAudioTrack;
        if (!aTrack) {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const dst = oscillator.connect(audioCtx.createMediaStreamDestination());
                oscillator.start();
                aTrack = dst.stream.getAudioTracks()[0];
            } catch (e) {
                console.error("Dummy audio failed", e);
            }
        }

        localMediaStream = new MediaStream();
        if (vTrack) localMediaStream.addTrack(vTrack);
        if (aTrack) localMediaStream.addTrack(aTrack);

        isDummyStream = true;
        return localMediaStream;
    }



    function setupRoomEvents() {
        room
            .on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
                if (track.kind === 'video') {
                    if (publication.source === LivekitClient.Track.Source.ScreenShare) {
                        DBG("🖥️ Ekran paylaşımı gəldi (İştirakçı)");
                        // If it's a student sharing, we can switch. But teacher uses Camera track.
                    }
                    
                    const targetVidId = isViewSwapped ? 'localPreview' : 'remVid';
                    const vid = document.getElementById(targetVidId);
                    
                    if (vid) {
                        track.attach(vid);
                        const stream = track.mediaStream || new MediaStream([track.mediaStreamTrack]);
                        showRemoteVideo(stream);
                    }
                }
                if (track.kind === 'audio') {
                    track.attach();
                }
            })
            .on('dataReceived', (payload, participant) => {
                const decoder = new TextDecoder();
                try {
                    const d = JSON.parse(decoder.decode(payload));
                    console.log("📥 Data Received:", d, "from", participant?.identity);
                    handleIncomingData(d, participant);
                } catch (e) {
                    console.error("Data decode error", e);
                }
            })
            .on(LivekitClient.RoomEvent.Disconnected, () => {
                DBG("❌ Server ilə bağlantı kəsildi");
                setTimeout(startLiveKit, 3000);
            });
    }

    async function publishMedia() {
        if (!room || room.state !== 'connected') return;
        try {
            const tracks = localMediaStream.getTracks();
            DBG("🎥 LiveKit-ə yayımlanan treklər: " + tracks.map(t => t.kind).join(', '));
            for (const track of tracks) {
                track.enabled = true; // Ensure active
                if (track.kind === 'video') {
                    const localVideo = new LivekitClient.LocalVideoTrack(track);
                    await room.localParticipant.publishTrack(localVideo, { 
                        source: LivekitClient.Track.Source.Camera,
                        videoEncoding: {
                            maxBitrate: 400000,
                            maxFramerate: 15
                        }
                    });
                } else if (track.kind === 'audio') {
                    const localAudio = new LivekitClient.LocalAudioTrack(track);
                    await room.localParticipant.publishTrack(localAudio);
                }
            }
            DBG("🎥 Media yayımlanır.");
        } catch (e) {
            console.error("Publish error", e);
        }
    }

    async function broadcastData(data) {
        if (!room || room.state !== 'connected') return;
        
        // Explicitly include sender info for robust signaling
        data.sender = uName;
        data.peerId = room.localParticipant.identity;
        
        const encoder = new TextEncoder();
        const payload = encoder.encode(JSON.stringify(data));
        try {
            await room.localParticipant.publishData(payload, { reliable: true });
        } catch (e) {
            console.error("Broadcast error:", e);
        }
    }

    function handleIncomingData(d, participant) {
        // Ignore self-messages and already seen messages to prevent duplication
        if (d.msgId && seenMessages.has(d.msgId)) return;
        if (d.msgId) seenMessages.add(d.msgId);
        
        if (!participant || participant.identity === room.localParticipant.identity) return;

        const sender = d.sender || participant.name || participant.identity;
        if (d.type === 'chat') {
            const type = d.isPrivate ? 'özəl' : (sender.includes('Müəllim') ? 'müəllim' : 'tələbə');
            appendChatMessage(sender, d.message, type);
        } else if (d.type === 'file') {
            appendFileMessage(sender, d.fileName, d.fileData);
        } else if (d.type === 'mic_approved') {
            micApproved = true;
            micRequested = false;
            setMic(true);
            showAlert("Söz verildi! 🎤");
            const btn = document.getElementById('btnMic');
            if (btn) {
                btn.innerHTML = '<i data-lucide="mic" style="width: 20px; height: 20px;"></i>';
                btn.classList.remove('active-red');
                btn.classList.add('active-green');
                btn.title = "Mikrofonu Bağla";
            }
            if (window.lucide) lucide.createIcons();
        } else if (d.type === 'teacher_screen_share_started') {
            DBG("🖥️ Müəllim ekran paylaşımı başladı (Signal)");
            if (isViewSwapped) swapVideoSources();
        } else if (d.type === 'teacher_screen_share_stopped') {
            DBG("🖥️ Müəllim ekran paylaşımı bitdi (Signal)");
            showAlert("Müəllim ekran paylaşımını dayandırdı.");
        }
        
        if (d.type === 'mute_force' || d.type === 'mic_rejected') {
            micApproved = false;
            micRequested = false;
            setMic(false);
            const btn = document.getElementById('btnMic');
            if (btn) {
                btn.innerHTML = '<i data-lucide="hand" style="width: 20px; height: 20px;"></i>';
                btn.classList.remove('active-green');
                btn.classList.add('active-red');
                btn.title = "Söz İstə";
            }
            if (window.lucide) lucide.createIcons();
        } else if (d.type === 'whiteboard_approved') {
            startActualWhiteboard();
        } else if (d.type === 'whiteboard_rejected') {
            showAlert("Müəllim lövhə istəyini rədd etdi.", "error");
            wbRequested = false; // Reset request state
            wbApproved = false;
            const btn = document.getElementById('btnWhiteboard');
            if (btn) btn.classList.remove('active-orange');
        } else if (d.type === 'whiteboard_force_stop') {
            if (isWhiteboardActive) stopWhiteboard();
        } else if (d.type === 'screen_share_force_stop') {
            if (isScreenSharing) stopScreenShare();
        }
        if (d.type === 'notification') {
            showAlert(d.message, 'info');
        } else if (d.type === 'screen_share_approved') {
            screenApproved = true;
            screenRequested = false;
            startActualScreenShare();
        } else if (d.type === 'screen_share_rejected') {
            showAlert("Müəllim ekran paylaşımı istəyini rədd etdi.", "error");
            screenRequested = false; // Reset request state
            screenApproved = false;
            const btn = document.getElementById('btnScreen');
            if (btn) btn.classList.remove('active-orange');
        } else if (d.type === 'kick_user') {
            window.location.href = 'live-classes.php';
        } else if (d.type === 'lesson_ended') {
            showAlert("Dərs bitdi.");
            setTimeout(() => window.location.href = 'live-classes.php', 3000);
        }
    }


    function showRemoteVideo(stream) {
        teacherStream = stream;
        const targetVidId = (typeof isViewSwapped !== 'undefined' && isViewSwapped) ? 'localPreview' : 'remVid';
        const vid = document.getElementById(targetVidId);
        if (!vid) return;

        const vTracks = stream.getVideoTracks().length;
        const aTracks = stream.getAudioTracks().length;

        DBG(`📹 Müəllim yayımı: ${vTracks} video, ${aTracks} audio track tapıldı.`);

        if (vTracks === 0) {
            DBG("⚠️ XƏBƏRDARLIQ: Müəllimdən görüntü gəlmir!");
        }

        // --- iOS & Safari Autoplay Robustness ---
        // 1. Force inline playback attributes via JS
        vid.setAttribute('playsinline', '');
        vid.setAttribute('webkit-playsinline', '');
        vid.setAttribute('autoplay', '');

        // 2. Force muted = true BEFORE assigning srcObject
        vid.muted = true;

        // 3. Assign stream directly — do NOT call vid.load()!
        //    On iOS Safari, .load() on a MediaStream resets the source and kills playback.
        vid.srcObject = stream;

        // 4. Play function with unmute attempt
        const tryPlay = () => {
            vid.play().then(() => {
                DBG("▶️ Video yayımı başladı");
                // Attempt to unmute (works after user gesture like 'Join')
                setTimeout(() => {
                    vid.muted = false;
                }, 100);
            }).catch(e => {
                DBG("🔇 Otomatik səs bloklandı, 'Unmute' düyməsini istifadə edin.");
                vid.muted = true;
                vid.play().catch(() => { });
                const unmuteBtn = document.getElementById('unmuteBtn');
                if (unmuteBtn) unmuteBtn.style.display = 'flex';
            });
        };

        // 5. iOS: auto-retry on pause events (power saving, tab switch, Safari restrictions)
        vid.onpause = () => {
            if (vid.srcObject && vid.srcObject.active) {
                DBG("⚠️ iOS video dayandı, yenidən başladılır...");
                setTimeout(tryPlay, 300);
            }
        };

        // 6. Play when metadata is ready, or immediately if already loaded
        if (vid.readyState >= 2) {
            tryPlay();
        } else {
            vid.onloadedmetadata = tryPlay;
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function unmuteRemoteVideo() {
        const vid = document.getElementById('remVid');
        vid.muted = false;
        vid.play().then(() => document.getElementById('unmuteBtn').style.display = 'none');
    }

    function swapVideoSources() {
        const mainVid = document.getElementById('remVid');
        const miniVid = document.getElementById('localPreview');
        const mainLabel = document.getElementById('mainVidLabel');
        const miniLabel = document.getElementById('miniVidLabel');

        if (!mainVid || !miniVid) return;

        isViewSwapped = !isViewSwapped;

        if (isViewSwapped) {
            // Student becomes LARGE, Teacher becomes SMALL
            mainVid.srcObject = localMediaStream;
            miniVid.srcObject = teacherStream;

            mainLabel.innerHTML = '<div style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></div>Mən';
            miniLabel.innerHTML = '<div style="width: 5px; height: 5px; background: #3b82f6; border-radius: 50%; opacity: 0.8;"></div>Müəllim';

            mainVid.muted = true; // No self-echo
            miniVid.muted = false; // Hear teacher in small window

            // Adjust styles
            mainVid.style.objectFit = 'cover';
            miniVid.style.objectFit = 'contain';

            LOG("🔄 Bakış bucağı dəyişdirildi: Öz görüntünüz böyüdü.", "#10b981");
        } else {
            // Restore: Teacher is LARGE, Student is SMALL
            mainVid.srcObject = teacherStream;
            miniVid.srcObject = localMediaStream;

            mainLabel.innerHTML = '<div style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; box-shadow: 0 0 8px #3b82f6;"></div>Müəllim: <?php echo e($lesson["first_name"] . " " . $lesson["last_name"]); ?>';
            miniLabel.innerHTML = '<div style="width: 5px; height: 5px; background: #fff; border-radius: 50%; opacity: 0.8;"></div>Mən';

            mainVid.muted = false;
            miniVid.muted = true;

            mainVid.style.objectFit = 'contain';
            miniVid.style.objectFit = 'cover';

            LOG("🔄 Bakış bucağı qaytarıldı: Müəllim görüntüsü böyüdü.", "#3b82f6");
        }

        if (window.lucide) lucide.createIcons();
    }

    // --- OTHER UI LOGIC ---
    async function toggleScreenShare() {
        if (!room || room.state !== 'connected') return;
        const btn = document.getElementById('btnScreen');

        if (isScreenSharing) {
            await stopScreenShare();
        } else if (screenRequested) {
            showAlert("Sorğu artıq göndərilib, gözləyin...");
        } else {
            // Send request to teacher
            screenRequested = true;
            await broadcastData({
                type: 'screen_share_request',
                sender: uName
            });
            if (btn) btn.classList.add('active-orange');
            showAlert("Ekran paylaşımı üçün sorğu göndərildi...");
            LOG("🖥️ Ekran paylaşımı sorğusu göndərildi.", "#f59e0b");
        }
    }


    async function startActualScreenShare() {
        DBG("🖥️ Ekran paylaşımı başladılır (getDisplayMedia çağırılır)...");
        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    displaySurface: 'monitor',
                    logicalSurface: true,
                    cursor: 'always',
                    width: { ideal: 1920, max: 1920 },
                    height: { ideal: 1080, max: 1080 },
                    frameRate: { ideal: 30, max: 60 }
                },
                audio: false
            });
            const screenTrack = screenStream.getVideoTracks()[0];
            camVideoTrack = localMediaStream.getVideoTracks()[0];

            screenTrack.onended = () => {
                if (isScreenSharing) stopScreenShare();
            };

            localMediaStream.removeTrack(camVideoTrack);
            localMediaStream.addTrack(screenTrack);
            document.getElementById('localPreview').srcObject = localMediaStream;

            // Publish the new track
            if (room && room.state === 'connected') {
                const localScreenTrack = new LivekitClient.LocalVideoTrack(screenTrack);
                await room.localParticipant.publishTrack(localScreenTrack, { 
                    source: LivekitClient.Track.Source.ScreenShare,
                    videoEncoding: {
                        maxBitrate: 3000000, // 3 Mbps
                        maxFramerate: 30
                    }
                });
            }

            const btn = document.getElementById('btnScreen');
            if (btn) {
                btn.classList.remove('active-orange');
                btn.classList.add('active-green');
            }
            isScreenSharing = true;
            showAlert("Ekranınız paylaşılır 🖥️", "success");
            LOG("✅ Ekran paylaşımı başladıldı.", "#10b981");
        } catch (e) {
            console.error("Screen share error:", e);
            screenRequested = false;
            screenApproved = false;
            const btn = document.getElementById('btnScreen');
            if (btn) {
                btn.classList.remove('active-green', 'active-orange');
            }
            
            if (e.name === 'NotAllowedError') {
                showAlert("Ekran paylaşımı rədd edildi və ya ləğv olundu.", "error");
            } else {
                showAlert("Ekran paylaşımı xətası: " + (e.message || e.name), "error");
            }
        }
    }

    async function stopScreenShare() {
        isScreenSharing = false;
        screenApproved = false;
        screenRequested = false;
        const btn = document.getElementById('btnScreen');
        if (btn) {
            btn.classList.remove('active-green');
            btn.classList.remove('active-orange');
        }
        showAlert("Ekran paylaşımı dayandırıldı.");
        LOG("⏹️ Ekran paylaşımı dayandırıldı.", "#f59e0b");

        if (screenStream) {
            screenStream.getTracks().forEach(t => t.stop());
            screenStream = null;
        }

        if (camVideoTrack) {
            // Restore camera to preview
            const vTracks = localMediaStream.getVideoTracks();
            if (vTracks.length > 0) localMediaStream.removeTrack(vTracks[0]);
            localMediaStream.addTrack(camVideoTrack);
            document.getElementById('localPreview').srcObject = localMediaStream;
            
            try {
                // Unpublish screen from LiveKit
                const publication = room.localParticipant.getTrackPublication(LivekitClient.Track.Source.ScreenShare);
                if (publication && publication.track) {
                    await room.localParticipant.unpublishTrack(publication.track);
                }
            } catch(e) {
                console.error("Screen unpublish error:", e);
            }
        }

        try {
            await broadcastData({ type: 'screen_share_ended' });
        } catch (e) { console.error(e); }
    }


    // === HEARTBEAT INTERVAL (IMG-based for reliability) ===
    let heartbeatInterval = null;

    function sendHeartbeat() {
        // Only send heartbeat if connected
        if (!room || room.state !== 'connected') return;

        const peerId = (typeof room !== 'undefined' && room && room.localParticipant) ? room.localParticipant.identity : '';
        const img = new Image();
        img.src = `api/heartbeat.php?id=${lID}&peer_id=${peerId}&t=${Date.now()}`;
        img.onload = () => console.log('💓 Heartbeat:', new Date().toLocaleTimeString());
        img.onerror = () => console.error('❌ Heartbeat xətası');
    }

    function startHeartbeat() {
        if (heartbeatInterval) clearInterval(heartbeatInterval);
        sendHeartbeat();
        console.log('✅ Heartbeat sistemi aktivləşdirildi');
        // Hər 5 saniyədə bir (12 saniyəlik timeout üçün mükəmməldir)
        heartbeatInterval = setInterval(sendHeartbeat, 5000);
    }

    // Start after 3 seconds
    setTimeout(startHeartbeat, 3000);

    // Restart when tab becomes visible
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            console.log('📱 Tab aktiv - heartbeat restart');
            startHeartbeat();
        }
    });

    window.addEventListener('beforeunload', () => {
        const peerId = (typeof room !== 'undefined' && room && room.localParticipant) ? room.localParticipant.identity : '';
        navigator.sendBeacon(`api/heartbeat.php?id=${lID}&peer_id=${peerId}&action=leave&t=${Date.now()}`);
    });

    function leaveLesson() {
        if (confirm("Canlı dərsdən ayrılmaq istədiyinizə əminsiniz?")) {
            console.log("🚀 Dərsi tərk edirsiniz...");
            const peerId = (typeof room !== 'undefined' && room && room.localParticipant) ? room.localParticipant.identity : '';
            navigator.sendBeacon(`api/heartbeat.php?id=${lID}&peer_id=${peerId}&action=leave&t=${Date.now()}`);
            setTimeout(() => {
                window.location.href = 'live-classes.php';
            }, 500);
        }
    }

    // === WHITEBOARD LOGIC (V8.0 HIGH-FIDELITY SYNC) ===
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
    var eraserSize = 30; 
    var pencilSize = 3; 

    var wbPages = [];
    var currentPageIndex = 0;
    let undoStack = [];
    let redoStack = [];
    const MAX_HISTORY = 30;

    var laserX = 0;
    var laserY = 0;
    var laserActive = false;
    var wbCall = null;
    var wbDPR = 1;
    
    // Event listener references for cleanup
    var wbResizeHandler = null;
    var wbMouseDownHandler = null;
    var wbMouseMoveHandler = null;
    var wbMouseUpHandler = null;
    var wbMouseOutHandler = null;

    function toggleWBToolbar() {
        const toolbar = document.querySelector('.wb-controls-floating');
        const tab = document.getElementById('wbToolbarOpenTab');
        const isCollapsed = toolbar.classList.toggle('wb-collapsed');
        tab.classList.toggle('visible', isCollapsed);
    }

    function toggleWhiteboard() {
        if (!room || room.state !== 'connected') return;
        const btn = document.getElementById('btnWhiteboard');

        if (isWhiteboardActive) {
            stopWhiteboard();
        } else if (wbRequested) {
            showAlert("Sorğu artıq göndərilib.");
        } else {
            wbRequested = true;
            broadcastData({
                type: 'whiteboard_request',
                sender: uName
            });
            if (btn) btn.classList.add('active-orange');
            showAlert("Lövhə üçün icazə istənildi...");
            LOG("📝 Lövhə üçün icazə istənildi.", "#f59e0b");
        }
    }

    function setWBTool(t) {
        wbTool = t;
        document.querySelectorAll('.wb-tool-btn').forEach(b => b.classList.remove('active'));
        const activeBtn = document.getElementById('tool' + t.charAt(0).toUpperCase() + t.slice(1));
        if (activeBtn) activeBtn.classList.add('active');
        
        if (t !== 'laser') {
            const lsr = document.getElementById('laserCursor');
            if (lsr) lsr.style.display = 'none';
            laserActive = false;
        }

        // Update size display based on current tool
        const sizeDisp = document.getElementById('sizeDisplay');
        if (sizeDisp) {
            sizeDisp.innerText = (t === 'eraser' ? eraserSize : pencilSize) + 'px';
        }
    }

    let wbStreamCanvas, wbStreamCtx, wbStreamInterval;
    let wbStreamHealth = { trackCount: 0, lastUpdate: 0, isAlive: false };
    let wbReconnectAttempts = 0;
    const MAX_WB_RECONNECT_ATTEMPTS = 3;

    function validateWhiteboardStream(stream) {
        if (!stream) return false;
        const videoTracks = stream.getVideoTracks();
        if (videoTracks.length === 0) {
            console.error("❌ Whiteboard stream has no video tracks!");
            return false;
        }
        const track = videoTracks[0];
        if (track.readyState !== 'live') {
            console.error("❌ Whiteboard video track not live:", track.readyState);
            return false;
        }
        return true;
    }

    function monitorWhiteboardStream() {
        const checkInterval = setInterval(() => {
            if (!isWhiteboardActive) {
                clearInterval(checkInterval);
                return;
            }
            // Health monitoring logic
        }, 5000); // Check every 5 seconds
    }

    async function startActualWhiteboard() {
        wbRequested = false;
        isWhiteboardActive = true;
        const btn = document.getElementById('btnWhiteboard');
        if (btn) {
            btn.classList.remove('active-orange');
            btn.classList.add('active-green');
        }
        
        LOG("✅ Lövhə aktivləşdirildi.", "#10b981");

        // Reset reconnect attempts when starting fresh
        wbReconnectAttempts = 0;
        
        micRequested = false; // Reset other request states
        screenRequested = false;
        
        const overlay = document.getElementById('whiteboardOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
            setTimeout(() => {
                overlay.classList.add('is-visible');
                // Force resize after display change
                setTimeout(() => {
                    initWBCanvas();
                    if (wbResizeHandler) wbResizeHandler();
                }, 50);
            }, 10);
        }
        
        if (window.lucide) lucide.createIcons();

        // --- HIGH-FIDELITY COMPOSITING SETUP ---
        if (!wbStreamCanvas) {
            wbStreamCanvas = document.createElement('canvas');
        }
        wbStreamCtx = wbStreamCanvas.getContext('2d');
        wbStreamCanvas.width = 1280;
        wbStreamCanvas.height = 720;

        if (wbStreamInterval) clearInterval(wbStreamInterval);
        wbStreamInterval = setInterval(() => {
            if (!wbCanvas || !isWhiteboardActive) return;
            
            try {
                // 1. Background
                wbStreamCtx.fillStyle = '#ffffff';
                wbStreamCtx.fillRect(0, 0, wbStreamCanvas.width, wbStreamCanvas.height);
                
                // 2. Pattern (only draw if needed)
                if (wbBgType !== 'plain') {
                    wbStreamCtx.beginPath();
                    wbStreamCtx.strokeStyle = '#cbd5e1';
                    wbStreamCtx.lineWidth = 1;
                    if (wbBgType === 'grid') {
                        const step = 30 * (wbStreamCanvas.height / wbCanvas.height);
                        for (let x = step; x < wbStreamCanvas.width; x += step) {
                            wbStreamCtx.moveTo(x, 0); wbStreamCtx.lineTo(x, wbStreamCanvas.height);
                        }
                        for (let y = step; y < wbStreamCanvas.height; y += step) {
                            wbStreamCtx.moveTo(0, y); wbStreamCtx.lineTo(wbStreamCanvas.width, y);
                        }
                    } else if (wbBgType === 'lines') {
                        const step = 25 * (wbStreamCanvas.height / wbCanvas.height);
                        for (let y = step; y < wbStreamCanvas.height; y += step) {
                            wbStreamCtx.moveTo(0, y); wbStreamCtx.lineTo(wbStreamCanvas.width, y);
                        }
                    }
                    wbStreamCtx.stroke();
                }

                // 3. Drawing
                if (wbCanvas && wbCanvas.width > 0) {
                    wbStreamCtx.drawImage(wbCanvas, 0, 0, wbStreamCanvas.width, wbStreamCanvas.height);
                }

                // 4. Laser Pointer (only draw if active)
                if (laserActive && wbTool === 'laser' && wbCanvas && wbCanvas.width > 0) {
                    const scaleX = wbStreamCanvas.width / wbCanvas.width;
                    const scaleY = wbStreamCanvas.height / wbCanvas.height;
                    const sLX = laserX * scaleX;
                    const sLY = laserY * scaleY;

                    wbStreamCtx.save();
                    const grad = wbStreamCtx.createRadialGradient(sLX, sLY, 0, sLX, sLY, 25);
                    grad.addColorStop(0, 'rgba(239, 68, 68, 0.8)');
                    grad.addColorStop(1, 'rgba(239, 68, 68, 0)');
                    wbStreamCtx.fillStyle = grad;
                    wbStreamCtx.beginPath(); wbStreamCtx.arc(sLX, sLY, 25, 0, Math.PI*2); wbStreamCtx.fill();
                    wbStreamCtx.beginPath(); wbStreamCtx.arc(sLX, sLY, 8, 0, Math.PI*2); wbStreamCtx.fillStyle = '#ef4444'; wbStreamCtx.fill();
                    wbStreamCtx.strokeStyle = 'white'; wbStreamCtx.lineWidth = 2; wbStreamCtx.stroke();
                    wbStreamCtx.restore();
                }
            } catch(e) {
                console.warn("Whiteboard streaming error:", e);
            }
        }, 50);

        console.log("🎨 Whiteboard system initialized with stream validation.");
        
        // --- PUBLISH TO LIVEKIT ---
        if (room && room.state === 'connected') {
            try {
                const wbStream = wbStreamCanvas.captureStream(20);
                const wbTrack = wbStream.getVideoTracks()[0];
                if (wbTrack) {
                    const localWBTrack = new LivekitClient.LocalVideoTrack(wbTrack);
                    await room.localParticipant.publishTrack(localWBTrack, { 
                        source: LivekitClient.Track.Source.ScreenShare,
                        name: 'whiteboard',
                        videoEncoding: {
                            maxBitrate: 300000, // 300kbps is plenty for whiteboard
                            maxFramerate: 10
                        }
                    });
                    DBG("📡 Lövhə yayımı müəllimə göndərildi.");
                }
            } catch (err) {
                console.error("Whiteboard publish failed:", err);
            }
        }
    }

    function stopWhiteboard() {
        if (!isWhiteboardActive && !wbRequested) return;
        
        isWhiteboardActive = false;
        wbRequested = false;
        wbApproved = false;
        wbReconnectAttempts = 0;
        
        const btn = document.getElementById('btnWhiteboard');
        if (btn) {
            btn.classList.remove('active-green');
            btn.classList.remove('active-orange');
        }
        
        const overlay = document.getElementById('whiteboardOverlay');
        if (overlay) {
            overlay.classList.remove('is-visible');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 300);
        }
        
        LOG("⏹️ Lövhə dayandırıldı.", "#f59e0b");
        
        // ====== COMPLETE RESOURCE CLEANUP ======
        
        if (wbStreamInterval) {
            clearInterval(wbStreamInterval);
            wbStreamInterval = null;
        }
        
        if (wbStreamCanvas) {
            wbStreamCanvas.width = 0;
            wbStreamCanvas.height = 0;
            wbStreamCtx = null;
        }
        
        if (room && room.state === 'connected') {
            const publications = room.localParticipant.getTrackPublications();
            for (const pub of publications) {
                if (pub.track && (pub.track.name === 'whiteboard' || pub.source === LivekitClient.Track.Source.ScreenShare)) {
                    // Only unpublish if it's the whiteboard track
                    if (pub.track.name === 'whiteboard') {
                        room.localParticipant.unpublishTrack(pub.track);
                    }
                }
            }
            broadcastData({ type: 'whiteboard_ended' });
        }
        
        if (wbCanvas) {
            if (wbMouseDownHandler) wbCanvas.removeEventListener('mousedown', wbMouseDownHandler);
            if (wbMouseMoveHandler) wbCanvas.removeEventListener('mousemove', wbMouseMoveHandler);
            if (wbMouseUpHandler) wbCanvas.removeEventListener('mouseup', wbMouseUpHandler);
            if (wbMouseOutHandler) wbCanvas.removeEventListener('mouseout', wbMouseOutHandler);
            
            wbMouseDownHandler = null;
            wbMouseMoveHandler = null;
            wbMouseUpHandler = null;
            wbMouseOutHandler = null;
            wbCanvas = null;
            wbCtx = null;
        }
        
        if (wbResizeHandler) {
            window.removeEventListener('resize', wbResizeHandler);
            wbResizeHandler = null;
        }
        
        undoStack = [];
        redoStack = [];
        isDrawing = false;
        wbSnapshot = null;
        laserActive = false;
        
        const toolbar = document.querySelector('.wb-controls-floating');
        if (toolbar) toolbar.classList.remove('wb-collapsed');
        const tab = document.getElementById('wbToolbarOpenTab');
        if (tab) tab.classList.remove('visible');
        
        console.log("🎨 Whiteboard resources completely cleaned up");
    }

    function initWBCanvas() {
        if (wbCanvas) return;
        wbCanvas = document.getElementById('wbCanvasInternal');
        wbCtx = wbCanvas.getContext('2d');
        
        // Create and store resize handler for later cleanup
        wbResizeHandler = () => {
            if (!wbCanvas || !wbCtx) return;
            const container = wbCanvas.parentElement;
            if (!container || container.clientWidth === 0) return;
            let tempImg = (wbCanvas.width > 0) ? wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height) : null;
            wbCanvas.width = container.clientWidth;
            wbCanvas.height = container.clientHeight;
            wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
            if (tempImg) wbCtx.putImageData(tempImg, 0, 0);
            else if (wbPages.length === 0 || !wbPages[currentPageIndex]) saveState();
            setWBBackground(wbBgType);
        };
        
        window.addEventListener('resize', wbResizeHandler);
        wbResizeHandler();
        
        if (wbPages.length === 0) { wbPages.push(null); updatePageIndicator(); }

        // Store mouse handlers for later cleanup
        wbMouseDownHandler = (e) => {
            if (wbTool === 'laser') return;
            if (wbTool === 'text') { drawText(e.offsetX, e.offsetY); return; }
            saveState();
            isDrawing = true;
            [startX, startY] = [e.offsetX, e.offsetY];
            [lastX, lastY] = [e.offsetX, e.offsetY];
            wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        };
        
        wbMouseMoveHandler = (e) => {
            const lsr = document.getElementById('laserCursor');
            if (wbTool === 'laser') {
                lsr.style.display = 'block';
                lsr.style.left = (e.clientX - 6) + 'px'; lsr.style.top = (e.clientY - 6) + 'px';
                laserX = e.offsetX; laserY = e.offsetY; laserActive = true;
            } else { 
                lsr.style.display = 'none'; 
                laserActive = false; 
            }
            if (!isDrawing) return;
            if (wbTool === 'pencil' || wbTool === 'eraser') drawFreehand(e.offsetX, e.offsetY);
            else if (wbTool !== 'text' && wbTool !== 'laser') {
                wbCtx.putImageData(wbSnapshot, 0, 0);
                drawShape(e.offsetX, e.offsetY);
            }
        };
        
        wbMouseUpHandler = () => { isDrawing = false; wbSnapshot = null; };
        wbMouseOutHandler = () => { isDrawing = false; document.getElementById('laserCursor').style.display = 'none'; laserActive = false; };

        wbCanvas.addEventListener('mousedown', wbMouseDownHandler);
        wbCanvas.addEventListener('mousemove', wbMouseMoveHandler);
        wbCanvas.addEventListener('mouseup', wbMouseUpHandler);
        wbCanvas.addEventListener('mouseout', wbMouseOutHandler);

        // Touch handlers with cleanup references
        var touchStartHandler = (e) => {
            e.preventDefault(); 
            const r = wbCanvas.getBoundingClientRect();
            const p = { x: (e.touches[0].clientX - r.left) * (wbCanvas.width / r.width), y: (e.touches[0].clientY - r.top) * (wbCanvas.height / r.height) };
            if (wbTool === 'laser') return;
            if (wbTool === 'text') { drawText(p.x, p.y); return; }
            saveState(); isDrawing = true; [startX, startY] = [p.x, p.y]; [lastX, lastY] = [p.x, p.y];
            wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
        };
        
        var touchMoveHandler = (e) => {
            e.preventDefault(); 
            if (!isDrawing) return; 
            const r = wbCanvas.getBoundingClientRect();
            const p = { x: (e.touches[0].clientX - r.left) * (wbCanvas.width / r.width), y: (e.touches[0].clientY - r.top) * (wbCanvas.height / r.height) };
            if (wbTool === 'pencil' || wbTool === 'eraser') drawFreehand(p.x, p.y);
            else if (wbTool !== 'text' && wbTool !== 'laser') { wbCtx.putImageData(wbSnapshot, 0, 0); drawShape(p.x, p.y); }
        };
        
        var touchEndHandler = (e) => { isDrawing = false; };
        
        wbCanvas.addEventListener('touchstart', touchStartHandler, {passive:false});
        wbCanvas.addEventListener('touchmove', touchMoveHandler, {passive:false});
        wbCanvas.addEventListener('touchend', touchEndHandler, {passive:false});
        
        // Store touch handlers on the canvas for later cleanup
        wbCanvas._touchStartHandler = touchStartHandler;
        wbCanvas._touchMoveHandler = touchMoveHandler;
        wbCanvas._touchEndHandler = touchEndHandler;
    }

    function saveCurrentPage() { if (wbCanvas) wbPages[currentPageIndex] = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height); }
    function loadPage(idx) {
        if (wbPages[idx]) wbCtx.putImageData(wbPages[idx], 0, 0);
        else wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
        updatePageIndicator();
    }
    function addNewPage() { 
        saveCurrentPage(); 
        currentPageIndex = wbPages.length; 
        wbPages.push(null); 
        wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); 
        updatePageIndicator(); 
    }
    function prevPage() { if (currentPageIndex > 0) { saveCurrentPage(); currentPageIndex--; loadPage(currentPageIndex); } }
    function nextPage() { if (currentPageIndex < wbPages.length - 1) { saveCurrentPage(); currentPageIndex++; loadPage(currentPageIndex); } }
    function deletePage() { if (wbPages.length <= 1) return; if (confirm("Səhifə silinsin?")) { wbPages.splice(currentPageIndex, 1); if (currentPageIndex >= wbPages.length) currentPageIndex = wbPages.length - 1; loadPage(currentPageIndex); } }
    function updatePageIndicator() { document.getElementById('pageIndicator').innerText = (currentPageIndex + 1) + '/' + wbPages.length; }

    function saveState() {
        if (!wbCtx) return;
        if (undoStack.length >= MAX_HISTORY) undoStack.shift();
        undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height));
        redoStack = [];
    }
    function undo() { if (undoStack.length > 0) { redoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height)); wbCtx.putImageData(undoStack.pop(), 0, 0); } }
    function redo() { if (redoStack.length > 0) { undoStack.push(wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height)); wbCtx.putImageData(redoStack.pop(), 0, 0); } }

    function setWBBackground(type) {
        wbBgType = type;
        document.querySelectorAll('[id^="bg"]').forEach(b => b.classList.remove('active'));
        const bgBtn = document.getElementById('bg' + type.charAt(0).toUpperCase() + type.slice(1));
        if (bgBtn) bgBtn.classList.add('active');
        
        const canvasEl = document.getElementById('wbCanvasInternal');
        if (!canvasEl) return;

        // Fill the actual canvas buffer with white to prevent transparency issues in streaming
        const ctx = canvasEl.getContext('2d');
        const temp = ctx.getImageData(0, 0, canvasEl.width, canvasEl.height);
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvasEl.width, canvasEl.height);
        ctx.putImageData(temp, 0, 0);

        if (type === 'grid') { canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px), linear-gradient(90deg, #94a3b8 1px, transparent 1px)'; canvasEl.style.backgroundSize = '30px 30px'; }
        else if (type === 'lines') { canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px)'; canvasEl.style.backgroundSize = '100% 25px'; }
        else canvasEl.style.backgroundImage = 'none';
    }

    function changeSize(d) {
        if (wbTool === 'eraser') eraserSize = Math.max(10, Math.min(100, eraserSize + d));
        else pencilSize = Math.max(1, Math.min(20, pencilSize + d));
        const val = (wbTool === 'eraser' ? eraserSize : pencilSize);
        const disp = document.getElementById('sizeDisplay');
        if (disp) disp.innerText = val + 'px';
        console.log("📏 Whiteboard size changed to:", val);
    }

    function wbUploadImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => showImagePlacement(img);
                img.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function setWBColor(c, el) { wbColor = c; document.querySelectorAll('.wb-color').forEach(i => i.classList.remove('active')); if (el) el.classList.add('active'); }
    function openColorPicker() { document.getElementById('customColorPicker').click(); }
    function setCustomColor(c) { wbColor = c; document.querySelectorAll('.wb-color').forEach(i => i.classList.remove('active')); }

    function drawFreehand(x, y) {
        wbCtx.beginPath(); wbCtx.moveTo(lastX, lastY); wbCtx.lineTo(x, y);
        if (wbTool === 'eraser') { wbCtx.globalCompositeOperation = 'destination-out'; wbCtx.lineWidth = eraserSize; }
        else { wbCtx.globalCompositeOperation = 'source-over'; wbCtx.strokeStyle = wbColor; wbCtx.lineWidth = pencilSize; }
        wbCtx.lineCap = 'round'; wbCtx.lineJoin = 'round'; wbCtx.stroke();
        wbCtx.globalCompositeOperation = 'source-over'; [lastX, lastY] = [x, y];
    }
    function drawShape(x, y) {
        wbCtx.beginPath();
        wbCtx.strokeStyle = wbColor;
        wbCtx.lineWidth = pencilSize;
        wbCtx.lineCap = 'round';
        wbCtx.lineJoin = 'round';
        
        if (wbTool === 'line') {
            wbCtx.moveTo(startX, startY);
            wbCtx.lineTo(x, y);
        } else if (wbTool === 'rect') {
            wbCtx.rect(startX, startY, x - startX, y - startY);
        } else if (wbTool === 'circle') {
            const radius = Math.sqrt(Math.pow(x - startX, 2) + Math.pow(y - startY, 2));
            wbCtx.arc(startX, startY, radius, 0, Math.PI * 2);
        } else if (wbTool === 'arrow') {
            drawArrow(startX, startY, x, y);
        }
        wbCtx.stroke();
    }
    function drawArrow(x1, y1, x2, y2) {
        const h = 15, a = Math.atan2(y2-y1, x2-x1);
        wbCtx.moveTo(x1, y1); wbCtx.lineTo(x2, y2);
        wbCtx.lineTo(x2 - h * Math.cos(a - Math.PI/6), y2 - h * Math.sin(a - Math.PI/6));
        wbCtx.moveTo(x2, y2); wbCtx.lineTo(x2 - h * Math.cos(a + Math.PI/6), y2 - h * Math.sin(a + Math.PI/6));
    }
    function drawText(x, y) { const t = prompt("Mətn:"); if (t) { saveState(); wbCtx.font = "24px Inter"; wbCtx.fillStyle = wbColor; wbCtx.fillText(t, x, y); } }

    var placementImg = null, isDraggingImage = false, isResizingImage = false, imgDragStartX, imgDragStartY, imgStartLeft, imgStartTop, imgStartWidth, imgAspectRatio;

    function showImagePlacement(img) {
        const ov = document.getElementById('imagePlacementOverlay'), ct = document.getElementById('imagePlacementContainer'), el = document.getElementById('placementImage');
        el.src = img.src; imgAspectRatio = img.width/img.height;
        const w = (wbCanvas.width||1280)*0.5, h = w/imgAspectRatio;
        ct.style.left = ((wbCanvas.width||1280)-w)/2 + 'px'; ct.style.top = ((wbCanvas.height||720)-h)/2 + 'px'; ct.style.width = w+'px'; ct.style.height = h+'px';
        ov.style.display = 'block';
        
        // Mouse Events
        ct.onmousedown = (e) => { 
            if(e.target.id==='resizeHandle') return; 
            isDraggingImage=true; imgDragStartX=e.clientX; imgDragStartY=e.clientY; 
            imgStartLeft=parseInt(ct.style.left); imgStartTop=parseInt(ct.style.top); 
            document.onmousemove= (ev)=>{ 
                if(!isDraggingImage) return; 
                ct.style.left=(imgStartLeft+(ev.clientX-imgDragStartX))+'px'; 
                ct.style.top=(imgStartTop+(ev.clientY-imgDragStartY))+'px'; 
            }; 
            document.onmouseup=()=>{isDraggingImage=false; document.onmousemove=null;}; 
        };
        document.getElementById('resizeHandle').onmousedown = (e) => { 
            isResizingImage=true; imgDragStartX=e.clientX; imgStartWidth=parseInt(ct.style.width); 
            document.onmousemove=(ev)=>{ 
                if(!isResizingImage) return; 
                let nW=Math.max(50, imgStartWidth+(ev.clientX-imgDragStartX)); 
                ct.style.width=nW+'px'; ct.style.height=(nW/imgAspectRatio)+'px'; 
            }; 
            document.onmouseup=()=>{isResizingImage=false; document.onmousemove=null;}; 
        };

        // Touch Events
        const handleTouchDrag = (e) => {
            if(e.target.id==='resizeHandle') return;
            const t = e.touches[0]; isDraggingImage=true; imgDragStartX=t.clientX; imgDragStartY=t.clientY;
            imgStartLeft=parseInt(ct.style.left); imgStartTop=parseInt(ct.style.top);
        };
        const handleTouchMove = (e) => {
            if(!isDraggingImage && !isResizingImage) return;
            e.preventDefault(); const t = e.touches[0];
            if(isDraggingImage){
                ct.style.left=(imgStartLeft+(t.clientX-imgDragStartX))+'px';
                ct.style.top=(imgStartTop+(t.clientY-imgDragStartY))+'px';
            } else if(isResizingImage){
                let nW=Math.max(50, imgStartWidth+(t.clientX-imgDragStartX));
                ct.style.width=nW+'px'; ct.style.height=(nW/imgAspectRatio)+'px';
            }
        };
        ct.ontouchstart = handleTouchDrag;
        document.getElementById('resizeHandle').ontouchstart = (e) => {
            const t = e.touches[0]; isResizingImage=true; imgDragStartX=t.clientX; imgStartWidth=parseInt(ct.style.width);
        };
        window.ontouchmove = handleTouchMove;
        window.ontouchend = () => { isDraggingImage=false; isResizingImage=false; };
    }

    function confirmImagePlacement() {
        const ct = document.getElementById('imagePlacementContainer');
        saveState(); 
        wbCtx.drawImage(placementImg, parseInt(ct.style.left), parseInt(ct.style.top), parseInt(ct.style.width), parseInt(ct.style.height));
        document.getElementById('imagePlacementOverlay').style.display = 'none'; placementImg = null;
    }
    function cancelImagePlacement() { document.getElementById('imagePlacementOverlay').style.display = 'none'; placementImg = null; }
    function cleanupImagePlacementTouchListeners() {} // Simplified
    function clearWhiteboard() { if(confirm("Təmizlənsin?")) { saveState(); wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); } }
    function exportWhiteboard() { const l = document.createElement('a'); l.download = 'whiteboard.png'; l.href = wbCanvas.toDataURL(); l.click();    }

    /**
     * Set max bitrate for outgoing video senders
     * @param {RTCPeerConnection} pc 
     * @param {number} maxKbps 
     */
    function applyBitrateLimit(pc, maxKbps) {
        if (!pc || !pc.getSenders) return;
        pc.getSenders().forEach(sender => {
            if (sender.track && sender.track.kind === 'video') {
                const parameters = sender.getParameters();
                if (!parameters.encodings || parameters.encodings.length === 0) {
                    parameters.encodings = [{}];
                }
                parameters.encodings[0].maxBitrate = maxKbps * 1000;
                sender.setParameters(parameters).catch(e => console.warn("Bitrate control exception:", e));
            }
        });
    }
</script>

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

        <!-- Size control -->
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

        <!-- FİQURLAR -->
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

        <!-- RƏNGLƏR -->
        <div class="wb-color-grid">
            <div class="wb-color active" style="background: #000000;" onclick="setWBColor('#000000', this)" title="Qara"></div>
            <div class="wb-color" style="background: #ef4444;" onclick="setWBColor('#ef4444', this)" title="Qırmızı"></div>
            <div class="wb-color" style="background: #3b82f6;" onclick="setWBColor('#3b82f6', this)" title="Mavi"></div>
            <div class="wb-color" style="background: #10b981;" onclick="setWBColor('#10b981', this)" title="Yaşıl"></div>
            <div class="wb-color" style="background: #f59e0b;" onclick="setWBColor('#f59e0b', this)" title="Narıncı"></div>
            <div class="wb-color" style="background: #8b5cf6;" onclick="setWBColor('#8b5cf6', this)" title="Bənövşəyi"></div>
            <div class="wb-color" style="background: #ec4899;" onclick="setWBColor('#ec4899', this)" title="Çəhrayı"></div>
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

        <!-- FON -->
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

        <!-- ƏMƏLİYYATLAR -->
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
    <div id="wbToolbarOpenTab" onclick="toggleWBToolbar()">
        <i data-lucide="grid-3x3" style="width:20px;height:20px;color:white;"></i>
        <span style="font-size: 11px; font-weight: 800; color: white; letter-spacing: 0.5px;">ALƏTLƏRİ AÇ</span>
    </div>

    <!-- Top Bar Overlay (Info & Exit) -->
    <div style="position: absolute; top: 15px; left: 15px; right: 15px; display: flex; justify-content: space-between; align-items: flex-start; z-index: 2010; pointer-events: none;">
        <div style="background: rgba(15, 23, 42, 0.65); color: white; border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 100px; font-weight: 600; font-size: 11px; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); pointer-events: auto;">
            <div style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; box-shadow: 0 0 8px rgba(239, 68, 68, 0.8);"></div>
            STUDİO WHITEBOARD PRO
        </div>

        <button onclick="toggleWhiteboard()" title="Lövhəni Bağla & Kameraya Qayıt"
            style="background: rgba(15, 23, 42, 0.65); color: white; border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 100px; font-weight: 600; font-size: 12px; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(10px); cursor: pointer; pointer-events: auto; transition: all 0.2s ease;"
            onmouseover="this.style.background='#ef4444'; this.style.borderColor='#ef4444'; this.style.transform='scale(1.05)'"
            onmouseout="this.style.background='rgba(15, 23, 42, 0.65)'; this.style.borderColor='rgba(255, 255, 255, 0.1)'; this.style.transform='scale(1)'">
            <i data-lucide="x" style="width:16px;height:16px;"></i> BAĞLA
        </button>
    </div>

    <div id="laserCursor"></div>

    <div id="imagePlacementOverlay" style="display: none; position: absolute; inset: 0; z-index: 3000; background: rgba(0,0,0,0.3);">
        <div id="imagePlacementContainer" style="position: absolute; cursor: move; border: 2px dashed #3b82f6; box-shadow: 0 10px 30px rgba(0,0,0,0.3); touch-action: none;">
            <img id="placementImage" style="width: 100%; height: 100%; object-fit: contain; pointer-events: none;">
            <div id="resizeHandle" style="position: absolute; bottom: -12px; right: -12px; width: 28px; height: 28px; background: #3b82f6; border: 2px solid white; border-radius: 50%; cursor: se-resize; display: flex; align-items: center; justify-content: center; font-size: 11px; color: white; user-select: none; touch-action: none;">↘</div>
        </div>
        <div style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px;">
            <button onclick="confirmImagePlacement()" style="background: #22c55e; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);">✓ Yerləşdir</button>
            <button onclick="cancelImagePlacement()" style="background: #ef4444; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);">✕ Ləğv Et</button>
        </div>
        <div style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;">🖼️ Şəkli sürükləyin. Küncündən tutub ölçüsünü dəyişin.</div>
    </div>

    <div style="flex: 1; position: relative; background: #ffffff; cursor: crosshair; overflow: hidden;">
        <canvas id="wbCanvasInternal" style="display: block; touch-action: none;"></canvas>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
