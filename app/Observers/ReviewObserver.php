<?php

namespace App\Observers;

use App\Models\Review;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $review->artisan->recalculateSmartRating();
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        $review->artisan->recalculateSmartRating();
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $review->artisan->recalculateSmartRating();
    }

    /**
     * Handle the Review "restored" event.
     */
    public function restored(Review $review): void
    {
        $review->artisan->recalculateSmartRating();
    }

    /**
     * Handle the Review "force deleted" event.
     */
    public function forceDeleted(Review $review): void
    {
        $review->artisan->recalculateSmartRating();
    }
}
