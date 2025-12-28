<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Conversation;
use App\Mail\ServiceInquiryMail;
use App\Notifications\ServiceInquiry;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public User $user;
    public $posts = [];
    public bool $pingSent = false;

    public function rendering($view)
    {
        $title = $this->user->name . ' (@' . ($this->user->username ?? $this->user->id) . ') | ' . ($this->user->work ?? 'Artisan') . ' on Allsers';
        $description = $this->user->bio ?? 'Explore the portfolio and services of ' . $this->user->name . ' on Allsers. Professional ' . ($this->user->work ?? 'Artisan') . ' available for hire.';
        $image = $this->user->profile_picture_url ?? asset('assets/allsers.png');

        $view->title($title);

        return $view->with([
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaImage' => $image,
            'metaUrl' => route('artisan.profile', $this->user->username ?? $this->user->id),
        ]);
    }

    public function mount($user)
    {
        // If $user is a string/ID, find it manually
        if (!($user instanceof User)) {
            $this->user = User::where('username', $user)
                ->orWhere('id', $user)
                ->firstOrFail();
        } else {
            $this->user = $user;
        }

        $this->loadPosts();
    }

    public function loadPosts()
    {
        $this->posts = Post::where('user_id', $this->user->id)
            ->with([
                'user',
                'repostOf.user',
                'likes' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
                'bookmarks' => function ($query) {
                    $query->where('user_id', auth()->id());
                }
            ])
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->get();
    }

    public function startConversation()
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'));
        }

        $userId = $this->user->id;
        $authId = auth()->id();

        $conversation = auth()->user()->conversations()
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create();
            $conversation->users()->attach([$authId, $userId]);
        }

        return $this->redirect(route('chat', $conversation->id), navigate: true);
    }

    public function pingUser()
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'));
        }

        if ($this->pingSent)
            return;

        $sender = auth()->user();
        Mail::to($this->user->email)->send(new ServiceInquiryMail($sender, $this->user));
        $this->user->notify(new ServiceInquiry($sender));

        $this->pingSent = true;

        $this->dispatch('toast', type: 'success', title: 'Message Sent!', message: 'Your inquiry has been sent to ' . $this->user->name);
    }
}; ?>

@push('head')
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('artisan.profile', $user->username ?? $user->id) }}">
    <meta property="og:title"
        content="{{ $user->name }} (@{{ $user->username ?? $user->id }}) | {{ $user->work ?? 'Artisan' }} on Allsers">
    <meta property="og:description"
        content="{{ $user->bio ?? 'Explore the portfolio and services of ' . $user->name . ' on Allsers.' }}">
    <meta property="og:image" content="{{ $user->profile_picture_url ?? asset('assets/allsers.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ route('artisan.profile', $user->username ?? $user->id) }}">
    <meta property="twitter:title"
        content="{{ $user->name }} (@{{ $user->username ?? $user->id }}) | {{ $user->work ?? 'Artisan' }} on Allsers">
    <meta property="twitter:description"
        content="{{ $user->bio ?? 'Explore the portfolio and services of ' . $user->name . ' on Allsers.' }}">
    <meta property="twitter:image" content="{{ $user->profile_picture_url ?? asset('assets/allsers.png') }}">
@endpush

