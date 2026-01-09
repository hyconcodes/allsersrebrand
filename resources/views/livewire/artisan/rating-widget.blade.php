<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Review;
use Illuminate\Support\Facades\Request;

new class extends Component {
    public User $artisan;
    public $rating = 0;
    public $comment = '';
    public $averageRating = 0;
    public $totalReviews = 0;
    public $reviews = [];
    public $ratingCounts = [];
    public $alreadyRated = false;

    public function mount(User $artisan)
    {
        $this->artisan = $artisan;
        $this->loadRatingData();
    }

    public function loadRatingData()
    {
        $this->averageRating = $this->artisan->reviews()->avg('rating') ?? 0;
        $this->totalReviews = $this->artisan->reviews()->count();
        $this->reviews = $this->artisan->reviews()->with('reviewer')->latest()->take(5)->get();
        
        // Calculate distribution
        $counts = $this->artisan->reviews()
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
            
        $this->ratingCounts = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = $counts[$i] ?? 0;
            $percentage = $this->totalReviews > 0 ? ($count / $this->totalReviews) * 100 : 0;
            $this->ratingCounts[$i] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        // Check if current user (or guest IP) has already rated
        if (auth()->check()) {
            $this->alreadyRated = $this->artisan->reviews()->where('reviewer_id', auth()->id())->exists();
        } else {
             $this->alreadyRated = $this->artisan->reviews()->where('ip_address', Request::ip())->whereNull('reviewer_id')->exists();
        }
    }

    public function setRating($value)
    {
        if ($this->alreadyRated)
            return;
        $this->rating = $value;
    }

    public function submitReview()
    {
        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($this->alreadyRated) {
            $this->dispatch('toast', type: 'error', message: 'You have already rated this artisan.');
            return;
        }

        Review::create([
            'reviewer_id' => auth()->id(), // Nullable for guests
            'artisan_id' => $this->artisan->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'ip_address' => Request::ip(),
        ]);

        $this->reset(['rating', 'comment']);
        $this->loadRatingData();
        $this->dispatch('toast', type: 'success', message: 'Review submitted successfully!');
    }
}; ?>

<div class="bg-white dark:bg-zinc-900 rounded-2xl p-6 border border-zinc-200 dark:border-zinc-800 shadow-sm mt-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="font-bold text-lg text-zinc-900 dark:text-zinc-100">{{ __('Reviews & Ratings') }}</h3>
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1">
                <flux:icon name="star" variant="solid" class="size-5 text-yellow-400" />
                <span class="font-black text-xl text-zinc-900 dark:text-zinc-100">{{ number_format($averageRating, 1) }}</span>
            </div>
            <span class="text-sm text-zinc-500 dark:text-zinc-400">({{ $totalReviews }} {{ Str::plural('review', $totalReviews) }})</span>
        </div>
    </div>
    
    <!-- Rating Breakdown -->
    <div class="mb-8 space-y-2">
        @foreach($ratingCounts as $star => $data)
            <div class="flex items-center gap-3 text-xs">
                <span class="font-bold w-3 text-right text-zinc-700 dark:text-zinc-300">{{ $star }}</span>
                <flux:icon name="star" variant="solid" class="size-3 text-zinc-300 dark:text-zinc-600" />
                <div class="flex-1 h-2 bg-zinc-100 dark:bg-zinc-800/50 rounded-full overflow-hidden">
                    <div class="h-full bg-yellow-400 rounded-full" style="width: {{ $data['percentage'] }}%"></div>
                </div>
                <span class="w-8 text-right text-zinc-400 tabular-nums">{{ $data['count'] }}</span>
            </div>
        @endforeach
    </div>

    @if(!$alreadyRated && auth()->id() !== $artisan->id)
        <div class="mb-8 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-100 dark:border-zinc-800">
            <h4 class="font-bold text-sm text-zinc-900 dark:text-white mb-3">{{ __('Rate this Artisan') }}</h4>

            <div class="flex items-center gap-2 mb-4">
                @foreach(range(1, 5) as $star)
                    <button wire:click="setRating({{ $star }})" class="focus:outline-none transition-transform hover:scale-110">
                        <flux:icon 
                            name="star" 
                            variant="{{ $rating >= $star ? 'solid' : 'outline' }}" 
                            class="size-8 {{ $rating >= $star ? 'text-yellow-400' : 'text-zinc-300 dark:text-zinc-600' }}" 
                        />
                    </button>
                @endforeach
            </div>

            @if($rating > 0)
                <div class="space-y-3">
                    <textarea wire:model="comment" 
                        placeholder="Share your experience (optional)..."
                        class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-yellow-400/20 resize-none h-24"></textarea>

                    <button wire:click="submitReview" 
                        class="w-full py-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-lg text-sm font-bold hover:opacity-90 transition-opacity">
                        {{ __('Submit Review') }}
                    </button>
                </div>
            @endif
        </div>
    @elseif($alreadyRated)
         <div class="mb-8 p-4 bg-green-50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/20 rounded-xl text-center">
            <p class="text-sm text-green-600 dark:text-green-400 font-medium">
                {{ __('Thanks for your feedback!') }}
            </p>
        </div>
    @endif

    <div class="space-y-4">
        @forelse($reviews as $review)
            <div class="pb-4 border-b border-zinc-100 dark:border-zinc-800 last:border-0 last:pb-0">
                <div class="flex items-start gap-3">
                    <div class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                        @if($review->reviewer)
                             @if($review->reviewer->profile_picture_url)
                                <img src="{{ $review->reviewer->profile_picture_url }}" class="size-full rounded-full object-cover">
                             @else
                                <span class="text-xs font-bold text-zinc-500">{{ $review->reviewer->initials() }}</span>
                             @endif
                        @else
                            <flux:icon name="user" class="size-4 text-zinc-400" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $review->reviewer ? $review->reviewer->name : 'Guest User' }}
                            </span>
                            <span class="text-xs text-zinc-400">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="flex items-center gap-0.5 mb-2">
                            @foreach(range(1, 5) as $i)
                                <flux:icon name="star" variant="solid" class="size-3 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-zinc-200 dark:text-zinc-700' }}" />
                            @endforeach
                        </div>
                        @if($review->comment)
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">{{ $review->comment }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-zinc-400">
                <p class="text-sm">{{ __('No reviews yet. Be the first to rate!') }}</p>
            </div>
        @endforelse
    </div>
</div>
