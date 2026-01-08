<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class UpdateUserLocationOnLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof \App\Models\User && $user->role === 'artisan') {
            $lat = request('latitude');
            $lng = request('longitude');

            if ($lat && $lng) {
                $user->update([
                    'latitude' => $lat,
                    'longitude' => $lng,
                ]);
            }
        }
    }
}
