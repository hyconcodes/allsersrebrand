<?php

use App\Models\Challenge;
use App\Models\Post;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new class extends Component {
    public Challenge $challenge;
    public $activeTab = 'trending';
    public $showPostModal = false;

    public function rendering(\Illuminate\View\View $view)
    {
        $title = $this->challenge->title . ' Challenge | Allsers';
        $description = Str::limit(strip_tags($this->challenge->guidelines), 160);

        $view->layout('components.layouts.app', [
            'title' => $title,
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaUrl' => route('challenges.show', $this->challenge->custom_link),
        ]);
    }

    public function mount($slug)
    {
        $this->challenge = Challenge::with(['creator', 'judges', 'participants', 'badge'])
            ->where('custom_link', $slug)
            ->firstOrFail();
    }

    public function with()
    {
        $query = Post::with(['user', 'ratings'])
            ->where('challenge_id', $this->challenge->id)
            ->withCount('likes');

        if ($this->activeTab === 'trending') {
            $query->orderByDesc('likes_count')->orderByDesc('created_at');
        } else {
            $query->latest();
        }

        return [
            'posts' => $query->paginate(10),
            'pinnedPost' => Post::with('user')->where('challenge_id', $this->challenge->id)->where('is_challenge_pinned', true)->first(),
            'judgePosts' => Post::with('user')
                ->where('challenge_id', $this->challenge->id)
                ->whereIn('user_id', $this->challenge->judges->pluck('id'))
                ->latest()
                ->get(),
            'isParticipant' => auth()->check() && $this->challenge->isParticipant(auth()->user()),
            'isJudge' => auth()->check() && $this->challenge->isJudge(auth()->user()),
            'pendingInvitation' =>
                auth()->check() &&
                $this->challenge
                    ->judges()
                    ->where('challenge_judges.user_id', auth()->id())
                    ->where('challenge_judges.status', 'pending')
                    ->exists(),
            'timeLeft' => $this->challenge->hasEnded() ? 'Ended' : $this->challenge->end_at->diffForHumans(now(), true) . ' left',
        ];
    }

    public function join()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!$this->challenge->isParticipant(auth()->user())) {
            $this->challenge->participants()->attach(auth()->id(), ['joined_at' => now()]);
            $this->dispatch('toast', type: 'success', title: 'Welcome!', message: 'You have joined the challenge!');
        }
    }

    public function ratePost($postId, $rating)
    {
        if (!$this->challenge->isJudge(auth()->user())) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'Only judges can rate posts.');
            return;
        }

        \App\Models\ChallengeRating::updateOrCreate(['post_id' => $postId, 'user_id' => auth()->id()], ['rating' => $rating]);

        $this->dispatch('toast', type: 'success', title: 'Rated', message: 'Your rating has been submitted.');
    }

    public function acceptInvitation()
    {
        if (!auth()->check()) {
            return;
        }

        $this->challenge->judges()->updateExistingPivot(auth()->id(), [
            'status' => 'accepted',
        ]);

        $this->dispatch('toast', type: 'success', title: 'Invitation Accepted', message: 'You are now an official judge for this challenge!');
        $this->challenge->load('judges');
    }

    public function declineInvitation()
    {
        if (!auth()->check()) {
            return;
        }

        $this->challenge->judges()->updateExistingPivot(auth()->id(), [
            'status' => 'declined',
        ]);

        $this->dispatch('toast', type: 'info', title: 'Invitation Declined', message: 'You have declined the judging invitation.');
        $this->challenge->load('judges');
    }

    public function pinPost($postId)
    {
        if (auth()->id() !== $this->challenge->creator_id) {
            return;
        }

        // Unpin others
        Post::where('challenge_id', $this->challenge->id)->update(['is_challenge_pinned' => false]);

        // Pin new
        Post::where('id', $postId)->update(['is_challenge_pinned' => true]);

        $this->dispatch('toast', type: 'success', title: 'Pinned', message: 'Post pinned to challenge top.');
    }

    #[On('post-created')]
    public function handlePostCreated()
    {
        // Refresh
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content (Left) -->
        <div class="flex-1 space-y-8">
            <!-- Header Card (Design matching the image) -->
            <div
                class="relative rounded-3xl overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <!-- Banner Image / Background -->
                <div class="h-64 relative bg-zinc-100 dark:bg-zinc-800">
                    @if ($challenge->banner_url)
                        <img src="{{ asset('storage/' . $challenge->banner_url) }}"
                            class="size-full object-cover opacity-60">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>

                    <!-- Back Button -->
                    <a href="{{ route('challenges.index') }}"
                        class="absolute top-6 left-6 p-2 rounded-full bg-white/20 backdrop-blur-md text-white hover:bg-white/40 transition-colors">
                        <flux:icon name="arrow-left" class="size-5" />
                    </a>

                    <!-- Share Button -->
                    <button x-data="{
                        copy() {
                            navigator.clipboard.writeText(window.location.href);
                            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Challenge link copied to clipboard.' });
                        }
                    }" @click="copy"
                        class="absolute top-6 right-6 px-4 py-2 rounded-full bg-white text-zinc-900 font-bold text-sm shadow-lg hover:bg-zinc-100 transition-colors">
                        {{ __('Share') }}
                    </button>

                    <!-- Title Overlay -->
                    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-center text-white w-full px-4">
                        <h1 class="text-3xl font-extrabold tracking-tight">#{{ $challenge->hashtag }}</h1>
                        <p class="mt-2 text-zinc-200 font-medium">
                            {{ __('Checkout the guidelines for what to create!') }}</p>

                        <!-- Stats Pill -->
                        <div class="mt-6 flex items-center justify-center gap-4">
                            <div
                                class="px-6 py-2 rounded-2xl bg-white/20 backdrop-blur-lg border border-white/30 text-center min-w-[120px]">
                                <p class="text-[10px] uppercase font-bold tracking-widest text-zinc-300">
                                    {{ __('Prizes') }}</p>
                                <p class="text-lg font-black">{{ $challenge->prizes }}</p>
                            </div>
                            <div
                                class="px-6 py-2 rounded-2xl bg-white/20 backdrop-blur-lg border border-white/30 text-center min-w-[120px]">
                                @if ($challenge->hasEnded())
                                    <p class="text-[10px] uppercase font-bold tracking-widest text-red-400">
                                        {{ __('Status') }}</p>
                                    <p class="text-lg font-black">{{ __('Ended') }}</p>
                                @else
                                    <p class="text-[10px] uppercase font-bold tracking-widest text-zinc-300">
                                        {{ __('Time Left') }}</p>
                                    <p class="text-lg font-black">{{ $timeLeft }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer / Action Area -->
                <div class="p-8 text-center bg-white dark:bg-zinc-900">
                    <div class="flex items-center justify-center -space-x-3 mb-4">
                        @foreach ($challenge->participants->take(5) as $p)
                            <div
                                class="size-10 rounded-full border-4 border-white dark:border-zinc-900 bg-zinc-100 overflow-hidden shadow-sm">
                                @if ($p->profile_picture_url)
                                    <img src="{{ $p->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <div class="size-full flex items-center justify-center text-[10px] font-bold">
                                        {{ $p->initials() }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($challenge->participants->count() > 0)
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium mb-6">
                            {{ __('Join') }} <span
                                class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($challenge->participants->count()) }}</span>
                            {{ __('challenge participants') }}
                        </p>
                    @endif

                    @if ($pendingInvitation)
                        <div
                            class="bg-purple-50 dark:bg-purple-900/20 rounded-3xl p-6 border border-purple-200 dark:border-purple-800 mb-6">
                            <p class="text-sm font-bold text-purple-700 dark:text-purple-300 mb-4">
                                {{ __('You have been invited to judge this challenge!') }}</p>
                            <div class="flex items-center justify-center gap-4">
                                <flux:button wire:click="acceptInvitation" variant="primary" size="sm">
                                    {{ __('Accept Invitation') }}</flux:button>
                                <flux:button wire:click="declineInvitation" variant="ghost" size="sm">
                                    {{ __('Decline') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    @if (!$isParticipant && !$isJudge && !$pendingInvitation && !$challenge->hasEnded())
                        <button wire:click="join"
                            class="px-8 py-3 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 font-black hover:scale-105 active:scale-95 transition-all shadow-xl">
                            {{ __('Join challenge') }}
                        </button>
                    @elseif($isParticipant && !$challenge->hasEnded())
                        <button
                            onclick="Livewire.dispatch('open-challenge-post-modal', { challengeId: {{ $challenge->id }}, hashtag: '{{ $challenge->hashtag }}' })"
                            class="px-8 py-3 rounded-full bg-[var(--color-brand-purple)] text-white font-black hover:scale-105 active:scale-95 transition-all shadow-xl">
                            {{ __('Post Submission') }}
                        </button>
                    @elseif($isJudge && !$challenge->hasEnded())
                        <div
                            class="px-8 py-3 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-bold inline-block">
                            {{ __('You are a Judge') }}
                        </div>
                    @endif

                    @if ($challenge->creator_id === auth()->id())
                        <a href="{{ route('challenges.manage', $challenge->custom_link) }}"
                            class="mt-4 block text-xs font-bold text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                            {{ __('Manage Challenge') }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Tabs & Feed -->
            <div class="space-y-6">
                <!-- Navigation -->
                <div class="flex items-center justify-center border-b border-zinc-200 dark:border-zinc-800">
                    <button wire:click="$set('activeTab', 'trending')"
                        class="px-8 py-4 text-sm font-bold transition-all relative {{ $activeTab === 'trending' ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 hover:text-zinc-600' }}">
                        {{ __('Trending') }}
                        @if ($activeTab === 'trending')
                            <div class="absolute bottom-0 left-0 w-full h-1 bg-zinc-900 dark:bg-white"></div>
                        @endif
                    </button>
                    <button wire:click="$set('activeTab', 'recent')"
                        class="px-8 py-4 text-sm font-bold transition-all relative {{ $activeTab === 'recent' ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 hover:text-zinc-600' }}">
                        {{ __('Recent') }}
                        @if ($activeTab === 'recent')
                            <div class="absolute bottom-0 left-0 w-full h-1 bg-zinc-900 dark:bg-white"></div>
                        @endif
                    </button>
                    <button wire:click="$set('activeTab', 'guidelines')"
                        class="px-8 py-4 text-sm font-bold transition-all relative {{ $activeTab === 'guidelines' ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 hover:text-zinc-600' }}">
                        {{ __('Guidelines') }}
                        @if ($activeTab === 'guidelines')
                            <div class="absolute bottom-0 left-0 w-full h-1 bg-zinc-900 dark:bg-white"></div>
                        @endif
                    </button>
                </div>

                <!-- Feed Content -->
                <div class="min-h-[400px]">
                    @if ($activeTab === 'guidelines')
                        <div
                            class="bg-white dark:bg-zinc-900 rounded-3xl p-8 border border-zinc-200 dark:border-zinc-800 shadow-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                            <h2 class="text-xl font-bold mb-4 text-zinc-900 dark:text-white">
                                {{ __('Challenge Guidelines') }}</h2>
                            <div class="prose dark:prose-invert max-w-none">
                                {!! nl2br(e($challenge->guidelines)) !!}
                            </div>
                        </div>
                    @else
                        <div class="space-y-6">
                            <!-- Pinned Post -->
                            @if ($pinnedPost)
                                <div class="relative">
                                    <div
                                        class="absolute -top-3 -left-3 z-10 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-3">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                                        </svg>
                                        {{ __('Pinned Post') }}
                                    </div>
                                    <div class="ring-4 ring-zinc-900/5 dark:ring-white/5 rounded-3xl overflow-hidden">
                                        <livewire:challenge.post-card :post="$pinnedPost" :isJudge="$isJudge"
                                            :key="'pinned-' . $pinnedPost->id" />
                                    </div>
                                </div>
                            @endif

                            <!-- Posts List -->
                            @forelse($posts as $post)
                                @if ($pinnedPost && $post->id === $pinnedPost->id)
                                    @continue
                                @endif
                                <livewire:challenge.post-card :post="$post" :isJudge="$isJudge" :key="'post-' . $post->id" />
                            @empty
                                <div
                                    class="bg-white dark:bg-zinc-900 rounded-3xl p-12 text-center border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                    <div
                                        class="size-20 rounded-full bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-6">
                                        <flux:icon name="camera" class="size-8 text-zinc-300" />
                                    </div>
                                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">
                                        {{ __('No submissions yet') }}
                                    </h3>
                                    <p class="text-zinc-500 mt-2">
                                        {{ __('Be the first to participate in this challenge!') }}</p>
                                </div>
                            @endforelse

                            <div class="mt-8">
                                {{ $posts->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar (Right) -->
        <div class="w-full lg:w-80 space-y-8">
            <!-- Judges Widget -->
            <div
                class="bg-white dark:bg-zinc-900 rounded-3xl p-6 border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <h3 class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-6">{{ __('The Judges') }}
                </h3>
                <div class="space-y-6">
                    @forelse($challenge->judges as $judge)
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-full overflow-hidden bg-zinc-100 shadow-sm">
                                @if ($judge->profile_picture_url)
                                    <img src="{{ $judge->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <div class="size-full flex items-center justify-center text-xs font-bold">
                                        {{ $judge->initials() }}</div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ $judge->name }}
                                </p>
                                <p class="text-xs text-zinc-500 truncate">{{ $judge->work ?? __('Artisan') }}</p>
                            </div>
                            @if ($judge->pivot->status === 'accepted')
                                <div class="size-2 rounded-full bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]">
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-zinc-500 italic">{{ __('No judges invited yet') }}</p>
                    @endforelse
                </div>
            </div>

            <!-- Sponsor Widget (Placeholder matching design) -->
            <div
                class="bg-white dark:bg-zinc-900 rounded-3xl p-6 border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <h3 class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-6">
                    {{ __('Sponsored By') }}
                </h3>
                <div
                    class="grid grid-cols-2 gap-4 items-center justify-items-center opacity-60 grayscale hover:grayscale-0 transition-all">
                    <!-- Just placeholders as per image style -->
                    <div class="font-black italic text-zinc-900 dark:text-white tracking-widest">{{ __('REPLIT') }}
                    </div>
                    <div class="font-black text-zinc-900 dark:text-white tracking-tighter">{{ __('SUNO') }}</div>
                    <div class="font-black text-zinc-900 dark:text-white">{{ __('TOLSTOY') }}</div>
                    <div class="font-black text-zinc-900 dark:text-white tracking-widest">{{ __('KNACK') }}</div>
                </div>
            </div>

            @if ($challenge->winner)
                <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-3xl p-6 text-white shadow-xl">
                    <h3 class="text-[10px] uppercase font-black tracking-widest text-white/80 mb-4">
                        {{ __('WINNER REVEALED') }}</h3>
                    <div class="flex items-center gap-4">
                        <div class="size-14 rounded-full border-4 border-white/30 overflow-hidden shadow-2xl">
                            @if ($challenge->winner->profile_picture_url)
                                <img src="{{ $challenge->winner->profile_picture_url }}"
                                    class="size-full object-cover">
                            @endif
                        </div>
                        <div>
                            <p class="font-black text-lg">{{ $challenge->winner->name }}</p>
                            <p class="text-xs font-medium text-white/90">{{ __('Challenge Champion') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Submission Modal -->
    <livewire:challenge.post-submission-modal />
</div>
