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

    /**
     * End an active call — official route is DELETE /v1/calls/{call_id}
     * (docs: "End a call"; a POST .../end does not exist and 404s).
     *
     * Retries up to 3 times because the API occasionally times out. A JSON
     * 404 is NOT trusted blindly: Voicebip has returned "call: not found"
     * for calls that were still very much alive — verify via GET that the
     * call is actually terminal before treating it as ended. Never throws.
     */
    public static function endCall(string $callId): bool
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withToken(config('services.voicebip.api_key'))
                    ->acceptJson()
                    ->timeout(12)
                    ->connectTimeout(3)
                    ->delete(rtrim(config('services.voicebip.endpoint'), '/').'/calls/'.$callId);

                if ($response->successful()) {
                    return true;
                }

                $isJson404 = $response->status() === 404
                    && str_contains((string) $response->header('Content-Type'), 'json');

                if ($isJson404) {
                    if (self::callIsOver($callId)) {
                        return true;
                    }

                    Log::warning('Voicebip end-call returned a phantom 404; call still active.', [
                        'call_id' => $callId,
                        'attempt' => $attempt,
                    ]);
                } else {
                    Log::warning('Voicebip end-call rejected.', [
                        'call_id' => $callId,
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'body' => \Illuminate\Support\Str::limit($response->body(), 300),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Voicebip end-call errored.', [
                    'call_id' => $callId,
                    'attempt' => $attempt,
                    'exception' => $e->getMessage(),
                ]);
            }

            if ($attempt < 3) {
                sleep(2);
            }
        }

        return false;
    }

    /**
     * Whether the call is in a terminal state (GET /v1/calls/{id}).
     * Terminal statuses resolve reliably even though live-call status
     * freezes at "initiated".
     */
    private static function callIsOver(string $callId): bool
    {
        try {
            $response = Http::withToken(config('services.voicebip.api_key'))
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(3)
                ->get(rtrim(config('services.voicebip.endpoint'), '/').'/calls/'.$callId);

            if (! $response->successful()) {
                return false;
            }

            return in_array($response->json('status'), ['completed', 'failed', 'no_answer', 'busy'], true);
        } catch (\Throwable) {
            return false;
        }
    }
}
