/**
 * Scroll-into-view video autoplay.
 *
 * Any <video data-autoplay muted playsinline preload="metadata"> plays when at
 * least 60% visible and pauses when scrolled away. Videos start muted because
 * browsers reject autoplay with sound; the per-blade mute button (Alpine) lets
 * users unmute. If an unmuted play() is rejected, we fall back to muted.
 *
 * A MutationObserver re-attaches the IntersectionObserver to videos added by
 * Livewire (infinite scroll, modals, page navigations).
 */
const SELECTOR = 'video[data-autoplay]';
const THRESHOLD = 0.6;

function safePlay(video) {
    const attempt = video.play();

    if (attempt && typeof attempt.catch === 'function') {
        attempt.catch(() => {
            if (!video.muted) {
                video.muted = true;
                video.play().catch(() => {});
            }
        });
    }
}

function initVideoAutoplay() {
    const seen = new WeakSet();

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const video = entry.target;

            if (!video.isConnected) {
                observer.unobserve(video);
                return;
            }

            if (entry.isIntersecting) {
                safePlay(video);
            } else {
                video.pause();
            }
        });
    }, { threshold: THRESHOLD });

    const attach = (video) => {
        if (seen.has(video)) {
            return;
        }

        seen.add(video);
        observer.observe(video);
    };

    const scan = () => {
        document.querySelectorAll(SELECTOR).forEach(attach);
    };

    scan();

    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) {
            return;
        }
        scheduled = true;
        setTimeout(() => {
            scheduled = false;
            scan();
        }, 100);
    }).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('livewire:navigated', scan);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVideoAutoplay);
} else {
    initVideoAutoplay();
}
