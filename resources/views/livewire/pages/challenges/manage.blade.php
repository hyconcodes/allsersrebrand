<?php

use App\Models\Challenge;
use App\Models\User;
use App\Models\Badge;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public Challenge $challenge;
    public $title;
    public $guidelines;
    public $prizes;
    public $start_at;
    public $end_at;
    public $banner;

    public $winnerId;
    public $badgeName;
    public $badgeDescription;
    public $badgeIcon;

    public function mount($slug)
    {
        $this->challenge = Challenge::where('custom_link', $slug)->firstOrFail();

        if (auth()->id() !== $this->challenge->creator_id && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $this->title = $this->challenge->title;
        $this->guidelines = $this->challenge->guidelines;
        $this->prizes = $this->challenge->prizes;
        $this->start_at = $this->challenge->start_at->format('Y-m-d\TH:i');
        $this->end_at = $this->challenge->end_at->format('Y-m-d\TH:i');
        $this->winnerId = $this->challenge->winner_id;
    }

    public function updateDetails()
    {
        $this->validate([
            'title' => 'required|min:5|max:100',
            'guidelines' => 'required|min:20',
            'prizes' => 'required|min:10',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
        ]);

        $this->challenge->update([
            'title' => $this->title,
            'guidelines' => $this->guidelines,
            'prizes' => $this->prizes,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
        ]);

        if ($this->banner) {
            $bannerPath = $this->banner->store('challenges/banners', 'public');
            $this->challenge->update(['banner_url' => $bannerPath]);
        }

        $this->dispatch('toast', type: 'success', title: 'Updated', message: 'Challenge details have been saved.');
    }

    public function awardWinner()
    {
        $this->validate([
            'winnerId' => 'required|exists:users,id',
            'badgeName' => 'required|min:3',
            'badgeIcon' => 'nullable|image|max:1024',
        ]);

        $iconPath = $this->badgeIcon ? $this->badgeIcon->store('badges', 'public') : null;

        $badge = Badge::create([
            'challenge_id' => $this->challenge->id,
            'name' => $this->badgeName,
            'description' => $this->badgeDescription ?? "Winner of {$this->challenge->title}",
            'icon_url' => $iconPath,
        ]);

        $winner = User::find($this->winnerId);
        $winner->badges()->attach($badge->id, ['awarded_at' => now()]);

        $this->challenge->update(['winner_id' => $this->winnerId]);

        // Notify winner
        $winner->notify(new \App\Notifications\ChallengeWinnerNotification($this->challenge));

        $this->dispatch('toast', type: 'success', title: 'Winner Awarded!', message: "Congratulations to {$winner->name}!");
    }
}; ?>

<div class="max-w-4xl mx-auto py-12 px-4 space-y-12">
    <!-- Back to Challenge -->
    <a href="{{ route('challenges.show', $challenge->custom_link) }}"
        class="flex items-center gap-2 text-zinc-500 hover:text-zinc-900 font-bold transition-colors">
        <flux:icon name="arrow-left" class="size-4" />
        {{ __('Back to Challenge Page') }}
    </a>

    <!-- Settings Card -->
    <div
        class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-zinc-100 dark:border-zinc-800">
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Challenge Settings') }}</h2>
        </div>

        <form wire:submit.prevent="updateDetails" class="p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model="title" />
                </div>
                <div>
                    <flux:label>{{ __('Start Date') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="start_at" />
                </div>
                <div>
                    <flux:label>{{ __('End Date') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="end_at" />
                </div>
                <div class="md:col-span-2">
                    <flux:label>{{ __('Banner Image') }}</flux:label>
                    <div class="mt-2 flex items-center gap-6">
                        @if ($challenge->banner_url || $banner)
                            <div class="size-24 rounded-xl overflow-hidden shadow-sm">
                                <img src="{{ $banner ? $banner->temporaryUrl() : asset('storage/' . $challenge->banner_url) }}"
                                    class="size-full object-cover">
                            </div>
                        @endif
                        <flux:input type="file" wire:model="banner" />
                    </div>
                </div>
                <div class="md:col-span-2">
                    <flux:label>{{ __('Guidelines') }}</flux:label>
                    <flux:textarea wire:model="guidelines" rows="6" />
                </div>
                <div class="md:col-span-2">
                    <flux:label>{{ __('Prizes') }}</flux:label>
                    <flux:textarea wire:model="prizes" rows="2" />
                </div>
            </div>

            <div class="pt-6">
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </div>

    <!-- Reward & Winner Card -->
    <div
        class="bg-gradient-to-br from-zinc-50 to-zinc-100 dark:from-zinc-800/50 dark:to-zinc-900 rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 p-8 shadow-sm">
        <div class="max-w-2xl mx-auto text-center space-y-8">
            <div>
                <h2 class="text-2xl font-black text-zinc-900 dark:text-white">{{ __('Reward & Close Challenge') }}</h2>
                <p class="text-zinc-500 mt-2">
                    {{ __('Select the winner and award them a unique badge once the challenge ends.') }}</p>
            </div>

            @if ($challenge->winner_id)
                <div
                    class="bg-white dark:bg-zinc-900 p-6 rounded-2xl inline-flex items-center gap-4 border border-zinc-200 shadow-xl">
                    <flux:icon name="check-circle" class="size-8 text-green-500" />
                    <div class="text-left">
                        <p class="text-sm font-bold">{{ __('Winner Selected') }}</p>
                        <p class="text-lg font-black text-[var(--color-brand-purple)]">{{ $challenge->winner->name }}
                        </p>
                    </div>
                </div>
            @else
                <form wire:submit.prevent="awardWinner" class="space-y-6 text-left">
                    <div>
                        <flux:label>{{ __('Select Winner') }}</flux:label>
                        <select wire:model="winnerId"
                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white focus:ring-[var(--color-brand-purple)] focus:border-[var(--color-brand-purple)]">
                            <option value="">{{ __('Choose a participant...') }}</option>
                            @foreach ($challenge->participants as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}
                                    (@<span>{{ $p->username }}</span>)</option>
                            @endforeach
                        </select>
                        <flux:error name="winnerId" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:label>{{ __('Badge Name') }}</flux:label>
                            <flux:input wire:model="badgeName" placeholder="e.g. Master Artisan 2024" />
                            <flux:error name="badgeName" />
                        </div>
                        <div>
                            <flux:label>{{ __('Badge Description') }}</flux:label>
                            <flux:input wire:model="badgeDescription" placeholder="Quick description..." />
                        </div>
                        <div>
                            <flux:label>{{ __('Badge Icon') }}</flux:label>
                            <flux:input type="file" wire:model="badgeIcon" />
                        </div>
                    </div>

                    <div class="pt-4">
                        <flux:button type="submit" variant="primary" class="w-full h-12">
                            {{ __('Award Badge & End Challenge') }}</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
