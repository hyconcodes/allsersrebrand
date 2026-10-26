<?php

use App\Models\ChallengeRating;
use App\Models\Post;
use Livewire\Volt\Component;

new class extends Component
{
    public Post $post;

    public $isJudge = false;

    public $userRating = 0;

    public function mount($post, $isJudge = false)
    {
        $this->post = $post;
        $this->isJudge = $isJudge;

        if (auth()->check()) {
            $ratingRecord = ChallengeRating::where('post_id', $post->id)
                ->where('user_id', auth()->id())
                ->first();
            $this->userRating = $ratingRecord ? $ratingRecord->rating : 0;
        }
    }

    public function setRating($stars)
    {
        if (! $this->isJudge) {
            return;
        }

        ChallengeRating::updateOrCreate(['post_id' => $this->post->id, 'user_id' => auth()->id()], ['rating' => $stars]);

        $this->userRating = $stars;
        $this->dispatch('toast', type: 'success', title: 'Submitted', message: 'Your star rating has been recorded!');

        // Refresh post to update average (if we displayed it)
        $this->post->load('ratings');
    }

    public function toggleLike()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if ($this->post->isLikedBy(auth()->user())) {
            $this->post
                ->likes()
                ->where('user_id', auth()->id())
                ->delete();
        } else {
            $this->post->likes()->create(['user_id' => auth()->id()]);
        }

        $this->post->loadCount('likes');
    }
}; ?>

