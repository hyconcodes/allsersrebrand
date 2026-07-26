<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Engagement;
use App\Mail\ServiceInquiryMail;
use App\Notifications\ServiceInquiry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithFileUploads;

    public $artisans = [];
    public $latitude;
    public $longitude;
    public $locationName = '';
    public $inFeed = false;
    public $limit = 5;
    public $isWelcome = false;

    // Inquiry Properties
    public $showInquiryForm = false;
    public $selectedArtisanRecord = null;
    public $inquiryTask = '';
    public $inquiryLocation = '';
    public $inquiryUrgency = 'medium';
    public $inquiryPhotos = [];

    public function mount()
    {
        $this->limit = $this->inFeed ? 16 : 5;

        $user = auth()->user();

        if ($user) {
            $this->latitude = $user->latitude;
            $this->longitude = $user->longitude;

            if ($user->address && str_contains($user->address, ',')) {
                $parts = array_map('trim', explode(',', $user->address));
                if (count($parts) >= 2) {
                    $this->locationName = preg_match('/[0-9]/', $parts[0]) && count($parts) > 2 ? $parts[1] : $parts[0];
                } else {
                    $this->locationName = $parts[0];
                }
            }
        }

        if (!$this->latitude || !$this->longitude) {
            $this->latitude = 6.5244;
            $this->longitude = 3.3792;
            $this->locationName = $this->locationName ?: 'Lagos';
        }

        $this->loadPros();
    }

    public function loadPros()
    {
        $this->artisans = User::where('role', 'artisan')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('id', '!=', auth()->id())
            ->whereNotIn('work', ['Guest', 'Provider', 'Admin'])
            ->select('users.*')
            ->withAvg('reviews', 'rating')
            ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$this->latitude, $this->longitude, $this->latitude])
            ->orderBy('distance')
            ->limit(50)
            ->get();

        $this->artisans = $this->artisans
            ->filter(fn($artisan) => $artisan->profileCompletion()['is_complete'])
            ->take($this->limit);
    }

    public function startInquiry($artisanId)
    {
        $this->selectedArtisanRecord = User::find($artisanId);
        $this->inquiryLocation = $this->locationName;
        $this->showInquiryForm = true;
    }

    public function submitStructuredInquiry()
    {
        if (!$this->selectedArtisanRecord) {
            return;
        }

        $this->validate([
            'inquiryTask' => 'required|min:10',
            'inquiryLocation' => 'required',
            'inquiryUrgency' => 'required|in:low,medium,high',
            'inquiryPhotos.*' => 'image|max:5120',
        ]);

        $sender = auth()->user();
        $artisan = $this->selectedArtisanRecord;

        $photoPaths = [];
        if ($this->inquiryPhotos) {
            foreach ($this->inquiryPhotos as $photo) {
                $photoPaths[] = $photo->store('inquiries/photos', 'cloudinary');
            }
        }

        $conversation = Conversation::whereHas('users', fn($q) => $q->where('users.id', $sender->id))
            ->whereHas('users', fn($q) => $q->where('users.id', $artisan->id))
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create(['last_message_at' => now()]);
            $conversation->users()->attach([$sender->id, $artisan->id]);
        }

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

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'type' => 'inquiry',
            'engagement_id' => $engagement->id,
            'content' => $this->inquiryTask,
        ]);

        $conversation->update(['last_message_at' => now()]);

        try {
            Mail::to($artisan->email)->queue(new ServiceInquiryMail($sender, $artisan));
            $artisan->notify(new ServiceInquiry($sender));
        } catch (\Exception $e) {
            $artisan->notify(new ServiceInquiry($sender));
        }

        $this->dispatch('toast', type: 'success', title: 'Inquiry Sent!', message: 'Taking you to your conversation with ' . $artisan->name);

        return $this->redirect(route('chat', $conversation->id), navigate: true);
    }
}; ?>
<div>
    @if ($inFeed)
        {{-- Horizontal In-Feed Version --}}
        <div class="relative my-6 lg:my-8 group/scroll">
            <div class="flex items-center justify-between px-1 mb-4">
                <h3 class="font-bold text-xs uppercase {{ $isWelcome ? 'text-zinc-600 dark:text-zinc-400' : 'text-zinc-400' }}">
                    {{ __('Near you') }}
                </h3>
                <div class="flex gap-2">
                    <button
                        class="hidden lg:flex items-center justify-center size-6 rounded-full {{ $isWelcome ? 'bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400' }} hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
                        onclick="document.getElementById('pros-scroll-container').scrollBy({left: -280, behavior: 'smooth'})">
                        <flux:icon name="chevron-left" class="size-3" />
                    </button>
                    <button
                        class="hidden lg:flex items-center justify-center size-6 rounded-full {{ $isWelcome ? 'bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400' }} hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
                        onclick="document.getElementById('pros-scroll-container').scrollBy({left: 280, behavior: 'smooth'})">
                        <flux:icon name="chevron-right" class="size-3" />
                    </button>
                </div>
            </div>

            <div id="pros-scroll-container"
                class="flex overflow-x-auto pb-8 -mx-4 px-4 lg:mx-0 lg:px-0 gap-4 snap-x snap-mandatory scroll-smooth hide-scrollbar w-full max-w-full">
                @forelse ($artisans as $pro)
                    @php
                        $gradients = [
                            'from-zinc-800 to-zinc-900',
                            'from-indigo-900 via-zinc-900 to-zinc-900',
                            'from-purple-900/20 via-zinc-900 to-zinc-900',
                            'from-emerald-900/10 via-zinc-900 to-zinc-900',
                        ];
                        $gradient = $gradients[$loop->index % count($gradients)];
                    @endphp

                    <a href="{{ route('artisan.profile', $pro) }}" wire:navigate
                        class="relative flex-none w-[220px] sm:w-[260px] aspect-[4/3] rounded-3xl overflow-hidden snap-center group shadow-xl hover:shadow-2xl transition-all duration-300 ring-1 ring-white/10">
                        <div class="absolute inset-0 bg-gradient-to-br {{ $gradient }}">
                            @if ($pro->profile_picture_url)
                                <img loading="lazy" src="{{ $pro->profile_picture_url }}" alt=""
                                    class="absolute inset-0 size-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-700">
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
                        </div>

                        <div class="absolute inset-0 p-5 flex flex-col justify-between z-10">
                            <div>
                                <p class="text-xs text-white/70 font-medium flex items-center gap-1">
                                    <flux:icon name="map-pin" class="size-3" />
                                    {{ $this->locationName ?: __('Nearby') }}
                                </p>
                            </div>

                            <div>
                                <h4 class="text-lg font-bold text-white mb-1.5 group-hover:translate-x-1 transition-transform tracking-tight leading-tight">
                                    {{ ucwords($pro->work ?: 'Professional') }}
                                </h4>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="size-8 rounded-full border border-white/20 bg-black/50 overflow-hidden backdrop-blur-sm">
                                            @if ($pro->profile_picture_url)
                                                <img loading="lazy" src="{{ $pro->profile_picture_url }}" class="size-full object-cover">
                                            @else
                                                <div class="size-full flex items-center justify-center text-xs font-bold text-white">
                                                    {{ $pro->initials() }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-xs font-bold text-white leading-none truncate">{{ $pro->name }}</span>
                                            <div class="flex items-center gap-1 mt-0.5">
                                                <flux:icon name="star" variant="solid" class="size-3 text-yellow-500" />
                                                <span class="text-xs font-bold text-white">
                                                    {{ $pro->reviews_avg_rating ? number_format($pro->reviews_avg_rating, 1) : 'New' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="text-center text-xs text-zinc-500 font-bold">it's seems you are not close to any artisan. Spread the word!</p>
                @endforelse
            </div>

            <style>
                .hide-scrollbar::-webkit-scrollbar { display: none; }
                .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            </style>
        </div>
    @else
        {{-- X-Style Sidebar "Nearby Pros" --}}
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Nearby Pros') }}</h3>
            </div>

            <div>
                @forelse ($artisans as $pro)
                    <div class="flex items-center gap-3 px-5 py-3.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ !$loop->last ? 'border-b border-zinc-50 dark:border-zinc-800/30' : '' }}">
                        <div class="relative shrink-0">
                            <div class="size-10 rounded-full overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                                @if ($pro->profile_picture_url)
                                    <img loading="lazy" src="{{ $pro->profile_picture_url }}" alt="{{ $pro->name }}" class="size-full object-cover">
                                @else
                                    <div class="size-full flex items-center justify-center text-xs font-bold text-zinc-400">
                                        {{ $pro->initials() }}
                                    </div>
                                @endif
                            </div>
                            @if ($pro->work_status === 'available')
                                <div class="absolute -bottom-0.5 -right-0.5 size-3 bg-green-500 border-2 border-white dark:border-zinc-900 rounded-full"></div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate leading-tight">{{ $pro->name }}</h4>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate flex items-center gap-1 mt-0.5">
                                <span>{{ $pro->work ?: 'Professional' }}</span>
                                <span class="size-0.5 bg-zinc-300 dark:bg-zinc-600 rounded-full shrink-0"></span>
                                <span class="flex items-center gap-0.5 shrink-0">
                                    <flux:icon name="star" variant="solid" class="size-2.5 text-yellow-500" />
                                    {{ $pro->reviews_avg_rating ? number_format($pro->reviews_avg_rating, 1) : 'New' }}
                                </span>
                                <span class="size-0.5 bg-zinc-300 dark:bg-zinc-600 rounded-full shrink-0"></span>
                                <span class="shrink-0">{{ round($pro->distance, 1) }}km</span>
                            </p>
                        </div>

                        <button wire:click="startInquiry({{ $pro->id }})"
                            class="shrink-0 px-3 py-1.5 text-xs font-bold rounded-lg bg-purple-600 text-white hover:bg-purple-700 active:scale-95 transition-all">
                            {{ __('Ping') }}
                        </button>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center">
                        <p class="text-xs text-zinc-500">No pros currently active in your immediate area. Spread the word!</p>
                    </div>
                @endforelse
            </div>

            @if ($artisans->isNotEmpty())
                <a href="{{ route('finder') }}" wire:navigate
                    class="flex items-center justify-center gap-1 px-5 py-3.5 border-t border-zinc-100 dark:border-zinc-800 text-xs font-bold text-purple-600 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    {{ __('Find more in Discover') }}
                    <flux:icon name="arrow-right" class="size-3.5" />
                </a>
            @endif
        </div>

        {{-- Ping Inquiry Modal --}}
        <flux:modal wire:model="showInquiryForm" class="w-full sm:max-w-xl">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Ping') }} {{ $selectedArtisanRecord?->name }}</h2>
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
    @endif
</div>