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
        $this->appId = config('services.onesignal.app_id') ?? '';
        $this->restKey = config('services.onesignal.rest_api_key') ?? '';
    }

    /**
     * Send a push notification to a specific player ID.
     */
    public function sendToUser(string $playerId, string $title, string $message, string $url = null, array $data = [])
    {
        if (empty($this->restKey)) {
            Log::warning('OneSignal REST API key is not configured.');
            return null;
        }

        // Adapted from OneSignal API v16 / v1 spec and user's cURL example
        $fields = [
            'app_id' => $this->appId,
            'include_subscription_ids' => [$playerId], // Updated from include_player_ids to include_subscription_ids
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
            'target_channel' => 'push',
            'data' => $data,
            'url' => $url,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $this->restKey,
            'Content-Type' => 'application/json; charset=utf-8',
        ])->post('https://api.onesignal.com/notifications', $fields);

        if ($response->failed()) {
            Log::error('OneSignal notification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $fields
            ]);
        } else {
            Log::info('OneSignal notification sent successfully', [
                'player_id' => $playerId,
                'response' => $response->json()
            ]);
        }

        return $response->json();
    }
}
