<?php

use Livewire\Volt\Component;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\ServiceInquiryMail;
use App\Notifications\ServiceInquiry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Engagement;

new #[Layout('components.layouts.app')] #[Title('Artisan Finder')] class extends Component {
    use WithFileUploads;

    #[Url]
    public $search = '';

    public $lat = null;
    public $lng = null;
    public $address = null;
    public $selectedArtisan = null;
    public $sentPings = [];
    public $pingingId = null;

    // Structured Inquiry Properties
    public $showInquiryForm = false;
    public $inquiryTask = '';
    public $inquiryLocation = '';
    public $inquiryUrgency = 'medium';
    public $inquiryPhotos = [];

    public function mount()
    {
        if (auth()->check()) {
            $this->lat = auth()->user()->latitude;
            $this->lng = auth()->user()->longitude;
            $this->address = auth()->user()->address;
        }

        // Default if not set
        if (!$this->lat) {
            $this->lat = 6.5244;
            $this->lng = 3.3792;
        }
    }

    public function setLocation($lat, $lng)
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->resolveAddress();
    }

    public function resolveAddress()
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders(['User-Agent' => 'Allsers-App'])->get("https://nominatim.openstreetmap.org/reverse?format=json&lat={$this->lat}&lon={$this->lng}&zoom=18");

            if ($response->successful()) {
                $this->address = $response->json()['display_name'] ?? 'Unknown location';
            }
        } catch (\Exception $e) {
            $this->address = 'Current Location';
        }
    }

    public function selectArtisan($id)
    {
        $artisan = User::find($id);
        $this->selectedArtisan = $artisan;
        $this->dispatch('artisan-selected', artisan: [
            'id' => $artisan->id,
            'name' => $artisan->name,
            'work' => $artisan->work ?: 'Professional',
            'latitude' => $artisan->latitude,
            'longitude' => $artisan->longitude,
            'profile_picture_url' => $artisan->profile_picture_url,
            'experience_year' => $artisan->experience_year ?: '0',
        ]);
    }

    public function startInquiry($artisanId)
    {
        $this->selectedArtisan = User::find($artisanId);
        $this->inquiryLocation = $this->address;
        $this->showInquiryForm = true;
    }

    public function submitStructuredInquiry()
    {
        if (!$this->selectedArtisan) {
            return;
        }

        $this->validate([
            'inquiryTask' => 'required|min:10',
            'inquiryLocation' => 'required',
            'inquiryUrgency' => 'required|in:low,medium,high',
            'inquiryPhotos.*' => 'image|max:5120', // 5MB Max
        ]);

        $sender = auth()->user();
        $artisan = $this->selectedArtisan;

        // 1. Handle Photos
        $photoPaths = [];
        if ($this->inquiryPhotos) {
            foreach ($this->inquiryPhotos as $photo) {
                $photoPaths[] = $photo->store('inquiries/photos', 'cloudinary');
            }
        }

        // 2. Find or Create Conversation
        $conversation = Conversation::whereHas('users', function ($q) use ($sender) {
            $q->where('users.id', $sender->id);
        })
            ->whereHas('users', function ($q) use ($artisan) {
                $q->where('users.id', $artisan->id);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create(['last_message_at' => now()]);
            $conversation->users()->attach([$sender->id, $artisan->id]);
        }

        // 3. Create Engagement
        $engagement = Engagement::create([
            'user_id' => $sender->id,
            'artisan_id' => $artisan->id,
            'conversation_id' => $conversation->id,
            'status' => 'pending',
            'title' => $this->inquiryTask,
            'location_context' => $this->inquiryLocation,
            'urgency_level' => $this->inquiryUrgency,
            'inquiry_photos' => $photoPaths,
        ]);

        // 4. Create Inquiry Message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'type' => 'inquiry',
            'engagement_id' => $engagement->id,
            'content' => $this->inquiryTask,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // 5. Notify
        try {
            Mail::to($artisan->email)->queue(new ServiceInquiryMail($sender, $artisan));
            $artisan->notify(new ServiceInquiry($sender));
        } catch (\Exception $e) {
            $artisan->notify(new ServiceInquiry($sender));
        }

        $this->dispatch('toast', type: 'success', title: 'Inquiry Sent!', message: 'Taking you to your conversation with ' . $artisan->name);

        return $this->redirect(route('chat', $conversation->id), navigate: true);
    }

    public function pingArtisan($userId)
    {
        $this->startInquiry($userId);
    }

    public function with()
    {
        $query = User::where('role', 'artisan')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('id', '!=', auth()->id())
            ->whereNotIn('work', ['Guest', 'Provider', 'Admin'])
            ->select('users.*')
            ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$this->lat, $this->lng, $this->lat])
            ->orderBy('distance');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")->orWhere('work', 'like', "%{$this->search}%");
            });
        }

        $allResults = $query->get();

        $nearby = $allResults->filter(fn($u) => $u->distance <= 10);

        // Suggested only if searching
        $suggested = $this->search ? $allResults->filter(fn($u) => $u->distance > 10) : collect();

        return [
            'nearby' => $nearby,
            'suggested' => $suggested,
        ];
    }
}; ?>

