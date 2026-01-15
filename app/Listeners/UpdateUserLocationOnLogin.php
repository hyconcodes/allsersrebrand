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
                // Try to resolve country code
                $countryCode = 'NG';
                try {
                    /** @var \Illuminate\Http\Client\Response $response */
                    $response = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Allsers-App'])
                        ->timeout(3)
                        ->get("https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=10");

                    if ($response->successful()) {
                        $countryCode = strtoupper($response->json()['address']['country_code'] ?? 'NG');
                    }
                } catch (\Exception $e) {
                    // Fallback to existing or default
                    $countryCode = $user->country_code ?: 'NG';
                }

                $user->update([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'country_code' => $countryCode,
                ]);
            }
        }
    }
}
