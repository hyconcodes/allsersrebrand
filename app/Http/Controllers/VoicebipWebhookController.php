<?php

namespace App\Http\Controllers;

use App\Models\VoiceAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VoicebipWebhookController extends Controller
{
    /**
     * Receive Voicebip webhook deliveries (HMAC-SHA256 signed).
     *
     * Three request shapes land here (distinguished by body fields):
     *  - {"event": "call.screening"}  -> allow the call; the agent's
     *    static greeting speaks the notice.
     *  - {"event": "turn"}            -> stays silent while the greeting
     *    plays (noise must never cut or repeat it); once the supervisor
     *    has seen the greeting finish, a turn closes with goodbye +
     *    end_call in case the hang-up failed.
     *  - {"event_type": ...}          -> lifecycle events; call.completed
     *    settles the audit row (never-answered -> failed).
     *
     * SMS/WhatsApp BYOM payloads are acknowledged with {}. Every other
     * event is acknowledged with {"received": true}, and no path 500s.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.voicebip.webhook_secret');
        $body = $request->getContent();

        if ($secret === '') {
            Log::warning('Voicebip webhook received but VOICEBIP_WEBHOOK_SECRET is not configured.');

            return response()->json(['received' => true]);
        }

        if (! $this->signatureIsValid($request, $body, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->json()->all();

        // BYOM pre-greeting screening: allow — the static greeting is the
        // announcement and plays right after this.
        if (($event['event'] ?? null) === 'call.screening') {
            return response()->json(['end_call' => false]);
        }

        // BYOM conversation turn (callee speech / transcribed noise).
        if (($event['event'] ?? null) === 'turn') {
            try {
                return response()->json($this->handleByomTurn($event));
            } catch (\Throwable $e) {
                Log::error('Voicebip BYOM turn handler failed.', ['exception' => $e->getMessage()]);

                return response()->json(['text' => '', 'end_call' => false]);
            }
        }

        // BYOM messaging (SMS/WhatsApp): an empty body is a valid ack.
        if (isset($event['inbound_message'])) {
            return response()->json([]);
        }

        $eventType = (string) ($event['event_type'] ?? '');

        try {
            if ($eventType === 'call.completed') {
                $this->handleCallCompleted($event);
            }
        } catch (\Throwable $e) {
            Log::error('Voicebip webhook event handler failed.', [
                'event_type' => $eventType,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Verify X-Voicebip-Signature: HMAC-SHA256 over "{timestamp}.{rawBody}".
     * Rejects replays older than 5 minutes and accepts the rotation
     * grace-period header (X-Voicebip-Signature-Previous) when it still
     * validates against the configured (not yet rotated) secret.
     */
    private function signatureIsValid(Request $request, string $body, string $secret): bool
    {
        $signature = (string) $request->header('X-Voicebip-Signature');
        $timestamp = (string) $request->header('X-Voicebip-Timestamp');

        if ($signature === '' || $timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        if (hash_equals($expected, $signature)) {
            return true;
        }

        $previous = (string) $request->header('X-Voicebip-Signature-Previous');

        return $previous !== '' && hash_equals($expected, $previous);
    }

    /**
     * BYOM turn: the callee (or transcribed noise) produced speech.
     *
     * While the greeting is playing this must do nothing — no speech, no
     * hang-up — so noise can never cut or repeat the notice. Once the
     * supervisor has flagged the greeting as finished, a turn means the
     * hang-up failed: close politely and end the call.
     *
     * @return array<string, mixed>
     */
    private function handleByomTurn(array $event): array
    {
        $callId = $event['call_id'] ?? null;

        if (filled($callId) && Cache::has('voicebip:greeted:'.$callId)) {
            return ['text' => 'Thank you. Goodbye.', 'end_call' => true];
        }

        return ['text' => '', 'end_call' => false];
    }

    /**
     * The call ended. Announcements are marked spoken at dial (the static
     * greeting guarantees delivery), so settle both directions: a connected
     * duration of 6s+ means the notice had time to play; shorter means the
     * call was never really answered — downgrade to failed for auditing.
     */
    private function handleCallCompleted(array $event): void
    {
        $callId = $event['payload']['call_id'] ?? ($event['call_id'] ?? null);

        if (blank($callId)) {
            return;
        }

        $announcement = VoiceAnnouncement::where('call_id', $callId)
            ->whereIn('status', [VoiceAnnouncement::STATUS_PENDING, VoiceAnnouncement::STATUS_SPOKEN])
            ->first();

        if (! $announcement) {
            return;
        }

        $duration = (int) ($event['payload']['duration_seconds'] ?? 0);
        $spoken = $duration >= 6;

        $announcement->update([
            'status' => $spoken ? VoiceAnnouncement::STATUS_SPOKEN : VoiceAnnouncement::STATUS_FAILED,
            'spoken_at' => $spoken ? ($announcement->spoken_at ?? now()) : null,
        ]);
    }
}
