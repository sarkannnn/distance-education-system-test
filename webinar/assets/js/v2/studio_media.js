/**
 * Webinar V2 - LiveKit Media Manager
 * Replaces PeerJS with LiveKit SFU architecture
 */

class StudioMedia {
    constructor(config) {
        this.wID = config.wID;
        this.uName = config.uName;
        this.role = config.role || 'teacher';
        this.room = null;
        this.camStream = null;
        this.screenStream = null;
        this.participants = new Map();

        this.isCamOn = false;
        this.isMicOn = false;
        this.isScreenOn = false;
        this.isWBActive = false;
        this.isViewSwapped = false;
        this.isOnStage = false;

        this.outputCanvas = document.getElementById(this.role === 'teacher' ? 'outputCanvas' : 'studentOutputCanvas');
        this.outputCtx = this.outputCanvas ? this.outputCanvas.getContext('2d', { alpha: false }) : null;
        this.composingStream = null;
        this.compositorInterval = null;

        this.init();
    }

    async init() {
        console.log("StudioMedia: Initializing LiveKit...");

        if (this.role === 'teacher') {
            await this.initTeacherMedia();
            this.startCompositing();
        }

        await this.connectToRoom();
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
            this.updateMediaState();
            return true;
        } catch (err) {
            console.warn("StudioMedia: Camera/Mic access denied", err);
            return false;
        }
    }

    // ==================== LiveKit Connection ====================

    async connectToRoom(publish = null) {
        try {
            const shouldPublish = publish !== null ? publish : (this.role === 'teacher');
            const url = `api/livekit_token.php?room=webinar-${this.wID}${shouldPublish ? '&publish=true' : ''}`;
            
            console.log("StudioMedia: Fetching LiveKit token...");
            const resp = await fetch(url);
            const data = await resp.json();

            if (!data.success) {
                console.error("StudioMedia: Token failed:", data.message);
                this.updateStatus('Token xətası', 'bg-rose-500');
                return false;
            }

            const serverUrl = data.serverUrl;
            
            // Disconnect old room if reconnecting
            if (this.room) {
                await this.room.disconnect();
            }

            this.room = new LivekitClient.Room({
                adaptiveStream: true,
                dynacast: true,
            });

            // --- Event Handlers ---
            this.room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, pub, participant) => {
                this.onTrackSubscribed(track, pub, participant);
            });

            this.room.on(LivekitClient.RoomEvent.TrackUnsubscribed, (track, pub, participant) => {
                track.detach();
                console.log("StudioMedia: Track unsubscribed from", participant.identity);
            });

            this.room.on(LivekitClient.RoomEvent.ParticipantConnected, (participant) => {
                console.log("StudioMedia: Participant joined:", participant.identity);
            });

            this.room.on(LivekitClient.RoomEvent.ParticipantDisconnected, (participant) => {
                console.log("StudioMedia: Participant left:", participant.identity);
                this.removeParticipantFromGrid(participant.identity);
            });

            this.room.on(LivekitClient.RoomEvent.DataReceived, (payload, participant) => {
                this.onDataReceived(payload, participant);
            });

            this.room.on(LivekitClient.RoomEvent.Disconnected, () => {
                console.log("StudioMedia: Disconnected from room");
                this.updateStatus('Bağlantı kəsildi', 'bg-rose-500');
            });

            this.room.on(LivekitClient.RoomEvent.Reconnecting, () => {
                this.updateStatus('Yenidən qoşulur...', 'bg-amber-500');
            });

            this.room.on(LivekitClient.RoomEvent.Reconnected, () => {
                this.updateStatus('CANLI', 'bg-emerald-500');
            });

            // --- Connect ---
            await this.room.connect(serverUrl, data.token);
            console.log("StudioMedia: Connected to room", this.room.name);
            this.updateStatus('CANLI', 'bg-emerald-500');

            // Publish tracks if teacher
            if (this.role === 'teacher') {
                await this.publishCompositorTrack();
            }

            // Handle already-connected participants (if we joined late)
            this.room.remoteParticipants.forEach((participant) => {
                participant.trackPublications.forEach((pub) => {
                    if (pub.track && pub.isSubscribed) {
                        this.onTrackSubscribed(pub.track, pub, participant);
                    }
                });
            });

            return true;
        } catch (err) {
            console.error("StudioMedia: LiveKit connection failed", err);
            this.updateStatus('Qoşulma xətası', 'bg-rose-500');
            return false;
        }
    }

    async publishCompositorTrack() {
        if (!this.composingStream) return;

        const videoTrack = this.composingStream.getVideoTracks()[0];
        const audioTrack = this.composingStream.getAudioTracks()[0];

        if (videoTrack) {
            await this.room.localParticipant.publishTrack(videoTrack, {
                name: 'composite',
                simulcast: false,
                source: LivekitClient.Track.Source.Camera,
            });
        }
        if (audioTrack) {
            await this.room.localParticipant.publishTrack(audioTrack, {
                name: 'audio',
                source: LivekitClient.Track.Source.Microphone,
            });
        }
        console.log("StudioMedia: Published compositor tracks to LiveKit");
    }

    // ==================== Track Subscription ====================

    onTrackSubscribed(track, publication, participant) {
        const isTeacherTrack = participant.identity.startsWith('teacher_') || participant.identity.startsWith('admin_');

        if (track.kind === LivekitClient.Track.Kind.Video) {
            if (this.role === 'student' && isTeacherTrack) {
                // Student receives teacher's video → show in main view
                const el = track.attach();
                el.id = 'remoteVidLK';
                const remoteVid = document.getElementById('remoteVid');
                if (remoteVid) {
                    remoteVid.srcObject = el.srcObject || track.mediaStream;
                    remoteVid.play().catch(() => {
                        const overlay = document.getElementById('unmuteOverlay');
                        if (overlay) overlay.classList.remove('hidden');
                    });
                }
            } else if (this.role === 'teacher') {
                // Teacher receives student's video → add to gallery
                const el = track.attach();
                const stream = el.srcObject || track.mediaStream;
                this.addParticipantToGrid(participant.identity, participant.name || participant.identity, stream);
                if (this.participants.size === 1) {
                    this.featureParticipant(participant.identity);
                }
            }
        }

        if (track.kind === LivekitClient.Track.Kind.Audio) {
            const el = track.attach();
            el.style.display = 'none';
            document.body.appendChild(el);
        }
    }

    // ==================== Data Channel (Chat) ====================

    onDataReceived(payload, participant) {
        try {
            const text = new TextDecoder().decode(payload);
            const data = JSON.parse(text);
            
            if (data.type === 'chat' && window.StudioChat) {
                window.StudioChat.appendMessage(data.sender, data.message);
            }
        } catch (e) {
            console.warn("StudioMedia: Invalid data received", e);
        }
    }

    broadcast(data) {
        if (!this.room) return;
        const encoded = new TextEncoder().encode(JSON.stringify(data));
        this.room.localParticipant.publishData(encoded, LivekitClient.DataPacket_Kind.RELIABLE);
    }

    // ==================== Student Stage ====================

    async previewStage() {
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
            if (lobbyVid) lobbyVid.srcObject = this.camStream;
            this.startAudioVisualizer(this.camStream, 'studentMicVisualizer');
            this.updateMediaState();
            return true;
        } catch (err) {
            console.error("StudioMedia: Preview error", err);
            alert("Kamera və ya mikrofona icazə verilməyib.");
            return false;
        }
    }

    async confirmJoinStage() {
        if (!this.camStream) return false;
        console.log("StudioMedia: Joining stage...");

        try {
            // Show local PiP
            const localVid = document.getElementById('localVidMobile');
            const wrapper = document.getElementById('mobileLocalStageWrapper');
            if (localVid && wrapper) {
                localVid.srcObject = this.camStream;
                wrapper.classList.remove('hidden');
            }

            // Start compositor for student
            if (!this.composingStream) this.startCompositing();

            // Reconnect with publish permissions and publish tracks
            await this.connectToRoom(true);
            await this.publishCompositorTrack();

            this.isOnStage = true;
            return true;
        } catch (err) {
            console.error("StudioMedia: Error joining stage", err);
            return false;
        }
    }

    async leaveStage() {
        console.log("StudioMedia: Leaving stage...");

        // Unpublish all local tracks
        if (this.room) {
            this.room.localParticipant.trackPublications.forEach((pub) => {
                if (pub.track) {
                    this.room.localParticipant.unpublishTrack(pub.track);
                }
            });
        }

        if (this.camStream) {
            this.camStream.getTracks().forEach(t => t.stop());
            this.camStream = null;
        }
        if (this.compositorInterval) {
            clearInterval(this.compositorInterval);
            this.compositorInterval = null;
        }
        this.composingStream = null;

        const wrapper = document.getElementById('mobileLocalStageWrapper');
        const localVid = document.getElementById('localVidMobile');
        if (wrapper) wrapper.classList.add('hidden');
        if (localVid) localVid.srcObject = null;

        this.isCamOn = false;
        this.isMicOn = false;
        this.isOnStage = false;

        // Reconnect as viewer (no publish)
        await this.connectToRoom(false);
    }

    // ==================== Media Controls ====================

    toggleMic() {
        this.isMicOn = !this.isMicOn;
        if (this.camStream) {
            this.camStream.getAudioTracks().forEach(t => t.enabled = this.isMicOn);
        }
        // Also mute/unmute in LiveKit
        if (this.room) {
            this.room.localParticipant.setMicrophoneEnabled(this.isMicOn).catch(() => {});
        }
        this.updateMediaState();
        return this.isMicOn;
    }

    toggleCam() {
        this.isCamOn = !this.isCamOn;
        if (this.camStream) {
            this.camStream.getVideoTracks().forEach(t => t.enabled = this.isCamOn);
        }
        this.updateMediaState();
        return this.isCamOn;
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
                this.screenStream.getVideoTracks()[0].onended = () => this.stopScreenShare();
                this.updateMediaState();
            } catch (err) {
                console.warn("StudioMedia: Screen share cancelled", err);
            }
        } else {
            this.stopScreenShare();
        }
        return this.isScreenOn;
    }

    stopScreenShare() {
        if (this.screenStream) {
            this.screenStream.getTracks().forEach(t => t.stop());
            this.screenStream = null;
        }
        this.isScreenOn = false;
        this.updateMediaState();
    }

    updateStatus(text, colorClass) {
        const el = document.getElementById('connectionStatus');
        if (el) {
            const span = el.querySelector('span');
            const dot = el.querySelector('div');
            if (span) span.innerText = text;
            if (dot) dot.className = `w-2 h-2 rounded-full ${colorClass}`;
        }
    }

    updateMediaState() {
        // Update buttons
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

        if (screenBtn) screenBtn.classList.toggle('active-green', this.isScreenOn);

        // Update previews
        const previews = ['localPreviewVid', 'lobbyPreview', 'studentLobbyVideo'].map(id => document.getElementById(id));
        const camSource = document.getElementById('camSource');

        previews.forEach(vid => {
            if (!vid) return;
            if (this.isScreenOn && this.screenStream) {
                if (vid.srcObject !== this.screenStream) { vid.srcObject = this.screenStream; vid.play().catch(() => {}); }
                vid.classList.remove('mirrored-video');
            } else if (this.camStream) {
                if (vid.srcObject !== this.camStream) { vid.srcObject = this.camStream; vid.play().catch(() => {}); }
                vid.classList.add('mirrored-video');
            } else {
                vid.srcObject = null;
            }
            vid.style.opacity = (this.isCamOn || this.isScreenOn) ? "1" : "0.2";
        });

        if (camSource && !this.isScreenOn && this.camStream) camSource.srcObject = this.camStream;
        if (window.lucide) window.lucide.createIcons();
    }

    // ==================== Gallery View ====================

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
        grid.classList.remove('grid-1','grid-2','grid-3','grid-4','grid-5','grid-6','grid-7','grid-8','grid-9');
        grid.classList.add(count > 9 ? 'grid-9' : `grid-${count}`);
    }

    featureParticipant(id) {
        if (this.role !== 'teacher') return;
        const p = this.participants.get(id);
        if (!p) return;
        const container = document.getElementById('featuredVideoContainer');
        const video = document.getElementById('featuredVideo');
        const nameEl = document.getElementById('featuredName');
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

    // ==================== PiP ====================

    async togglePiP() {
        try {
            const video = document.getElementById('localPreviewVid');
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled && video) {
                await video.requestPictureInPicture();
            }
        } catch (e) { console.error("PiP Error:", e); }
    }

    // ==================== Audio Visualizer ====================

    startAudioVisualizer(stream, elementId) {
        if (!stream || stream.getAudioTracks().length === 0) return;
        if (!this.audioCtx) this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
        if (!this.analyser) { this.analyser = this.audioCtx.createAnalyser(); this.analyser.fftSize = 256; }
        try {
            if (this.audioSource) this.audioSource.disconnect();
            this.audioSource = this.audioCtx.createMediaStreamSource(stream);
            this.audioSource.connect(this.analyser);
        } catch (e) { return; }

        const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
        const container = document.getElementById(elementId);
        if (!container) return;
        const bars = container.querySelectorAll('div');
        if (!bars.length) return;
        this.visualizerActive = true;

        const draw = () => {
            if (!this.visualizerActive) { bars.forEach(b => b.style.height = '4px'); return; }
            if (!this.isMicOn) { bars.forEach(b => b.style.height = '4px'); requestAnimationFrame(draw); return; }
            requestAnimationFrame(draw);
            this.analyser.getByteFrequencyData(dataArray);
            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) sum += dataArray[i];
            const volume = Math.min(24, Math.max(4, (sum / dataArray.length) * 0.8));
            bars.forEach(bar => { bar.style.height = `${Math.max(4, volume * (0.4 + Math.random() * 0.8))}px`; });
        };
        draw();
    }

    stopAudioVisualizer() {
        this.visualizerActive = false;
        if (this.audioSource) { this.audioSource.disconnect(); this.audioSource = null; }
    }

    // ==================== Compositor ====================

    startCompositing() {
        if (!this.outputCanvas) { console.warn("StudioMedia: No outputCanvas"); return; }
        const canvas = this.outputCanvas;
        const ctx = this.outputCtx;
        const W = 1920, H = 1080;

        const draw = () => {
            if (canvas.width !== W) { canvas.width = W; canvas.height = H; }
            ctx.fillStyle = "#000";
            ctx.fillRect(0, 0, W, H);
            let mainDrawn = false;

            // 1. Whiteboard
            if (this.isWBActive && window.studioWhiteboard) {
                ctx.fillStyle = "#fff";
                ctx.fillRect(0, 0, W, H);
                this.drawImageFit(ctx, window.studioWhiteboard.canvas, 0, 0, W, H);
                mainDrawn = true;
            }
            // 2. Screen Share
            else if (this.isScreenOn && this.screenStream) {
                const sv = document.getElementById('screenSource');
                if (sv && sv.readyState >= 2) { this.drawImageFit(ctx, sv, 0, 0, W, H); mainDrawn = true; }
            }

            // 3. Camera
            const cv = document.getElementById('camSource');
            if (this.isCamOn && cv && cv.readyState >= 2) {
                if (mainDrawn) {
                    const pw=480, ph=270, m=40, x=W-pw-m, y=H-ph-m;
                    ctx.save(); ctx.shadowBlur=30; ctx.shadowColor='rgba(0,0,0,0.5)';
                    ctx.fillStyle='#1e293b'; ctx.beginPath(); ctx.roundRect(x-5,y-5,pw+10,ph+10,20); ctx.fill(); ctx.clip();
                    this.drawImageCover(ctx, cv, x, y, pw, ph); ctx.restore();
                } else {
                    this.drawImageCover(ctx, cv, 0, 0, W, H);
                }
            }
        };

        this.compositorInterval = setInterval(draw, 1000/30);
        draw();

        const canvasStream = canvas.captureStream(30);
        const audioTracks = this.camStream ? this.camStream.getAudioTracks() : [];
        this.composingStream = new MediaStream([...canvasStream.getVideoTracks(), ...audioTracks]);
        console.log(`StudioMedia: Compositing started for ${this.role}`);
    }

    drawImageCover(ctx, img, x, y, w, h) {
        const iw = (img instanceof HTMLCanvasElement) ? img.width : img.videoWidth;
        const ih = (img instanceof HTMLCanvasElement) ? img.height : img.videoHeight;
        if (!iw || !ih) return;
        const a=w/h, ia=iw/ih; let sx,sy,sw,sh;
        if (ia>a) { sh=ih; sw=ih*a; sx=(iw-sw)/2; sy=0; } else { sw=iw; sh=iw/a; sx=0; sy=(ih-sh)/2; }
        ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
    }

    drawImageFit(ctx, img, x, y, w, h) {
        const iw = (img instanceof HTMLCanvasElement) ? img.width : img.videoWidth;
        const ih = (img instanceof HTMLCanvasElement) ? img.height : img.videoHeight;
        if (!iw || !ih) return;
        const ta=w/h, ia=iw/ih; let dw,dh,dx,dy;
        if (ia>ta) { dw=w; dh=w/ia; dx=x; dy=y+(h-dh)/2; } else { dh=h; dw=h*ia; dx=x+(w-dw)/2; dy=y; }
        ctx.drawImage(img, 0, 0, iw, ih, dx, dy, dw, dh);
    }

    // ==================== View Swap (Student) ====================

    swapViews() {
        if (this.role !== 'student') return;
        const remoteVid = document.getElementById('remoteVid');
        const localVid = document.getElementById('localVidMobile');
        if (!remoteVid || !localVid) return;
        this.isViewSwapped = !this.isViewSwapped;
        const tmp = remoteVid.srcObject;
        remoteVid.srcObject = localVid.srcObject;
        localVid.srcObject = tmp;
        if (this.isViewSwapped) { remoteVid.classList.add('mirrored-video'); localVid.classList.remove('mirrored-video'); }
        else { remoteVid.classList.remove('mirrored-video'); localVid.classList.add('mirrored-video'); }
    }
}

// Global init
window.initStudioMedia = (config) => {
    window.studioMedia = new StudioMedia(config);
};
