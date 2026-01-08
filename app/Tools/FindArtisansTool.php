<?php

namespace App\Tools;

use App\Models\User;
use Prism\Prism\Tool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FindArtisansTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('find_artisans')
            ->for('Find artisans and service providers near a specific location. Use this when a user asks for help finding someone nearby or around them.')
            ->withNumberParameter('latitude', 'The latitude of the target location')
            ->withNumberParameter('longitude', 'The longitude of the target location')
            ->withStringParameter('search', 'Optional search term to filter by work category or name')
            ->using($this);
    }

    public function __invoke(float $latitude, float $longitude, string $search = null): string
    {
        // Simple Haversine distance calculation
        $query = User::where('role', 'artisan')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('users.*')
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )
            ->having('distance', '<', 50) // Within 50km
            ->orderBy('distance');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('work', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%");
            });
        }

        $artisans = $query->limit(5)->get();

        if ($artisans->isEmpty()) {
            return "No artisans found near this location.";
        }

        return $artisans->map(function ($artisan) {
            $address = $artisan->address;

            // If address is missing, resolve it on the fly
            if (empty($address) && $artisan->latitude && $artisan->longitude) {
                try {
                    /** @var \Illuminate\Http\Client\Response $response */
                    $response = Http::withHeaders(['User-Agent' => 'Allsers-App'])
                        ->timeout(3)
                        ->get("https://nominatim.openstreetmap.org/reverse?format=json&lat={$artisan->latitude}&lon={$artisan->longitude}&zoom=16");

                    if ($response->successful()) {
                        $address = $response->json()['display_name'] ?? null;
                    }
                } catch (\Exception $e) {
                    // Fallback to coordinates string if API fails
                    $address = "Lagos, Nigeria";
                }
            }

            return [
                'id' => $artisan->id,
                'name' => $artisan->name,
                'username' => $artisan->username,
                'work' => $artisan->work,
                'profile_picture' => $artisan->profile_picture_url,
                'distance' => round($artisan->distance, 1) . 'km',
                'location' => $address ?: 'Unknown location',
                'experience' => $artisan->experience_year . ' years',
                'latitude' => $artisan->latitude,
                'longitude' => $artisan->longitude,
            ];
        })->toJson();
    }
}
