<!-- includes/v2/lobby.php -->
<div id="lobbyOverlay" class="lobby-overlay">
    <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.1) 1px, transparent 0); background-size: 40px 40px;"></div>
    
    <div class="lobby-card">
        <div class="lobby-preview">
            <video id="lobbyPreview" autoplay playsinline muted class="w-full h-full object-cover scale-x-[-1]"></video>
            <div class="absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-black/90 via-black/40 to-transparent flex flex-col items-center gap-3">
                
                <!-- Audio Visualizer Wave -->
                <div id="lobbyMicVisualizer" class="flex items-center justify-center gap-1.5 h-8 w-24 bg-black/40 backdrop-blur-md rounded-full px-4 border border-white/10">
                    <div class="w-1.5 h-1 bg-emerald-400 rounded-full transition-all duration-75"></div>
                    <div class="w-1.5 h-2 bg-emerald-400 rounded-full transition-all duration-75"></div>
                    <div class="w-1.5 h-1 bg-emerald-400 rounded-full transition-all duration-75"></div>
                    <div class="w-1.5 h-3 bg-emerald-400 rounded-full transition-all duration-75"></div>
                    <div class="w-1.5 h-1 bg-emerald-400 rounded-full transition-all duration-75"></div>
                </div>
                
                <div class="flex justify-center gap-4">
                    <button onclick="toggleLobbyMic()" id="lobbyMicBtn" class="btn-control active-green w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-xl border-none transition-all hover:scale-110 active:scale-95">
                        <i data-lucide="mic"></i>
                    </button>
                    <button onclick="toggleLobbyCam()" id="lobbyCamBtn" class="btn-control active-green w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-xl border-none transition-all hover:scale-110 active:scale-95">
                        <i data-lucide="video"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="lobby-info">
            <div class="mb-10">
                <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.3em] mb-4 block">Yayım Öncesi Hazırlıq</span>
                <h2 class="text-4xl font-black text-white mb-4 tracking-tight">Studioya xoş gəlmisiniz</h2>
                <p class="text-white/40 font-medium leading-relaxed">
                    Yayıma başlamazdan öncə səs və görüntünüzü yoxlayın. Hazır olduğunuzda aşağıdakı düyməni sıxaraq canlıya keçin.
                </p>
            </div>

            <div class="space-y-6">
                <div class="flex items-center gap-4 p-4 bg-white/5 rounded-2xl border border-white/5">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                        <i data-lucide="info" class="w-5 h-5 text-blue-400"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-white/20 uppercase tracking-widest">Sessiya</p>
                        <p class="text-sm font-bold text-white"><?php echo e($webinar['title']); ?></p>
                    </div>
                </div>

                <button onclick="startWebinarNow()" class="w-full py-6 bg-emerald-500 hover:bg-emerald-400 text-white rounded-[2rem] font-black text-lg tracking-widest uppercase transition-all shadow-[0_20px_40px_rgba(16,185,129,0.3)] hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-4">
                    Yayıma Başla <i data-lucide="play" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="mt-10 flex items-center gap-3 opacity-20">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                <span class="text-[9px] font-black uppercase tracking-widest">TƏHLÜKƏSİZ YAYIM SİSTEMİ V2.0</span>
            </div>
        </div>
    </div>
</div>
