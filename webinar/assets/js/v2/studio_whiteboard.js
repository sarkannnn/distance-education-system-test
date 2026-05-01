/**
 * Webinar V2 - Advanced Whiteboard Manager
 */

class StudioWhiteboard {
    constructor(canvasId, role = 'teacher') {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
        this.role = role;
        
        this.isDrawing = false;
        this.tool = 'pencil';
        this.color = '#000000';
        this.size = 4;
        this.eraserSize = 30;
        
        this.lastX = 0;
        this.lastY = 0;
        this.startX = 0;
        this.startY = 0;
        
        this.pages = [null];
        this.currentPage = 0;
        this.snapshot = null;
        this.history = [];
        
        this.laserActive = false;
        this.laserX = 0;
        this.laserY = 0;

        if (this.canvas) {
            this.init();
        } else {
            console.warn("StudioWhiteboard: Canvas not found", canvasId);
        }
        console.log("StudioWhiteboard: Initialized for", role);
    }

    init() {
        this.resize();
        window.addEventListener('resize', () => this.resize());
        this.addEventListeners();
    }

    resize() {
        const container = this.canvas.parentElement;
        if (!container) return;
        
        // Save current content before resize
        let temp = null;
        if (this.canvas.width > 0 && this.canvas.height > 0) {
            temp = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        }

        this.canvas.width = container.clientWidth;
        this.canvas.height = container.clientHeight;
        
        if (temp) this.ctx.putImageData(temp, 0, 0);

        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
    }

    addEventListeners() {
        const getXY = (e) => {
            const rect = this.canvas.getBoundingClientRect();
            let clientX, clientY;
            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }
            return {
                x: clientX - rect.left,
                y: clientY - rect.top,
                rawX: clientX,
                rawY: clientY
            };
        };

        const start = (e) => {
            const { x, y } = getXY(e);
            if (this.tool === 'laser') return;
            if (this.tool === 'text') {
                this.drawText(x, y);
                return;
            }

            this.saveState();
            this.isDrawing = true;
            this.startX = x;
            this.startY = y;
            this.lastX = x;
            this.lastY = y;
            this.snapshot = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        };

        const move = (e) => {
            const { x, y, rawX, rawY } = getXY(e);
            
            if (this.tool === 'laser') {
                this.updateLaser(x, y, rawX, rawY);
                return;
            } else {
                this.hideLaser();
            }

            if (!this.isDrawing) return;

            if (this.tool === 'pencil' || this.tool === 'eraser') {
                this.drawFreehand(x, y);
            } else {
                this.ctx.putImageData(this.snapshot, 0, 0);
                this.drawShape(x, y);
            }
        };

        const stop = () => {
            if (this.isDrawing) {
                this.isDrawing = false;
                this.snapshot = null;
            }
        };

        this.canvas.addEventListener('mousedown', start);
        this.canvas.addEventListener('mousemove', move);
        this.canvas.addEventListener('mouseup', stop);
        this.canvas.addEventListener('mouseout', () => {
            stop();
            this.hideLaser();
        });

