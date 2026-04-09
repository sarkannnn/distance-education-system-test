<!-- Include Tailwind CSS uniquely for the Chatbot with Preflight disabled to protect existing dashboard styles -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false, // Ensures existing dashboard styles are NOT overridden
        }
    }
</script>
<style>
#chatbot-container {
            z-index: 99999;
        }

        /* Bulletproof theme overrides to protect against platform-specific global CSS */
        #chat-window {
            background-color: rgba(10, 26, 62, 0.98) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        #chat-window * {
            box-sizing: border-box;
            --tw-border-opacity: 1;
            --tw-bg-opacity: 1;
            --tw-text-opacity: 1;
        }

        .chat-window {
            transform-origin: bottom right;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .chat-window.hidden {
            opacity: 0;
            transform: scale(0.9) translateY(40px);
            pointer-events: none;
        }

        .chat-window.visible {
            opacity: 1;
            transform: scale(1) translateY(0);
            pointer-events: auto;
        }

        .chat-message {
            max-width: 88%;
            padding: 14px 18px;
            border-radius: 22px;
            font-size: 14px;
            line-height: 1.6;
            animation: messagePop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            position: relative;
            letter-spacing: -0.01em;
        }

        .chat-message-bot b, .chat-message-bot strong {
            color: #fff;
            font-weight: 700;
        }

        .chat-message-bot i, .chat-message-bot em {
            color: #cbd5e1;
        }

        .chat-message-user {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 6px;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
        }

        .chat-message-bot {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #e2e8f0;
            align-self: flex-start;
            border-bottom-left-radius: 6px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .source-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        .source-local { color: #34d399; background: rgba(52, 211, 153, 0.15); border: 1px solid rgba(52, 211, 153, 0.2); }
        .source-gemini { color: #60a5fa; background: rgba(96, 165, 250, 0.15); border: 1px solid rgba(96, 165, 250, 0.2); }
        .source-openai { color: #a78bfa; background: rgba(167, 139, 250, 0.15); border: 1px solid rgba(167, 139, 250, 0.2); }
        .source-fallback { color: #fbbf24; background: rgba(251, 191, 36, 0.15); border: 1px solid rgba(251, 191, 36, 0.2); }

        /* Scroll Indicators & Snap */
        .faq-categories, #chat-suggestions {
            scroll-snap-type: x mandatory;
            padding-left: 20px;
            padding-right: 20px;
        }

        .category-btn, .suggestion-btn {
            scroll-snap-align: start;
        }

        .faq-categories {
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            padding: 4px 15px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
            width: 100%;
            min-height: min-content;
            scroll-behavior: smooth;
            scroll-snap-type: x mandatory;
        }

        /* Desktop Scrollbar */
        @media (min-width: 640px) {
            .faq-categories::-webkit-scrollbar, #chat-suggestions::-webkit-scrollbar {
                height: 4px;
                display: block;
            }
            .faq-categories::-webkit-scrollbar-track, #chat-suggestions::-webkit-scrollbar-track {
                background: transparent;
            }
            .faq-categories::-webkit-scrollbar-thumb, #chat-suggestions::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.05);
                border-radius: 10px;
            }
            .faq-categories:hover::-webkit-scrollbar-thumb, #chat-suggestions:hover::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.15);
            }
        }

        @media (max-width: 640px) {
            .faq-categories::-webkit-scrollbar, #chat-suggestions::-webkit-scrollbar { display: none; }
        }

        .scroll-wrapper {
            position: relative;
            width: 100%;
            overflow: visible;
        }

        .scroll-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .scroll-wrapper:hover .scroll-arrow.visible {
            opacity: 1;
            pointer-events: auto;
        }

        .scroll-arrow:hover {
            background: rgba(37, 99, 235, 0.9);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-50%) scale(1.1);
        }

        .scroll-arrow-left { left: 4px; }
        .scroll-arrow-right { right: 4px; }

        @media (max-width: 768px) {
            .scroll-arrow { display: none !important; }
        }

        .category-btn {
            flex: 0 0 auto;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 12px;
            height: 38px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            user-select: none;
            scroll-snap-align: start;
        }

        .category-btn i { width: 20px; height: 20px; color: #60a5fa; flex-shrink: 0; }

        .category-btn:hover, .category-btn.active {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: white;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px -5px rgba(59, 130, 246, 0.4);
        }

        .category-btn.active i { color: #fff; }

        #chat-suggestions {
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            padding: 6px 15px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
        }
        #chat-suggestions::-webkit-scrollbar { display: none; }

        .suggestion-btn {
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
            color: #cbd5e1;
            transition: all 0.2s;
            cursor: pointer;
            user-select: none;
            scroll-snap-align: start;
        }

        .suggestion-btn:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }

        .typing-indicator span {
            display: inline-block;
            width: 4px;
            height: 4px;
            background: #64748b;
            border-radius: 50%;
            margin-right: 2px;
            animation: typing 1.4s infinite;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-4px); }
        }
