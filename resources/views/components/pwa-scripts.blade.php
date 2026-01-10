<div id="pwa-install-prompt" class="fixed bottom-6 left-6 right-6 md:left-auto md:right-8 md:max-w-sm z-[100] transform translate-y-32 opacity-0 transition-all duration-500 ease-out pointer-events-none">
    <div class="bg-white dark:bg-zinc-900 rounded-[2rem] p-5 shadow-2xl border border-primary/10 flex flex-col gap-4 pointer-events-auto overflow-hidden relative">
        <div class="absolute top-0 right-0 p-8 bg-primary/5 rounded-full -mr-10 -mt-10 blur-2xl"></div>
        
        <div class="flex items-start gap-4 relative z-10">
            <div class="size-12 bg-primary rounded-2xl flex items-center justify-center shrink-0 shadow-lg shadow-primary/20">
                <img src="/favicon.svg" alt="Allsers" class="size-8">
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ __('Install Allsers App') }}</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">
                    {{ __('Access Allsers instantly from your home screen. It\'s fast, reliable, and works offline.') }}
                </p>
            </div>
        </div>

        <div class="flex gap-2 relative z-10 mt-1">
            <button id="pwa-install-btn" class="flex-1 bg-primary hover:bg-primary-dark text-white text-sm font-bold py-3 rounded-2xl transition-all shadow-button active:scale-95">
                {{ __('Install Now') }}
            </button>
            <button id="pwa-close-btn" class="px-5 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 text-sm font-bold py-3 rounded-2xl transition-all">
                {{ __('Maybe Later') }}
            </button>
        </div>
    </div>
</div>

<script>
    // Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('Service Worker registered', reg))
                .catch(err => console.log('Service Worker registration failed', err));
        });
    }

    // PWA Install Prompt Logic
    let deferredPrompt;
    const promptElement = document.getElementById('pwa-install-prompt');
    const installBtn = document.getElementById('pwa-install-btn');
    const closeBtn = document.getElementById('pwa-close-btn');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;

        // Check if user has already dismissed the prompt in this session
        if (sessionStorage.getItem('pwa_prompt_dismissed')) return;

        // Show the custom prompt after a short delay
        setTimeout(() => {
            promptElement.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
            promptElement.classList.add('translate-y-0', 'opacity-1');
        }, 3000);
    });

    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        
        // Show the install prompt
        deferredPrompt.prompt();
        
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        
        // We've used the prompt, and can't use it again
        deferredPrompt = null;
        
        // Hide our custom UI
        hidePrompt();
    });

    closeBtn.addEventListener('click', () => {
        hidePrompt();
        // Store dismissal in session so it doesn't show again until reload/new session
        sessionStorage.setItem('pwa_prompt_dismissed', 'true');
    });

    function hidePrompt() {
        promptElement.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
        promptElement.classList.remove('translate-y-0', 'opacity-1');
    }

    // Handle iOS users separately (they don't support beforeinstallprompt)
    const isIos = () => {
        const userAgent = window.navigator.userAgent.toLowerCase();
        return /iphone|ipad|ipod/.test(userAgent);
    }
    const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);

    if (isIos() && !isInStandaloneMode()) {
        if (!sessionStorage.getItem('pwa_prompt_dismissed')) {
            // Customize the prompt for iOS
            const promptTitle = promptElement.querySelector('h3');
            const promptDesc = promptElement.querySelector('p');
            const installButtonText = promptElement.querySelector('#pwa-install-btn');

            promptTitle.textContent = "Add to Home Screen";
            promptDesc.textContent = "Tap the share icon in your browser and select 'Add to Home Screen' to install Allsers.";
            installButtonText.style.display = 'none'; // iOS users have to do it manually

            setTimeout(() => {
                promptElement.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
                promptElement.classList.add('translate-y-0', 'opacity-1');
            }, 5000);
        }
    }
</script>
