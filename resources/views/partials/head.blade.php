<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $metaTitle ?? ($title ?? config('app.name')) }}</title>
<meta name="description"
    content="{{ $metaDescription ?? 'Connect with verified artisans and service providers. Chat directly, view their work, and hire with confidence.' }}" />
<meta name="author" content="Allsers" />

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
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
<meta name="theme-color" content="#6a11cb" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="Allsers" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])

<!-- Leaflet Maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- OneSignal SDK -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(async function(OneSignal) {
        try {
            // Initialize OneSignal
            await OneSignal.init({
                appId: "{{ config('services.onesignal.app_id') }}",
                // safari_web_id: "web.onesignal.auto.00b75e31-4d41-4106-ab79-a5c68121f393",
                notifyButton: {
                    enable: false, // Disable the notify button
                },
                promptOptions: {
                    slidedown: {
                        enabled: true, // Enable slide-down prompt
                        timeDelay: 5, // Show after 5 seconds
                        autoPrompt: true, // Automatically show the prompt
                        actionMessage: "Subscribe to Allsers to receive real-time notifications for your messages and inquiries!",
                        acceptButtonText: "Subscribe",
                        cancelButtonText: "No, thanks",
                    },
                },
            });

            // Fetch the Subscription ID
            const subscriptionId = await OneSignal.User.PushSubscription.id;

            if (subscriptionId) {
                console.warn('OneSignal Subscription ID:', subscriptionId);
                // The actual saving to database is handled by the livewire:onesignal-handler
                // component which is already active and listening for this ID.
            } else {
                console.warn('No OneSignal Subscription ID available yet.');
            }
        } catch (error) {
            console.error('Error initializing OneSignal or retrieving Subscription ID:', error);
        }
    });
</script>

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
