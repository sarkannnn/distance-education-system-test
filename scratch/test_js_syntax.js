
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

    const lID = ""dummy"";
    const uName = ""dummy"";
    const uID = ""dummy"";
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

    // View Swapping
    let isViewSwapped = false;
    let teacherStream = null;

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
    "dummy"
        lessonStartTime = "dummy";
    "dummy"

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
            const newState = !localMediaStream.getAudioTracks()[0].enabled;
            setMic(newState);
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
        document.getElementById('btnMicItem').style.background = "#f59e0b";
        showAlert("Mikrofon üçün icazə istənildi...");
    }

    function setMic(enabled) {
        if (localMediaStream) localMediaStream.getAudioTracks().forEach(t => t.enabled = enabled);
    }

    async function toggleCam() {
        if (!localMediaStream) return;

        // If we are on dummy stream, try to get real camera instead of just toggling
        if (isDummyStream) {
            showAlert("Kamera yenidən yoxlanılır...");
            try {
                // Try to get real media (A+V or just V)
                let stream;
                try {
                    stream = await getMediaStream(true, true);
                } catch (e) {
                    stream = await getMediaStream(false, true);
                    DBG("Kamera aktivləşdi (səssiz).");
                }

                isDummyStream = false;

                // Replace video track in current stream
                const newVideoTrack = stream.getVideoTracks()[0];
                const oldVideoTrack = localMediaStream.getVideoTracks()[0];

                if (oldVideoTrack) localMediaStream.removeTrack(oldVideoTrack);
                localMediaStream.addTrack(newVideoTrack);

                // If we also got a new audio track and current one is dummy, replace it too
                if (stream.getAudioTracks().length > 0) {
                    const newAudioTrack = stream.getAudioTracks()[0];
                    const oldAudioTrack = localMediaStream.getAudioTracks()[0];
                    // Check if it's a dummy audio track (placeholder)
                    if (oldAudioTrack) localMediaStream.removeTrack(oldAudioTrack);
                    localMediaStream.addTrack(newAudioTrack);
                    newAudioTrack.enabled = false; // Keep muted by default
                }

                // Update preview
                document.getElementById('localPreview').srcObject = localMediaStream;

                // If there's an active LiveKit session, we must unpublish old and publish new track
                if (room && room.state === 'connected') {
                    DBG("📡 Yeni kamera yayımı müəllimə göndərilir...");
                    
                    // 1. Unpublish old video track if any
                    const publications = room.localParticipant.getTrackPublications();
                    for (const pub of publications) {
                        if (pub.track && pub.track.kind === 'video' && pub.source === LivekitClient.Track.Source.Camera) {
                            await room.localParticipant.unpublishTrack(pub.track);
                        }
                    }

                    // 2. Publish new track
                    const localTrack = new LivekitClient.LocalVideoTrack(newVideoTrack);
                    await room.localParticipant.publishTrack(localTrack, { source: LivekitClient.Track.Source.Camera });
                }

                newVideoTrack.enabled = true; // Ensure it's active
                updateCamUI(true);
                showAlert("Kamera uğurla aktivləşdirildi! ✅", "success");
                return; // Exit here, no need to toggle
            } catch (err) {
                console.error("Camera acquisition failed:", err);
                // Show specific error to help user debug
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

        // Normal toggle for real stream
        const tracks = localMediaStream.getVideoTracks();
        if (tracks.length > 0) {
            const newState = !tracks[0].enabled;
            tracks.forEach(t => t.enabled = newState);
            updateCamUI(newState);

            // If we just turned on the camera after it was off, 
            // maybe we should ensure teacher sees it (some browsers might need re-negotiation)
            if (newState && p && dataConn && dataConn.peer && dataConn.open) {
                // p.call(dataConn.peer, localMediaStream, { metadata: { name: uName, userId: uID } });
            }
        }
    }

    function updateCamUI(enabled) {
        const btn = document.getElementById('btnCamItem');
        if (!btn) return;
        btn.style.background = enabled ? "rgba(59, 130, 246, 0.2)" : "#ef4444";
        btn.style.borderColor = enabled ? "#3b82f6" : "rgba(239, 68, 68, 0.4)";
        btn.innerHTML = enabled ? '<i data-lucide="video" style="width: 20px; height: 20px; color: white;"></i>' : '<i data-lucide="video-off" style="width: 20px; height: 20px; color: white;"></i>';
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
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (msg === '') return;

        const msgObj = {
            type: 'chat',
            message: msg,
            sender: uName
        };
        await broadcastData(msgObj);
        appendChatMessage('Mən', msg, 'mən');
        input.value = '';
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
        ctx.fillText("Tələbə: "dummy"", 320, 350);

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

    async function initStudent() {
        document.getElementById('overlay').style.display = 'none';

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
                    let realA = null;
                    try {
                        const aOnly = await getMediaStream(true, false);
                        realA = aOnly.getAudioTracks()[0];
                        DBG("⚠️ Kamera tapılmadı, yalnız mikrofon aktivdir.");
                    } catch (err3) {
                        DBG("⚠️ Kamera və mikrofon tapılmadı.");
                    }
                    createDummyStream(realA);
                }
            }
        } catch (e) {
            console.error("Media init error:", e);
            createDummyStream();
        }

        if (localMediaStream) {
            localMediaStream.getAudioTracks().forEach(t => t.enabled = false);
            document.getElementById('localPreview').srcObject = localMediaStream;
            document.getElementById('localControlsWrapper').style.display = 'block';
            updateCamUI(!isDummyStream);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // Start LiveKit connection
        await startLiveKit();
    }




    // ─── LiveKit Configuration ───
    async function startLiveKit() {
        try {
            DBG("📡 LiveKit qoşulması başladılır...");
            const res = await fetch(`../api/livekit_token.php?room=${lID}`, { credentials: 'include' });
            const data = await res.json();
            if(!data.success) throw new Error(data.message);

            room = new LivekitClient.Room({
                adaptiveStream: true,
                dynacast: true,
            });

            setupRoomEvents();

            await room.connect(data.serverUrl, data.token);
            DBG("🚀 LiveKit Serverinə qoşuldu! ✅");
            signalReady = true;

            if (localMediaStream) {
                await publishMedia();
            }

        } catch (e) {
            DBG("❌ LiveKit Xətası: " + e.message);
            setTimeout(startLiveKit, 5000);
        }
    }

    function setupRoomEvents() {
        room
            .on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
                if (track.kind === 'video') {
                    const vid = document.getElementById('remVid');
                    if (vid) {
                        DBG(`📹 Müəllim görüntüsü alındı ✅ (${participant.identity})`);
                        track.attach(vid);
                        // Also call showRemoteVideo to handle UI/state if needed, passing a reconstructed stream for compatibility
                        const stream = track.mediaStream || new MediaStream([track.mediaStreamTrack]);
                        showRemoteVideo(stream);
                    }
                }
                if (track.kind === 'audio') {
                    track.attach();
                }
            })
            .on(LivekitClient.RoomEvent.DataReceived, (payload, participant) => {
                const decoder = new TextDecoder();
                try {
                    const d = JSON.parse(decoder.decode(payload));
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
                    await room.localParticipant.publishTrack(localVideo, { source: LivekitClient.Track.Source.Camera });
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
        const encoder = new TextEncoder();
        const payload = encoder.encode(JSON.stringify(data));
        try {
            await room.localParticipant.publishData(payload, { reliable: true });
        } catch (e) {
            console.error("Broadcast error:", e);
        }
    }

    function handleIncomingData(d, participant) {
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
            document.getElementById('btnMicItem').innerHTML = '<i data-lucide="mic" style="width: 20px; height: 20px; color: white;"></i>';
            if (window.lucide) lucide.createIcons();
            document.getElementById('btnMicItem').style.background = '#22c55e';
        }
        if (d.type === 'mute_force' || d.type === 'mic_rejected') {
            micApproved = false;
            micRequested = false;
            setMic(false);
            document.getElementById('btnMicItem').innerHTML = '<i data-lucide="hand" style="width: 20px; height: 20px; color: white;"></i>';
            if (window.lucide) lucide.createIcons();
            document.getElementById('btnMicItem').style.background = '#ef4444';
        } else if (d.type === 'whiteboard_approved') {
            startActualWhiteboard();
        } else if (d.type === 'whiteboard_rejected') {
            showAlert("Müəllim lövhə istəyini rədd etdi.", "error");
            document.getElementById('btnWhiteboardItem').style.background = 'rgba(255,255,255,0.1)';
        } else if (d.type === 'whiteboard_force_stop') {
            if (isWhiteboardActive) stopWhiteboard();
        }
        if (d.type === 'notification') {
            showAlert(d.message, 'info');
        } else if (d.type === 'screen_share_approved') {
            screenApproved = true;
            startActualScreenShare();
        } else if (d.type === 'kick_user') {
            window.location.href = 'live-classes.php';
        } else if (d.type === 'lesson_ended') {
            showAlert("Dərs bitdi.");
            setTimeout(() => window.location.href = 'live-classes.php', 3000);
        }
    }


    function showRemoteVideo(stream) {
        teacherStream = stream;
        const vid = document.getElementById('remVid');
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

            mainLabel.innerHTML = '<div style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; box-shadow: 0 0 8px #3b82f6;"></div>Müəllim: "dummy"';
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
            document.getElementById('btnScreenItem').style.background = "#f59e0b";
            showAlert("Ekran paylaşımı üçün sorğu göndərildi...");
        }
    }


    async function startActualScreenShare() {
        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: true
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
                await room.localParticipant.publishTrack(localScreenTrack, { source: LivekitClient.Track.Source.ScreenShare });
            }

            document.getElementById('btnScreenItem').style.background = "#10b981";
            isScreenSharing = true;
            showAlert("Ekranınız paylaşılır 🖥️", "success");
        } catch (e) {
            console.error("Screen share error:", e);
            screenRequested = false;
            screenApproved = false;
            document.getElementById('btnScreenItem').style.background = "rgba(255,255,255,0.1)";
        }
    }

    async function stopScreenShare() {
        if (screenStream) screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;

        if (camVideoTrack) {
            localMediaStream.removeTrack(localMediaStream.getVideoTracks()[0]);
            localMediaStream.addTrack(camVideoTrack);
            document.getElementById('localPreview').srcObject = localMediaStream;
            
            // Unpublish screen from LiveKit
            const publication = room.localParticipant.getTrackPublicationBySource(LivekitClient.Track.Source.ScreenShare);
            if (publication) {
                await room.localParticipant.unpublishTrack(publication.track);
            }
        }

        await broadcastData({
            type: 'screen_share_ended'
        });

        document.getElementById('btnScreenItem').style.background = "rgba(255,255,255,0.1)";
        isScreenSharing = false;
        screenApproved = false;
        screenRequested = false;
        showAlert("Ekran paylaşımı dayandırıldı.");
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
        if (!isWhiteboardActive) {
            if (!room || room.state !== 'connected') {
                showAlert("Dərs qoşulu deyil!");
                return;
            }
            broadcastData({
                type: 'whiteboard_request',
                sender: uName
            });
            showAlert("Lövhə üçün icazə istənildi...");
            document.getElementById('btnWhiteboardItem').style.background = '#f59e0b';
        } else {
            stopWhiteboard();
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

    function startActualWhiteboard() {
        // Reset reconnect attempts when starting fresh
        wbReconnectAttempts = 0;
        
        isWhiteboardActive = true;
        const overlay = document.getElementById('whiteboardOverlay');
        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('is-visible'), 10);
        document.getElementById('btnWhiteboardItem').style.background = '#3b82f6';
        
        initWBCanvas();
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
                        source: LivekitClient.Track.Source.ScreenShare, // Use ScreenShare source so it triggers spotlight on teacher side
                        name: 'whiteboard'
                    });
                    DBG("📡 Lövhə yayımı müəllimə göndərildi.");
                }
            } catch (err) {
                console.error("Whiteboard publish failed:", err);
            }
        }
    }

    function stopWhiteboard() {
        isWhiteboardActive = false;
        wbReconnectAttempts = 0; // Reset reconnection attempts
        
        const overlay = document.getElementById('whiteboardOverlay');
        overlay.classList.remove('is-visible');
        document.getElementById('btnWhiteboardItem').style.background = 'rgba(255,255,255,0.1)';
        
        // ====== COMPLETE RESOURCE CLEANUP ======
        
        // 1. Stop canvas stream interval
        if (wbStreamInterval) {
            clearInterval(wbStreamInterval);
            wbStreamInterval = null;
        }
        
        // 2. Release stream canvas resources
        if (wbStreamCanvas) {
            try {
                const stream = wbStreamCanvas.captureStream ? wbStreamCanvas.captureStream() : null;
                if (stream) {
                    stream.getTracks().forEach(track => {
                        if (track && track.stop) track.stop();
                    });
                }
                wbStreamCanvas.width = 0;
                wbStreamCanvas.height = 0;
                wbStreamCtx = null;
            } catch(e) { console.warn("Error releasing stream canvas:", e); }
        }
        
        // 3. Unpublish from LiveKit
        if (room && room.state === 'connected') {
            const publications = room.localParticipant.getTrackPublications();
            for (const pub of publications) {
                if (pub.track && pub.track.kind === 'video' && pub.track.name === 'whiteboard') {
                    room.localParticipant.unpublishTrack(pub.track);
                }
            }
        }
        
        // 4. Remove event listeners to prevent memory leaks
        if (wbCanvas) {
            // Remove mouse listeners
            if (wbMouseDownHandler) wbCanvas.removeEventListener('mousedown', wbMouseDownHandler);
            if (wbMouseMoveHandler) wbCanvas.removeEventListener('mousemove', wbMouseMoveHandler);
            if (wbMouseUpHandler) wbCanvas.removeEventListener('mouseup', wbMouseUpHandler);
            if (wbMouseOutHandler) wbCanvas.removeEventListener('mouseout', wbMouseOutHandler);
            
            // Remove touch listeners
            if (wbCanvas._touchStartHandler) wbCanvas.removeEventListener('touchstart', wbCanvas._touchStartHandler);
            if (wbCanvas._touchMoveHandler) wbCanvas.removeEventListener('touchmove', wbCanvas._touchMoveHandler);
            if (wbCanvas._touchEndHandler) wbCanvas.removeEventListener('touchend', wbCanvas._touchEndHandler);
            
            // Clear all stored handlers
            wbMouseDownHandler = null;
            wbMouseMoveHandler = null;
            wbMouseUpHandler = null;
            wbMouseOutHandler = null;
            wbCanvas._touchStartHandler = null;
            wbCanvas._touchMoveHandler = null;
            wbCanvas._touchEndHandler = null;
            
            // Reset canvas reference
            wbCanvas = null;
            wbCtx = null;
        }
        
        // 5. Remove resize listener
        if (wbResizeHandler) {
            window.removeEventListener('resize', wbResizeHandler);
            wbResizeHandler = null;
        }
        
        // 6. Clear undo/redo stacks to free memory
        undoStack = [];
        redoStack = [];
        
        // 7. Clear whiteboard state variables
        isDrawing = false;
        wbSnapshot = null;
        laserActive = false;
        laserX = 0;
        laserY = 0;
        wbTool = 'pencil';
        
        // 8. Reset stream health monitoring
        wbStreamHealth = { trackCount: 0, lastUpdate: 0, isAlive: false };
        
        setTimeout(() => { overlay.style.display = 'none'; }, 300);
        // In LiveKit, we use broadcastData
        broadcastData({ type: 'whiteboard_ended' });
        
        // Reset toolbar
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

        if (type === 'grid') { canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px), linear-gradient(90deg, #94a3b8 1px, transparent 1px)'; canvasEl.style.backgroundSize = '30px 30px'; }
        else if (type === 'lines') { canvasEl.style.backgroundImage = 'linear-gradient(#94a3b8 1px, transparent 1px)'; canvasEl.style.backgroundSize = '100% 25px'; }
        else canvasEl.style.backgroundImage = 'none';
        canvasEl.style.backgroundColor = 'white';
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
