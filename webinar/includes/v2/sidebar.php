<!-- includes/v2/sidebar.php -->
<aside class="studio-sidebar w-[380px]">
    <div class="sidebar-tabs">
        <button onclick="switchTab('chat')" id="tabBtnChat" class="tab-btn active">
            <i data-lucide="message-square" class="w-4 h-4"></i> Çat
        </button>
        <button onclick="switchTab('participants')" id="tabBtnParticipants" class="tab-btn">
            <i data-lucide="users" class="w-4 h-4"></i> Qoşulanlar
        </button>
    </div>

    <!-- Chat Content -->
    <div id="tabChat" class="flex-1 flex flex-col overflow-hidden">
        <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
            <div class="text-center py-10 opacity-20">
                <i data-lucide="messages-square" class="mx-auto w-10 h-10 mb-4"></i>
                <p class="text-[10px] font-black uppercase tracking-widest">Müzakirəyə başlayın</p>
            </div>
        </div>

        <div class="p-6 bg-black/20 border-t border-white/5">
            <div class="relative">
                <input type="text" id="chatInput" placeholder="Mesaj yazın..." 
                    class="w-full bg-white/5 border border-white/10 rounded-2xl pl-6 pr-14 py-4 text-sm focus:outline-none focus:border-emerald-500/50 transition-all font-medium placeholder:text-white/10">
                <button onclick="sendChat()" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center hover:bg-emerald-400 transition-all active:scale-95 shadow-lg shadow-emerald-500/20">
                    <i data-lucide="send" class="w-4 h-4 text-white"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Participants Content -->
    <div id="tabParticipants" class="hidden flex-1 flex flex-col overflow-hidden">
        <div class="p-6 border-b border-white/5 flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-widest text-emerald-400">İştirakçılar</span>
            <span id="participantCount" class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-[10px] font-bold rounded-full">0</span>
        </div>
        <div id="participantList" class="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">
            <!-- Dynamic list -->
        </div>
    </div>

    <!-- Participant Self-View (Moved from Main Stage) -->
    <div id="mobileLocalStageWrapper" class="hidden p-4 border-t border-white/5 bg-black/20">
        <div class="relative aspect-video bg-[#06112a] rounded-2xl overflow-hidden shadow-2xl border border-white/10 group">
            <video id="localVidMobile" autoplay playsinline muted class="w-full h-full object-cover scale-x-[-1] mirrored-video"></video>
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent opacity-60 pointer-events-none"></div>
            
            <!-- Swap/Maximize Button -->
            <button onclick="window.studioMedia.swapViews()" class="absolute top-2 right-2 w-8 h-8 bg-black/40 backdrop-blur-md rounded-lg flex items-center justify-center text-white/70 hover:text-white opacity-0 group-hover:opacity-100 transition-all z-10" title="Böyüt">
                <i data-lucide="maximize-2" class="w-4 h-4"></i>
            </button>

            <div class="absolute bottom-2 left-3 flex items-center gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]"></div>
                <span class="text-[9px] text-white/90 font-black tracking-widest uppercase">Siz (İştirakçı)</span>
            </div>
        </div>
    </div>
</aside>
