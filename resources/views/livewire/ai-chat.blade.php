<?php

use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use App\Tools\FindArtisansTool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\User;
use App\Mail\ServiceInquiryMail;
use App\Notifications\ServiceInquiry;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $isOpen = false;
    public $messages = [];
    public $input = '';
    public $isThinking = false;
    public $hasBeenClosed = false;
    public $lat = null;
    public $lng = null;
    public $address = null;
    public $showLocationRequest = false;
    public $completion = null;
    public $sentPings = [];
    public $pingingId = null;

    public function mount()
    {
        $this->messages = [['role' => 'assistant', 'content' => 'Hi! I’m Lila 👋. I’m here to help you discover trusted artisans and connect with service providers right in your neighborhood.']];

        if (auth()->check()) {
            if (auth()->user()->latitude) {
                $this->lat = auth()->user()->latitude;
                $this->lng = auth()->user()->longitude;
                $this->resolveAddress();
            } else {
                $this->showLocationRequest = true;
            }

            if (auth()->user()->role === 'artisan' && !auth()->user()->is_admin) {
                $this->completion = auth()->user()->profileCompletion();
            }
        }
    }

    public function setLocation($lat, $lng)
    {
        $this->showLocationRequest = false;
        if ($this->lat != $lat || $this->lng != $lng) {
            $this->lat = $lat;
            $this->lng = $lng;
            $this->resolveAddress();

            // Proactively let the user know we found them
            if ($this->address) {
                $this->messages[] = ['role' => 'assistant', 'content' => "Perfect! I see you're near **{$this->address}**. Now I can help you find the best artisans just around the corner! 🌸"];
            }
        }
    }

    public function denyLocation()
    {
        $this->showLocationRequest = false;
        $this->messages[] = ['role' => 'assistant', 'content' => "No worries! You can still search for artisans by name or work, but I won't be able to tell you exactly how close they are. If you change your mind, just click the map icon! 😊"];
    }

    public function resolveAddress()
    {
        if (!$this->lat || !$this->lng) {
            return;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders(['User-Agent' => 'Allsers-App'])->get("https://nominatim.openstreetmap.org/reverse?format=json&lat={$this->lat}&lon={$this->lng}&zoom=18&addressdetails=1");

            if ($response->successful()) {
                $data = $response->json();
                $this->address = $data['display_name'] ?? 'Unknown location';
            }
        } catch (\Exception $e) {
            $this->address = 'Location found';
        }
    }

    #[On('open-lila')]
    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen) {
            $this->dispatch('play-sound');
            if (!$this->lat) {
                $this->showLocationRequest = true;
            }
        }
    }

    public function requestLocation()
    {
        $this->dispatch('get-location');
    }

    public function closeWidget()
    {
        $this->hasBeenClosed = true;
    }

    public function sendMessage()
    {
        if (empty(trim($this->input))) {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $this->input];
        $this->input = '';
        $this->isThinking = true;

        $this->dispatch('scroll-to-bottom');
        $this->dispatch('generate-ai-response');
    }

    public function pingArtisan($userId)
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'));
        }

        if (in_array($userId, $this->sentPings)) {
            return;
        }

        $artisan = User::find($userId);
        if (!$artisan) {
            return;
        }

        $this->pingingId = $userId;
        $sender = auth()->user();

        try {
            Mail::to($artisan->email)->send(new ServiceInquiryMail($sender, $artisan));
            $artisan->notify(new ServiceInquiry($sender));
            $this->sentPings[] = $userId;
            $this->dispatch('toast', type: 'success', title: 'Ping Sent!', message: 'Your inquiry has been sent to ' . $artisan->name);
        } catch (\Exception $e) {
            // Log the error if needed: Log::error($e->getMessage());
            // We still mark it as "sent" in the UI to prevent spamming, or we can let them retry.
            // But usually, if the mail server is down, we should tell them something went wrong.
            $this->dispatch('toast', type: 'info', title: 'Connection Alert', message: "We're having trouble reaching the mail server, but we've logged your interest in " . $artisan->name . ". They'll see it in their notifications! 🌸");

            // Still notify inside the app even if mail fails
            $artisan->notify(new ServiceInquiry($sender));
            $this->sentPings[] = $userId;
        } finally {
            $this->pingingId = null;
        }
    }

    #[On('generate-ai-response')]
    public function processAI()
    {
        $throttleKey = 'lila_user_throttle:' . (auth()->id() ?? request()->ip()); // Define throttleKey here for error handling

        try {
            // 1. Global Daily Quota Check (Hard limit at 1000/day, we cut off at 950)
            $dayKey = 'lila_global_daily_quota:' . date('Y-m-d');
            $dailyCount = Cache::get($dayKey, 0);
            if ($dailyCount >= 950) {
                throw new \Exception('GLOBAL_QUOTA_EXCEEDED');
            }

            // 2. Per-User Rate Limiting (5 requests per minute)
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                throw new \Exception('USER_THROTTLE_EXCEEDED');
            }

            // 3. Response Caching
            // Clear message history hash - if session is long, similar questions should return cached answers
            $historyHash = md5(json_encode($this->messages) . ($this->address ?? ''));
            $cacheKey = 'lila_response_cache:' . $historyHash;

            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $this->messages[] = $cachedData;
                $this->isThinking = false;
                $this->dispatch('scroll-to-bottom');
                return;
            }

            $prismMessages = [];
            foreach ($this->messages as $msg) {
                if ($msg['role'] === 'user') {
                    $prismMessages[] = new UserMessage($msg['content']);
                } else {
                    $prismMessages[] = new AssistantMessage($msg['content'] ?? '');
                }
            }

            // Provide current location context to Lila
            $locationContext = $this->lat ? "\nUser's current location: " . ($this->address ?: 'Detecting...') . "\n(Note: If you need to use find_artisans, use these internal coordinates: Lat {$this->lat}, Lng {$this->lng})" : '';

            $providerName = config('services.ai.provider', 'Groq');
            $provider = Provider::from($providerName);
            $model = config('services.ai.model', 'llama-3.1-8b-instant');

            $response = Prism::text()
                ->using($provider, $model ?: 'llama-3.1-8b-instant')
                ->withSystemPrompt(view('prompts.lila-system')->render() . $locationContext)
                ->withMessages($prismMessages)
                ->withTools([new FindArtisansTool()])
                ->withMaxSteps(3)
                ->asText();

            $artisansFound = [];
            foreach ($response->steps as $step) {
                foreach ($step->toolResults as $result) {
                    if ($result->toolName === 'find_artisans') {
                        $toolResult = $result->result;
                        $data = is_string($toolResult) ? json_decode((string) $toolResult, true) : $toolResult;
                        if (is_array($data)) {
                            $artisansFound = array_merge($artisansFound, $data);
                        }
                    }
                }
            }

            $assistantMsg = [
                'role' => 'assistant',
                'content' => $response->text,
                'artisans' => $artisansFound,
            ];

            // Success!
            // 4. Update Daily Count
            Cache::put($dayKey, $dailyCount + 1, now()->addDay());

            // 5. Update User Throttle
            RateLimiter::hit($throttleKey, 60);

            // 6. Cache Response
            Cache::put($cacheKey, $assistantMsg, now()->addHour());

            $this->messages[] = $assistantMsg;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if ($errorMessage === 'GLOBAL_QUOTA_EXCEEDED') {
                $friendlyMessage = "Lila has been incredibly busy helping people today and has reached her daily limit. She'll be back and refreshed tomorrow morning! 🌸";
            } elseif ($errorMessage === 'USER_THROTTLE_EXCEEDED') {
                $seconds = RateLimiter::availableIn($throttleKey);
                $friendlyMessage = "Whoa there! I'm thinking as fast as I can. Please wait about {$seconds} seconds before your next question! 🌸";
            } else {
                $friendlyMessage = "I'm sorry, I'm having a bit of trouble connecting to my brain right now. Please check your internet connection or try again in a moment! 🌸";

                if (str_contains($errorMessage, '429') || str_contains($errorMessage, 'rate_limit')) {
                    $friendlyMessage = 'It looks like a lot of people are talking to me right now. Could you wait a tiny bit and try again? ⏳';
                } elseif (str_contains($errorMessage, 'cURL error') || str_contains($errorMessage, 'timed out')) {
                    $friendlyMessage = "It's taking me a little longer than usual to think. My connection timed out—could you try sending that again? ⏳";
                }
            }

            $this->messages[] = ['role' => 'assistant', 'content' => $friendlyMessage];
        }

        $this->isThinking = false;
        $this->dispatch('scroll-to-bottom');
    }
}; ?>

