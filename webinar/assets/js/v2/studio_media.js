/**
 * Webinar V2 - Media & PeerJS Manager
 */

class StudioMedia {
    constructor(config) {
        this.wID = config.wID;
        this.uName = config.uName;
        this.role = config.role || 'teacher';
        this.teacherPeerId = config.teacherPeerId || null;
        this.peer = null;
        this.stream = null;
        this.camStream = null;
        this.screenStream = null;
        this.allDataConns = [];
        this.participants = new Map();

        this.isCamOn = false;
        this.isMicOn = false;
        this.isScreenOn = false;

        this.activeStudentCall = null;
        this.isWBActive = false;
        this.isViewSwapped = false;

        this.outputCanvas = document.getElementById(this.role === 'teacher' ? 'outputCanvas' : 'studentOutputCanvas');
        this.outputCtx = this.outputCanvas ? this.outputCanvas.getContext('2d', { alpha: false }) : null;
        this.composingStream = null;

        this.init();
    }

    async init() {
        console.log("StudioMedia: Initializing PeerJS...");

        if (this.role === 'student') {
            console.log("StudioMedia: Student mode detected");
            this.startPeerJS();
        } else {
            console.log("StudioMedia: Teacher mode detected");
            // Wait for media before starting PeerJS so we have a stream to answer with
            await this.initTeacherMedia();
            this.startCompositing();
            this.startPeerJS();
        }
    }

    async initTeacherMedia() {
        try {
            this.camStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: { echoCancellation: true, noiseSuppression: true }
            });
            this.isCamOn = true;
            this.isMicOn = true;

            this.startAudioVisualizer(this.camStream, 'lobbyMicVisualizer');

            // Allow updateMediaState to handle the srcObject assignment and playback
            this.updateMediaState();

