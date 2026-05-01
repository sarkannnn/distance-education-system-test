<?php

/**
 * LiveKit Egress Recording View
 * This page is used by LiveKit Egress to record the lesson layout.
 * It joins the room as a hidden participant and renders the compositor canvas.
 */
// Egress Recording View - Secured with HMAC secret
require_once 'includes/helpers.php';
$db = Database::getInstance();

$lessonId = $_GET['id'] ?? null;
$providedSecret = $_GET['secret'] ?? '';

if (!$lessonId || !$providedSecret) {
    http_response_code(400);
    die('Bad Request');
}

$salt = getenv('EGRESS_SECRET_SALT') ?: 'change-this-in-production';
$expectedSecret = hash_hmac('sha256', "lesson_{$lessonId}", $salt);

if (!hash_equals($expectedSecret, $providedSecret)) {
    error_log("[Egress] Unauthorized access attempt to lesson {$lessonId} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(403);
    die('Unauthorized');
}

$lesson = $db->fetch("SELECT * FROM live_classes WHERE id = ?", [$lessonId]);

if (!$lesson) {
    die("Dərs tapılmadı.");
}

// Generate a special recorder token (high priority, hidden)
// Note: In production, you'd use a dedicated service account or secret key
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <title>REC: <?php echo e($lesson['course_id']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            width: 1920px;
            height: 1080px;
            /* Fixed high-res for Egress */
            background: #0f172a;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }

        #recordingCanvas {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Hidden elements for tracking */
        #hiddenVideos {
            display: none;
        }
    </style>
</head>

<body>
    <canvas id="recordingCanvas" width="1920" height="1080"></canvas>

    <div id="hiddenVideos">
        <video id="teacherCam" autoplay playsinline muted></video>
        <video id="teacherScreen" autoplay playsinline muted></video>
        <video id="studentSpotlight" autoplay playsinline muted></video>
    </div>

    <script>
        const lID = "<?php echo $lessonId; ?>";
        const canvas = document.getElementById('recordingCanvas');
        const ctx = canvas.getContext('2d', {
            alpha: false
        });

        let room;
        let teacherCamVid = document.getElementById('teacherCam');
        let teacherScreenVid = document.getElementById('teacherScreen');
        let spotlightVid = document.getElementById('studentSpotlight');

        let isWhiteboardActive = false;
        let isStudentSpotlight = false;
        let spotlightName = "";

        // Whiteboard internal canvas (for rendering drawings)
        const wbCanvas = document.createElement('canvas');
        wbCanvas.width = 1920;
        wbCanvas.height = 1080;
        const wbCtx = wbCanvas.getContext('2d');

        async function initRecorder() {
            try {
                // Fetch token for 'EgressRecorder'
                const res = await fetch(`../api/livekit_token.php?room=${lID}&identity=EgressRecorder&role=recorder`);
                const data = await res.json();

                room = new LivekitClient.Room();
                await room.connect(data.serverUrl, data.token);
                console.log("Connected to room for recording");

                room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
                    if (track.kind === 'video') {
                        const isTeacher = participant.identity.includes('instructor') || participant.metadata?.includes('teacher');

                        if (publication.source === LivekitClient.Track.Source.Camera && isTeacher) {
                            track.attach(teacherCamVid);
                        } else if (publication.source === LivekitClient.Track.Source.ScreenShare && isTeacher) {
                            track.attach(teacherScreenVid);
                        } else if (publication.source === LivekitClient.Track.Source.ScreenShare) {
                            // Student screen share (Spotlight)
                            track.attach(spotlightVid);
                        }
                    }
                });

                room.on(LivekitClient.RoomEvent.DataReceived, (payload, participant) => {
                    const decoder = new TextDecoder();
                    const d = JSON.parse(decoder.decode(payload));
                    handleSignaling(d, participant);
                });

                // Start Compositor Loop
                setInterval(drawFrame, 33); // 30 FPS

            } catch (e) {
                console.error("Recorder Init Error:", e);
            }
        }

        function handleSignaling(d, participant) {
            if (d.type === 'whiteboard_started') isWhiteboardActive = true;
            if (d.type === 'whiteboard_stopped' || d.type === 'whiteboard_force_stop') isWhiteboardActive = false;

            if (d.type === 'screen_share_started') {
                isStudentSpotlight = true;
                spotlightName = d.sender || "Tələbə";
            }
            if (d.type === 'screen_share_stopped' || d.type === 'screen_share_force_stop') {
                isStudentSpotlight = false;
            }

            // Sync Whiteboard Drawings
            if (d.type === 'wb_draw') {
                renderWBDraw(d);
            }
            if (d.type === 'wb_clear') {
                wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
            }
        }

        function renderWBDraw(data) {
            wbCtx.beginPath();
            wbCtx.strokeStyle = data.color || '#000';
            wbCtx.lineWidth = data.size || 3;
            wbCtx.lineCap = 'round';
            wbCtx.lineJoin = 'round';

            if (data.tool === 'eraser') {
                wbCtx.globalCompositeOperation = 'destination-out';
            } else {
                wbCtx.globalCompositeOperation = 'source-over';
            }

            if (data.mode === 'freehand') {
                wbCtx.moveTo(data.x1, data.y1);
                wbCtx.lineTo(data.x2, data.y2);
                wbCtx.stroke();
            } else if (data.mode === 'shape') {
                // Clear rect logic for shapes would be complex here, 
                // typically we'd need snapshots. For now, basic sync.
            }
            wbCtx.globalCompositeOperation = 'source-over';
        }

        function drawFrame() {
            // 1. Background
            ctx.fillStyle = "#0f172a";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // 2. Base Layer: Teacher Screen or Whiteboard or Cam
            if (isWhiteboardActive) {
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(wbCanvas, 0, 0);
            } else if (teacherScreenVid.readyState >= 2) {
                ctx.drawImage(teacherScreenVid, 0, 0, canvas.width, canvas.height);
            } else if (teacherCamVid.readyState >= 2) {
                // Standard view: Camera
                ctx.drawImage(teacherCamVid, 0, 0, canvas.width, canvas.height);
            }

            // 3. Overlay Layer: Student Spotlight or PIP Cam
            if (isStudentSpotlight && spotlightVid.readyState >= 2) {
                // Draw spotlighted student screen
                ctx.drawImage(spotlightVid, 0, 0, canvas.width, canvas.height);

                // Draw Teacher PIP in corner
                if (teacherCamVid.readyState >= 2) {
                    const pipW = 400;
                    const pipH = 225;
                    ctx.strokeStyle = "#3b82f6";
                    ctx.lineWidth = 4;
                    ctx.strokeRect(canvas.width - pipW - 40, 40, pipW, pipH);
                    ctx.drawImage(teacherCamVid, canvas.width - pipW - 40, 40, pipW, pipH);
                }
            } else if (isWhiteboardActive && teacherCamVid.readyState >= 2) {
                // Draw Teacher PIP over Whiteboard
                const pipW = 320;
                const pipH = 180;
                ctx.drawImage(teacherCamVid, canvas.width - pipW - 40, 40, pipW, pipH);
            }

            // 4. Branding / Badges
            ctx.fillStyle = "rgba(0,0,0,0.5)";
            ctx.fillRect(40, 40, 300, 50);
            ctx.fillStyle = "#fff";
            ctx.font = "bold 20px Inter";
            ctx.fillText("NSU DISTANT TƏHSİL", 60, 73);
        }

        initRecorder();
    </script>
</body>

</html>