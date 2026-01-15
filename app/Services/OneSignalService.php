<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected $appId;
    protected $restKey;

    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->restKey = config('services.onesignal.rest_api_key');
    }

    /**
     * Send a push notification to a specific player ID.
     */
    public function sendToUser(string $playerId, string $title, string $message, string $url = null)
    {
        if (empty($this->restKey)) {
            Log::warning('OneSignal REST API key is not configured.');
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $this->restKey,
            'Content-Type' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
                    'app_id' => $this->appId,
                    'include_player_ids' => [$playerId],
                    'headings' => ['en' => $title],
                    'contents' => ['en' => $message],
                    'url' => $url,
                ]);

        if ($response->failed()) {
            Log::error('OneSignal notification failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }

        return $response->json();
    }
}
