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

    public function startConversation()
    {
        $userId = $this->user->id;
        $authId = auth()->id();

        // Find conversation with both users
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
                        @if($user->profile_picture_url)
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
                    @if(auth()->id() !== $user->id)
                        @if($user->isGuest())
                            <flux:button wire:click="startConversation" variant="primary" icon="chat-bubble-left-right"
                                class="rounded-full px-6">
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
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 relative">
                @if($post->repost_of_id)
                    <div class="absolute left-[10px] top-[70px] bottom-[50px] w-0.5 bg-[#6a11cb] opacity-50 z-0"></div>
                @endif
                <div @click="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                    class="cursor-pointer relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-2">
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

                        <flux:dropdown>
                            <button class="text-zinc-400 hover:text-zinc-600">
                                <flux:icon name="ellipsis-horizontal" class="size-5" />
                            </button>
                            <flux:menu>
                                @if($post->user_id === auth()->id())
                                    <flux:menu.item wire:click="deletePost({{ $post->id }})" wire:confirm="{{ __('Are you sure?') }}" icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>
                                @else
                                    <flux:menu.item icon="flag">{{ __('Report') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    @if($post->content)
                        @if(Str::length($post->content) > 300)
                            <div x-data="{ expanded: false }">
                                <p x-show="!expanded"
                                    class="text-zinc-700 dark:text-zinc-300 mb-2 text-sm leading-relaxed whitespace-pre-line">
                                    {!! $post->formatted_content_summary !!}</p>
                                <p x-show="expanded"
                                    class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                    {!! $post->formatted_content !!}</p>
                                <button x-show="!expanded" @click.stop="expanded = true"
                                    class="text-sm font-medium text-[var(--color-brand-purple)] hover:underline mb-4">{{ __('See More') }}</button>
                            </div>
                        @else
                            <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                                {!! $post->formatted_content !!}</p>
                        @endif
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
                                {!! $post->repostOf->formatted_content !!}</p>
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