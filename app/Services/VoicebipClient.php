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
     * Replace the agent's greeting (PATCH /v1/agents/{id}).
     *
     * Used to bake the dynamic notice into the greeting right before a
     * dial, so the callee hears the announcement the moment they answer.
     * Returns false on any failure — never throws.
     */
    public static function setGreeting(string $text): bool
    {
        try {
            $response = Http::withToken(config('services.voicebip.api_key'))
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(3)
                ->patch(rtrim(config('services.voicebip.endpoint'), '/').'/agents/'.config('services.voicebip.agent_id'), [
                    'greeting_text' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('Voicebip greeting update failed.', [
                    'status' => $response->status(),
                    'body' => \Illuminate\Support\Str::limit($response->body(), 300),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Voicebip greeting update errored.', ['exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Fetch a call's current state (GET /v1/calls/{id}), or null on error.
     *
     * @return array<string, mixed>|null
     */
    public static function getCall(string $callId): ?array
    {
        try {
            $response = Http::withToken(config('services.voicebip.api_key'))
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(3)
                ->get(rtrim(config('services.voicebip.endpoint'), '/').'/calls/'.$callId);

            return $response->successful() ? ($response->json() ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * End an active call (POST /v1/calls/{id}/end). Returns false on
     * failure — an already-completed call is not an error worth raising.
     */
    public static function endCall(string $callId): bool
    {
        try {
            $response = Http::withToken(config('services.voicebip.api_key'))
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(3)
                ->post(rtrim(config('services.voicebip.endpoint'), '/').'/calls/'.$callId.'/end');

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Voicebip end-call failed.', [
                'call_id' => $callId,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
