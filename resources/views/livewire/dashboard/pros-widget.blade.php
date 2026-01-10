<?php

use Livewire\Volt\Component;
use App\Models\User;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $artisans = [];
    public $latitude;
    public $longitude;
    public $locationName = '';
    public $inFeed = false;

    public function mount()
    {
        $user = auth()->user();

        if ($user) {
            $this->latitude = $user->latitude;
            $this->longitude = $user->longitude;

            if ($user->address && str_contains($user->address, ',')) {
                $parts = array_map('trim', explode(',', $user->address));
                // Common format: Street, City, State. We want City.
                if (count($parts) >= 2) {
                    // If the first part looks like a street number/name, take the second
                    $this->locationName = preg_match('/[0-9]/', $parts[0]) && count($parts) > 2 ? $parts[1] : $parts[0];
                } else {
                    $this->locationName = $parts[0];
                }
            }
        }

        if (!$this->latitude || !$this->longitude) {
            // Default to Lagos center for guests or users without location
            $this->latitude = 6.5244;
            $this->longitude = 3.3792;
            $this->locationName = $this->locationName ?: 'Lagos';
        }

        $this->loadPros();
    }

    public function loadPros()
    {
        // Fetch 3 closest top-rated artisans
        $this->artisans = User::where('role', 'artisan')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('id', '!=', auth()->id())
            ->select('users.*')
            ->withAvg('reviews', 'rating')
            ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$this->latitude, $this->longitude, $this->latitude])
            // ->whereIn('work_status', ['available', 'at_work']) // Instant value: Available now
            ->orderBy('distance')
            ->limit(20) // Fetch more candidates to allow for filtering
            ->get();

        // Filter out incomplete profiles
        $this->artisans = $this->artisans
            ->filter(function ($artisan) {
                return $artisan->profileCompletion()['is_complete'];
            })
            ->take(3); // Take top 3 after filtering
    }
}; ?>
<div>
    @if ($inFeed)
        {{-- Horizontal In-Feed Version --}}
        <div class="relative my-6 lg:my-8 group/scroll">
            {{-- Header --}}
            <div class="flex items-center justify-between px-1 mb-4">
                <h3 class="font-black text-[10px] uppercase tracking-[0.2em] text-zinc-400">
                    {{ __('Near you') }}
                </h3>
                <div class="flex gap-2">
                    {{-- Scroll Indicators (Desktop) --}}
                    <button
                        class="hidden lg:flex items-center justify-center size-6 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-400 transition-colors"
                        onclick="document.getElementById('pros-scroll-container').scrollBy({left: -280, behavior: 'smooth'})">
                        <flux:icon name="chevron-left" class="size-3" />
                    </button>
                    <button
                        class="hidden lg:flex items-center justify-center size-6 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-400 transition-colors"
                        onclick="document.getElementById('pros-scroll-container').scrollBy({left: 280, behavior: 'smooth'})">
                        <flux:icon name="chevron-right" class="size-3" />
                    </button>
                </div>
            </div>

            {{-- Scroll Container --}}
            {{-- Negative margin on mobile (-mx-4) to span full width, padding (px-4) to align content --}}
            <div id="pros-scroll-container"
                class="flex overflow-x-auto pb-8 -mx-4 px-4 lg:mx-0 lg:px-0 gap-4 snap-x snap-mandatory scroll-smooth hide-scrollbar w-full max-w-full">
                @forelse ($artisans as $pro)
                    @php
                        $loc = $this->locationName ?: 'local';
                        $work = strtolower($pro->work);
                        $heading = match ($work) {
                            'barber' => 'Elite barber for you',
                            'facial', 'makeup' => 'Expert beautician',
                            'plumber' => 'Emergency plumber',
                            'carpenter' => 'Master woodwork',
                            default => ($pro->work ?: 'Top-rated') . ' pro',
                        };

                        // Simple distinct background gradients for variety
                        $gradients = [
                            'from-zinc-800 to-zinc-900',
                            'from-indigo-900 via-zinc-900 to-zinc-900',
                            'from-purple-900/20 via-zinc-900 to-zinc-900',
                            'from-emerald-900/10 via-zinc-900 to-zinc-900',
                        ];
                        $gradient = $gradients[$loop->index % count($gradients)];
                    @endphp

                    <a href="{{ route('artisan.profile', $pro->username) }}" wire:navigate
                        class="relative flex-none w-[220px] sm:w-[260px] aspect-[4/3] rounded-3xl overflow-hidden snap-center group shadow-xl hover:shadow-2xl transition-all duration-300 ring-1 ring-white/10">
                        <!-- Background Image (or Gradient) -->
                        <div class="absolute inset-0 bg-gradient-to-br {{ $gradient }}">
                            @if ($pro->profile_picture_url)
                                <img src="{{ $pro->profile_picture_url }}" alt=""
                                    class="absolute inset-0 size-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-700">
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
                        </div>

                        <!-- Content Overlay -->
                        <div class="absolute inset-0 p-5 flex flex-col justify-between z-10">
                            <div class="flex items-center gap-2">
                                <div class="px-2 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/10">
                                    <span
                                        class="text-[8px] font-black text-white tracking-widest uppercase flex items-center gap-1">
                                        <flux:icon name="sparkles" variant="solid" class="size-3 text-yellow-300" />
                                        {{ $pro->work ?: 'Expert' }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <h4
                                    class="text-lg font-black text-white mb-1 group-hover:translate-x-1 transition-transform tracking-tight leading-tight">
                                    {{ $heading }}
                                </h4>
                                <p class="text-[10px] text-white/70 font-medium mb-4 flex items-center gap-1">
                                    <flux:icon name="map-pin" class="size-3" />
                                    {{ $this->locationName ?: __('Nearby') }}
                                </p>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="size-8 rounded-full border border-white/20 bg-black/50 overflow-hidden backdrop-blur-sm">
                                            @if ($pro->profile_picture_url)
                                                <img src="{{ $pro->profile_picture_url }}"
                                                    class="size-full object-cover">
                                            @else
                                                <div
                                                    class="size-full flex items-center justify-center text-[10px] font-black text-white">
                                                    {{ $pro->initials() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-[10px] font-bold text-white leading-none">{{ $pro->name }}</span>
                                            <div class="flex items-center gap-1 mt-0.5">
                                                <flux:icon name="star" variant="solid"
                                                    class="size-3 text-yellow-500" />
                                                <span class="text-[9px] font-bold text-white">
                                                    {{ $pro->reviews_avg_rating ? number_format($pro->reviews_avg_rating, 1) : 'New' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        class="size-8 rounded-full bg-white text-black flex items-center justify-center opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300 shadow-lg">
                                        <flux:icon name="arrow-right" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    {{-- Fallback --}}  
                    <p class="text-center text-[10px] text-zinc-500 font-bold">it's seems you are not close to any artisan. Spread the word!</p>
                @endforelse
            </div>

            <style>
                .hide-scrollbar::-webkit-scrollbar {
                    display: none;
                }

                .hide-scrollbar {
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }
            </style>
        </div>
    @else
        {{-- Original Sidebar Version --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-black text-sm uppercase tracking-wider text-zinc-900 dark:text-zinc-100 italic">
                    {{ __('Artisans near me') }}
                </h3>
                <span
                    class="flex items-center gap-1.5 px-2 py-1 bg-green-500/10 text-green-600 rounded-full text-[10px] font-black uppercase tracking-tighter animate-pulse">
                    <span class="size-1.5 bg-green-500 rounded-full"></span>
                    {{ __('Available Now') }}
                </span>
            </div>

            <div class="space-y-5">
                @forelse ($artisans as $pro)
                    @php
                        $loc = $this->locationName ?: 'your area';
                        $work = strtolower($pro->work);

                        $heading = match ($work) {
                            'barber' => "Best barber in $loc",
                            'facial', 'makeup', 'beautician' => "Top-rated facial in $loc",
                            'plumber' => 'Expert plumber nearby',
                            'carpenter' => "Master craftsman in $loc",
                            'electrician' => 'Pro electrician available',
                            default => 'Top-rated ' . ($pro->work ?: 'Pro') . ' near you',
                        };

                        // Add "Available Now" feel
                        if ($pro->work_status === 'available' && rand(0, 1)) {
                            $heading = "Available now in $loc";
                        }
                    @endphp
                    <div
                        class="group relative flex items-center gap-4 p-2 -mx-2 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-all duration-300">
                        <div class="relative">
                            <div
                                class="size-12 rounded-full overflow-hidden border-2 border-white dark:border-zinc-900 shadow-md group-hover:scale-110 transition-transform">
                                @if ($pro->profile_picture_url)
                                    <img src="{{ $pro->profile_picture_url }}" alt="{{ $pro->name }}"
                                        class="size-full object-cover">
                                @else
                                    <div
                                        class="size-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 font-black text-sm">
                                        {{ $pro->initials() }}
                                    </div>
                                @endif
                            </div>
                            @if ($pro->work_status === 'available')
                                <div
                                    class="absolute -bottom-1 -right-1 size-4 bg-green-500 border-2 border-white dark:border-zinc-900 rounded-full shadow-sm">
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div
                                class="flex items-center gap-1 text-[11px] font-black text-[var(--color-brand-purple)] uppercase tracking-tight mb-0.5">
                                <flux:icon name="sparkles" variant="solid" class="size-3" />
                                {{ $heading }}
                            </div>
                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $pro->name }}
                            </h4>
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate flex items-center gap-1.5">
                                <span class="font-bold">{{ $pro->work ?: 'Professional' }}</span>
                                <span class="size-0.5 bg-zinc-300 rounded-full"></span>
                                <span class="flex items-center gap-0.5">
                                    <flux:icon name="star" variant="solid" class="size-3 text-yellow-500" />
                                    {{ $pro->reviews_avg_rating ? number_format($pro->reviews_avg_rating, 1) : 'New' }}
                                </span>
                                <span class="size-0.5 bg-zinc-300 rounded-full"></span>
                                <span>{{ round($pro->distance, 1) }}km away</span>
                            </p>
                        </div>

                        <a href="{{ route('artisan.profile', $pro->username) }}" wire:navigate
                            class="shrink-0 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-lg hover:scale-105 active:scale-95 transition-all">
                            {{ __('Hire') }}
                        </a>
                    </div>
                @empty
                    <div
                        class="py-8 text-center bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800">
                        <p class="text-xs text-zinc-500 font-medium px-4">No pros currently active in your immediate
                            area.
                            Spread the word!</p>
                    </div>
                @endforelse
            </div>

            @if ($artisans->isNotEmpty())
                <button
                    class="w-full mt-6 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-500 dark:text-zinc-400 border border-zinc-100 dark:border-zinc-800 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                    {{ __('Discover More Professionals') }}
                </button>
            @endif
        </div>
    @endif
</div>
