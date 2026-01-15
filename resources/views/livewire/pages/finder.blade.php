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

new #[Layout('components.layouts.app')] #[Title('Artisan Finder')] class extends Component {
    #[Url]
    public $search = '';

    public $lat = null;
    public $lng = null;
    public $address = null;
    public $selectedArtisan = null;
    public $sentPings = [];
    public $pingingId = null;

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
        $this->selectedArtisan = User::find($id);
        $this->dispatch(
            'artisan-selected',
            artisan: [
                'id' => $this->selectedArtisan->id,
                'name' => $this->selectedArtisan->name,
                'work' => $this->selectedArtisan->work ?: 'Professional',
                'latitude' => $this->selectedArtisan->latitude,
                'longitude' => $this->selectedArtisan->longitude,
                'distance' => round($this->calculateDistance($this->selectedArtisan), 1) . ' km',
                'profile_picture_url' => $this->selectedArtisan->profile_picture_url,
                'experience_year' => $this->selectedArtisan->experience_year ?: '0',
            ],
        );
    }

    protected function calculateDistance($artisan)
    {
        $theta = $this->lng - $artisan->longitude;
        $dist = sin(deg2rad($this->lat)) * sin(deg2rad($artisan->latitude)) + cos(deg2rad($this->lat)) * cos(deg2rad($artisan->latitude)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344;
    }

    public function pingArtisan($userId)
    {
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
            $this->dispatch('toast', type: 'info', title: 'Connection Alert', message: 'App notification sent to ' . $artisan->name . '. Email is currently unavailable. 🌸');
            $artisan->notify(new ServiceInquiry($sender));
            $this->sentPings[] = $userId;
        } finally {
            $this->pingingId = null;
        }
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
    
            const userIcon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div class='size-4 bg-blue-500 border-2 border-white rounded-full shadow-lg'></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
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
    
                const purpleIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div class='size-4 bg-purple-600 border-2 border-white rounded-full shadow-lg'></div>`,
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
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
    
                const midpoint = [
                    (this.userLat + aLat) / 2,
                    (this.userLng + aLng) / 2
                ];
    
                this.distanceLabel = L.marker(midpoint, {
                    icon: L.divIcon({
                        className: 'distance-label',
                        html: `<div class='bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-full text-[11px] font-black shadow-xl border-2 border-purple-500 dark:border-purple-400 text-purple-600 dark:text-purple-300 animate-in zoom-in duration-300'>${artisan.distance}</div>`,
                        iconSize: [window.innerWidth < 640 ? 60 : 70, 24],
                        iconAnchor: [window.innerWidth < 640 ? 30 : 35, 12]
                    })
                }).addTo(this.map);
    
                const bounds = L.latLngBounds([
                    [this.userLat, this.userLng],
                    [aLat, aLng]
                ]);
    
                this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
            }, 200);
        }
    }" x-init="initMap();
    $watch('userLat', () => updateUserMarker())"
    x-on:artisan-selected.window="updateArtisanOnMap($event.detail.artisan)">

    <!-- Mobile View Toggle -->
    <div
        class="lg:hidden flex border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 sticky top-0 z-20 overflow-hidden shadow-sm shrink-0">
        <button @click="mobileView = 'list'"
            class="flex-1 py-4 text-xs font-black uppercase tracking-widest transition-all relative"
            :class="mobileView === 'list' ? 'text-purple-600' : 'text-zinc-400'">
            {{ __('List View') }}
            <div x-show="mobileView === 'list'" class="absolute bottom-0 left-0 right-0 h-1 bg-purple-600" x-transition>
            </div>
        </button>
        <button @click="mobileView = 'map'; setTimeout(() => map.invalidateSize(), 100)"
            class="flex-1 py-4 text-xs font-black uppercase tracking-widest transition-all relative"
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
                class="text-lg lg:text-xl font-black text-zinc-900 dark:text-white mb-4 items-center gap-2 hidden lg:flex">
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
                        <p class="text-[10px] font-black uppercase text-zinc-400 tracking-widest">Base Location</p>
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
                                        class="size-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center text-lg lg:text-xl font-black text-zinc-500">
                                        {{ $selectedArtisan->initials() }}
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base lg:text-lg font-black text-zinc-900 dark:text-white truncate">
                                    {{ $selectedArtisan->name }}</h2>
                                <p
                                    class="text-[10px] lg:text-xs font-black uppercase text-purple-600 tracking-widest mt-0.5">
                                    {{ $selectedArtisan->work ?: 'Expert' }}</p>
                                <div class="flex items-center gap-3 mt-3">
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="star" variant="solid" class="size-3 text-yellow-500" />
                                        <span
                                            class="text-[10px] font-black dark:text-zinc-400">{{ $selectedArtisan->reviews_avg_rating ? number_format($selectedArtisan->reviews_avg_rating, 1) : 'New' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="briefcase" class="size-3 text-zinc-400" />
                                        <span
                                            class="text-[10px] font-black text-zinc-500">{{ $selectedArtisan->experience_year ?: '0' }}Y
                                            Exp</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 hidden">
                            <flux:button :href="route('artisan.profile', $selectedArtisan->username)" wire:navigate
                                variant="ghost" class="flex-1 rounded-xl">View Profile</flux:button>

                            <button wire:click="pingArtisan('{{ $selectedArtisan->id }}')" wire:loading.attr="disabled"
                                class="flex-1 bg-purple-600 text-white font-black py-2.5 rounded-xl hover:bg-purple-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 disabled:opacity-50">
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
                            class="flex items-center gap-2 text-[10px] font-black uppercase text-zinc-400 tracking-widest mb-4">
                            <span class="size-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            Nearby Professionals
                        </h3>
                        <div class="space-y-3">
                            @foreach ($nearby as $artisan)
                                <div wire:key="nearby-{{ $artisan->id }}"
                                    wire:click="selectArtisan('{{ $artisan->id }}')"
                                    class="group p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/30 border {{ $selectedArtisan && $selectedArtisan->id == $artisan->id ? 'border-purple-500 ring-2 ring-purple-500/10' : 'border-zinc-100 dark:border-zinc-800' }} hover:border-purple-200 dark:hover:border-purple-800 transition-all cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 rounded-xl overflow-hidden shrink-0">
                                            @if ($artisan->profile_picture_url)
                                                <img src="{{ $artisan->profile_picture_url }}"
                                                    class="size-full object-cover">
                                            @else
                                                <div
                                                    class="size-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-black text-zinc-500">
                                                    {{ $artisan->initials() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                                                    {{ $artisan->name }}</p>
                                                <span
                                                    class="shrink-0 text-[10px] font-black text-purple-600 bg-purple-50 dark:bg-purple-900/30 px-2 py-0.5 rounded-full">{{ round($artisan->distance, 1) }}km</span>
                                            </div>
                                            <p class="text-[10px] text-zinc-500 font-medium uppercase truncate">
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
                        <h3 class="text-[10px] font-black uppercase text-zinc-400 tracking-widest mb-4">Suggested
                            Experts</h3>
                        <div class="space-y-3">
                            @foreach ($suggested as $artisan)
                                <div wire:key="suggested-{{ $artisan->id }}"
                                    wire:click="selectArtisan('{{ $artisan->id }}')"
                                    class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border {{ $selectedArtisan && $selectedArtisan->id == $artisan->id ? 'border-purple-500 ring-2 ring-purple-500/10' : 'border-zinc-100 dark:border-zinc-800' }} hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-all cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 rounded-xl overflow-hidden shrink-0 grayscale opacity-70">
                                            @if ($artisan->profile_picture_url)
                                                <img src="{{ $artisan->profile_picture_url }}"
                                                    class="size-full object-cover">
                                            @else
                                                <div
                                                    class="size-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-black text-zinc-500">
                                                    {{ $artisan->initials() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-bold text-zinc-600 dark:text-zinc-400 truncate">
                                                    {{ $artisan->name }}</p>
                                                <span
                                                    class="shrink-0 text-[10px] font-bold text-zinc-400">{{ round($artisan->distance, 1) }}km</span>
                                            </div>
                                            <p class="text-[10px] text-zinc-500 font-medium uppercase truncate">
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
                    <div class="size-3 bg-blue-500 rounded-full border border-white shadow-sm"></div>
                    <span class="text-[10px] font-black uppercase text-zinc-500 tracking-wider">You</span>
                </div>
                <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="flex items-center gap-2">
                    <div class="size-3 bg-purple-600 rounded-full border border-white shadow-sm"></div>
                    <span
                        class="text-[10px] font-black uppercase text-zinc-500 tracking-wider text-nowrap">Expert</span>
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
                    class="text-[9px] lg:text-[10px] font-black uppercase tracking-widest text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Live
                    Network</span>
            </div>
        </div>

        <!-- Artisan Card Overlay (On Map) -->
        <div class="absolute bottom-24 lg:bottom-10 right-4 lg:right-10 z-20 pointer-events-none"
            x-show="selectedArtisan">
            <div
                class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md p-5 rounded-[2rem] shadow-2xl border border-white/20 pointer-events-auto w-72 lg:w-80 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="flex items-start gap-4 mb-5">
                    <div
                        class="size-14 lg:size-16 rounded-2xl overflow-hidden border-2 border-purple-500 shrink-0 shadow-lg bg-zinc-100 dark:bg-zinc-800">
                        <template x-if="selectedArtisan && selectedArtisan.profile_picture_url">
                            <img :src="selectedArtisan.profile_picture_url" class="size-full object-cover">
                        </template>
                        <template x-if="!selectedArtisan || !selectedArtisan.profile_picture_url">
                            <div class="size-full flex items-center justify-center text-zinc-400 font-black text-lg">
                                <span
                                    x-text="selectedArtisan ? selectedArtisan.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0,2) : ''"></span>
                            </div>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base lg:text-lg text-zinc-900 dark:text-white truncate"
                            x-text="selectedArtisan ? selectedArtisan.name : ''"></h3>
                        <p class="text-[10px] lg:text-xs font-black uppercase text-purple-600 tracking-wider"
                            x-text="selectedArtisan ? (selectedArtisan.work || 'Expert') : ''"></p>
                        <div class="mt-1 flex items-center gap-1.5">
                            <span
                                class="px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-[10px] font-bold text-zinc-500"
                                x-text="selectedArtisan ? (selectedArtisan.experience_year || '0') + 'Y Exp' : ''"></span>
                            <span
                                class="px-2 py-0.5 rounded-full bg-purple-600/10 text-[10px] font-black text-purple-600"
                                x-text="selectedArtisan ? selectedArtisan.distance + ' away' : ''"></span>
                            <template x-if="selectedArtisan && selectedArtisan.is_premium">
                                <span
                                    class="px-2 py-0.5 rounded-full bg-yellow-500/10 text-[10px] font-black text-yellow-500">Premium</span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button wire:click="pingArtisan(selectedArtisan.id)" wire:loading.attr="disabled"
                        wire:target="pingArtisan"
                        class="block w-full py-3.5 text-white font-black rounded-2xl transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed group shadow-lg"
                        :disabled="selectedArtisan && $wire.sentPings.includes(selectedArtisan.id)"
                        :class="selectedArtisan && $wire.sentPings.includes(selectedArtisan.id) ?
                            'bg-green-500 shadow-green-500/20' :
                            'bg-purple-600 shadow-purple-500/30 hover:bg-purple-700 hover:scale-[1.02]'">

                        {{-- Loading Spinner (Native Livewire) --}}
                        <div wire:loading wire:target="pingArtisan">
                            <div class="flex items-center gap-2">
                                <div class="size-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                                </div>
                                <span
                                    class="text-xs uppercase tracking-widest leading-none">{{ __('Sending...') }}</span>
                            </div>
                        </div>

                        {{-- Button Content (Hidden while loading) --}}
                        <div wire:loading.remove wire:target="pingArtisan">
                            {{-- Sent State --}}
                            <template x-if="selectedArtisan && $wire.sentPings.includes(selectedArtisan.id)">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="check" class="size-4" />
                                    <span
                                        class="text-xs uppercase tracking-widest leading-none">{{ __('Ping Sent!') }}</span>
                                </div>
                            </template>

                            {{-- Idle State --}}
                            <template x-if="selectedArtisan && !$wire.sentPings.includes(selectedArtisan.id)">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="chat-bubble-left-right"
                                        class="size-4 transition-transform group-hover:scale-110" />
                                    <span
                                        class="text-xs uppercase tracking-widest leading-none">{{ __('Ping Now') }}</span>
                                </div>
                            </template>
                        </div>
                    </button>

                    <button @click="mobileView = 'list'"
                        class="lg:hidden text-[10px] font-black uppercase text-zinc-400 tracking-widest py-2 hover:text-zinc-600 transition-colors">
                        Back to List
                    </button>
                </div>
            </div>
        </div>
    </div>
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