<div class="max-w-4xl mx-auto px-4 py-8" x-data="{ 
    copy(text) {
        navigator.clipboard.writeText(text).then(() => {
            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Profile link copied to clipboard.' });
        });
    }
}">
    <!-- SEO Optimization (In-body fallback) -->
    <div class="hidden">
        <h1>{{ $user->name }} - {{ $user->work ?? 'Artisan' }} Profile</h1>
        <p>{{ $user->bio }}</p>
    </div>

    <!-- Profile Header -->
    <div
        class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 overflow-hidden shadow-sm mb-8">
        <div class="h-32 bg-gradient-to-r from-[var(--color-brand-purple)] to-[#6a11cb]"></div>
        <div class="px-8 pb-8">
            <div class="relative flex justify-between items-end -mt-12 mb-6">
                <div class="size-24 rounded-2xl bg-white dark:bg-zinc-900 p-1 shadow-lg">
                    <div
                        class="w-full h-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-700 dark:text-purple-300 font-bold text-3xl overflow-hidden">
                        @if($user->profile_picture_url)
                            <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </div>
                </div>

                <div class="flex gap-3">
                    @auth
                        @if(auth()->id() !== $user->id)
                            @if($user->isGuest())
                                <flux:button wire:click="startConversation" variant="primary" icon="chat-bubble-left-right"
                                    class="rounded-full px-6">
                                    {{ __('Chat') }}
                                </flux:button>
                            @else
                                <button wire:click="pingUser"
                                    class="px-6 py-2.5 rounded-full font-bold text-sm transition-all flex items-center gap-2 @if($pingSent) bg-green-100 text-green-700 @else bg-[var(--color-brand-purple)] text-white hover:opacity-90 @endif">
                                    @if($pingSent)
                                        <flux:icon name="check" class="size-4" /> {{ __('Ping Sent!') }}
                                    @else
                                        <flux:icon name="paper-airplane" class="size-4" /> {{ __('Ping to Contact') }}
                                    @endif
                                </button>
                            @endif
                        @endif
                    @else
                        <flux:button :href="route('login')" variant="primary" icon="paper-airplane"
                            class="rounded-full px-6">
                            {{ __('Contact Artisan') }}
                        </flux:button>
                    @endauth

                    <flux:button @click="copy('{{ route('artisan.profile', $user->username ?? $user->id) }}')"
                        variant="outline" icon="share" class="rounded-full shadow-none">
                        {{ __('Share') }}
                    </flux:button>
                </div>
            </div>

            <div class="space-y-1">
                <h1 class="text-2xl font-black text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    {{ $user->name }}
                    @if($user->isArtisan())
                        <flux:icon name="check-badge" class="size-6 text-blue-500 fill-current" />
                    @endif
                </h1>
                <p>{{ '@' . $user->username }}</p>
                <p class="text-[var(--color-brand-purple)] font-bold uppercase tracking-wide text-xs">
                    {{ $user->work ?? __('Artisan') }}
                </p>
            </div>

            <div class="mt-4 flex flex-wrap gap-6 text-sm">
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="map-pin" class="size-4" />
                    {{ $user->address ?? __('Global') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="briefcase" class="size-4" />
                    {{ $user->experience_year ?? '0' }}+ {{ __('Years Exp.') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="calendar-days" class="size-4" />
                    {{ __('Joined') }} {{ $user->created_at->format('M Y') }}
                </div>
            </div>

            @if($user->bio)
                <p class="mt-6 text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed max-w-2xl">
                    {{ $user->bio }}
                </p>
            @endif
        </div>
    </div>

    <!-- Portfolio Section -->
    <div class="space-y-6 max-w-2xl">
        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 px-2">{{ __('Portfolio & Showcase') }}</h2>

        @forelse($posts as $post)
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 relative group overflow-hidden">
                <div @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                    class="cursor-pointer relative z-10">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="size-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-xs overflow-hidden">
                            @if($post->user->profile_picture_url)
                                <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                            @else
                                {{ $post->user->initials() }}
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center gap-2 text-sm">
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ $post->user->name }}</h3>
                                @if($post->repost_of_id)
                                    <div
                                        class="flex items-center gap-1 text-[10px] text-zinc-500 font-medium bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded-full">
                                        <flux:icon name="arrow-path-rounded-square" class="size-3" />
                                        <span>reposted work</span>
                                    </div>
                                @endif
                            </div>
                            <p class="text-[10px] text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    @if($post->content)
                        @if(Str::length($post->content) > 300)
                            <div x-data="{ expanded: false }">
                                <p x-show="!expanded"
                                    class="text-zinc-700 dark:text-zinc-300 mb-2 text-sm leading-relaxed whitespace-pre-line">
                                    {!! $post->formatted_content_summary !!}
                                </p>
                                <p x-show="expanded"
                                    class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                    {!! $post->formatted_content !!}
                                </p>
                                <button x-show="!expanded" @click.stop="expanded = true"
                                    class="text-sm font-medium text-[var(--color-brand-purple)] hover:underline mb-4">{{ __('See More') }}</button>
                            </div>
                        @else
                            <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                {!! $post->formatted_content !!}
                            </p>
                        @endif
                    @endif

                    @if($post->images)
                        @php $imageArray = array_filter(explode(',', $post->images)); @endphp
                        @if(count($imageArray) > 0)
                            <div
                                class="mb-4 rounded-xl overflow-hidden @if(count($imageArray) === 1) h-80 @else grid grid-cols-2 gap-2 h-64 @endif">
                                @foreach($imageArray as $image)
                                    <img src="{{ route('images.show', ['path' => trim($image)]) }}"
                                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                                @endforeach
                            </div>
                        @endif
                    @endif

                    @if($post->video)
                        <div class="mb-4 rounded-xl overflow-hidden h-80 border border-zinc-100 dark:border-zinc-800">
                            <video src="{{ route('images.show', ['path' => $post->video]) }}" class="w-full h-full object-cover"
                                controls></video>
                        </div>
                    @endif

                    <!-- Original Post Preview (Repost) -->
                    @if($post->repostOf)
                        <div @click.stop="$dispatch('open-post-detail', { postId: {{ $post->repost_of_id }} })"
                            class="mb-4 p-4 border border-zinc-100 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer ring-1 ring-transparent hover:ring-[var(--color-brand-purple)]/30">
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="size-6 rounded-full bg-purple-50 flex items-center justify-center text-[10px] overflow-hidden">
                                    @if($post->repostOf->user->profile_picture_url)
                                        <img src="{{ $post->repostOf->user->profile_picture_url }}" class="size-full object-cover">
                                    @else
                                        {{ $post->repostOf->user->initials() }}
                                    @endif
                                </div>
                                <span
                                    class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $post->repostOf->user->name }}</span>
                                <span class="text-[10px] text-zinc-400">•
                                    {{ $post->repostOf->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mb-2 whitespace-pre-wrap">
                                {!! $post->repostOf->formatted_content !!}
                            </p>
                            @if($post->repostOf->images)
                                @php $originImages = array_filter(explode(',', $post->repostOf->images)); @endphp
                                @if(count($originImages) > 0)
                                    <div class="h-32 rounded-lg overflow-hidden border border-zinc-200/50">
                                        <img src="{{ route('images.show', ['path' => trim($originImages[0])]) }}"
                                            class="size-full object-cover">
                                    </div>
                                @endif
                            @elseif($post->repostOf->video)
                                <div
                                    class="h-32 rounded-lg overflow-hidden bg-black flex items-center justify-center border border-zinc-200/50">
                                    <video src="{{ route('images.show', ['path' => $post->repostOf->video]) }}"
                                        class="w-full h-full object-cover" controls></video>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-6 pt-3 border-t border-zinc-50 dark:border-zinc-800/50 mt-2">
                    <span class="flex items-center gap-1.5 text-zinc-500">
                        <flux:icon name="heart" class="size-5" />
                        <span class="text-sm font-medium">{{ $post->likes_count }}</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-zinc-500">
                        <flux:icon name="chat-bubble-left" class="size-5" />
                        <span class="text-sm font-medium">{{ $post->all_comments_count }}</span>
                    </span>
                </div>
            </div>
        @empty
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-12 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <p class="text-zinc-500 text-sm">{{ __('No portfolio items yet.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Modals -->
    <livewire:dashboard.post-detail />
</div>