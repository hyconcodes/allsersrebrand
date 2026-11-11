<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoicebipClient
{
    /**
     * Whether the Voicebip integration has its credentials configured.
     */
    public static function configured(): bool
    {
        return filled(config('services.voicebip.api_key'))
            && filled(config('services.voicebip.agent_id'))
            && filled(config('services.voicebip.from_number'));
    }

    /**
     * Place a one-way outbound notice call via POST /v1/calls.
     *
     * Returns the Voicebip call_id on success, or null on any failure —
     * never throws, so callers' user flows are never affected.
     */
    public static function initiateCall(string $toE164): ?string
    {
        try {
            $response = Http::withToken(config('services.voicebip.api_key'))
                ->acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->post(rtrim(config('services.voicebip.endpoint'), '/').'/calls', [
                    'agent_id' => config('services.voicebip.agent_id'),
                    'from_number' => config('services.voicebip.from_number'),
                    'to_number' => $toE164,
                ]);

            if ($response->failed()) {
                Log::warning('Voicebip call initiation failed.', [
                    'to' => $toE164,
                    'status' => $response->status(),
                    'body' => \Illuminate\Support\Str::limit($response->body(), 500),
                ]);

                return null;
            }

            $callId = $response->json('call_id');

            if (blank($callId)) {
                Log::warning('Voicebip call accepted but returned no call_id.', ['to' => $toE164]);

                return null;
            }

            return $callId;
        } catch (\Throwable $e) {
            Log::error('Voicebip call initiation errored.', [
                'to' => $toE164,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
