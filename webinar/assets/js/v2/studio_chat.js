/**
 * Webinar V2 - Chat & Participant Manager
 */

class StudioChat {
    constructor() {
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.participantCount = document.getElementById('participantCount');
    }

    appendMessage(sender, message, isSystem = false) {
        if (!this.chatMessages) return;

        const div = document.createElement('div');
        div.className = `flex flex-col ${isSystem ? 'items-center' : 'items-start'} mb-4`;
        
        if (isSystem) {
            div.innerHTML = `<span class="bg-white/5 px-3 py-1 rounded-full text-[10px] text-white/20 font-bold uppercase tracking-widest">${message}</span>`;
        } else {
            div.innerHTML = `
                <span class="text-[9px] font-black text-white/20 uppercase tracking-widest mb-1">${sender}</span>
                <div class="bg-white/5 border border-white/5 rounded-2xl px-4 py-2 text-sm text-white/80 max-w-[80%] break-words">
                    ${message}
                </div>
            `;
        }

        this.chatMessages.appendChild(div);
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;

        // Remove empty state if present
        const emptyState = this.chatMessages.querySelector('.opacity-20');
        if (emptyState) emptyState.remove();
    }

    updateParticipantCount(count) {
        if (this.participantCount) {
            this.participantCount.innerText = count;
        }
    }

    sendMessage() {
        const msg = this.chatInput.value.trim();
        if (!msg) return;

        // Append to local UI
        this.appendMessage('Mən', msg);
        this.chatInput.value = '';

        // Broadcast via StudioMedia
        if (window.studioMedia) {
            window.studioMedia.broadcast({
                type: 'chat',
                sender: window.studioMedia.uName,
                message: msg
            });
        }
    }
}

// Global initialization
window.StudioChat = new StudioChat();

// Event listeners
document.getElementById('chatInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') window.StudioChat.sendMessage();
});

window.sendChat = () => window.StudioChat.sendMessage();