            return true;
        } catch (err) {
            console.warn("StudioMedia: Camera/Mic access denied or not found", err);
            return false;
        }
    }

    startPeerJS() {
        const peerId = (this.role === 'teacher') ? `w-${this.wID}-teacher` : null;
        console.log(`StudioMedia: Starting PeerJS (ID: ${peerId || 'Auto'})...`);
        
        this.peer = new Peer(peerId, {
            debug: 3,
            config: {
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            }
        });

        this.peer.on('open', (id) => {
            console.log("StudioMedia: Peer opened with ID", id);
            this.updateStatus('Online', 'bg-emerald-500');
            if (this.role === 'teacher') {
                this.updatePeerIdInDB(id);
            } else {
                // Wait a bit to ensure teacher is ready
                setTimeout(() => this.connectToTeacher(), 2000);
            }
        });

        this.peer.on('connection', (conn) => this.handleDataConnection(conn));
        this.peer.on('call', (call) => this.handleIncomingCall(call));

        this.peer.on('error', (err) => {
            console.error("StudioMedia: PeerJS Error", err);
            this.updateStatus('Xəta', 'bg-rose-500');

            if (err.type === 'unavailable-id' && this.role === 'teacher') {
                alert("Bu vebinar artıq başqa tabda açıqdır. Zəhmət olmasa digər tabları bağlayın.");
            }

            if (err.type === 'peer-unavailable' && this.role === 'student') {
                console.log("StudioMedia: Teacher unavailable, retrying in 3s...");
                setTimeout(() => this.connectToTeacher(), 3000);
            }
        });
    }

    updateStatus(text, colorClass) {
        const statusEl = document.getElementById('connectionStatus');
        if (statusEl) {
            statusEl.querySelector('span').innerText = text;
            const dot = statusEl.querySelector('div');
            dot.className = `w-2 h-2 rounded-full ${colorClass}`;
        }
    }

    async connectToTeacher() {
        // Use the predictable teacher ID format
        this.teacherPeerId = `w-${this.wID}-teacher`;
        console.log("StudioMedia: Connecting to teacher via predictable ID", this.teacherPeerId);
        this.establishConnection();
    }

    establishConnection() {
        if (!this.teacherPeerId) return;
        console.log("StudioMedia: Establishing connection to", this.teacherPeerId);

        if (this.watcherConn) this.watcherConn.close();
        this.watcherConn = this.peer.connect(this.teacherPeerId, {
            metadata: { name: this.uName, type: 'watcher' }
        });
        this.handleDataConnection(this.watcherConn);

        // Call teacher with silent audio to get their stream
        const silentStream = this.createSilentStream();

        if (this.watcherCall) this.watcherCall.close();
        this.watcherCall = this.peer.call(this.teacherPeerId, silentStream, {
            metadata: { name: this.uName, type: 'watcher' }
        });
        this.handleIncomingCall(this.watcherCall);
    }

    createSilentStream() {
        const ctx = new AudioContext();
        const dest = ctx.createMediaStreamDestination();
        return dest.stream;
    }

    async previewStage() {
        console.log("StudioMedia: Previewing stage...");
        try {
            if (!this.camStream) {
                this.camStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: { echoCancellation: true, noiseSuppression: true }
                });
                this.isCamOn = true;
                this.isMicOn = true;
            }

            const lobbyVid = document.getElementById('studentLobbyVideo');
            if (lobbyVid) {
                lobbyVid.srcObject = this.camStream;
            }

            this.startAudioVisualizer(this.camStream, 'studentMicVisualizer');

            this.updateMediaState();
            return true;
        } catch (err) {
            console.error("StudioMedia: Error getting preview", err);
            alert("Kamera və ya mikrofona icazə verilməyib.");
            return false;
        }
    }

    async confirmJoinStage() {
        if (!this.teacherPeerId || !this.camStream) return false;
        console.log("StudioMedia: Confirming join stage...");

        try {
            // Show PiP
            const localVid = document.getElementById('localVidMobile');
            const wrapper = document.getElementById('mobileLocalStageWrapper');
            if (localVid && wrapper) {
                localVid.srcObject = this.camStream;
                wrapper.classList.remove('hidden');
            }

            // Call teacher with composed stream
            if (this.stageCall) this.stageCall.close();
            
            if (!this.composingStream) this.startCompositing();

            this.stageCall = this.peer.call(this.teacherPeerId, this.composingStream || this.camStream, {
                metadata: { name: this.uName, type: 'stage' }
            });

            this.handleIncomingCall(this.stageCall);
            return true;
        } catch (err) {
            console.error("StudioMedia: Error joining stage", err);
            return false;
        }
    }

    async leaveStage() {
        console.log("StudioMedia: Leaving stage...");

        // Stop local media tracks
        if (this.camStream) {
            this.camStream.getTracks().forEach(t => t.stop());
            this.camStream = null;
        }

        if (this.stageCall) {
            this.stageCall.close();
            this.stageCall = null;
        }

        const wrapper = document.getElementById('mobileLocalStageWrapper');
        const localVid = document.getElementById('localVidMobile');
        if (wrapper) wrapper.classList.add('hidden');
        if (localVid) localVid.srcObject = null;

        this.isCamOn = false;
        this.isMicOn = false;

        // Stop compositor
        if (this.compositorInterval) {
            clearInterval(this.compositorInterval);
            this.compositorInterval = null;
        }
        this.composingStream = null;

        // Reconnect as watcher
        this.connectToTeacher();
    }

    handleDataConnection(conn) {
        console.log("StudioMedia: Data connection established with", conn.peer);
        this.allDataConns.push(conn);

        conn.on('data', (data) => {
            console.log("StudioMedia: Data received:", data);
            if (data.type === 'chat' && window.StudioChat) {
                window.StudioChat.appendMessage(data.sender, data.message);
                this.broadcast(data, conn.peer);
            } else if (data.type === 'whiteboard' && window.studioWhiteboard) {
                window.studioWhiteboard.handleIncomingData(data);
                if (this.role === 'teacher') this.broadcast(data, conn.peer);
            }
        });

        conn.on('close', () => {
            this.allDataConns = this.allDataConns.filter(c => c.peer !== conn.peer);
            if (window.StudioChat) window.StudioChat.updateParticipantCount(this.allDataConns.length);
            this.removeParticipantFromGrid(conn.peer);
        });
    }

    handleIncomingCall(call) {
        console.log("StudioMedia: Incoming call received");

        // Teacher role uses the composed stream (Canvas + Audio)
        let myStream = (this.role === 'teacher' && this.composingStream) ? this.composingStream : this.camStream;
        
        if (!myStream) {
            myStream = this.createSilentStream();
        }

        call.answer(myStream);

        call.on('stream', (remoteStream) => {
            if (this.role === 'student') {
                // STUDENT ROLE: We received the Teacher's stream
                console.log("StudioMedia: Received teacher stream");
                this.updateStatus('CANLI', 'bg-emerald-500');
                const remoteVid = document.getElementById('remoteVid');
                if (remoteVid) {
                    remoteVid.srcObject = remoteStream;
                    console.log("StudioMedia: Setting remote stream to remoteVid");
                    setTimeout(() => {
                        remoteVid.play().catch(err => {
                            console.warn("StudioMedia: Autoplay blocked", err);
                            const unmuteOverlay = document.getElementById('unmuteOverlay');
                            if (unmuteOverlay) unmuteOverlay.classList.remove('hidden');
                        });
                    }, 500);
                }
            } else {
                // TEACHER ROLE
                const isWatcher = call.metadata && call.metadata.type === 'watcher';

                if (isWatcher) {
                    console.log("StudioMedia: Received watcher connection (no stage)");
                } else {
                    console.log("StudioMedia: Received student stage stream");
                    this.activeStudentCall = call;
                    const studentSource = document.getElementById('studentSource');
                    if (studentSource) {
                        studentSource.srcObject = remoteStream;
                        studentSource.play();
                    }
                    // Also add to gallery
                    this.addParticipantToGrid(call.peer, call.metadata?.name || 'İştirakçı', remoteStream);

                    // Automatically feature if it's the first student joining stage
                    if (this.participants.size === 1) {
                        this.featureParticipant(call.peer);
                    }

                    call.on('close', () => {
                        console.log("StudioMedia: Stage stream closed");
                        this.removeParticipantFromGrid(call.peer);
                    });
                }
            }
        });
    }

    broadcast(data, skipPeerId = null) {
        this.allDataConns.forEach(conn => {
            if (conn.peer !== skipPeerId) {
                conn.send(data);
            }
        });
    }

    async updatePeerIdInDB(id) {
        try {
            const resp = await fetch(`api/update_peer_id.php?id=${this.wID}&peer_id=${id}`);
            const data = await resp.json();
            if (data.success) console.log("StudioMedia: DB updated with peer ID");
        } catch (err) {
            console.error("StudioMedia: DB update failed", err);
        }
    }

    toggleMic() {
        this.isMicOn = !this.isMicOn;
        this.updateMediaState();
        return this.isMicOn;
    }

    toggleCam() {
        this.isCamOn = !this.isCamOn;
        this.updateMediaState();
        return this.isCamOn;
    }

    updateMediaState() {
        if (this.camStream) {
            this.camStream.getAudioTracks().forEach(t => t.enabled = this.isMicOn);
            this.camStream.getVideoTracks().forEach(t => t.enabled = this.isCamOn);
        }

        // Update Studio Buttons
        const micBtn = document.getElementById('btnMic');
        const camBtn = document.getElementById('btnCam');

        if (micBtn) {
            micBtn.classList.toggle('active-green', this.isMicOn);
            micBtn.classList.toggle('active-red', !this.isMicOn);
        }
        if (camBtn) {
            camBtn.classList.toggle('active-green', this.isCamOn);
            camBtn.classList.toggle('active-red', !this.isCamOn);
        }

        // Update Lobby Buttons
        const lobbyMicBtn = document.getElementById('lobbyMicBtn');
        const lobbyCamBtn = document.getElementById('lobbyCamBtn');

        if (lobbyMicBtn) {
            lobbyMicBtn.classList.toggle('bg-emerald-500', this.isMicOn);
            lobbyMicBtn.classList.toggle('bg-rose-500', !this.isMicOn);
            lobbyMicBtn.querySelector('i').setAttribute('data-lucide', this.isMicOn ? 'mic' : 'mic-off');
        }
        if (lobbyCamBtn) {
            lobbyCamBtn.classList.toggle('bg-emerald-500', this.isCamOn);
            lobbyCamBtn.classList.toggle('bg-rose-500', !this.isCamOn);
            lobbyCamBtn.querySelector('i').setAttribute('data-lucide', this.isCamOn ? 'video' : 'video-off');
        }

        const localVid = document.getElementById('localPreviewVid');
        if (localVid) localVid.style.opacity = this.isCamOn ? "1" : "0.2";

        if (window.lucide) window.lucide.createIcons();
    }

    // --- Gallery View Management ---
    addParticipantToGrid(id, name, stream = null) {
        if (this.participants.has(id)) return;

        const grid = document.getElementById('mainVideoContainer');
        const box = document.createElement('div');
        box.className = 'participant-box';
        box.id = `participant-${id}`;

        const video = document.createElement('video');
        video.autoplay = true;
        video.playsInline = true;
        if (stream) video.srcObject = stream;

        const nameTag = document.createElement('div');
        nameTag.className = 'participant-name';
        nameTag.innerText = name;

        box.appendChild(video);
        box.appendChild(nameTag);
        
        // Add click listener to feature this participant
        box.style.cursor = 'pointer';
        box.addEventListener('click', () => this.featureParticipant(id));

        grid.appendChild(box);

        this.participants.set(id, { name, element: box });
        this.updateGridLayout();
    }

    removeParticipantFromGrid(id) {
        const p = this.participants.get(id);
        if (p && p.element) {
            p.element.remove();
            this.participants.delete(id);
            this.updateGridLayout();
        }
    }

    updateGridLayout() {
        const grid = document.getElementById('mainVideoContainer');
        const count = grid.querySelectorAll('.participant-box').length;

        // Remove all grid-X classes
        grid.classList.remove('grid-1', 'grid-2', 'grid-3', 'grid-4', 'grid-5', 'grid-6', 'grid-7', 'grid-8', 'grid-9');

        // Add appropriate class
        const gridClass = count > 9 ? 'grid-9' : `grid-${count}`;
        grid.classList.add(gridClass);
    }

    featureParticipant(id) {
        if (this.role !== 'teacher') return;
        const p = this.participants.get(id);
        if (!p) return;

        const container = document.getElementById('featuredVideoContainer');
        const video = document.getElementById('featuredVideo');
        const nameEl = document.getElementById('featuredName');
        
        // Find the video element in the grid to get its stream
        const sourceVid = p.element.querySelector('video');
        if (sourceVid && sourceVid.srcObject) {
            video.srcObject = sourceVid.srcObject;
            nameEl.innerText = `${p.name} - Lövhə/Video`;
            container.classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();
        }
    }

    closeFeatured() {
        const container = document.getElementById('featuredVideoContainer');
        const video = document.getElementById('featuredVideo');
        if (container) container.classList.add('hidden');
        if (video) video.srcObject = null;
    }

    // --- PiP Management ---
    async togglePiP() {
        try {
            const video = document.getElementById('localPreviewVid');
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled) {
                await video.requestPictureInPicture();
            }
        } catch (error) {
            console.error("PiP Error:", error);
        }
    }

    async toggleScreen() {
        if (!this.isScreenOn) {
            try {
                this.screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { width: { ideal: 1920 }, height: { ideal: 1080 } }
                });

                const screenSource = document.getElementById('screenSource');
                if (screenSource) screenSource.srcObject = this.screenStream;

                this.isScreenOn = true;

                // Replace video track for all connected peers
                const screenTrack = this.screenStream.getVideoTracks()[0];
                this.replaceVideoTrack(screenTrack);

                screenTrack.onended = () => {
                    this.stopScreenShare();
                };

                this.updateMediaState();
                console.log("StudioMedia: Screen sharing started");
            } catch (err) {
                console.warn("StudioMedia: Screen share cancelled", err);
            }
        } else {
            this.stopScreenShare();
        }
        return this.isScreenOn;
    }

    replaceVideoTrack(newTrack) {
        // In compositing mode, we don't need to replace tracks for peers 
        // because they are watching the outputCanvas stream which we are drawing to.
        if (this.composingStream) return;

        if (!this.peer || !newTrack) return;

        // Iterate through all media calls and replace the video track
        Object.values(this.peer.connections).forEach(conns => {
            conns.forEach(conn => {
                if (conn.peerConnection) {
                    const senders = conn.peerConnection.getSenders();
                    const sender = senders.find(s => s.track && s.track.kind === 'video');
                    if (sender) {
                        sender.replaceTrack(newTrack);
                    }
                }
            });
        });
    }

    stopScreenShare() {
        if (this.screenStream) {
            this.screenStream.getTracks().forEach(t => t.stop());
            this.screenStream = null;
        }
        this.isScreenOn = false;

        // Revert to camera track
        if (this.camStream) {
            const camTrack = this.camStream.getVideoTracks()[0];
            if (camTrack) this.replaceVideoTrack(camTrack);
        }

        this.updateMediaState();
        console.log("StudioMedia: Screen sharing stopped");
    }

    updateMediaState() {
        if (this.camStream) {
            this.camStream.getAudioTracks().forEach(t => t.enabled = this.isMicOn);
            this.camStream.getVideoTracks().forEach(t => t.enabled = this.isCamOn);
        }

        // Update Studio Buttons
        const micBtns = [document.getElementById('btnMic'), document.getElementById('lobbyMicBtn')];
        const camBtns = [document.getElementById('btnCam'), document.getElementById('lobbyCamBtn')];
        const screenBtn = document.getElementById('btnScreen');

        micBtns.forEach(btn => {
            if (btn) {
                btn.classList.toggle('active-green', this.isMicOn);
                btn.classList.toggle('active-red', !this.isMicOn);
                const icon = btn.querySelector('i');
                if (icon) icon.setAttribute('data-lucide', 'mic');
            }
        });

        camBtns.forEach(btn => {
            if (btn) {
                btn.classList.toggle('active-green', this.isCamOn);
                btn.classList.toggle('active-red', !this.isCamOn);
                const icon = btn.querySelector('i');
                if (icon) icon.setAttribute('data-lucide', 'video');
            }
        });

        if (screenBtn) {
            screenBtn.classList.toggle('active-green', this.isScreenOn);
        }

        // Update UI Preview
        const localVid = document.getElementById('localPreviewVid');
        const lobbyVid = document.getElementById('lobbyPreview');
        const studentLobbyVid = document.getElementById('studentLobbyVideo');
        const camSource = document.getElementById('camSource');

        [localVid, lobbyVid, studentLobbyVid].forEach(vid => {
            if (vid) {
                if (this.isScreenOn && this.screenStream) {
                    if (vid.srcObject !== this.screenStream) {
                        vid.srcObject = this.screenStream;
                        vid.play().catch(e => console.warn("StudioMedia: Preview play failed", e));
                    }
                    vid.classList.remove('mirrored-video');
                } else if (this.camStream) {
                    if (vid.srcObject !== this.camStream) {
                        vid.srcObject = this.camStream;
                        vid.play().catch(e => console.warn("StudioMedia: Preview play failed", e));
                    }
                    vid.classList.add('mirrored-video');
                } else {
                    vid.srcObject = null;
                }
                vid.style.opacity = (this.isCamOn || this.isScreenOn) ? "1" : "0.2";
            }
        });

        if (camSource && !this.isScreenOn && this.camStream) {
            camSource.srcObject = this.camStream;
        }

        if (window.lucide) window.lucide.createIcons();
    }

    startAudioVisualizer(stream, elementId) {
        if (!stream) return;
        const audioTracks = stream.getAudioTracks();
        if (audioTracks.length === 0) return;

        if (!this.audioCtx) {
            this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }

        if (this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }

        if (!this.analyser) {
            this.analyser = this.audioCtx.createAnalyser();
            this.analyser.fftSize = 256;
        }

        try {
            if (this.audioSource) {
                this.audioSource.disconnect();
            }
            this.audioSource = this.audioCtx.createMediaStreamSource(stream);
            this.audioSource.connect(this.analyser);
        } catch (e) {
            console.warn("StudioMedia: Audio visualizer source error", e);
            return;
        }

        const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
        const container = document.getElementById(elementId);
        if (!container) return;

        const bars = container.querySelectorAll('div');
        if (bars.length === 0) return;

        this.visualizerActive = true;

        const draw = () => {
            if (!this.visualizerActive) {
                bars.forEach(b => b.style.height = '4px');
                return;
            }

            if (!this.isMicOn) {
                bars.forEach(b => b.style.height = '4px');
                requestAnimationFrame(draw);
                return;
            }

            requestAnimationFrame(draw);
            this.analyser.getByteFrequencyData(dataArray);

            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }
            let average = sum / dataArray.length;

            // Map average (0-255) to bar height (4px - 24px)
            const volume = Math.min(24, Math.max(4, average * 0.8));

            bars.forEach((bar, i) => {
                const height = Math.max(4, volume * (0.4 + Math.random() * 0.8));
                bar.style.height = `${height}px`;
            });
        };

        draw();
    }

    stopAudioVisualizer() {
        this.visualizerActive = false;
        if (this.audioSource) {
            this.audioSource.disconnect();
            this.audioSource = null;
        }
    }

    // --- Compositing Logic (Architecture from Old Code) ---
    startCompositing() {
        if (!this.outputCanvas) {
            console.warn("StudioMedia: No outputCanvas found for role", this.role);
            return;
        }
        
        const canvas = this.outputCanvas;
        const ctx = this.outputCtx;
        const targetW = 1920;
        const targetH = 1080;

        const draw = () => {
            if (canvas.width !== targetW || canvas.height !== targetH) {
                canvas.width = targetW;
                canvas.height = targetH;
            }

            // Fill background
            ctx.fillStyle = "#000";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            let mainSourceDrawn = false;

            // 1. Draw Whiteboard if active
            if (this.isWBActive && window.studioWhiteboard) {
                ctx.fillStyle = "#fff";
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                this.drawImageFit(ctx, window.studioWhiteboard.canvas, 0, 0, canvas.width, canvas.height);
                mainSourceDrawn = true;
            } 
            // 2. Or Screen Share
            else if (this.isScreenOn && this.screenStream) {
                const screenVid = document.getElementById('screenSource');
                if (screenVid && screenVid.readyState >= 2) {
                    this.drawImageFit(ctx, screenVid, 0, 0, canvas.width, canvas.height);
                    mainSourceDrawn = true;
                }
            }

            // 3. Draw Camera (as Main or PiP)
            const camVid = document.getElementById('camSource');
            if (this.isCamOn && camVid && camVid.readyState >= 2) {
                if (mainSourceDrawn) {
                    // PiP Mode (Bottom Right)
                    const pipW = 480;
                    const pipH = 270;
                    const margin = 40;
                    const x = canvas.width - pipW - margin;
                    const y = canvas.height - pipH - margin;

                    ctx.save();
                    ctx.shadowBlur = 30;
                    ctx.shadowColor = 'rgba(0,0,0,0.5)';
                    ctx.fillStyle = '#1e293b';
                    ctx.beginPath();
                    ctx.roundRect(x - 5, y - 5, pipW + 10, pipH + 10, 20);
                    ctx.fill();
                    ctx.clip();
                    this.drawImageCover(ctx, camVid, x, y, pipW, pipH);
                    ctx.restore();
                } else {
                    // Full Screen Mode
                    this.drawImageCover(ctx, camVid, 0, 0, canvas.width, canvas.height);
                }
            }
        };

        this.compositorInterval = setInterval(draw, 1000 / 30);
        draw();

        // Capture stream from canvas
        const canvasStream = canvas.captureStream(30);
        const sourceStream = (this.role === 'teacher') ? this.camStream : this.camStream; // Both use their own camStream for audio
        const audioTracks = sourceStream ? sourceStream.getAudioTracks() : [];
        this.composingStream = new MediaStream([...canvasStream.getVideoTracks(), ...audioTracks]);
        
        console.log(`StudioMedia: Compositing started for ${this.role}`);
    }

    drawImageCover(ctx, img, x, y, w, h) {
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

    drawImageFit(ctx, img, x, y, w, h) {
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

    swapViews() {
        if (this.role !== 'student') return;
        
        const remoteVid = document.getElementById('remoteVid');
        const localVid = document.getElementById('localVidMobile');
        const remoteName = document.querySelector('#teacherBox .participant-name');
        const localName = document.querySelector('#mobileLocalStageWrapper .text-white\\/90');

        if (!remoteVid || !localVid) return;

        this.isViewSwapped = !this.isViewSwapped;

        // Swap Streams
        const tempStream = remoteVid.srcObject;
        remoteVid.srcObject = localVid.srcObject;
        localVid.srcObject = tempStream;

        // Swap Mirroring
        // Self-view is mirrored by default, Remote is not.
        // If swapped: Remote (now local) is mirrored, Local (now remote) is not.
        if (this.isViewSwapped) {
            remoteVid.classList.add('mirrored-video');
            localVid.classList.remove('mirrored-video');
        } else {
            remoteVid.classList.remove('mirrored-video');
            localVid.classList.add('mirrored-video');
        }

        // Swap Name Tags
        if (remoteName && localName) {
            const tempName = remoteName.innerText;
            remoteName.innerText = localName.innerText;
            localName.innerText = tempName;
        }

        console.log("StudioMedia: Views swapped", this.isViewSwapped);
    }
}

// Global initialization helper
window.initStudioMedia = (config) => {
    window.studioMedia = new StudioMedia(config);
};
