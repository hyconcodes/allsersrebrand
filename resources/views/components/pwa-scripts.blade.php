<div id="pwa-install-prompt" class="hidden fixed bottom-[5.5rem] sm:bottom-10 left-6 right-6 lg:left-auto lg:right-10 lg:max-w-sm z-[9999] transform translate-y-24 opacity-50 transition-all duration-700 ease-out">
    <div class="bg-white dark:bg-zinc-900 rounded-xl p-6 border border-zinc-100 dark:border-zinc-800 flex flex-col gap-5 pointer-events-auto overflow-hidden relative group">
        <div class="absolute top-0 right-0 p-12 bg-[var(--color-brand-purple)]/10 rounded-full -mr-16 -mt-16 blur-3xl transition-all group-hover:bg-[var(--color-brand-purple)]/20"></div>
        
        <div class="flex items-center gap-5 relative z-10">
            <div class="size-14 bg-gradient-to-tr from-[var(--color-brand-purple)] to-purple-500 rounded-2xl flex items-center justify-center shrink-0">
                <img src="{{ asset('favicon.ico') }}" alt="Allsers" class="size-9">
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-lg tracking-tight">{{ __('Install Allsers') }}</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 leading-relaxed font-medium">
                    {{ __('Access Allsers from your home screen just like a native app.') }}
                </p>
            </div>
        </div>

        <div class="flex gap-3 relative z-10 mt-1">
            <button id="pwa-install-btn" class="flex-1 bg-[var(--color-brand-purple)] hover:opacity-90 text-white text-sm font-bold py-3.5 rounded-2xl transition-all active:scale-95">
                {{ __('Install Now') }}
            </button>
            <button id="pwa-close-btn" class="px-6 bg-zinc-50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 dark:text-zinc-400 text-sm font-bold py-3.5 rounded-2xl transition-all active:scale-95">
                {{ __('Later') }}
            </button>
        </div>
    </div>
</div>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js');
        });
    }

    let deferredPrompt;
    const promptElement = document.getElementById('pwa-install-prompt');
    const installBtn = document.getElementById('pwa-install-btn');
    const closeBtn = document.getElementById('pwa-close-btn');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        if (sessionStorage.getItem('pwa_prompt_dismissed')) return;

        setTimeout(() => {
            promptElement.classList.remove('hidden', 'translate-y-32', 'opacity-0', 'pointer-events-none');
            promptElement.classList.add('translate-y-0', 'opacity-1');
        }, 3000);
    });

    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        hidePrompt();
    });

    closeBtn.addEventListener('click', () => {
        hidePrompt();
        sessionStorage.setItem('pwa_prompt_dismissed', 'true');
    });

    function hidePrompt() {
        promptElement.classList.add('translate-y-100', 'opacity-0', 'pointer-events-none', 'hidden');
        promptElement.classList.remove('translate-y-0', 'opacity-1');
    }

    const isIos = () => /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    const isInStandaloneMode = () => ('standalone' in window.navigator) && window.navigator.standalone;

    if (isIos() && !isInStandaloneMode()) {
        if (!sessionStorage.getItem('pwa_prompt_dismissed')) {
            const promptTitle = promptElement.querySelector('h3');
            const promptDesc = promptElement.querySelector('p');
            const installButtonText = promptElement.querySelector('#pwa-install-btn');

            promptTitle.textContent = "Add to Home Screen";
            promptDesc.textContent = "Tap the share icon in your browser and select 'Add to Home Screen' to install Allsers.";
            installButtonText.style.display = 'none';

            setTimeout(() => {
                promptElement.classList.remove('hidden', 'translate-y-32', 'opacity-50', 'pointer-events-none');
                promptElement.classList.add('translate-y-0', 'opacity-1');
            }, 5000);
        }
    }
</script>
