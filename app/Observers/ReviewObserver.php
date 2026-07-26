<?php

namespace App\Observers;

use App\Models\Review;
use App\Jobs\RecalculateSmartRating;

class ReviewObserver
{
    public function created(Review $review): void
    {
        RecalculateSmartRating::dispatch($review->artisan);
    }

    public function updated(Review $review): void
    {
        RecalculateSmartRating::dispatch($review->artisan);
    }

    public function deleted(Review $review): void
    {
        RecalculateSmartRating::dispatch($review->artisan);
    }

    public function restored(Review $review): void
    {
        RecalculateSmartRating::dispatch($review->artisan);
    }

    public function forceDeleted(Review $review): void
    {
        RecalculateSmartRating::dispatch($review->artisan);
    }
}
