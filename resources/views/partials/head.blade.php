<style>[x-cloak]{display:none!important}</style>
<script>
    (function() {
        var theme = localStorage.getItem('flux__theme') || 'light';
        document.documentElement.classList.remove('dark', 'light');
        document.documentElement.classList.add(theme);
    })();
</script>
<meta charset="utf-8" />
<meta name="viewport"
    content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="robots"
    content="{{ $metaRobots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }}" />

<title>{{ $metaTitle ?? ($title ?? config('app.name')) }}</title>
<meta name="description"
    content="{{ $metaDescription ?? 'Connect with verified artisans and service providers. Chat directly, view their work, and hire with confidence.' }}" />
<meta name="author" content="Allsers" />

<!-- Open Graph / Facebook -->
<meta property="og:type" content="{{ $metaType ?? 'website' }}">
<meta property="og:url" content="{{ $metaUrl ?? url()->current() }}">
<meta property="og:title" content="{{ $metaTitle ?? ($title ?? config('app.name')) }}">
<meta property="og:description"
    content="{{ $metaDescription ?? 'Connect with verified artisans and service providers.' }}">
<meta property="og:image" content="{{ $metaImage ?? asset('assets/allsers.png') }}">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="{{ $metaUrl ?? url()->current() }}">
<meta property="twitter:title" content="{{ $metaTitle ?? ($title ?? config('app.name')) }}">
<meta property="twitter:description"
    content="{{ $metaDescription ?? 'Connect with verified artisans and service providers.' }}">
<meta property="twitter:image" content="{{ $metaImage ?? asset('assets/allsers.png') }}">

<link rel="canonical" href="{{ $metaUrl ?? url()->current() }}" />
<link rel="manifest" href="/manifest.json" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="theme-color" content="#6a11cb" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="Allsers" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@auth
<link rel="prefetch" href="{{ route('dashboard') }}">
<link rel="prefetch" href="{{ route('notifications') }}">
<link rel="prefetch" href="{{ route('bookmarks') }}">
<link rel="prefetch" href="{{ route('finder') }}">
<link rel="prefetch" href="{{ route('chat') }}">
<link rel="prefetch" href="{{ route('lila') }}">
@endauth

@vite(['resources/css/app.css', 'resources/js/app.js'])

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<!-- Leaflet Maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- OneSignal SDK DISABLED — migrated to native Web Push (VAPID) -->
{{-- <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(async function(OneSignal) {
        try {
            await OneSignal.init({
                appId: "{{ config('services.onesignal.app_id') }}",
                notifyButton: { enable: false },
                promptOptions: {
                    slidedown: {
                        enabled: true,
                        timeDelay: 5,
                        autoPrompt: true,
                        actionMessage: "Subscribe to Allsers to receive real-time notifications for your messages and inquiries!",
                        acceptButtonText: "Subscribe",
                        cancelButtonText: "No, thanks",
                    },
                },
            });
            const subscriptionId = await OneSignal.User.PushSubscription.id;
            if (subscriptionId) {
                console.warn('OneSignal Subscription ID:', subscriptionId);
            } else {
                console.warn('No OneSignal Subscription ID available yet.');
            }
        } catch (error) {
            console.error('Error initializing OneSignal:', error);
        }
    });
</script> --}}
<meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}" />

@fluxAppearance
@snowfall

<!-- SVG Gradient for Map Polyline -->
<svg style="width:0;height:0;position:absolute;" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="line-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#3B82F6" />
            <stop offset="100%" stop-color="#6D28D9" />
        </linearGradient>
    </defs>
</svg>