<div x-data="{
    open: @entangle('isOpen'),
    closed: @entangle('hasBeenClosed'),
    showMap: false,
    selectedArtisan: null,
    map: null,
    userMarker: null,
    artisanMarker: null,
    polyline: null,
    playSound() {
        let audio = new Audio('{{ asset('assets/mixkit-cartoon-toy-whistle-616.wav') }}');
        audio.play();
    },
    initMap() {
        if (!this.selectedArtisan) return;

        // Wait for modal transition to complete
        setTimeout(() => {
            const container = document.getElementById('artisan-map');
            if (!container) return;

            if (this.map) {
                this.map.remove();
            }

            const userLat = @js($lat) || 6.5244; // Default to Lagos if null
            const userLng = @js($lng) || 3.3792;
            const artisanLat = parseFloat(this.selectedArtisan.latitude);
            const artisanLng = parseFloat(this.selectedArtisan.longitude);

            if (!artisanLat || !artisanLng) return;

            this.map = L.map('artisan-map', {
                zoomControl: true,
                dragging: true
            }).setView([userLat, userLng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);

            const userIcon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div style='background-color: #3B82F6; width: 14px; height: 14px; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);'></div>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            });

            const purpleIcon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div style='background-color: var(--color-brand-purple); width: 14px; height: 14px; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 15px rgba(109, 40, 217, 0.5);'></div>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            });

            console.log('Initializing Map with User:', userLat, userLng, 'Artisan:', artisanLat, artisanLng);

            this.userMarker = L.marker([userLat, userLng], { icon: userIcon })
                .addTo(this.map)
                .bindPopup('Your Current Location')
                .openPopup();

            this.artisanMarker = L.marker([artisanLat, artisanLng], { icon: purpleIcon })
                .addTo(this.map)
                .bindPopup(`<b>${this.selectedArtisan.name}</b><br>${this.selectedArtisan.work}`);

            this.polyline = L.polyline([
                [userLat, userLng],
                [artisanLat, artisanLng]
            ], {
                className: 'gradient-polyline',
                weight: 6,
                opacity: 0.9,
                dashArray: '1, 12',
                lineCap: 'round'
            }).addTo(this.map);

            const bounds = L.latLngBounds([
                [userLat, userLng],
                [artisanLat, artisanLng]
            ]);

            this.map.fitBounds(bounds, { padding: [50, 50] });

            // Critical: Force Leaflet to recalculate container size
            setTimeout(() => {
                this.map.invalidateSize();
            }, 100);
        }, 400);
    }
}" x-show="!closed" x-on:play-sound.window="playSound()"
    class="fixed bottom-6 right-6 z-[9999] flex flex-col items-end gap-4">

    <!-- Map Modal -->
    <template x-if="showMap">
        <div class="fixed inset-0 z-[10001] flex items-center justify-center p-4 bg-zinc-900/80 backdrop-blur-md"
            @keydown.escape.window="showMap = false">
            <div
                class="bg-white dark:bg-zinc-900 w-full max-w-5xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col h-[85vh] border border-white/20 relative">

                <!-- Map Container -->
                <div class="flex-1 relative bg-zinc-100 dark:bg-zinc-800">
                    <div id="artisan-map" class="absolute inset-0 z-10" x-init="initMap()"></div>

                    <!-- Header Overlay -->
                    <div
                        class="absolute top-6 left-6 right-6 z-20 flex justify-between items-start pointer-events-none">
                        <!-- User Card (Top Left) -->
                        <div
                            class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md p-4 rounded-2xl shadow-xl border border-white/20 pointer-events-auto max-w-sm animate-in fade-in slide-in-from-left-4 duration-500">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-blue-500/10 flex items-center justify-center">
                                    <flux:icon name="map-pin" class="size-5 text-blue-500" />
                                </div>
                                <div class="flex-1">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Your
                                        Location</p>
                                    <p class="text-xs font-bold text-zinc-900 dark:text-white leading-tight mt-0.5"
                                        x-text="'{{ $address ?: 'Detecting your coordinates...' }}'"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Close Button -->
                        <button @click="showMap = false"
                            class="pointer-events-auto size-12 flex items-center justify-center bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md rounded-full shadow-xl border border-white/20 hover:scale-110 active:scale-95 transition-all">
                            <flux:icon name="x-mark" class="size-6 text-zinc-600 dark:text-zinc-400" />
                        </button>
                    </div>

                    <!-- Artisan Card (Bottom Right) -->
                    <div class="absolute bottom-10 right-10 z-20 pointer-events-none">
                        <div
                            class="bg-white dark:bg-zinc-900 p-6 rounded-[2rem] shadow-2xl border border-white/20 pointer-events-auto w-80 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div class="flex items-start gap-4 mb-5">
                                <div
                                    class="size-16 rounded-2xl overflow-hidden border-2 border-[var(--color-brand-purple)] shrink-0">
                                    <template x-if="selectedArtisan.profile_picture">
                                        <img :src="selectedArtisan.profile_picture" class="size-full object-cover">
                                    </template>
                                    <template x-if="!selectedArtisan.profile_picture">
                                        <div
                                            class="size-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400">
                                            <flux:icon name="user" class="size-8" />
                                        </div>
                                    </template>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-black text-lg text-zinc-900 dark:text-white truncate"
                                        x-text="selectedArtisan.name"></h3>
                                    <p class="text-xs font-black uppercase text-[var(--color-brand-purple)] tracking-wider"
                                        x-text="selectedArtisan.work"></p>
                                    <div class="mt-2 flex items-center gap-1.5">
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-[10px] font-bold text-zinc-500"
                                            x-text="selectedArtisan.experience"></span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-[var(--color-brand-purple)]/10 text-[10px] font-black text-[var(--color-brand-purple)]"
                                            x-text="selectedArtisan.distance + ' away'"></span>
                                    </div>
                                </div>
                            </div>

                            <button @click="$wire.pingArtisan(selectedArtisan.id)"
                                class="block w-full py-4 text-white font-black rounded-2xl hover:shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="$wire.pingingId == selectedArtisan.id || $wire.sentPings.includes(selectedArtisan.id)"
                                :class="$wire.sentPings.includes(selectedArtisan.id) ?
                                    'bg-green-500 hover:shadow-green-500/30' :
                                    ($wire.pingingId == selectedArtisan.id ? 'bg-zinc-400' :
                                        'bg-[var(--color-brand-purple)] hover:shadow-purple-500/30')">
                                <template x-if="$wire.pingingId == selectedArtisan.id">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                                        </div>
                                        <span>{{ __('Sending Ping...') }}</span>
                                    </div>
                                </template>
                                <template
                                    x-if="$wire.sentPings.includes(selectedArtisan.id) && $wire.pingingId != selectedArtisan.id">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="check" class="size-5" />
                                        <span>{{ __('Ping Sent!') }}</span>
                                    </div>
                                </template>
                                <template
                                    x-if="!$wire.sentPings.includes(selectedArtisan.id) && $wire.pingingId != selectedArtisan.id">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="chat-bubble-left-right" class="size-5" />
                                        <span>{{ __('Ping') }}</span>
                                    </div>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Chat Box -->
    <div x-show="open" x-transition:enter="transition ease-out duration-300 transform opacity-0 translate-y-4"
        x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200 transform opacity-100 translate-y-0"
        x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4"
        class="w-80 md:w-96 h-[500px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="p-4 bg-[var(--color-brand-purple)] text-white flex justify-between items-center shrink-0">
            <div class="flex items-center gap-2">
                <div
                    class="size-8 rounded-full bg-white/20 flex items-center justify-center overflow-hidden animate-lila-idle">
                    <img src="{{ asset('assets/lila-avatar.png') }}" alt="Lila" class="size-full object-cover">
                </div>
                <div>
                    <div class="font-bold text-sm leading-none">Lila</div>
                    <div class="text-[10px] opacity-80 mt-1 flex items-center gap-1">
                        <span class="size-1.5 bg-green-400 rounded-full animate-pulse"></span>
                        {{ $address ? Str::limit($address, 25) : 'Allsers Assistant' }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button wire:click="requestLocation" title="Refresh Location"
                    class="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
                    <flux:icon name="map-pin" class="size-4" />
                </button>
                <button @click="open = false" class="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-zinc-50 dark:bg-zinc-900/50"
            x-init="$watch('messages', () => { $nextTick(() => { $el.scrollTop = $el.scrollHeight }) });
            $watch('open', (value) => { if (value) $nextTick(() => { $el.scrollTop = $el.scrollHeight }) });"
            x-on:scroll-to-bottom.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
            @foreach ($messages as $message)
                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div
                        class="max-w-[85%] px-4 py-3 rounded-2xl text-sm {{ $message['role'] === 'user' ? 'bg-[var(--color-brand-purple)] text-white shadow-md' : 'bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 shadow-sm' }}">
                        @if ($message['role'] === 'assistant')
                            <div
                                class="mb-2 flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-[var(--color-brand-purple)] opacity-70">
                                <flux:icon name="sparkles" class="size-3" />
                                <span>Lila</span>
                            </div>
                            <div class="markdown-content space-y-3 leading-relaxed">
                                {!! Illuminate\Support\Str::markdown($message['content'] ?? '') !!}
                            </div>

                            @if (!empty($message['artisans']))
                                <div class="mt-4 space-y-3">
                                    @foreach ($message['artisans'] as $artisan)
                                        <div
                                            class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-xl p-3 flex gap-3 shadow-sm hover:border-[var(--color-brand-purple)] transition-colors">
                                            <div
                                                class="size-12 rounded-lg bg-zinc-200 dark:bg-zinc-800 overflow-hidden shrink-0">
                                                @if ($artisan['profile_picture'])
                                                    <img src="{{ $artisan['profile_picture'] }}"
                                                        class="size-full object-cover">
                                                @else
                                                    <div
                                                        class="size-full flex items-center justify-center text-zinc-400">
                                                        <flux:icon name="user" class="size-6" />
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-bold text-zinc-900 dark:text-white truncate">
                                                    {{ $artisan['name'] }}
                                                </div>
                                                <div class="text-[10px] text-zinc-500 font-medium uppercase">
                                                    {{ $artisan['work'] }}
                                                </div>
                                                <div class="mt-1 flex items-center gap-2 text-[10px] text-zinc-400">
                                                    <span class="flex items-center gap-0.5">
                                                        <flux:icon name="briefcase" class="size-3" />
                                                        {{ $artisan['experience'] }}
                                                    </span>
                                                </div>
                                                <button
                                                    @click="selectedArtisan = {{ json_encode($artisan) }}; showMap = true"
                                                    class="mt-2 block w-full text-center py-2 bg-[var(--color-brand-purple)] text-white text-[11px] font-bold rounded-lg hover:shadow-lg hover:shadow-purple-500/20 transition-all">
                                                    {{ __('View on Map') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($showLocationRequest)
                <div class="flex justify-start">
                    <div
                        class="max-w-[85%] bg-white dark:bg-zinc-800 border-2 border-[var(--color-brand-purple)] px-4 py-4 rounded-2xl shadow-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <flux:icon name="map-pin" class="size-5 text-[var(--color-brand-purple)]" />
                            <span class="font-bold text-zinc-900 dark:text-white">Find local experts nearby</span>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-4 leading-relaxed">
                            To show you trusted artisans just <strong>streets away</strong> from you, Lila needs your
                            location. This helps us connect you with the fastest help available! 📍
                        </p>
                        <div class="flex gap-2">
                            <button wire:click="requestLocation"
                                class="flex-1 bg-[var(--color-brand-purple)] text-white py-2 rounded-xl text-xs font-bold hover:opacity-90 transition-opacity">
                                Allow Access
                            </button>
                            <button wire:click="denyLocation"
                                class="flex-1 bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 py-2 rounded-xl text-xs font-bold hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-colors">
                                Not now
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($isThinking)
                <div class="flex justify-start">
                    <div
                        class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-4 py-3 rounded-2xl shadow-sm">
                        <div
                            class="mb-2 flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-[var(--color-brand-purple)] opacity-70">
                            <flux:icon name="sparkles" class="size-3" />
                            <span>Lila is thinking...</span>
                        </div>
                        <div class="flex gap-1.5 px-1">
                            <span class="size-1.5 bg-[var(--color-brand-purple)] rounded-full animate-bounce"></span>
                            <span
                                class="size-1.5 bg-[var(--color-brand-purple)] rounded-full animate-bounce [animation-delay:0.2s]"></span>
                            <span
                                class="size-1.5 bg-[var(--color-brand-purple)] rounded-full animate-bounce [animation-delay:0.4s]"></span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <script>
            document.addEventListener('livewire:initialized', () => {
                @this.on('get-location', () => {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition((position) => {
                            @this.setLocation(position.coords.latitude, position.coords.longitude);
                        }, (error) => {
                            console.error('Geolocation error:', error);
                        });
                    }
                });
            });
        </script>

        <style>
            .markdown-content p {
                margin-bottom: 0.75rem;
            }

            .markdown-content p:last-child {
                margin-bottom: 0;
            }

            .markdown-content ul,
            .markdown-content ol {
                margin-bottom: 0.75rem;
                padding-left: 1.25rem;
            }

            .markdown-content ul {
                list-style-type: disc;
            }

            .markdown-content ol {
                list-style-type: decimal;
            }

            .markdown-content li {
                margin-bottom: 0.25rem;
            }

            .markdown-content strong {
                font-weight: 700;
                color: inherit;
            }

            .markdown-content a {
                color: var(--color-brand-purple);
                text-decoration: underline;
                font-weight: 600;
            }

            @keyframes lila-entrance {
                0% {
                    transform: translateY(-95vh);
                    opacity: 1;
                }

                60% {
                    transform: translateY(20px) scale(1.1);
                }

                80% {
                    transform: translateY(-10px) scale(1.05);
                }

                100% {
                    transform: translateY(0) scale(1);
                    opacity: 1;
                }
            }

            @keyframes lila-float {

                0%,
                100% {
                    transform: translateX(0) rotate(0deg);
                }

                50% {
                    transform: translateX(-10px) rotate(-3deg);
                }
            }

            @keyframes lila-idle {

                0%,
                100% {
                    transform: translateX(0) rotate(0deg);
                }

                50% {
                    transform: translateX(-5px) rotate(-2deg);
                }
            }

            .animate-lila-entrance {
                animation: lila-entrance 1.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards,
                    lila-float 3s ease-in-out infinite 1.5s;
                will-change: transform;
            }

            .animate-lila-idle {
                animation: lila-idle 3s ease-in-out infinite;
                will-change: transform;
            }

            @keyframes wave {
                0% {
                    transform: rotate(0deg);
                }

                10% {
                    transform: rotate(14deg);
                }

                20% {
                    transform: rotate(-8deg);
                }

                30% {
                    transform: rotate(14deg);
                }

                40% {
                    transform: rotate(-4deg);
                }

                50% {
                    transform: rotate(10deg);
                }

                60% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(0deg);
                }
            }

            .animate-waving-hand {
                animation: wave 2s infinite;
                transform-origin: 70% 70%;
                display: inline-block;
            }
        </style>

        <style>
            .gradient-polyline {
                stroke: url(#line-gradient) !important;
                filter: drop-shadow(0 0 5px rgba(109, 40, 217, 0.3));
            }

            .leaflet-container {
                background: #f4f4f5 !important;
            }
        </style>

        <!-- Input -->
        <form wire:submit="sendMessage"
            class="p-4 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800 flex gap-2">
            <input type="text" wire:model="input" placeholder="Ask Lila anything..."
                class="flex-1 bg-zinc-100 dark:bg-zinc-800 border-none rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-[var(--color-brand-purple)] dark:text-white">
            <button type="submit"
                class="bg-[var(--color-brand-purple)] text-white p-2 rounded-xl hover:opacity-90 transition-opacity">
                <flux:icon name="paper-airplane" class="size-5" />
            </button>
        </form>
    </div>

    <!-- Floating Widget -->
    <div x-show="!open" x-data="{ checklist: false, greeting: false }" x-init="setTimeout(() => greeting = true, 1800);
    setTimeout(() => greeting = false, 8000)"
        class="hidden lg:flex relative items-center bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-full px-4 py-2 shadow-xl cursor-pointer hover:shadow-2xl transition-all group">

        <!-- Greeting Bubble -->
        <div x-show="greeting" x-transition:enter="transition ease-out duration-500"
            x-transition:enter-start="opacity-0 translate-y-4 scale-90"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            class="absolute bottom-full right-0 mb-4 bg-white dark:bg-zinc-800 px-4 py-2 rounded-2xl shadow-2xl border border-zinc-100 dark:border-zinc-700 whitespace-nowrap z-[10000]">
            <div class="flex items-center gap-2">
                <span class="text-sm font-black text-zinc-900 dark:text-white">Hi! I'm Lila</span>
                <span class="text-lg animate-waving-hand">👋</span>
            </div>
            <!-- Pointer -->
            <div
                class="absolute -bottom-1.5 right-6 w-3 h-3 bg-white dark:bg-zinc-800 border-b border-r border-zinc-100 dark:border-zinc-700 rotate-45">
            </div>
        </div>

        <!-- Profile Completion Checklist Popover -->
        @if ($completion && !$completion['is_complete'])
            <div x-show="checklist" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 scale-95" @click.away="checklist = false"
                class="absolute bottom-full right-0 mb-4 w-72 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-2xl overflow-hidden z-50">
                <div class="p-4 border-b border-zinc-100 dark:border-zinc-700 bg-[var(--color-brand-purple)]/5">
                    <h4 class="font-black text-xs uppercase tracking-wider text-[var(--color-brand-purple)]">
                        {{ __('Profile Completion') }}
                    </h4>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                            <div class="h-full bg-[var(--color-brand-purple)] transition-all duration-500"
                                style="width: {{ $completion['percentage'] }}%"></div>
                        </div>
                        <span
                            class="text-[10px] font-bold text-zinc-600 dark:text-zinc-400">{{ $completion['percentage'] }}%</span>
                    </div>
                </div>
                <div class="p-2 max-h-64 overflow-y-auto">
                    <div class="space-y-1">
                        @foreach ($completion['completed'] as $item)
                            <div class="flex items-center gap-2 p-2 rounded-lg bg-green-50/50 dark:bg-green-900/10">
                                <flux:icon name="check-circle" variant="solid" class="size-3.5 text-green-500" />
                                <span
                                    class="text-[11px] text-zinc-600 dark:text-zinc-400 line-through opacity-60">{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                        @foreach ($completion['missing'] as $item)
                            <div
                                class="flex items-center gap-2 p-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                <div class="size-3.5 border-2 border-zinc-200 dark:border-zinc-600 rounded-full"></div>
                                <span
                                    class="text-[11px] font-medium text-zinc-800 dark:text-zinc-200">{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="p-3 bg-zinc-50 dark:bg-zinc-700/30 border-t border-zinc-100 dark:border-zinc-700">
                    <a href="{{ route('profile.edit') }}"
                        class="block w-full text-center py-2 bg-[var(--color-brand-purple)] text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:opacity-90 transition-opacity">
                        {{ __('Complete Profile') }}
                    </a>
                </div>
            </div>
        @endif

        <div class="flex items-center gap-3" @click="playSound(); @this.toggle()">
            <div class="relative">
                <div
                    class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border-2 border-[var(--color-brand-purple)] animate-lila-entrance">
                    <img src="{{ asset('assets/lila-avatar.png') }}" class="size-full object-cover rounded-full">
                </div>
                <div
                    class="absolute -top-1 -right-1 size-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white dark:border-zinc-900">
                    1
                </div>
            </div>

            <div class="flex flex-col">
                <div class="flex items-center gap-1">
                    <span class="text-sm font-black text-zinc-900 dark:text-white">Lila</span>
                    <span
                        class="px-1.5 bg-zinc-100 dark:bg-zinc-800 rounded text-[10px] font-bold text-zinc-500">Allsers
                        Finder</span>
                </div>
                <span class="text-[10px] text-zinc-500 truncate w-32">Welcome to Allsers. How...</span>
            </div>

            @if ($completion && !$completion['is_complete'])
                <div class="relative size-10 group/stat" @click.stop="checklist = !checklist">
                    <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="16" fill="none"
                            class="stroke-zinc-100 dark:stroke-zinc-800" stroke-width="3"></circle>
                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-green-500"
                            stroke-width="3" stroke-dasharray="{{ ($completion['percentage'] / 100) * 100 }}, 100"
                            stroke-linecap="round">
                        </circle>
                    </svg>
                    <div
                        class="absolute inset-0 flex items-center justify-center text-[10px] font-black text-zinc-900 dark:text-white group-hover/stat:text-[var(--color-brand-purple)] transition-colors">
                        {{ $completion['percentage'] }}%
                    </div>
                </div>
            @endif

            <div class="text-zinc-400 group-hover:text-zinc-600 transition-colors">
                <flux:icon name="chevron-up" class="size-5" />
            </div>
        </div>

        <button @click.stop="@this.closeWidget()"
            class="absolute -top-2 -left-2 size-6 bg-zinc-200/50 dark:bg-zinc-800/50 text-zinc-500 hover:text-zinc-800 rounded-full flex items-center justify-center backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity">
            <flux:icon name="x-mark" class="size-3" />
        </button>
    </div>
</div>
