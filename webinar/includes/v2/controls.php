<!-- includes/v2/controls.php -->
<div class="controls-bar">
    <!-- Media Controls -->
    <div class="control-item">
        <button id="btnMic" onclick="toggleMic()" class="btn-control active-green">
            <i data-lucide="mic"></i>
        </button>
        <span class="control-label">Səs</span>
    </div>

    <div class="control-item">
        <button id="btnCam" onclick="toggleCam()" class="btn-control active-green">
            <i data-lucide="video"></i>
        </button>
        <span class="control-label">Video</span>
    </div>

    <div class="w-px h-8 bg-white/10 mx-2"></div>

    <!-- Presentation Controls -->
    <div class="control-item">
        <button id="btnScreen" onclick="toggleScreen()" class="btn-control">
            <i data-lucide="monitor"></i>
        </button>
        <span class="control-label">Ekran</span>
    </div>

    <div class="control-item">
        <button id="btnWhiteboard" onclick="toggleWhiteboard()" class="btn-control">
            <i data-lucide="edit-3"></i>
        </button>
        <span class="control-label">Lövhə</span>
    </div>

    <div class="control-item">
        <button id="btnPiP" onclick="togglePiP()" class="btn-control">
            <i data-lucide="external-link"></i>
        </button>
        <span class="control-label">PiP</span>
    </div>

    <div class="w-px h-8 bg-white/10 mx-2"></div>

    <!-- More Controls -->
    <div class="control-item">
        <button id="btnSettings" onclick="openSettings()" class="btn-control">
            <i data-lucide="settings"></i>
        </button>
        <span class="control-label">Parametrlər</span>
    </div>
</div>