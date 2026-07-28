<div x-data="lightbox()"
    x-on:open-lightbox.window="open($event.detail)"
    x-show="show"
    x-transition.opacity.duration.200ms
    role="dialog"
    aria-modal="true"
    aria-label="Image viewer"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-zinc-950/90 dark:bg-black/90 select-none"
    x-cloak>
    <div class="absolute inset-0" @click.self="close()"></div>

    <button type="button" @click="close()"
        class="absolute top-4 right-4 z-10 size-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white/80 hover:text-white transition-all active:scale-90">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
            <path d="M6 18 18 6M6 6l12 12"/>
        </svg>
    </button>

    <div class="relative flex items-center justify-center w-full h-full p-4 sm:p-8"
        @touchstart="handleTouchStart"
        @touchend="handleTouchEnd">
        <template x-for="(img, i) in images" :key="i">
            <div x-show="i === currentIndex"
                x-transition:enter="transition-all duration-200 ease-out"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition-all duration-150 ease-in"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="flex items-center justify-center max-w-full max-h-full"
                x-cloak>
                <img :src="img"
                    :alt="'Image ' + (i + 1)"
                    class="max-w-full max-h-[85vh] w-auto h-auto object-contain rounded-lg shadow-2xl"
                    @contextmenu.prevent
                    x-on:error="$el.style.display='none'">
            </div>
        </template>

        <template x-if="!images.length">
            <div class="text-white/50 text-sm">No image to display</div>
        </template>
    </div>

    <template x-if="images.length > 1">
        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-3">
            <div class="flex items-center gap-1.5">
                <template x-for="(_, i) in images" :key="i">
                    <button type="button" @click="currentIndex = i"
                        :class="i === currentIndex ? 'bg-white w-5' : 'bg-white/40 hover:bg-white/60 w-1.5'"
                        class="h-1.5 rounded-full transition-all duration-300" :aria-label="'Go to image ' + (i + 1)"></button>
                </template>
            </div>
            <span class="text-white/60 text-xs font-medium ml-2" x-text="(currentIndex + 1) + ' / ' + images.length"></span>
        </div>
    </template>

    <template x-if="images.length > 1 && currentIndex > 0">
        <button type="button" @click="prev()"
            class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 size-10 sm:size-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white/80 hover:text-white transition-all active:scale-90 backdrop-blur-sm">
            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </button>
    </template>

    <template x-if="images.length > 1 && currentIndex < images.length - 1">
        <button type="button" @click="next()"
            class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 size-10 sm:size-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white/80 hover:text-white transition-all active:scale-90 backdrop-blur-sm">
            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                <path d="m9 18 6-6-6-6"/>
            </svg>
        </button>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('lightbox', () => ({
            show: false,
            images: [],
            currentIndex: 0,
            touchStartX: 0,
            touchEndX: 0,

            open(detail) {
                const imgs = (detail.images || []).filter(Boolean);
                this.images = imgs;
                this.currentIndex = detail.index ?? 0;
                if (this.images.length) {
                    this.show = true;
                }
            },

            close() {
                this.show = false;
            },

            prev() {
                if (this.currentIndex > 0) {
                    this.currentIndex--;
                }
            },

            next() {
                if (this.currentIndex < this.images.length - 1) {
                    this.currentIndex++;
                }
            },

            init() {
                document.addEventListener('keydown', (e) => {
                    if (!this.show) return;
                    if (e.key === 'Escape') this.close();
                    if (e.key === 'ArrowLeft') this.prev();
                    if (e.key === 'ArrowRight') this.next();
                });
            },

            handleTouchStart(e) {
                this.touchStartX = e.changedTouches[0].screenX;
            },

            handleTouchEnd(e) {
                this.touchEndX = e.changedTouches[0].screenX;
                const delta = this.touchStartX - this.touchEndX;
                if (Math.abs(delta) > 50) {
                    if (delta > 0) {
                        this.next();
                    } else {
                        this.prev();
                    }
                }
            },
        }));
    });
</script>