<div
    class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 overflow-hidden {{ $post->user->judgingChallenges()->where('challenges.id', $post->challenge_id)->where('challenge_judges.status', 'accepted')->exists() ? 'ring-2 ring-[var(--color-brand-purple)] ring-offset-4 dark:ring-offset-zinc-950' : '' }}">
    <!-- Post Body -->
    <div class="flex gap-3 p-6">
        <div class="flex flex-col items-center shrink-0 self-stretch">
            <a href="{{ route('artisan.profile', $post->user) }}"
                class="size-12 rounded-full overflow-hidden bg-zinc-100 hover:opacity-80 transition-opacity"
                style="box-shadow: 0 0 0 2px {{ $post->user->accentColor() }}">
                @if ($post->user->profile_picture_url)
                    <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                @endif
            </a>
            <div class="w-0.5 flex-1 min-h-[16px]" style="background-color: {{ $post->user->accentColor() }}" aria-hidden="true"></div>
            <span class="size-5 shrink-0 rounded-full border-2 bg-white dark:bg-zinc-900 flex items-center justify-center overflow-hidden"
                style="border-color: {{ $post->user->accentColor() }}" aria-hidden="true">
                <img src="{{ asset('assets/allsers.png') }}" alt="" class="size-full object-cover">
            </span>
        </div>
        <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between mb-4">
            <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-bold text-zinc-900 dark:text-white capitalize">{{ $post->user->username }}</h3>
                        @if ($post->user->judgingChallenges()->where('challenges.id', $post->challenge_id)->where('challenge_judges.status', 'accepted')->exists())
                            <div
                                class="px-2 py-0.5 rounded-full bg-[var(--color-brand-purple)] text-xs font-bold text-white uppercase tracking-normal">
                                {{ __('JUDGE') }}</div>
                        @else
                            <div
                                class="px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-xs font-bold text-zinc-500 uppercase tracking-normal">
                                {{ __('PARTICIPANT') }}</div>
                        @endif
                        @if ($post->user->work && !$post->repost_of_id)
                            <span title="{{ $post->user->work }}"
                                class="max-w-[160px] sm:max-w-[220px] truncate rounded-full px-2 py-0.5 text-xs font-medium"
                                style="color: {{ $post->user->accentColor() }}; border: 1.5px dotted {{ $post->user->accentColor() }}; background-color: {{ $post->user->accentColor() }}1a; display: inline-block;">
                                {{ $post->user->work }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500">{{ $post->created_at->diffForHumans() }}</p>
                </div>

            <!-- Pin Button (Only for Creator) -->
            @if (auth()->id() === $post->challenge->creator_id)
                <button wire:click="$parent.pinPost({{ $post->id }})"
                    class="p-2 text-zinc-400 hover:text-zinc-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        fill="{{ $post->is_challenge_pinned ? 'currentColor' : 'none' }}" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                </button>
            @endif
        </div>

        <div class="text-zinc-700 dark:text-zinc-300 text-sm mb-6 leading-relaxed">
            {!! $post->formatted_content !!}
        </div>

        <!-- Media Display (Up to 4 images or 1 video) -->
        @if ($post->images)
            @php
                $imgs = is_array($post->images) ? $post->images : array_filter(explode(',', (string)$post->images));
                $imgUrls = array_map(fn($i) => asset('storage/' . $i), $imgs);
            @endphp
            <div
                class="grid {{ count($imgs) > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2 rounded-2xl overflow-hidden mb-6">
                @foreach ($imgUrls as $index => $imgUrl)
                    <div class="cursor-pointer overflow-hidden rounded-2xl"
                        @click="$dispatch('open-lightbox', { images: {{ Js::from($imgUrls) }}, index: {{ $index }} })">
                        <img src="{{ $imgUrl }}"
                            class="w-full h-auto max-h-[400px] object-cover hover:scale-[1.02] transition-transform duration-500">
                    </div>
                @endforeach
            </div>
        @elseif($post->video)
            <div x-data="{ muted: true }" class="relative rounded-2xl overflow-hidden mb-6 bg-black aspect-video flex items-center justify-center">
                <video x-ref="video" data-autoplay muted src="{{ asset('storage/' . $post->video) }}" class="w-full h-full" controls playsinline preload="metadata"></video>
                <button type="button" @click.stop="muted = !muted; $refs.video.muted = muted"
                    class="absolute top-2 right-2 z-10 flex items-center justify-center size-8 rounded-full bg-black/50 text-white backdrop-blur-sm hover:bg-black/70 transition-colors"
                    :aria-label="muted ? 'Unmute video' : 'Mute video'">
                    <flux:icon x-show="muted" name="speaker-x-mark" class="size-4" />
                    <flux:icon x-show="!muted" name="speaker-wave" class="size-4" style="display: none;" />
                </button>
            </div>
        @endif

        <!-- Interaction Bar -->
        <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center gap-6">
                <!-- Like -->
                <button wire:click="toggleLike"
                    x-data="{
                        liked: {{ $post->isLikedBy(auth()->user()) ? 'true' : 'false' }},
                        count: {{ $post->likes_count ?? 0 }},
                        hearts: [],
                        burst() {
                            const colors = ['text-rose-500','text-pink-400','text-amber-400','text-purple-500','text-sky-400','text-emerald-400','text-orange-400'];
                            const pts = [[-16,-12],[16,-10],[-11,-19],[12,-17],[0,-22],[-7,-15],[9,-14]];
                            this.hearts = pts.map(([x,y], i) => ({ id: Date.now()+i+Math.random(), x, y, color: colors[i % colors.length], delay: i*38 }));
                            setTimeout(() => this.hearts = [], 760);
                        }
                    }"
                    @click="const willLike = !liked; liked = willLike; count += willLike ? 1 : -1; if (willLike) burst()"
                    class="relative flex items-center gap-2 group"
                    :class="liked ? 'text-red-500' : 'text-zinc-400 hover:text-red-500'">
                    <span class="absolute inset-0 pointer-events-none overflow-visible" aria-hidden="true">
                        <template x-for="h in hearts" :key="h.id">
                            <span class="heart-burst absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2" :style="`--tx:${h.x}px; --ty:${h.y}px; animation-delay:${h.delay}ms`">
                                <span :class="h.color"><flux:icon name="heart" variant="solid" class="size-2.5 drop-shadow-sm" /></span>
                            </span>
                        </template>
                    </span>
                    <flux:icon name="heart" variant="solid"
                        class="relative size-5 transition-transform group-hover:scale-110" />
                    <span class="text-xs font-bold text-zinc-500" x-text="count">{{ number_format($post->likes_count) }}</span>
                </button>

                <!-- Comment (Direct link to post detail or local state) -->
                <button onclick="Livewire.dispatch('open-post-detail', { postId: {{ $post->id }} })"
                    class="flex items-center gap-2 group">
                    <flux:icon name="chat-bubble-left"
                        class="size-5 text-zinc-400 group-hover:scale-110 transition-transform" />
                    <span
                        class="text-xs font-bold text-zinc-500">{{ number_format($post->all_comments_count ?? $post->allComments()->count()) }}</span>
                </button>
            </div>

            <!-- Star Ratings (Judge functionality) -->
            <div class="flex items-center gap-1">
                @for ($i = 1; $i <= 5; $i++)
                    <button wire:click="setRating({{ $i }})" {{ !$isJudge ? 'disabled' : '' }}
                        class="{{ $isJudge ? 'hover:scale-125 transition-transform' : 'cursor-default' }}">
                        <flux:icon name="star"
                            class="size-5 {{ $i <= ($isJudge ? $userRating : $post->averageRating()) ? 'text-yellow-400' : 'text-zinc-200 dark:text-zinc-800' }}"
                            variant="{{ $i <= ($isJudge ? $userRating : $post->averageRating()) ? 'solid' : 'outline' }}" />
                    </button>
                @endfor
            </div>
        </div>
        </div>
    </div>
</div>
