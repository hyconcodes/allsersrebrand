<?php

namespace App\Http\Controllers;

use App\Models\VoiceAnnouncement;
use App\Services\VoicebipClient;
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
     *  - {"event": "turn"}            -> stays silent always; the agent
     *    never speaks or hangs up from caller speech — hang-up comes only
     *    from the supervisor and the idle-silence hook.
     *  - {"event_type": ...}          -> lifecycle events; call.completed
     *    settles the audit row (never-answered -> failed) and
     *    call.idle_silence hangs up notice calls (webhook-side backup
     *    for when the in-request supervisor dies).
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

            if ($eventType === 'call.idle_silence') {
                // The callee has been silent since the greeting — if this
                // is one of our notice calls, hang up now (webhook-side, so
                // it works even when the in-request supervisor was killed).
                $this->endNoticeCall($event['payload']['call_id'] ?? null);
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
     * Hang up a notice call from inside a webhook request (fast, does not
     * depend on the caller's request lifecycle). Idempotent: an already-
     * ended call answers with a JSON 404, treated as success.
     *
     * Guards: the call must be one of ours (audit row) AND must have been
     * marked "greeted" by the WS supervisor's first agent frame — idle
     * silence before the greeting TTS starts would otherwise kill the
     * call before the notice is ever spoken.
     */
    private function endNoticeCall(mixed $callId): void
    {
        if (blank($callId) || ! is_string($callId)) {
            return;
        }

        if (! VoiceAnnouncement::where('call_id', $callId)->exists()) {
            return;
        }

        if (! Cache::get('voicebip:greeted:'.$callId)) {
            return;
        }

        VoicebipClient::endCall($callId);

        Log::info('Voicebip notice hung up from webhook (idle silence).', [
            'call_id' => $callId,
        ]);
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
     * The agent NEVER speaks after the notice and never hangs up from a
     * turn — the caller talking must have zero effect. Hang-up belongs
     * solely to the supervisor (WS idle_silence frame -> DELETE) with the
     * idle-silence webhook as backup, so nothing here depends on the
     * caller saying anything.
     *
     * @return array<string, mixed>
     */
    private function handleByomTurn(array $event): array
    {
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
