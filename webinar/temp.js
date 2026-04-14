
        const wID = null;
        const uName = "null";
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
        let chunkFlushInterval;

        function startRecording() {
            if (!stream) return;
            try {
                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'video/webm;codecs=vp8,opus',
                    videoBitsPerSecond: 1500000 // 1.5 Mbps
                });

                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        flushChunk(event.data);
                    }
                };

                mediaRecorder.start(10000); // Trigger dataavailable every 10 seconds
                LOG("🔴 Video qeydiyyat başladı.", "#ef4444");
            } catch (e) {
                console.warn("MediaRecorder Error:", e);
                // Fallback for Safari
                try {
                    mediaRecorder = new MediaRecorder(stream);
                    mediaRecorder.ondataavailable = (event) => {
                        if (event.data.size > 0) flushChunk(event.data);
                    };
                    mediaRecorder.start(10000);
                    LOG("🔴 Video qeydiyyat başladı (fallback).", "#ef4444");
                } catch (err) {
                    LOG("❌ Qeydiyyat mümkün olmadı.", "red");
                }
            }
        }

        async function flushChunk(blob) {
            const formData = new FormData();
            formData.append('webinar_id', wID);
            formData.append('video_blob', blob);

            try {
                const resp = await fetch('api/upload_recording.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                if (data.success) {
                    LOG(`💾 Parça saxlanıldı: ${Math.round(data.size / 1024 / 1024 * 10) / 10} MB`, "#94a3b8");
                }
            } catch (err) {
                console.error("Flush error:", err);
            }
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
                const uniqueID = `ndu-webinar-${wID}-${Math.floor(Math.random()*1000)}`;
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
                    LOG(`👤 Tələbə qoşuldu: ${conn.metadata.name || 'Naməlum'}`, "#60a5fa");
                    
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
                        LOG("👤 Tələbə ayrıldı", "#94a3b8");
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
                            LOG("👤 Tələbə səhnəyə qoşuldu!", "#10b981");
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
                        closeStudentCall(false);
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
                    
                    drawImageCover(ctx, wbCanvas, 0, 0, canvas.width, canvas.height);

                    if (laserActive && wbTool === 'laser') {
                        const sX = canvas.width / wbCanvas.width;
                        const sY = canvas.height / wbCanvas.height;
                        const lx = laserX * sX, ly = laserY * sY;

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
                    drawImageCover(ctx, screenVid, 0, 0, canvas.width, canvas.height);
                    isMainDrawn = true;
                } else if (isStudentMain && activeStudentCall) {
                    const studentVid = document.getElementById('studentSource');
                    if (studentVid.readyState >= 2) {
                        drawImageCover(ctx, studentVid, 0, 0, canvas.width, canvas.height);
                        isMainDrawn = true;
                    }
                }
                
                if (!isMainDrawn && isCamOn && camVid.readyState >= 2) {
                    drawImageCover(ctx, camVid, 0, 0, canvas.width, canvas.height);
                }

                // Draw PIPs
                // If something else was main (Screen, WB, or Student), Teacher PIP
                if (isMainDrawn && isCamOn && camVid.readyState >= 2) {
                    // PIP should be approx 22% of the canvas width, but no smaller than 120px
                    const pipW = Math.max(120, canvas.width * 0.22);
                    const pipH = pipW * (9 / 16);
                    const padding = canvas.width * 0.02; // proportional padding
                    const pipX = canvas.width - pipW - padding; 
                    const pipY = canvas.height - pipH - padding;

                    ctx.save();
                    ctx.shadowColor = 'rgba(0,0,0,0.8)';
                    ctx.shadowBlur = canvas.width * 0.015;
                    ctx.shadowOffsetY = canvas.height * 0.01;
                    ctx.fillStyle = '#000';
                    ctx.fillRect(pipX, pipY, pipW, pipH);
                    ctx.shadowColor = 'transparent';

                    ctx.translate(pipX + pipW / 2, pipY + pipH / 2);
                    ctx.scale(-1, 1);
                    drawImageCover(ctx, camVid, -pipW / 2, -pipH / 2, pipW, pipH);
                    
                    ctx.scale(-1, 1);
                    ctx.strokeStyle = 'rgba(255,255,255,0.2)';
                    ctx.lineWidth = Math.max(1, canvas.width * 0.002);
                    ctx.strokeRect(-pipW / 2, -pipH / 2, pipW, pipH);
                    ctx.restore();
                }
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

            // Use setInterval instead of requestAnimationFrame so the broadcast 
            // continues even if the teacher switches tabs (prevents black screen for students)
            setInterval(draw, 1000/30);
            
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

            const data = { type: 'chat', sender: 'Müəllim', message: msg };
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
                container.innerHTML = '<div class="text-center py-10 opacity-20 text-[10px] font-bold uppercase tracking-widest">Tələbə yoxdur</div>';
                return;
            }

            allDataConns.forEach(conn => {
                const sName = (conn.metadata && conn.metadata.name) ? conn.metadata.name : 'Naməlum Tələbə';
                const initial = sName.charAt(0).toUpperCase();
                const div = document.createElement('div');
                div.className = 'group flex items-center justify-between p-3 rounded-2xl hover:bg-white/5 border border-transparent hover:border-white/5 transition-all';
                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-black text-xs">${initial}</div>
                        <div>
                            <div class="text-[11px] font-bold text-white/80">${sName}</div>
                            <div class="text-[9px] font-bold text-white/20 uppercase tracking-widest">Tələbə • Online</div>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            });
            lucide.createIcons();
        }

        function sendAnnouncement() {
            const msg = prompt("Bütün tələbələrə elan göndərin:");
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
                overlay.style.display = 'block';
                btn.classList.add('active-green');
                initWBCanvas();
                LOG("🎨 Professional Lövhə aktivdir", "#10b981");
                broadcast({ type: 'whiteboard_state', active: true });
            } else {
                overlay.style.display = 'none';
                btn.classList.remove('active-green');
                laserActive = false;
                document.getElementById('laserCursor').style.display = 'none';
                LOG("🎥 Kamera görüntüsünə qayıdıldı");
                broadcast({ type: 'whiteboard_state', active: false });
            }
        }

        function initWBCanvas() {
            if (wbCanvas) return;
            wbCanvas = document.getElementById('wbCanvasInternal');
            wbCtx = wbCanvas.getContext('2d');
            
            const resize = () => {
                const rect = wbCanvas.parentElement.getBoundingClientRect();
                const temp = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
                wbCanvas.width = rect.width;
                wbCanvas.height = rect.height;
                wbCtx.putImageData(temp, 0, 0);
                wbCtx.lineCap = 'round';
                wbCtx.lineJoin = 'round';
            };
            window.addEventListener('resize', resize);
            resize();

            // Init pages
            if (wbPages.length === 0) wbPages.push(null);
            updatePageIndicator();

            wbCanvas.onmousedown = (e) => {
                if (wbTool === 'laser') return;
                if (wbTool === 'text') { drawText(e.offsetX, e.offsetY); return; }
                
                saveState();
                isDrawing = true;
                [startX, startY] = [e.offsetX, e.offsetY];
                [lastX, lastY] = [e.offsetX, e.offsetY];
                wbSnapshot = wbCtx.getImageData(0, 0, wbCanvas.width, wbCanvas.height);
            };

            wbCanvas.onmousemove = (e) => {
                const laser = document.getElementById('laserCursor');
                if (wbTool === 'laser') {
                    laser.style.display = 'block';
                    laser.style.left = e.clientX + 'px';
                    laser.style.top = e.clientY + 'px';
                    laserActive = true;
                    laserX = e.offsetX;
                    laserY = e.offsetY;
                } else {
                    laser.style.display = 'none';
                    laserActive = false;
                }

                if (!isDrawing) return;
                
                if (wbTool === 'pencil' || wbTool === 'eraser') {
                    drawFreehand(e.offsetX, e.offsetY);
                } else {
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
                document.getElementById('laserCursor').style.display = 'none';
                laserActive = false;
            };
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
                LOG("⌛ Yayım dayandırılır və son görüntülər saxlanılır...", "#f59e0b");
                
                // Stop recording
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                    // Wait a bit for the last chunk to be processed via ondataavailable
                    await new Promise(r => setTimeout(r, 1000));
                }

                fetch('api/end_webinar.php?id=' + wID)
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            window.location.href = 'dashboard.php?success=webinar_ended';
                        } else {
                            alert(d.message);
                        }
                    });
            }
        }

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

            const camVid = document.getElementById('camSource');
            const studentVid = document.getElementById('studentSource');

            // If no student is on stage, hide the sidebar stage to avoid redundancy
            if (!activeStudentCall) {
                container.classList.add('hidden');
                mainCanvas.classList.add('mirrored-canvas'); // Teacher is always main when alone
                return;
            }

            container.classList.remove('hidden');

            // Reset classes
            container.classList.remove('bg-blue-500/5', 'bg-emerald-500/5');
            label.classList.remove('text-blue-400', 'text-emerald-400');
            dot.classList.remove('bg-blue-500', 'bg-emerald-500');

            if (isStudentMain && activeStudentCall) {
                // Showing TEACHER in sidebar because Student is on Main Stage
                video.srcObject = camStream;
                video.classList.add('scale-x-[-1]');
                label.innerText = "MƏNİM KAMERAM";
                container.classList.add('bg-blue-500/5');
                label.classList.add('text-blue-400');
                dot.classList.add('bg-blue-500');
                btnSwap.classList.add('bg-emerald-500/80');
                overlay.classList.add('hidden');
                
                // Main stage shows Student (Remote) - No mirror
                mainCanvas.classList.remove('mirrored-canvas');
            } else if (activeStudentCall) {
                // Showing STUDENT in sidebar
                video.srcObject = studentVid.srcObject;
                video.classList.remove('scale-x-[-1]');
                label.innerText = "TƏLƏBƏ CANLIDA";
                container.classList.add('bg-emerald-500/5');
                label.classList.add('text-emerald-400');
                dot.classList.add('bg-emerald-500', 'animate-pulse');
                btnSwap.classList.remove('bg-emerald-500/80');
                overlay.classList.remove('hidden');
                
                // Main stage shows Teacher (Local) - Mirror for natural feel
                mainCanvas.classList.add('mirrored-canvas');
            } else {
                // Default: Just Teacher self-preview
                mainCanvas.classList.add('mirrored-canvas');
                container.classList.add('hidden');
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
                LOG("🔍 Tələbə görüntüsü böyüdüldü");
            } else {
                overlay.classList.add('hidden');
            }
        }

        function toggleStudentMain() {
            if (!activeStudentCall) return;
            isStudentMain = !isStudentMain;
            updateSideStage();
            
            if (isStudentMain) {
                LOG("🌟 Tələbə əsas yayım səhnəsinə çıxarıldı!", "#10b981");
            } else {
                LOG("📺 Müəllim kamerasına qayıdıldı");
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
                LOG("🔌 Tələbə səhnədən çıxarıldı", "#ef4444");
                broadcast({ type: 'stage_ended' });
            }
        }

        function initWebinarTimer() {
            null
            let secondsElapsed = null;
            
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

        window.onload = () => {
            init();
            lucide.createIcons();
        };

        window.onbeforeunload = () => {
            if (peer) peer.destroy();
        };
    