</style>

<!-- Chatbot Widget HTML — Gemini AI Powered -->
    <div id="chatbot-container" class="fixed bottom-6 right-6 sm:bottom-10 sm:right-10 flex flex-col items-end gap-4 overflow-visible">
        <div id="chat-window" class="chat-window hidden w-[calc(100vw-48px)] sm:w-[400px] h-[580px] bg-[#0a1a3e]/98 backdrop-blur-2xl border border-white/10 rounded-[2.5rem] shadow-[0_30px_80px_rgba(0,0,0,0.5)] flex flex-col overflow-hidden">
            <!-- Header -->
            <div id="chat-header" class="cursor-move select-none p-3 bg-gradient-to-r from-blue-600/10 to-indigo-600/10 border-b border-white/10 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="relative w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-600/30">
                        <i data-lucide="bot" class="w-3.5 h-3.5 text-white"></i>
                        <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 bg-green-500 rounded-full border border-[#0a1a3e]"></span>
                    </div>
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-tight flex items-center gap-2">NDU Asistent
                            <span class="px-1.5 py-0.5 bg-gradient-to-r from-blue-500/20 to-indigo-500/20 border border-blue-400/20 rounded-md text-[8px] text-blue-300 font-bold uppercase tracking-widest">AI</span>
                        </h4>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button id="clear-chat" class="p-2 hover:bg-white/5 rounded-full transition-colors" title="Söhbəti təmizlə">
                        <i data-lucide="trash-2" class="w-4 h-4 text-white/30 hover:text-white/60"></i>
                    </button>
                    <button id="close-chat" class="p-2 hover:bg-white/5 rounded-full transition-colors" title="Bağla">
                        <i data-lucide="x" class="w-5 h-5 text-white/40"></i>
                    </button>
                </div>
            </div>
            <!-- Messages -->
            <div id="chat-messages" class="flex-1 overflow-y-auto p-5 flex flex-col gap-3 scrollbar-hide" data-lenis-prevent></div>
            <!-- FAQ Categories Scroll -->
            <div class="scroll-wrapper">
                <button class="scroll-arrow scroll-arrow-left" data-target="chat-categories">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <div id="chat-categories" class="faq-categories"></div>
                <button class="scroll-arrow scroll-arrow-right" data-target="chat-categories">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>
            <!-- Suggestions -->
            <div class="scroll-wrapper">
                <button class="scroll-arrow scroll-arrow-left" data-target="chat-suggestions">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <div id="chat-suggestions" class="px-3 py-1.5 border-t border-white/5 flex flex-nowrap gap-2 overflow-x-auto"></div>
                <button class="scroll-arrow scroll-arrow-right" data-target="chat-suggestions">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>
            <!-- Input -->
            <div class="p-3 bg-white/[0.03] border-t border-white/10">
                <form id="chat-form" class="flex gap-2 items-center">
                    <input type="text" id="chat-input" placeholder="Sualınızı yazın..." autocomplete="off"
                        class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-[12px] text-white focus:outline-none focus:border-blue-500/40 focus:bg-white/[0.07] transition-all placeholder:text-white/20">
                    <button type="submit" id="chat-send-btn" class="w-9 h-9 bg-blue-600 hover:bg-blue-500 disabled:opacity-40 disabled:cursor-not-allowed rounded-xl flex items-center justify-center shadow-lg shadow-blue-600/20 transition-all active:scale-90">
                        <i data-lucide="send" class="w-3.5 h-3.5 text-white"></i>
                    </button>
                </form>
            </div>
        </div>
        <!-- Floating Toggle Button -->
        <button id="chat-toggle" class="group relative w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-full shadow-lg shadow-blue-900/50 flex items-center justify-center hover:scale-110 active:scale-95 transition-all duration-300">
            <span class="absolute inset-0 bg-blue-600 rounded-full animate-ping opacity-15"></span>
            <i data-lucide="bot-message-square" class="w-6 h-6 sm:w-7 sm:h-7 text-white"></i>
        </button>
    </div>

    <script>
        // --- CHATBOT OPTIMIZED SYSTEM ---
        (function() {
            <?php
            // Create a unique identifier to isolate chat history per user account so data doesn't leak between users on same PC
            $chatUserPrefix = 'guest';
            if (session_status() === PHP_SESSION_ACTIVE) {
                if (isset($_SESSION['user_id'])) {
                    $chatUserPrefix = 'usr_' . $_SESSION['user_id'];
                } elseif (isset($_SESSION['student_id'])) {
                    $chatUserPrefix = 'std_' . $_SESSION['student_id'];
                } elseif (isset($_SESSION['teacher_id'])) {
                    $chatUserPrefix = 'tch_' . $_SESSION['teacher_id'];
                }
            }
            ?>
            const STORAGE_KEY = 'ndu_chat_history_<?php echo $chatUserPrefix; ?>';

            const chatToggle = document.getElementById('chat-toggle');
            const chatWindow = document.getElementById('chat-window');
            const chatHeader = document.getElementById('chat-header');
            const closeChat = document.getElementById('close-chat');
            const clearChat = document.getElementById('clear-chat');
            const chatForm = document.getElementById('chat-form');
            const chatInput = document.getElementById('chat-input');
            const chatMessages = document.getElementById('chat-messages');
            const chatCategories = document.getElementById('chat-categories');
            const chatSuggestions = document.getElementById('chat-suggestions');
            const chatSendBtn = document.getElementById('chat-send-btn');

            if (!chatToggle || !chatWindow) return;

            // Resolve dynamic base path to ensure it works globally in subdirectories
            let basePath = '';
            if (window.location.pathname.includes('/student')) {
                basePath = '../';
            } else if (window.location.pathname.includes('/teacher')) {
                basePath = '../';
            } else {
                // Determine typical roots, assuming it might be in a subfolder like /distant-tehsil/
                const pathParts = window.location.pathname.split('/');
                const appRootIndex = pathParts.indexOf('distant-tehsil');
                if (appRootIndex !== -1) {
                    basePath = '/' + pathParts.slice(1, appRootIndex + 1).join('/') + '/';
                } else {
                    basePath = '/';
                }
            }

            // Ensure consistent prefix for API calls
            const getApiUrl = (endpoint) => {
                if(basePath.startsWith('../')) {
                   return basePath + 'api/' + endpoint; 
                }
                return basePath.replace(/\/+$/, '') + '/api/' + endpoint;
            };

            let conversationHistory = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
            let localFaqData = { categories: [], faqs: [] };
            let currentCategoryId = 'dersler';
            let isProcessing = false;

            // Load Local FAQ Data
            async function loadLocalFaq() {
                try {
                    const res = await fetch(getApiUrl('data/local_qa_data.json'));
                    if (res.ok) {
                        localFaqData = await res.json();
                        renderCategories();
                        renderSuggestions('dersler');
                    }
                } catch (e) {
                    console.error("FAQ loading error:", e);
                }
            }

            function renderCategories() {
                chatCategories.innerHTML = '';
                localFaqData.categories.forEach(cat => {
                    const btn = document.createElement('div');
                    btn.className = `category-btn ${cat.id === currentCategoryId ? 'active' : ''}`;
                    btn.innerHTML = `<i data-lucide="${cat.icon}"></i> ${cat.name}`;
                    btn.onclick = () => {
                        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        currentCategoryId = cat.id;
                        renderSuggestions(cat.id);
                        
                        // Reset scroll to start when category changes
                        chatSuggestions.scrollTo({ left: 0, behavior: 'smooth' });
                    };
                    chatCategories.appendChild(btn);
                });
                if (window.lucide) lucide.createIcons();

                // Scroll discovery animation (peek)
                setTimeout(() => {
                    chatCategories.scrollTo({ left: 30, behavior: 'smooth' });
                    setTimeout(() => {
                        chatCategories.scrollTo({ left: 0, behavior: 'smooth' });
                    }, 500);
                }, 1000);
            }

            function renderSuggestions(categoryId) {
                chatSuggestions.innerHTML = '';
                const filtered = localFaqData.faqs.filter(f => f.category === categoryId);
                filtered.forEach(faq => {
                    const btn = document.createElement('button');
                    btn.className = 'suggestion-btn';
                    btn.innerText = faq.question;
                    btn.title = faq.question;
                    btn.onclick = () => {
                        if (isProcessing) return;
                        addMessage(faq.question, 'user');
                        // Local FAQ logic: skip API if we have exact match locally
                        handleLocalResponse(faq);
                    };
                    chatSuggestions.appendChild(btn);
                });
            }

            function handleLocalResponse(faq) {
                setProcessing(true);
                showTyping();
                setTimeout(() => {
                    removeTyping();
                    addMessage(faq.answer, 'bot', true, 'local');
                    setProcessing(false);
                }, 400);
            }

            function saveHistory() {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(conversationHistory));
            }

            function setProcessing(state) {
                isProcessing = state;
                chatSendBtn.disabled = state;
                chatInput.disabled = state;
                if (!state) chatInput.focus();
            }

            function addMessage(text, type = 'bot', save = true, source = null) {
                const msgDiv = document.createElement('div');
                msgDiv.className = `chat-message chat-message-${type}`;
                
                if (type === 'bot') {
                    msgDiv.innerHTML = text;
                    if (source) {
                        const badge = document.createElement('div');
                        badge.className = `source-badge source-${source}`;
                        let sourceText = source === 'local' ? 'Rəsmi Məlumat' : 
                                         source === 'fallback' ? 'Sistem Cavabı' : 'Süni İntellekt';
                        badge.innerHTML = `<i data-lucide="shield-check" style="width:10px;height:10px;"></i> ${sourceText}`;
                        msgDiv.appendChild(badge);
                    }
                } else {
                    msgDiv.textContent = text;
                }

                chatMessages.appendChild(msgDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;

                if (save) {
                    conversationHistory.push({ role: type === 'user' ? 'user' : 'model', text, source });
                    saveHistory();
                }

                if (window.lucide) lucide.createIcons();
            }

            function showTyping() {
                const typingDiv = document.createElement('div');
                typingDiv.id = 'typing-tmp';
                typingDiv.className = 'chat-message chat-message-bot';
                typingDiv.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
                chatMessages.appendChild(typingDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            function removeTyping() {
                const tmp = document.getElementById('typing-tmp');
                if (tmp) tmp.remove();
            }

            async function sendMessage(message) {
                setProcessing(true);
                showTyping();

                try {
                    const response = await fetch(getApiUrl('chatbot.php'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            message: message,
                            history: conversationHistory.slice(-10).map(h => ({role: h.role, text: h.text}))
                        })
                    });

                    removeTyping();
                    const data = await response.json();

                    if (data.success && data.reply) {
                        addMessage(data.reply, 'bot', true, data.source);
                    } else {
                        addMessage("Üzr istəyirəm, bir xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.", 'bot');
                    }
                } catch (err) {
                    removeTyping();
                    addMessage("Şəbəkə xətası. İnternet bağlantınızı yoxlayın.", 'bot');
                } finally {
                    setProcessing(false);
                }
            }

            function toggleChat(forceOpen = null) {
                const isOpen = forceOpen !== null ? forceOpen : chatWindow.classList.contains('hidden');
                if (isOpen) {
                    chatWindow.classList.remove('hidden');
                    chatWindow.classList.add('visible');
                    localStorage.setItem('ndu_chat_visibility', 'open');
                    
                    if (chatMessages.children.length === 0 && conversationHistory.length === 0) {
                        setTimeout(() => {
                            addMessage("Salam! 👋 NDU Distant Təhsil AI köməkçisiyəm. Nəyi öyrənmək istərdiniz?", 'bot');
                        }, 400);
                    }
                } else {
                    chatWindow.classList.add('hidden');
                    chatWindow.classList.remove('visible');
                    localStorage.setItem('ndu_chat_visibility', 'closed');
                }
            }

            chatToggle.onclick = () => toggleChat();
            closeChat.onclick = () => toggleChat(false);
            clearChat.onclick = () => {
                conversationHistory = [];
                saveHistory();
                chatMessages.innerHTML = '';
                addMessage("Söhbət təmizləndi.", 'bot');
            };

            chatForm.onsubmit = (e) => {
                e.preventDefault();
                const text = chatInput.value.trim();
                if (!text || isProcessing) return;
                addMessage(text, 'user');
                chatInput.value = '';
                sendMessage(text);
            };

            // Drag to scroll & Arrows logic
            [chatCategories, chatSuggestions].forEach(el => {
                if (!el) return;
                
                const wrapper = el.parentElement;
                const leftArrow = wrapper.querySelector('.scroll-arrow-left');
                const rightArrow = wrapper.querySelector('.scroll-arrow-right');

                const updateArrows = () => {
                    if (!leftArrow || !rightArrow) return;
                    const canScrollLeft = el.scrollLeft > 5;
                    const canScrollRight = el.scrollLeft < (el.scrollWidth - el.clientWidth - 5);
                    
                    leftArrow.classList.toggle('visible', canScrollLeft);
                    rightArrow.classList.toggle('visible', canScrollRight);
                };

                el.addEventListener('scroll', updateArrows);
                window.addEventListener('resize', updateArrows);
                setTimeout(updateArrows, 2000); // Initial check after content loads

                if (leftArrow) leftArrow.onclick = () => el.scrollBy({ left: -200, behavior: 'smooth' });
                if (rightArrow) rightArrow.onclick = () => el.scrollBy({ left: 200, behavior: 'smooth' });

                let isDown = false;
                let startX;
                let scrollLeft;

                el.addEventListener('mousedown', (e) => {
                    isDown = true;
                    startX = e.pageX - el.offsetLeft;
                    scrollLeft = el.scrollLeft;
                    el.style.cursor = 'grabbing';
                });

                el.addEventListener('mouseleave', () => {
                    isDown = false;
                });

                el.addEventListener('mouseup', () => {
                    isDown = false;
                    el.style.cursor = 'grab';
                });

                el.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - el.offsetLeft;
                    const walk = (x - startX) * 2;
                    el.scrollLeft = scrollLeft - walk;
                });

                // Mouse wheel support
                el.addEventListener('wheel', (e) => {
                    if (e.deltaY !== 0) {
                        e.preventDefault();
                        el.scrollLeft += e.deltaY * 1.5;
                        updateArrows();
                    }
                }, { passive: false });
            });

            // --- Drag & Drop Logic for Chat Window ---
            let isDragging = false;
            let dragOffsetX = 0;
            let dragOffsetY = 0;

            if (chatHeader && chatWindow) {
                chatHeader.addEventListener('mousedown', (e) => {
                    // Ignore clicks on close/clear buttons
                    if (e.target.closest('button')) return;

                    isDragging = true;
                    
                    // Switch to fixed positioning for dragging to separate from flow without interfering with transform animations
                    const rect = chatWindow.getBoundingClientRect();
                    chatWindow.style.position = 'fixed';
                    chatWindow.style.margin = '0';
                    chatWindow.style.bottom = 'auto';
                    chatWindow.style.right = 'auto';
                    
                    // Only set initial position if it's the first time we drag, otherwise rect jumping occurs
                    if (!chatWindow.style.left) {
                        chatWindow.style.left = rect.left + 'px';
                        chatWindow.style.top = rect.top + 'px';
                    }

                    dragOffsetX = e.clientX - rect.left;
                    dragOffsetY = e.clientY - rect.top;
                    
                    document.body.style.userSelect = 'none'; // Prevent text selection while dragging
                });

                document.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;
                    e.preventDefault();
                    
                    chatWindow.style.left = (e.clientX - dragOffsetX) + 'px';
                    chatWindow.style.top = (e.clientY - dragOffsetY) + 'px';
                });

                document.addEventListener('mouseup', () => {
                    if (isDragging) {
                        isDragging = false;
                        document.body.style.userSelect = '';
                    }
                });
            }

            // Init
            loadLocalFaq();
            if (conversationHistory.length > 0) {
                conversationHistory.forEach(msg => {
                    addMessage(msg.text, msg.role === 'user' ? 'user' : 'bot', false, msg.source);
                });
            }

            // Restore Chat Visibility State from LocalStorage
            if (localStorage.getItem('ndu_chat_visibility') === 'open') {
                toggleChat(true);
            }
            
        })();
    </script>