        this.canvas.addEventListener('touchstart', (e) => { e.preventDefault(); start(e); }, { passive: false });
        this.canvas.addEventListener('touchmove', (e) => { e.preventDefault(); move(e); }, { passive: false });
        this.canvas.addEventListener('touchend', stop);
    }

    drawFreehand(x, y) {
        this.ctx.beginPath();
        this.ctx.moveTo(this.lastX, this.lastY);
        this.ctx.lineTo(x, y);

        if (this.tool === 'eraser') {
            this.ctx.globalCompositeOperation = 'destination-out';
            this.ctx.lineWidth = this.eraserSize;
        } else {
            this.ctx.globalCompositeOperation = 'source-over';
            this.ctx.strokeStyle = this.color;
            this.ctx.lineWidth = this.size;
        }

        this.ctx.stroke();
        this.ctx.globalCompositeOperation = 'source-over';
        
        this.lastX = x;
        this.lastY = y;
    }

    drawShape(x, y) {
        this.ctx.beginPath();
        this.ctx.strokeStyle = this.color;
        this.ctx.lineWidth = this.size;

        if (this.tool === 'line') {
            this.ctx.moveTo(this.startX, this.startY);
            this.ctx.lineTo(x, y);
        } else if (this.tool === 'rect') {
            this.ctx.strokeRect(this.startX, this.startY, x - this.startX, y - this.startY);
        } else if (this.tool === 'circle') {
            let r = Math.sqrt(Math.pow(x - this.startX, 2) + Math.pow(y - this.startY, 2));
            this.ctx.arc(this.startX, this.startY, r, 0, 2 * Math.PI);
        } else if (this.tool === 'arrow') {
            const headlen = 15;
            const angle = Math.atan2(y - this.startY, x - this.startX);
            this.ctx.moveTo(this.startX, this.startY);
            this.ctx.lineTo(x, y);
            this.ctx.lineTo(x - headlen * Math.cos(angle - Math.PI / 6), y - headlen * Math.sin(angle - Math.PI / 6));
            this.ctx.moveTo(x, y);
            this.ctx.lineTo(x - headlen * Math.cos(angle + Math.PI / 6), y - headlen * Math.sin(angle + Math.PI / 6));
        }
        this.ctx.stroke();
    }

    drawText(x, y) {
        const txt = prompt("Mətn daxil edin:");
        if (txt) {
            this.ctx.font = "bold 24px 'Inter', sans-serif";
            this.ctx.fillStyle = this.color;
            this.ctx.fillText(txt, x, y);
        }
    }

    updateLaser(x, y, rawX, rawY) {
        this.laserActive = true;
        this.laserX = x;
        this.laserY = y;
        
        const laserEl = document.getElementById('laserCursor');
        if (laserEl) {
            laserEl.style.display = 'block';
            laserEl.style.left = rawX + 'px';
            laserEl.style.top = rawY + 'px';
        }
        
        if (this.role === 'teacher') {
            this.emitData({
                type: 'whiteboard',
                action: 'laser',
                data: { x: x / this.canvas.width, y: y / this.canvas.height, active: true }
            });
        }
    }

    hideLaser() {
        this.laserActive = false;
        const laserEl = document.getElementById('laserCursor');
        if (laserEl) laserEl.style.display = 'none';
        
        if (this.role === 'teacher') {
            this.emitData({
                type: 'whiteboard',
                action: 'laser',
                data: { active: false }
            });
        }
    }

    saveState() {
        this.history.push(this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height));
        if (this.history.length > 30) this.history.shift();
    }

    undo() {
        if (this.history.length === 0) return;
        this.ctx.putImageData(this.history.pop(), 0, 0);
        this.emitState();
    }

    clear(emit = true) {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.history = [];
        if (emit) {
            this.emitData({ type: 'whiteboard', action: 'clear' });
        }
    }

    // Page System
    addNewPage() {
        this.pages[this.currentPage] = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        this.pages.push(null);
        this.currentPage = this.pages.length - 1;
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.updatePageIndicator();
        this.emitState();
    }

    prevPage() {
        if (this.currentPage > 0) {
            this.pages[this.currentPage] = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            this.currentPage--;
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            if (this.pages[this.currentPage]) this.ctx.putImageData(this.pages[this.currentPage], 0, 0);
            this.updatePageIndicator();
            this.emitState();
        }
    }

    nextPage() {
        if (this.currentPage < this.pages.length - 1) {
            this.pages[this.currentPage] = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            this.currentPage++;
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            if (this.pages[this.currentPage]) this.ctx.putImageData(this.pages[this.currentPage], 0, 0);
            this.updatePageIndicator();
            this.emitState();
        }
    }

    updatePageIndicator() {
        const el = document.getElementById('pageIndicator');
        if (el) el.innerText = `${this.currentPage + 1} / ${this.pages.length}`;
    }

    setTool(tool) {
        this.tool = tool;
        if (tool === 'eraser') {
            this.setLineWidth(this.eraserSize);
        } else {
            this.setLineWidth(this.size);
        }
    }

    setColor(color) {
        this.color = color;
    }

    setLineWidth(width) {
        if (this.tool === 'eraser') {
            this.eraserSize = width;
        } else {
            this.size = width;
        }
    }

    setGrid(active) {
        this.canvas.style.backgroundImage = active ? 
            'linear-gradient(#94a3b8 1px, transparent 1px), linear-gradient(90deg, #94a3b8 1px, transparent 1px)' : 
            'none';
        this.canvas.style.backgroundSize = '30px 30px';
    }

    // Communication (Disabled per user request for separate systems)
    emitPath(x1, y1, x2, y2) {}
    emitState() {}
    emitData(data) {}

    handleIncomingData(data) {
        // Data sync disabled per request. 
        // Whiteboards are now shared via video stream compositor.
    }
}

window.initStudioWhiteboard = (canvasId, role) => {
    window.studioWhiteboard = new StudioWhiteboard(canvasId, role);
};
