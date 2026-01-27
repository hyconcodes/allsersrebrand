<?php

use App\Models\Challenge;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public $title = '';
    public $hashtag = '';
    public $guidelines = '';
    public $prizes = '';
    public $start_at;
    public $end_at;
    public $banner;
    public $selectedJudges = [];
    public $searchQuery = '';

    public function mount()
    {
        if (!auth()->check() || (!auth()->user()->isArtisan() && !auth()->user()->isAdmin())) {
            abort(403, 'Only artisans and admins can create challenges.');
        }
    }

    public function with()
    {
        return [
            'users' =>
                strlen($this->searchQuery) >= 2
                    ? User::where(function ($q) {
                        $q->where('name', 'like', '%' . $this->searchQuery . '%')->orWhere('username', 'like', '%' . $this->searchQuery . '%');
                    })
                        ->where('id', '!=', auth()->id())
                        ->limit(5)
                        ->get()
                    : [],
        ];
    }

    public function addJudge($userId)
    {
        if (!in_array($userId, $this->selectedJudges)) {
            $this->selectedJudges[] = $userId;
        }
        $this->searchQuery = '';
    }

    public function removeJudge($userId)
    {
        $this->selectedJudges = array_diff($this->selectedJudges, [$userId]);
    }

    public function create()
    {
        $this->validate([
            'title' => 'required|min:5|max:100',
            'hashtag' => 'required|alpha_dash|unique:challenges,hashtag',
            'guidelines' => 'required|min:20',
            'prizes' => 'required|min:10',
            'start_at' => 'required|date|after_or_equal:today',
            'end_at' => 'required|date|after:start_at',
            'banner' => 'nullable|image|max:5120',
        ]);

        $bannerPath = $this->banner ? $this->banner->store('challenges/banners', 'public') : null;

        $challenge = Challenge::create([
            'creator_id' => auth()->id(),
            'title' => $this->title,
            'hashtag' => str_replace('#', '', $this->hashtag),
            'guidelines' => $this->guidelines,
            'prizes' => $this->prizes,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'is_admin_challenge' => auth()->check() && auth()->user()->isAdmin(),
            'banner_url' => $bannerPath,
        ]);

        // Invite Judges
        foreach ($this->selectedJudges as $judgeId) {
            $challenge->judges()->attach($judgeId, ['status' => 'pending']);

            // Notify judge (Placeholder for now, would use a Notification class)
            $judge = User::find($judgeId);
            $judge->notify(new \App\Notifications\ChallengeJudgeInvitation($challenge));
        }

        $this->dispatch('toast', type: 'success', title: 'Success', message: 'Challenge created successfully!');

        return redirect()->route('challenges.show', $challenge->custom_link);
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 px-4">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <!-- Header -->
        <div class="p-6 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/20">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Create New Challenge') }}</h1>
            <p class="text-zinc-500 text-sm mt-1">
                {{ __('Spark creativity and reward the best artisans in the community.') }}</p>
        </div>

        <form wire:submit.prevent="create" class="p-8 space-y-8">
            <!-- Basic Info -->
            <div class="space-y-6">
                <div>
                    <flux:label>{{ __('Challenge Title') }}</flux:label>
                    <flux:input wire:model="title" placeholder="e.g. Modern Kitchen Design 2024" />
                    <flux:error name="title" />
                </div>

                <div>
                    <flux:label>{{ __('Unique Hashtag') }}</flux:label>
                    <flux:input wire:model="hashtag" prefix="#" placeholder="KitchenMakeover" />
                    <p class="text-[10px] text-zinc-500 mt-1 uppercase font-bold tracking-widest">
                        {{ __('This will be automatically added to all participation posts.') }}</p>
                    <flux:error name="hashtag" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <flux:label>{{ __('Start Date') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="start_at" />
                    <flux:error name="start_at" />
                </div>
                <div>
                    <flux:label>{{ __('End Date') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="end_at" />
                    <flux:error name="end_at" />
                </div>
            </div>

            <div>
                <flux:label>{{ __('Banner Image (Optional)') }}</flux:label>
                <div class="mt-2 flex items-center gap-4">
                    @if ($banner)
                        <div class="size-20 rounded-lg overflow-hidden border border-zinc-200">
                            <img src="{{ $banner->temporaryUrl() }}" class="size-full object-cover">
                        </div>
                    @endif
                    <flux:input type="file" wire:model="banner" />
                </div>
                <flux:error name="banner" />
            </div>

            <div>
                <flux:label>{{ __('Guidelines') }}</flux:label>
                <flux:textarea wire:model="guidelines" rows="5"
                    placeholder="What are the rules? What should they create?" />
                <flux:error name="guidelines" />
            </div>

            <div>
                <flux:label>{{ __('Prize Details') }}</flux:label>
                <flux:textarea wire:model="prizes" rows="3" placeholder="e.g. $500 Cash Prize + Profile Badge" />
                <flux:error name="prizes" />
            </div>

            <!-- Judges -->
            <div class="space-y-4">
                <flux:label>{{ __('Invite Judges') }}</flux:label>
                <div class="relative">
                    <flux:input wire:model.live.debounce.300ms="searchQuery" icon="magnifying-glass"
                        placeholder="Search for judges by name..." />

                    @if (count($users) > 0)
                        <div
                            class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xl p-2">
                            @foreach ($users as $user)
                                <button type="button" wire:click="addJudge({{ $user->id }})"
                                    class="w-full flex items-center gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                                    <div class="size-8 rounded-full overflow-hidden bg-zinc-100">
                                        @if ($user->profile_picture_url)
                                            <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                                        @else
                                            <div
                                                class="size-full flex items-center justify-center text-[10px] font-bold">
                                                {{ $user->initials() }}</div>
                                        @endif
                                    </div>
                                    <div class="text-left">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $user->name }}</p>
                                        <p class="text-xs text-zinc-500">@<span>{{ $user->username }}</span></p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Selected Judges -->
                @if (count($selectedJudges) > 0)
                    <div class="flex flex-wrap gap-2 pt-2">
                        @foreach ($selectedJudges as $judgeId)
                            @php $judgeUser = \App\Models\User::find($judgeId); @endphp
                            <div
                                class="flex items-center gap-2 bg-zinc-100 dark:bg-zinc-800 pl-1 pr-3 py-1 rounded-full border border-zinc-200 dark:border-zinc-700">
                                <div class="size-6 rounded-full overflow-hidden bg-zinc-200">
                                    @if ($judgeUser->profile_picture_url)
                                        <img src="{{ $judgeUser->profile_picture_url }}"
                                            class="size-full object-cover">
                                    @else
                                        <div class="size-full flex items-center justify-center text-[8px] font-bold">
                                            {{ $judgeUser->initials() }}</div>
                                    @endif
                                </div>
                                <span
                                    class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $judgeUser->name }}</span>
                                <button type="button" wire:click="removeJudge({{ $judgeId }})"
                                    class="text-zinc-400 hover:text-red-500">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pt-6">
                <flux:button type="submit" variant="primary" class="w-full h-12 text-lg">
                    {{ __('Launch Challenge') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
