<div x-data="{
    notifications: [],
    userId: {{ auth()->check() ? auth()->id() : 'null' }},
    _listening: false,
    init() {
        if (!this.userId || !window.Echo) {
            const checkEcho = setInterval(() => {
                if (window.Echo && this.userId) {
                    clearInterval(checkEcho);
                    this.listen();
                }
            }, 500);
            setTimeout(() => clearInterval(checkEcho), 10000);
            return;
        }
        this.listen();
        this.$el._chatToastCleanup = () => this.leave();
        document.addEventListener('livewire:navigated', () => {
            if (this._listening) return;
            setTimeout(() => this.listen(), 300);
        });
    },
    leave() {
        if (!this._listening || !window.Echo || !this.userId) return;
        try { window.Echo.leave(`user.${this.userId}`); } catch {}
        try { window.Echo.private(`user.${this.userId}`).stopListening('.message.sent'); } catch {}
        this._listening = false;
    },
    listen() {
        if (this._listening) return;
        if (!window.Echo || !this.userId) return;
        try {
            this.leave();
            window.Echo.private(`user.${this.userId}`)
                .listen('.message.sent', (e) => {
                    const isOnChatPage = window.location.pathname.includes('/chat/' + e.conversation_id);
                    const isViewingThisConversation = isOnChatPage && document.visibilityState === 'visible';
                    if (window.location.pathname.startsWith('/chat') && window.Livewire) {
                        try { window.Livewire.dispatch('refreshChat'); } catch {}
                    }
                    if (isViewingThisConversation) return;
                    this.addChatToast(e);
                });
            this._listening = true;
            console.log('Chat toast listening on private-user.' + this.userId);
        } catch (err) {
            console.error('Echo listen error', err);
        }
    },
    addChatToast(e) {
        const id = Date.now() + Math.random();
        const notification = {
            id: id,
            sender_name: e.sender_name || 'Someone',
            sender_avatar: e.sender_avatar || null,
            content: e.content || (e.image_path ? 'Sent an image' : (e.document_path ? 'Sent a document' : 'New message')),
            conversation_id: e.conversation_id,
            url: e.url || `/chat/${e.conversation_id}`,
            type: e.type || 'text',
            translateX: 0,
            startX: 0,
            dragging: false,
            progress: 100,
        };
        this.notifications.push(notification);
        this.$nextTick(() => {
            const idx = this.notifications.findIndex(n => n.id === id);
            if (idx !== -1) this.startProgress(idx);
        });
        setTimeout(() => this.remove(id), 5000);
    },
    startProgress(idx) {
        const interval = setInterval(() => {
            if (!this.notifications[idx]) { clearInterval(interval); return; }
            this.notifications[idx].progress -= 2;
            if (this.notifications[idx].progress <= 0) {
                clearInterval(interval);
                this.remove(this.notifications[idx].id);
            }
        }, 100);
    },
    remove(id) {
        const idx = this.notifications.findIndex(n => n.id === id);
        if (idx !== -1) {
            this.notifications[idx].leaving = true;
            setTimeout(() => {
                this.notifications = this.notifications.filter(n => n.id !== id);
            }, 300);
        }
    },
    goToChat(notification) {
        window.location.href = notification.url;
    },
    touchStart(e, idx) {
        this.notifications[idx].startX = e.touches[0].clientX;
        this.notifications[idx].dragging = true;
    },
    touchMove(e, idx) {
        if (!this.notifications[idx].dragging) return;
        const currentX = e.touches[0].clientX;
        const diff = currentX - this.notifications[idx].startX;
        this.notifications[idx].translateX = diff;
    },
    touchEnd(e, idx) {
        const n = this.notifications[idx];
        if (!n) return;
        n.dragging = false;
        const threshold = 80;
        if (Math.abs(n.translateX) > threshold) {
            this.remove(n.id);
        } else {
            n.translateX = 0;
        }
    },
    mouseStart(e, idx) {
        this.notifications[idx].startX = e.clientX;
        this.notifications[idx].dragging = true;
    },
    mouseMove(e, idx) {
        if (!this.notifications[idx].dragging) return;
        const diff = e.clientX - this.notifications[idx].startX;
        this.notifications[idx].translateX = diff;
    },
    mouseEnd(e, idx) {
        const n = this.notifications[idx];
        if (!n) return;
        n.dragging = false;
        if (Math.abs(n.translateX) > 80) this.remove(n.id);
        else n.translateX = 0;
    }
}" x-init="init()" class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-3 pointer-events-none items-center">
    <template x-for="(notification, idx) in notifications" :key="notification.id">
        <div
            @touchstart="touchStart($event, idx)"
            @touchmove="touchMove($event, idx)"
            @touchend="touchEnd($event, idx)"
            @mousedown="mouseStart($event, idx)"
            @mousemove.window="mouseMove($event, idx)"
            @mouseup.window="mouseEnd($event, idx)"
            @click="goToChat(notification)"
            :style="`transform: translateX(${notification.translateX}px); opacity: ${1 - Math.min(Math.abs(notification.translateX)/200, 0.7)}; transition: ${notification.dragging ? 'none' : 'transform 0.3s ease, opacity 0.3s ease'}`"
            :class="notification.leaving ? 'opacity-0 translate-y-2 scale-95' : 'opacity-100'"
            class="pointer-events-auto w-[320px] sm:w-[380px] bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden cursor-pointer select-none touch-pan-y"
            x-transition:enter="transition ease-out duration-400"
            x-transition:enter-start="opacity-0 -translate-y-8 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-8 scale-95"
        >
            <div class="p-3 flex gap-3">
                <div class="shrink-0 size-10 rounded-full overflow-hidden flex items-center justify-center text-white font-bold text-sm"
                     :style="notification.sender_avatar ? '' : 'background-color: #6a11cb'">
                    <template x-if="notification.sender_avatar">
                        <img :src="notification.sender_avatar" class="size-full object-cover" alt="" />
                    </template>
                    <template x-if="!notification.sender_avatar">
                        <span x-text="(notification.sender_name || 'A').trim().charAt(0).toUpperCase()"></span>
                    </template>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate" x-text="notification.sender_name"></p>
                        <span class="text-[11px] text-zinc-400 whitespace-nowrap">now</span>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5" x-text="notification.content"></p>
                    <p class="text-xs font-medium text-purple-600 dark:text-purple-400 mt-1">Tap to open chat</p>
                </div>
            </div>
            <div class="h-1 bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full bg-purple-600 transition-all ease-linear" :style="`width: ${notification.progress}%`"></div>
            </div>
        </div>
    </template>
</div>
