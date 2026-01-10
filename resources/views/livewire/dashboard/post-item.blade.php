<?php

use Livewire\Volt\Component;
use App\Models\Post;

new class extends Component {
    public Post $post;

    public function toggleLike()
    {
        $user = auth()->user();

        $existingLike = $this->post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
        } else {
            $this->post->likes()->create(['user_id' => $user->id]);

            if ($this->post->user_id !== $user->id) {
                $this->post->user->notify(new \App\Notifications\PostLiked($this->post, $user));
            }
        }

        // Refresh the post model to update counts and relationships
        $this->post->refresh()->loadCount(['likes', 'allComments']);
    }

    public function toggleBookmark()
    {
        $user = auth()->user();
        $existingBookmark = $this->post->bookmarks()->where('user_id', $user->id)->first();

        if ($existingBookmark) {
            $existingBookmark->delete();
        } else {
            $this->post->bookmarks()->create(['user_id' => $user->id]);
        }

        // Refresh the post model
        $this->post->refresh();
    }

    public function deletePost()
    {
        if ($this->post->user_id === auth()->id()) {
            $this->post->delete();
            $this->dispatch('post-deleted'); // Notify parent to refresh list
            $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Post has been deleted.');
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'You cannot delete this post.');
        }
    }
}; ?>

