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
        $view->title($this->user->name . ' - ' . ($this->user->work ?? 'Artisan'));
    }

    public function mount(User $user)
    {
        $this->user = $user;
        $this->loadPosts();
    }

    public function loadPosts()
    {
        $this->posts = Post::where('user_id', $this->user->id)
            ->with([
                'user',
                'repostOf.user',
                'comments' => function ($q) {
                    $q->latest()->limit(1)->with('user');
                },
                'likes' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
                'bookmarks' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ])
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->get();
    }

    public function pingUser()
    {
        if ($this->pingSent) {
            return;
        }

        $sender = auth()->user();

        // Send Email
        Mail::to($this->user->email)->send(new ServiceInquiryMail($sender, $this->user));

        // Send Notification
        $this->user->notify(new ServiceInquiry($sender));

        $this->pingSent = true;

        $this->dispatch('ping-sent');
        $this->dispatch('toast', type: 'success', title: 'Message Sent!', message: 'Your inquiry has been sent to ' . $this->user->name . '. Check your email for updates.');
    }

    public function startConversation()
    {
        $userId = $this->user->id;
        $authId = auth()->id();

        // Find conversation with both users
        $conversation = auth()
            ->user()
            ->conversations()
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

    public function deletePost($postId)
    {
        $post = Post::find($postId);
        if ($post && $post->user_id === auth()->id()) {
            $post->delete();
            $this->loadPosts();
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post has been deleted.');
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Unauthorized action.');
        }
    }
}; ?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Profile Header -->
    <div
        class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 overflow-hidden shadow-sm mb-8">
        <div class="h-32 bg-gradient-to-r from-purple-500 to-blue-500"></div>
        <div class="px-8 pb-8">
            <div class="relative flex justify-between items-end -mt-12 mb-6">
                <div class="size-24 rounded-2xl bg-white dark:bg-zinc-900 p-1 shadow-lg">
                    <div
                        class="w-full h-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-700 dark:text-purple-300 font-bold text-3xl overflow-hidden">
                        @if ($user->profile_picture_url)
                            <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </div>
                </div>

                <div class="flex gap-3" x-data="{
                    copy(text) {
                        navigator.clipboard.writeText(text).then(() => {
                            $dispatch('toast', { type: 'success', title: 'Link Copied!', message: 'Profile link copied to clipboard.' });
                        });
                    }
                }">
                    @if (auth()->id() !== $user->id)
                        @if ($user->isGuest())
                            <flux:button wire:click="startConversation" variant="primary" icon="chat-bubble-left-right"
                                class="rounded-full px-6">
                                {{ __('Chat') }}
                            </flux:button>
                        @else
                            <button wire:click="pingUser"
                                class="px-6 py-2.5 rounded-full font-bold text-sm transition-all flex items-center gap-2 @if ($pingSent) bg-green-100 text-green-700 cursor-default @else bg-[var(--color-brand-purple)] text-white hover:bg-[var(--color-brand-purple)]/90 @endif">
                                @if ($pingSent)
                                    <flux:icon name="check" class="size-4" />
                                    {{ __('Ping Sent!') }}
                                @else
                                    <flux:icon name="chat-bubble-left-right" class="size-4" />
                                    {{ __('Chat') }}
                                @endif
                            </button>
                        @endif
                    @endif
                    {{-- <flux:button @click="copy('{{ route('artisan.profile', $user->username ?? $user->id) }}')"
                        variant="outline" icon="share" class="rounded-full shadow-none">
                        {{ __('Share') }}
                    </flux:button> --}}
                </div>
            </div>

            <div class="space-y-1">
                <h1 class="text-2xl font-black text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    {{ $user->name }}
                    @if ($user->isArtisan())
                        <flux:icon name="check-badge" class="size-6 text-blue-500 fill-current" />
                    @endif
                </h1>
                <p class="text-[var(--color-brand-purple)] font-bold uppercase tracking-wide text-xs">
                    {{ $user->work ?? __('Guest') }}
                </p>
            </div>

            <div class="mt-4 flex gap-6 text-sm">
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="map-pin" class="size-4" />
                    {{ $user->address ?? __('Global') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="calendar-days" class="size-4" />
                    {{ __('Joined') }} {{ \Carbon\Carbon::parse($user->created_at)->format('M Y') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ count($posts) }}</span>
                    {{ __('Posts') }}
                </div>
            </div>

            @if ($user->bio)
                <p class="mt-6 text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed max-w-2xl">
                    {{ $user->bio }}
                </p>
            @endif

            <!-- Awarded Badges Section -->
            @if ($user->badges->count() > 0)
                <div class="mt-8 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-4">
                        {{ __('Special Badges & Awards') }}</h3>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($user->badges as $badge)
                            <div class="group relative flex flex-col items-center gap-2">
                                <div
                                    class="size-16 rounded-2xl bg-gradient-to-br from-yellow-400/20 to-orange-500/20 dark:from-yellow-400/10 dark:to-orange-500/10 p-2 border border-yellow-400/30 flex items-center justify-center transition-all hover:scale-110 hover:shadow-lg shadow-yellow-500/10">
                                    @if ($badge->icon_url)
                                        <img src="{{ asset('storage/' . $badge->icon_url) }}"
                                            class="size-full object-contain">
                                    @else
                                        <flux:icon name="trophy" class="size-8 text-yellow-500" />
                                    @endif
                                </div>
                                <span
                                    class="text-[10px] font-black text-zinc-900 dark:text-white text-center w-20 leading-tight uppercase tracking-tighter">{{ $badge->name }}</span>

                                <!-- Tooltip on hover -->
                                <div
                                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-zinc-900 text-white text-[10px] rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-center z-20">
                                    <p class="font-bold">{{ $badge->name }}</p>
                                    <p class="text-zinc-400 mt-1">{{ $badge->description }}</p>
                                    <p class="text-[8px] mt-2 text-yellow-400">{{ __('Awarded') }}:
                                        {{ \Carbon\Carbon::parse($badge->pivot->awarded_at)->format('M Y') }}</p>
                                    <div
                                        class="absolute top-full left-1/2 -translate-x-1/2 border-8 border-transparent border-t-zinc-900">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- User Posts -->
    <div class="space-y-6 max-w-2xl">
        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-4 px-2">{{ __('Portfolio & Posts') }}</h2>

        @forelse($posts as $post)
            <livewire:dashboard.post-item :post="$post" :wire:key="'user-post-'.$post->id" />
        @empty
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-12 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <p class="text-zinc-500 text-sm">{{ __('This user hasn\'t posted anything yet.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Integrate Post Detail Drawer -->
    <livewire:dashboard.post-detail />
</div>