<div class="flex h-[calc(100vh-64px)] lg:h-screen overflow-hidden bg-white dark:bg-zinc-950 flex-col lg:flex-row"
    x-data="{
        map: null,
        userMarker: null,
        artisanMarkers: [],
        polyline: null,
        distanceLabel: null,
        mobileView: 'list',
        selectedArtisan: @entangle('selectedArtisan'),
        userLat: @entangle('lat'),
        userLng: @entangle('lng'),
    
        initMap() {
            if (this.map) return;
    
            this.map = L.map('finder-map', {
                zoomControl: false,
                attributionControl: false
            }).setView([this.userLat, this.userLng], 13);
    
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
            }).addTo(this.map);
    
            const userIcon = L.icon({
                iconUrl: '{{ asset('assets/map_pointer_blue.svg') }}',
                iconSize: [32, 42],
                iconAnchor: [16, 42],
                popupAnchor: [0, -42]
            });
    
            this.userMarker = L.marker([this.userLat, this.userLng], { icon: userIcon })
                .addTo(this.map)
                .bindPopup('Your Location');
        },
        updateUserMarker() {
            if (this.userMarker) {
                this.userMarker.setLatLng([this.userLat, this.userLng]);
                this.map.panTo([this.userLat, this.userLng]);
            }
        },
        updateArtisanOnMap(artisan) {
            this.selectedArtisan = artisan;
            this.mobileView = 'map';
    
            setTimeout(() => {
                this.map.invalidateSize();
    
                const aLat = parseFloat(artisan.latitude);
                const aLng = parseFloat(artisan.longitude);
    
                // Remove existing
                if (this.polyline) this.map.removeLayer(this.polyline);
                if (this.distanceLabel) this.map.removeLayer(this.distanceLabel);
                this.artisanMarkers.forEach(m => this.map.removeLayer(m));
                this.artisanMarkers = [];
    
                const purpleIcon = L.icon({
                    iconUrl: '{{ asset('assets/map_pointer_purple.svg') }}',
                    iconSize: [32, 42],
                    iconAnchor: [16, 42],
                    popupAnchor: [0, -42]
                });
    
                const marker = L.marker([aLat, aLng], { icon: purpleIcon })
                    .addTo(this.map)
                    .bindPopup(`<b>${artisan.name}</b><br>${artisan.work}`)
                    .openPopup();
    
                this.artisanMarkers.push(marker);
    
                this.polyline = L.polyline([
                    [this.userLat, this.userLng],
                    [aLat, aLng]
                ], {
                    color: '#9333ea',
                    weight: 3,
                    opacity: 0.7,
                    dashArray: '5, 10',
                    lineCap: 'round'
                }).addTo(this.map);

                const userPoint = L.latLng(this.userLat, this.userLng);
                const artisanPoint = L.latLng(aLat, aLng);
                const distKm = (userPoint.distanceTo(artisanPoint) / 1000).toFixed(1);
    
                const midpoint = [
                    (this.userLat + aLat) / 2,
                    (this.userLng + aLng) / 2
                ];
    
                this.distanceLabel = L.marker(midpoint, {
                    icon: L.divIcon({
                        className: 'distance-label',
                        html: `<div class='bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-full text-xs font-bold shadow-xl border-2 border-purple-500 dark:border-purple-400 text-purple-600 dark:text-purple-300 animate-in zoom-in duration-300'>${distKm} km</div>`,
                        iconSize: [window.innerWidth < 640 ? 60 : 70, 24],
                        iconAnchor: [window.innerWidth < 640 ? 30 : 35, 12]
                    })
                }).addTo(this.map);
    
                const bounds = L.latLngBounds([
                    [this.userLat, this.userLng],
                    [aLat, aLng]
                ]);
    
                this.map.fitBounds(bounds, {
                    padding: [50, 50],
                    animate: true,
                    duration: 1
                });
            }, 200);
        }
    }" x-init="initMap();
    $watch('userLat', () => updateUserMarker())"
    x-on:artisan-selected.window="updateArtisanOnMap($event.detail.artisan)">

    <!-- Mobile View Toggle -->
    <div
        class="lg:hidden flex border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 sticky top-0 z-20 overflow-hidden  shrink-0">
        <button @click="mobileView = 'list'"
            class="flex-1 py-4 text-xs font-bold uppercase  transition-all relative"
            :class="mobileView === 'list' ? 'text-purple-600' : 'text-zinc-400'">
            {{ __('List View') }}
            <div x-show="mobileView === 'list'" class="absolute bottom-0 left-0 right-0 h-1 bg-purple-600" x-transition>
            </div>
        </button>
        <button @click="mobileView = 'map'; setTimeout(() => map.invalidateSize(), 100)"
            class="flex-1 py-4 text-xs font-bold uppercase  transition-all relative"
            :class="mobileView === 'map' ? 'text-purple-600' : 'text-zinc-400'">
            {{ __('Map View') }}
            <div x-show="mobileView === 'map'" class="absolute bottom-0 left-0 right-0 h-1 bg-purple-600" x-transition>
            </div>
        </button>
    </div>

    <!-- Left Panel: Search & Results -->
    <div class="w-full lg:w-[400px] flex flex-col border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 z-10 overflow-hidden transition-all duration-300"
        x-show="mobileView === 'list' || window.innerWidth >= 1024"
        :class="mobileView === 'list' ? 'flex' : 'hidden lg:flex'">
        <div class="p-4 lg:p-6 border-b border-zinc-100 dark:border-zinc-800 shrink-0">
            <h1
                class="text-lg lg:text-xl font-bold text-zinc-900 dark:text-white mb-4 items-center gap-2 hidden lg:flex">
                <flux:icon name="magnifying-glass-circle" class="size-6 text-purple-600" />
                Finder
            </h1>

            <div class="space-y-4">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Search by name or category..." class="rounded-2xl" />

                <div
                    class="flex items-center gap-2 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-2xl border border-zinc-100 dark:border-zinc-800">
                    <flux:icon name="map-pin" class="size-4 text-zinc-400" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold uppercase text-zinc-400 ">Base Location</p>
                        <p class="text-xs font-bold text-zinc-600 dark:text-zinc-300 truncate">
                            {{ $address ?: 'Lagos, Nigeria' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-8 scrollbar-hide pb-20 lg:pb-8">
            <!-- Selected Profile View -->
            @if ($selectedArtisan)
                <div class="animate-in fade-in slide-in-from-top-4 duration-300">
                    <div
                        class="bg-purple-50 dark:bg-purple-900/10 rounded-3xl p-4 lg:p-6 border border-purple-100 dark:border-purple-900/30">
                        <div class="flex items-start gap-4 mb-6">
                            <div
                                class="size-16 lg:size-20 rounded-2xl border-2 border-purple-500 overflow-hidden shadow-xl shrink-0">
                                @if ($selectedArtisan->profile_picture_url)
                                    <img src="{{ $selectedArtisan->profile_picture_url }}"
                                        class="size-full object-cover">
                                @else
                                    <div
                                        class="size-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center text-lg lg:text-xl font-bold text-zinc-500">
                                        {{ $selectedArtisan->initials() }}
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base lg:text-lg font-bold text-zinc-900 dark:text-white truncate">
                                    {{ $selectedArtisan->name }}</h2>
                                <p
                                    class="text-xs lg:text-xs font-bold uppercase text-purple-600  mt-0.5">
                                    {{ $selectedArtisan->work ?: 'Expert' }}</p>
                                <div class="flex items-center gap-3 mt-3">
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="star" variant="solid" class="size-3 text-yellow-500" />
                                        <span
                                            class="text-xs font-bold dark:text-zinc-400">{{ $selectedArtisan->reviews_avg_rating ? number_format($selectedArtisan->reviews_avg_rating, 1) : 'New' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="briefcase" class="size-3 text-zinc-400" />
                                        <span
                                            class="text-xs font-bold text-zinc-500">{{ $selectedArtisan->experience_year ?: '0' }}Y
                                            Exp</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 hidden">
                            <flux:button :href="route('artisan.profile', $selectedArtisan)" wire:navigate
                                variant="ghost" class="flex-1 rounded-xl">View Profile</flux:button>

                            <button wire:click="pingArtisan('{{ $selectedArtisan->id }}')" wire:loading.attr="disabled"
                                class="flex-1 bg-purple-600 text-white font-bold py-2.5 rounded-xl hover:bg-purple-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 disabled:opacity-50">
                                {{-- Loading Spinner --}}
                                <div wire:loading wire:target="pingArtisan('{{ $selectedArtisan->id }}')"
                                    class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                                </div>

                                {{-- Default Icons (Hidden while loading) --}}
                                <div wire:loading.remove wire:target="pingArtisan('{{ $selectedArtisan->id }}')">
                                    @if (in_array($selectedArtisan->id, $sentPings))
                                        <flux:icon name="check" class="size-4" />
                                    @else
                                        <flux:icon name="chat-bubble-left-right" class="size-4" />
                                    @endif
                                </div>

                                <span class="text-xs">
                                    <span wire:loading
                                        wire:target="pingArtisan('{{ $selectedArtisan->id }}')">Sending...</span>
                                    <span wire:loading.remove wire:target="pingArtisan('{{ $selectedArtisan->id }}')">
                                        {{ in_array($selectedArtisan->id, $sentPings) ? 'Ping Sent!' : 'Ping Now' }}
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Results Lists -->
            <div class="space-y-8">
                <!-- Nearby List -->
                @if ($nearby->isNotEmpty())
                    <div>
                        <h3
                            class="flex items-center gap-2 text-xs font-bold uppercase text-zinc-400  mb-4">
                            <span class="size-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            Nearby Professionals
                        </h3>
                        <div class="space-y-3">
                            @foreach ($nearby as $artisan)
                                <div wire:key="nearby-{{ $artisan->id }}"
                                    wire:click="selectArtisan('{{ $artisan->id }}')"
                                    x-on:click="$dispatch('artisan-selected', { artisan: { id: '{{ $artisan->id }}', name: '{{ $artisan->name }}', work: '{{ $artisan->work ?: 'Professional' }}', latitude: '{{ $artisan->latitude }}', longitude: '{{ $artisan->longitude }}', profile_picture_url: '{{ $artisan->profile_picture_url }}' } })"
                                    class="group p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/30 border {{ $selectedArtisan && $selectedArtisan->id == $artisan->id ? 'border-purple-500 ring-2 ring-purple-500/10' : 'border-zinc-100 dark:border-zinc-800' }} hover:border-purple-200 dark:hover:border-purple-800 transition-all cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 rounded-xl overflow-hidden shrink-0">
                                            @if ($artisan->profile_picture_url)
                                                <img src="{{ $artisan->profile_picture_url }}"
                                                    class="size-full object-cover">
                                            @else
                                                <div
                                                    class="size-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-500">
                                                    {{ $artisan->initials() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                                                    {{ $artisan->name }}</p>
                                                <span
                                                    class="shrink-0 text-xs font-bold text-purple-600 bg-purple-50 dark:bg-purple-900/30 px-2 py-0.5 rounded-full">{{ round($artisan->distance, 1) }}km</span>
                                            </div>
                                            <p class="text-xs text-zinc-500 font-medium uppercase truncate">
                                                {{ $artisan->work ?: 'Professional' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Suggested List (Only on search) -->
                @if ($search && $suggested->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-bold uppercase text-zinc-400  mb-4">Suggested
                            Experts</h3>
                        <div class="space-y-3">
                            @foreach ($suggested as $artisan)
                                <div wire:key="suggested-{{ $artisan->id }}"
                                    wire:click="selectArtisan('{{ $artisan->id }}')"
                                    x-on:click="$dispatch('artisan-selected', { artisan: { id: '{{ $artisan->id }}', name: '{{ $artisan->name }}', work: '{{ $artisan->work ?: 'Professional' }}', latitude: '{{ $artisan->latitude }}', longitude: '{{ $artisan->longitude }}', profile_picture_url: '{{ $artisan->profile_picture_url }}' } })"
                                    class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border {{ $selectedArtisan && $selectedArtisan->id == $artisan->id ? 'border-purple-500 ring-2 ring-purple-500/10' : 'border-zinc-100 dark:border-zinc-800' }} hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-all cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 rounded-xl overflow-hidden shrink-0 grayscale opacity-70">
                                            @if ($artisan->profile_picture_url)
                                                <img src="{{ $artisan->profile_picture_url }}"
                                                    class="size-full object-cover">
                                            @else
                                                <div
                                                    class="size-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-500">
                                                    {{ $artisan->initials() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-zinc-600 dark:text-zinc-400 truncate">
                                                    {{ $artisan->name }}</p>
                                                <span
                                                    class="shrink-0 text-xs font-bold text-zinc-400">{{ round($artisan->distance, 1) }}km</span>
                                            </div>
                                            <p class="text-xs text-zinc-500 font-medium uppercase truncate">
                                                {{ $artisan->work ?: 'Professional' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($nearby->isEmpty() && (!$search || $suggested->isEmpty()))
                    <div class="text-center py-12">
                        <flux:icon name="magnifying-glass"
                            class="size-12 text-zinc-100 dark:text-zinc-800 mx-auto mb-4" />
                        <p class="text-sm font-bold text-zinc-400">No artisans found near you.</p>
                        <p class="text-xs text-zinc-500 mt-2">Try searching for a specific service above.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="flex-1 relative bg-zinc-100 dark:bg-zinc-800 overflow-hidden"
        x-show="mobileView === 'map' || window.innerWidth >= 1024"
        :class="mobileView === 'map' ? 'block' : 'hidden lg:block'" wire:ignore>
        <div id="finder-map" class="absolute inset-0 z-0"></div>

        <!-- Map Overlays -->
        <div
            class="absolute bottom-20 lg:bottom-10 left-1/2 -translate-x-1/2 z-10 flex items-center gap-4 w-full px-4 justify-center">
            <div
                class="px-4 lg:px-6 py-3 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="size-3 bg-blue-500 rounded-full border border-white "></div>
                    <span class="text-xs font-bold uppercase text-zinc-500 tracking-wider">You</span>
                </div>
                <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="flex items-center gap-2">
                    <div class="size-3 bg-purple-600 rounded-full border border-white "></div>
                    <span
                        class="text-xs font-bold uppercase text-zinc-500 tracking-wider text-nowrap">Artisan</span>
                </div>
            </div>

            <button
                onclick="navigator.geolocation.getCurrentPosition(p => @this.setLocation(p.coords.latitude, p.coords.longitude))"
                class="size-10 lg:size-12 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 flex items-center justify-center hover:scale-110 active:scale-95 transition-all text-zinc-600 dark:text-zinc-400">
                <flux:icon name="map-pin" class="size-5" />
            </button>
        </div>

        <div class="absolute top-4 lg:top-10 left-4 lg:left-10 pointer-events-none z-10">
            <div
                class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md px-3 lg:px-4 py-2 rounded-xl border border-white/20 flex items-center gap-2">
                <span class="size-2 bg-green-500 rounded-full animate-ping"></span>
                <span
                    class="text-xs lg:text-xs font-bold uppercase  text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Live
                    Network</span>
            </div>
        </div>

        <!-- Artisan Card Overlay (On Map) -->
        <div class="absolute bottom-24 lg:bottom-10 right-4 lg:right-10 z-20 pointer-events-none"
            x-show="selectedArtisan">
            <div
                class="relative bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md p-4 rounded-3xl shadow-2xl border border-white/20 pointer-events-auto w-64 lg:w-72 animate-in fade-in slide-in-from-bottom-4 duration-500">

                <!-- Close Button -->
                <button @click.stop="selectedArtisan = null; $wire.selectedArtisan = null"
                    class="absolute -top-2 -right-2 size-7 bg-white dark:bg-zinc-800 rounded-full shadow-lg border border-zinc-100 dark:border-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors z-[100] pointer-events-auto">
                    <flux:icon name="x-mark" class="size-4" />
                </button>

                <div class="flex items-start gap-3 mb-3">
                    <div
                        class="size-10 lg:size-12 rounded-xl overflow-hidden border-2 border-purple-500 shrink-0 shadow-lg bg-zinc-100 dark:bg-zinc-800">
                        <template x-if="selectedArtisan && selectedArtisan.profile_picture_url">
                            <img :src="selectedArtisan.profile_picture_url" class="size-full object-cover">
                        </template>
                        <template x-if="!selectedArtisan || !selectedArtisan.profile_picture_url">
                            <div class="size-full flex items-center justify-center text-zinc-400 font-bold text-xs">
                                <span
                                    x-text="selectedArtisan ? selectedArtisan.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0,2) : ''"></span>
                            </div>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-sm lg:text-base text-zinc-900 dark:text-white truncate"
                            x-text="selectedArtisan ? selectedArtisan.name : ''"></h3>
                        <p class="text-xs lg:text-xs font-bold uppercase text-purple-600 tracking-wider"
                            x-text="selectedArtisan ? (selectedArtisan.work || 'Expert') : ''"></p>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <span
                                class="px-1.5 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-xs font-bold text-zinc-500"
                                x-text="selectedArtisan ? (selectedArtisan.experience_year || '0') + 'Y Exp' : ''"></span>
                            <span
                                class="px-1.5 py-0.5 rounded-full bg-purple-600/10 text-xs font-bold text-purple-600"
                                x-text="selectedArtisan ? selectedArtisan.distance + ' away' : ''"></span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <button wire:click="pingArtisan(selectedArtisan.id)" wire:loading.attr="disabled"
                        wire:target="pingArtisan"
                        class="block w-full py-2 text-white text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed group shadow-lg"
                        :disabled="selectedArtisan && $wire.sentPings.includes(selectedArtisan.id)"
                        :class="selectedArtisan && $wire.sentPings.includes(selectedArtisan.id) ?
                            'bg-green-500 shadow-green-500/20' :
                            'bg-purple-600 shadow-purple-500/30 hover:bg-purple-700 hover:scale-[1.02]'">

                        {{-- Loading Spinner (Native Livewire) --}}
                        <div wire:loading wire:target="pingArtisan">
                            <div class="flex items-center gap-2">
                                <div class="size-3 border-2 border-white/30 border-t-white rounded-full animate-spin">
                                </div>
                                <span
                                    class="text-xs uppercase  leading-none">{{ __('Sending...') }}</span>
                            </div>
                        </div>

                        {{-- Button Content (Hidden while loading) --}}
                        <div wire:loading.remove wire:target="pingArtisan">
                            {{-- Sent State --}}
                            <template x-if="selectedArtisan && $wire.sentPings.includes(selectedArtisan.id)">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="check" class="size-3.5" />
                                    <span
                                        class="text-xs uppercase  leading-none">{{ __('Ping Sent!') }}</span>
                                </div>
                            </template>

                            {{-- Idle State --}}
                            <template x-if="selectedArtisan && !$wire.sentPings.includes(selectedArtisan.id)">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="chat-bubble-left-right"
                                        class="size-3.5 transition-transform group-hover:scale-110" />
                                    <span
                                        class="text-xs uppercase  leading-none">{{ __('Ping Now') }}</span>
                                </div>
                            </template>
                        </div>
                    </button>

                    <button @click="mobileView = 'list'"
                        class="lg:hidden text-xs font-bold uppercase text-zinc-400  py-1 hover:text-zinc-600 transition-colors">
                        Back to List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Structured Inquiry Modal -->
    <flux:modal wire:model="showInquiryForm" class="w-full sm:max-w-xl">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Ping') }} {{ $selectedArtisan?->name }}</h2>
                <flux:modal.close>
                    <flux:icon name="x-mark" class="size-5 text-zinc-400 cursor-pointer hover:text-zinc-600" />
                </flux:modal.close>
            </div>

            <form wire:submit="submitStructuredInquiry" class="space-y-4">
                <flux:textarea wire:model="inquiryTask" label="What do you need done?"
                    placeholder="e.g. My kitchen sink is leaking and needs urgent repair..."
                    rows="3" />

                <flux:input wire:model="inquiryLocation" label="Location Context" placeholder="e.g. Floor 2, Building B, Ikeja"
                    icon="map-pin" />

                <flux:radio.group wire:model="inquiryUrgency" label="Urgency" variant="segmented">
                    <flux:radio value="low" label="Low" />
                    <flux:radio value="medium" label="Medium" />
                    <flux:radio value="high" label="Urgent" />
                </flux:radio.group>

                <div class="space-y-2">
                    <flux:label>{{ __('Photos (Optional)') }}</flux:label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach ($inquiryPhotos as $index => $photo)
                            <div class="relative aspect-square rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                <img loading="lazy" src="{{ $photo->temporaryUrl() }}" class="size-full object-cover">
                                <button type="button" @click="$wire.set('inquiryPhotos.{{ $index }}', null)"
                                    class="absolute top-1 right-1 size-5 bg-black/60 rounded-full flex items-center justify-center text-white">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endforeach

                        <label class="aspect-square rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 flex flex-col items-center justify-center cursor-pointer hover:border-purple-400 transition-colors group">
                            <flux:icon name="plus" class="size-5 text-zinc-400 group-hover:text-purple-600" />
                            <span class="text-[11px] font-bold text-zinc-400 dark:text-zinc-500 mt-1 group-hover:text-purple-600">{{ __('Photo') }}</span>
                            <input type="file" wire:model="inquiryPhotos" multiple class="hidden" accept="image/*">
                        </label>
                    </div>
                    <div wire:loading wire:target="inquiryPhotos" class="flex items-center gap-1.5 mt-1">
                        <div class="size-2.5 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-[11px] text-purple-600 font-bold">{{ __('Uploading...') }}</span>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="bg-red-50 dark:bg-red-900/20 text-red-500 p-3 rounded-lg text-xs font-bold">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full py-2.5 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700 active:scale-[0.98] transition-all text-sm disabled:opacity-50">
                    <span wire:loading.remove wire:target="submitStructuredInquiry">{{ __('Send Ping') }}</span>
                    <span wire:loading wire:target="submitStructuredInquiry" class="flex items-center justify-center gap-2">
                        <div class="size-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        {{ __('Sending...') }}
                    </span>
                </button>
            </form>
        </div>
    </flux:modal>
</div>

@push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .leaflet-container {
            background: #f8fafc;
        }

        .dark .leaflet-container {
            background: #09090b !important;
        }

        .dark .leaflet-tile {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }

        .dark .leaflet-control-zoom-in,
        .dark .leaflet-control-zoom-out {
            background: #18181b !important;
            color: white !important;
            border-color: #27272a !important;
        }
    </style>
    <script>
        document.addEventListener('livewire:navigated', () => {
            // Force leaflet to recalculate size after navigation
            window.dispatchEvent(new Event('resize'));
        });
    </script>
@endpush