<div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 relative">
    @if ($post->repost_of_id)
        <div class="absolute left-[10px] top-[75px] bottom-[60px] w-0.5 bg-[#6a11cb] opacity-50 z-0"></div>
    @endif
    <div wire:click="$dispatch('open-post-detail', { postId: {{ $post->id }} })" class="cursor-pointer relative z-10">
        <div class="flex justify-between items-start mb-4">
            <div class="flex items-center gap-3">
                <a @if (auth()->id() !== $post->user_id) href="{{ route('artisan.profile', $post->user) }}"
                wire:navigate @endif
                    @click.stop
                    class="size-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-sm overflow-hidden @if (auth()->id() !== $post->user_id) cursor-pointer hover:ring-2 hover:ring-[var(--color-brand-purple)] transition-all @endif">
                    @if ($post->user->profile_picture_url)
                        <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                    @else
                        {{ $post->user->initials() }}
                    @endif
                </a>
                <div>
                    <div class="flex items-center gap-2 text-sm">
                        <h3 @if (auth()->id() !== $post->user_id) wire:click.stop="window.location.href='{{ route('artisan.profile', $post->user) }}'" @endif
                            class="font-bold text-zinc-900 dark:text-zinc-100 @if (auth()->id() !== $post->user_id) hover:text-[var(--color-brand-purple)] cursor-pointer @endif">
                            {{ $post->user->name }}
                        </h3>
                        @if ($post->repost_of_id)
                            <div
                                class="flex items-center gap-1 text-[10px] text-zinc-500 font-medium bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded-full">
                                <flux:icon name="arrow-path-rounded-square" class="size-3" />
                                <span>reposted work</span>
                            </div>
                        @else
                            <span
                                class="bg-purple-100 text-[var(--color-brand-purple)] text-[8px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">
                                {{ $post->user->work ?? __('Artisan') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                </div>
            </div>
            <flux:dropdown>
                <button class="text-zinc-400 hover:text-zinc-600" wire:click.stop>
                    <flux:icon name="ellipsis-horizontal" class="size-5" />
                </button>

                <flux:menu>
                    @if ($post->user_id === auth()->id())
                        <flux:menu.item wire:click="deletePost"
                            wire:confirm="{{ __('Are you sure you want to delete this post?') }}" icon="trash"
                            variant="danger">{{ __('Delete') }}</flux:menu.item>
                    @else
                        <flux:menu.item wire:click="$parent.openReportModal({{ $post->id }})" icon="flag">
                            {{ __('Report') }}
                        </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        </div>

        {{-- Price Range Badge - Prominent Display --}}
        @if ($post->price_min || $post->price_max)
            <div class="mb-4 -mt-2">
                <div
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-4 py-2 rounded-full shadow-lg shadow-emerald-500/30">
                    <flux:icon name="currency-dollar" class="size-5" />
                    <div class="flex items-center gap-1.5 font-bold text-sm">
                        @if ($post->price_min && $post->price_max)
                            <span>₦{{ number_format($post->price_min, 0) }}</span>
                            <span class="opacity-75">-</span>
                            <span>₦{{ number_format($post->price_max, 0) }}</span>
                        @elseif ($post->price_min)
                            <span>₦{{ number_format($post->price_min, 0) }}</span>
                        @else
                            <span>{{ __('Up to') }} ₦{{ number_format($post->price_max, 0) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($post->content)
            @if (Str::length($post->content) > 300)
                <div x-data="{ expanded: false }">
                    <p x-show="!expanded"
                        class="text-zinc-700 dark:text-zinc-300 mb-2 text-sm leading-relaxed whitespace-pre-line">
                        {!! $post->formatted_content_summary !!}
                    </p>
                    <p x-show="expanded"
                        class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                        {!! $post->formatted_content !!}
                    </p>
                    <button x-show="!expanded"
                        @click.stop="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                        class="text-sm font-medium text-[var(--color-brand-purple)] hover:underline mb-4">{{ __('See More') }}</button>
                </div>
            @else
                <p class="text-zinc-700 dark:text-zinc-300 mb-4 text-sm leading-relaxed whitespace-pre-line">
                    {!! $post->formatted_content !!}
                </p>
            @endif
        @endif

        <!-- Images -->
        @if ($post->images)
            @php
                $imageArray = array_filter(explode(',', $post->images));
            @endphp
            @if (count($imageArray) > 0)
                @if (count($imageArray) === 1)
                    {{-- Single image --}}
                    <div class="mb-4 rounded-xl overflow-hidden h-80 relative">
                        <img src="{{ route('images.show', ['path' => trim($imageArray[0])]) }}" alt="Post image"
                            class="absolute inset-0 size-full object-cover hover:scale-105 transition-transform duration-500">
                    </div>
                @else
                    {{-- Multiple images --}}
                    <div
                        class="mb-4 rounded-xl overflow-hidden grid gap-2 @if (count($imageArray) === 2) grid-cols-2 h-64 @elseif(count($imageArray) === 3) grid-cols-2 grid-rows-2 h-[400px] @else grid-cols-2 grid-rows-2 h-[400px] @endif">
                        @foreach ($imageArray as $index => $image)
                            <div class="relative @if (count($imageArray) === 3 && $index === 0) row-span-2 @endif">
                                <img src="{{ route('images.show', ['path' => trim($image)]) }}" alt="Post image"
                                    class="absolute inset-0 size-full object-cover hover:scale-105 transition-transform duration-500">
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        @endif

        <!-- Video -->
        @if ($post->video)
            <div class="mb-4 rounded-xl overflow-hidden h-80">
                <video src="{{ route('images.show', ['path' => $post->video]) }}" class="w-full h-full object-cover"
                    controls></video>
            </div>
        @endif

        <!-- Original Post Preview (Repost) -->
        @if ($post->repostOf)
            <div wire:click.stop="$dispatch('open-post-detail', { postId: {{ $post->repost_of_id }} })"
                class="mb-4 p-4 border border-zinc-100 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer ring-1 ring-transparent hover:ring-[var(--color-brand-purple)]/30">
                <div class="flex items-center gap-2 mb-2">
                    <div
                        class="size-6 rounded-full bg-purple-50 flex items-center justify-center text-[10px] overflow-hidden">
                        @if ($post->repostOf->user->profile_picture_url)
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
                <div class="flex items-center gap-1.5 mb-2">
                    @if ($post->repostOf->price_min || $post->repostOf->price_max)
                        <div
                            class="inline-flex items-center gap-1 bg-emerald-500 text-white px-2 py-0.5 rounded-full text-[10px] font-bold">
                            <flux:icon name="currency-dollar" class="size-3" />
                            @if ($post->repostOf->price_min && $post->repostOf->price_max)
                                <span>₦{{ number_format($post->repostOf->price_min, 0) }} -
                                    ₦{{ number_format($post->repostOf->price_max, 0) }}</span>
                            @elseif ($post->repostOf->price_min)
                                <span>From ₦{{ number_format($post->repostOf->price_min, 0) }}</span>
                            @else
                                <span>Up to ₦{{ number_format($post->repostOf->price_max, 0) }}</span>
                            @endif
                        </div>
                    @endif
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mb-2 whitespace-pre-wrap">
                    {!! $post->repostOf->formatted_content !!}
                </p>
                @if ($post->repostOf->images)
                    @php $originImages = array_filter(explode(',', $post->repostOf->images)); @endphp
                    @if (count($originImages) > 0)
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

    <div class="flex items-center justify-between pt-2">
        <div class="flex items-center gap-6">
            <button wire:click.stop="toggleLike"
                @click="new Audio('{{ asset('assets/mixkit-cartoon-toy-whistle-616.wav') }}').play()"
                class="flex items-center gap-1.5 transition-colors group @if ($post->isLikedBy(auth()->user())) text-red-500 @else text-zinc-500 hover:text-red-500 @endif">
                @if ($post->isLikedBy(auth()->user()))
                    <svg class="size-5 fill-current group-hover:scale-110 transition-transform" viewBox="0 0 24 24"
                        fill="currentColor">
                        <path
                            d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                    </svg>
                @else
                    <flux:icon name="heart" class="size-5 group-hover:scale-110 transition-transform" />
                @endif
                <span class="text-sm font-medium">{{ $post->likes_count ?? 0 }}</span>
            </button>
            <button wire:click.stop="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                class="flex items-center gap-1.5 text-zinc-500 hover:text-blue-500 transition-colors">
                <flux:icon name="chat-bubble-left" class="size-5" />
                <span class="text-sm font-medium">{{ $post->all_comments_count ?? 0 }}</span>
            </button>
            @if (auth()->user()->isArtisan() && $post->canBeReposted())
                <button wire:click.stop="$parent.openRepostModal({{ $post->id }})"
                    class="flex items-center gap-1.5 text-zinc-500 hover:text-green-500 transition-colors"
                    title="{{ __('Repost Work') }}">
                    <flux:icon name="arrow-path-rounded-square" class="size-5" />
                </button>
            @endif
            {{-- <button x-data="{
                copied: false,
                share() {
                    const shareData = {
                        title: 'Post by {{ $post->user->name }}',
                        text: 'Check out this post on Allsers: {{ Str::limit($post->content, 50) }}',
                        url: window.location.origin + '/dashboard?post={{ $post->post_id }}'
                    };
            
                    if (navigator.share) {
                        navigator.share(shareData).catch(console.error);
                    } else {
                        navigator.clipboard.writeText(shareData.url).then(() => {
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        });
                    }
                }
            }" @click.stop="share()"
                class="flex items-center gap-1.5 transition-colors relative"
                :class="copied ? 'text-green-500' : 'text-zinc-500 hover:text-green-500'">
                <flux:icon name="share" class="size-5" />
                <span x-show="copied" x-transition
                    class="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-[10px] px-2 py-1 rounded shadow-lg whitespace-nowrap">
                    {{ __('Link Copied!') }}
                </span>
            </button> --}}
        </div>
        <div class="flex items-center gap-3">
            <button wire:click.stop="toggleBookmark"
                class="transition-colors @if ($post->isBookmarkedBy(auth()->user())) text-[var(--color-brand-purple)] @else text-zinc-400 hover:text-[var(--color-brand-purple)] @endif">
                @if ($post->isBookmarkedBy(auth()->user()))
                    <svg class="size-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 2c-1.1 0-2 .9-2 2v18l8-3.5 8 3.5V4c0-1.1-.9-2-2-2H6z" />
                    </svg>
                @else
                    <flux:icon name="bookmark" class="size-5" />
                @endif
            </button>
            @if ($post->user_id !== auth()->id())
                <a href="{{ route('artisan.profile', $post->user) }}" wire:navigate @click.stop
                    class="flex items-center gap-1.5 border border-[var(--color-brand-purple)] text-[var(--color-brand-purple)] px-4 py-1.5 rounded-full text-xs font-semibold hover:bg-[var(--color-brand-purple)] hover:text-white transition-all">
                    <flux:icon name="chat-bubble-left-right" class="size-3.5" />
                    {{ __('Chat') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Comment Preview Section --}}
    @if ($post->comments->isNotEmpty())
        <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <div class="flex items-start gap-2.5">
                <div class="shrink-0 size-6 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                    @if ($post->comments->first()->user->profile_picture_url)
                        <img src="{{ $post->comments->first()->user->profile_picture_url }}"
                            class="size-full object-cover">
                    @else
                        <div class="size-full flex items-center justify-center text-[8px] font-bold text-zinc-500">
                            {{ $post->comments->first()->user->initials() }}
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-2xl rounded-tl-none px-3 py-2">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $post->comments->first()->user->name }}
                            </span>
                            <span class="text-[10px] text-zinc-400 whitespace-nowrap">
                                {{ $post->comments->first()->created_at->diffForHumans(null, true, true) }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-300 line-clamp-2">
                            {{ htmlspecialchars_decode($post->comments->first()->content, ENT_QUOTES) }}
                        </p>
                    </div>
                    <button wire:click.stop="$dispatch('open-post-detail', { postId: {{ $post->id }} })"
                        class="text-[10px] font-bold text-[var(--color-brand-purple)] hover:underline mt-1 ml-1">
                        {{ __('See more') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
