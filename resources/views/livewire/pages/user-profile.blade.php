<?php

use App\Models\User;
use App\Models\Post;
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

    public function pingUser()
    {
        if ($this->pingSent)
            return;

        $sender = auth()->user();

        // Send Email
        Mail::to($this->user->email)->send(new ServiceInquiryMail($sender, $this->user));

        // Send Notification
        $this->user->notify(new ServiceInquiry($sender));

        $this->pingSent = true;

        $this->dispatch('ping-sent');
        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Message Sent!',
            message: 'Your inquiry has been sent to ' . $this->user->name . '. Check your email for updates.'
        );
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
                        class="w-full h-full rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-700 dark:text-purple-300 font-bold text-3xl">
                        {{ $user->initials() }}
                    </div>
                </div>

                <div class="flex gap-3">
                    @if(auth()->id() !== $user->id)
                        @if($user->isGuest())
                            <flux:button variant="primary" icon="chat-bubble-left-right" class="rounded-full px-6">
                                {{ __('Chat') }}
                            </flux:button>
                        @else
                            <button wire:click="pingUser"
                                class="px-6 py-2.5 rounded-full font-bold text-sm transition-all flex items-center gap-2 @if($pingSent) bg-green-100 text-green-700 cursor-default @else bg-[var(--color-brand-purple)] text-white hover:bg-[var(--color-brand-purple)]/90 @endif">
                                @if($pingSent)
                                    <flux:icon name="check" class="size-4" />
                                    {{ __('Ping Sent!') }}
                                @else
                                    <flux:icon name="paper-airplane" class="size-4" />
                                    {{ __('Ping to Contact') }}
                                @endif
                            </button>
                        @endif
                    @endif
                    <flux:button variant="outline" icon="share" class="rounded-full shadow-none">{{ __('Share') }}
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
                    {{ __('Joined') }} {{ $user->created_at->format('M Y') }}
                </div>
                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ count($posts) }}</span>
                    {{ __('Posts') }}
                </div>
            </div>

            @if($user->bio)
                <p class="mt-6 text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed max-w-2xl">
                    {{ $user->bio }}
                </p>
            @endif
        </div>
    </div>

    <!-- User Posts -->
    <div class="space-y-6 max-w-2xl">
        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-4 px-2">{{ __('Portfolio & Posts') }}</h2>

        @forelse($posts as $post)
            <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })" class="cursor-pointer">
                    @if($post->content)
                        <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed">
                            {{ $post->content }}
                        </p>
                    @endif

                    @if($post->images)
                        @php $imageArray = array_filter(explode(',', $post->images)); @endphp
                        @if(count($imageArray) > 0)
                            <div
                                class="mb-4 rounded-xl overflow-hidden @if(count($imageArray) === 1) h-80 @else grid grid-cols-2 gap-2 h-64 @endif relative">
                                @foreach($imageArray as $image)
                                    <img src="{{ route('images.show', ['path' => trim($image)]) }}" class="w-full h-full object-cover">
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-zinc-50 dark:border-zinc-800/50 mt-2">
                    <div class="flex items-center gap-6">
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
            </div>
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