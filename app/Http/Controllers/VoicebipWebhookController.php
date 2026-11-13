<?php

namespace App\Http\Controllers;

use App\Models\VoiceAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoicebipWebhookController extends Controller
{
    /**
     * Receive Voicebip webhook deliveries (HMAC-SHA256 signed).
     *
     * Three request shapes land here (distinguished by body fields):
     *  - {"event": "call.screening"}  -> pre-greeting hook: speak the
     *    pending announcement and end the call (dynamic greeting).
     *  - {"event": "turn"}            -> BYOM conversation turn: speak the
     *    announcement (or the generic notice) and hang up.
     *  - {"event_type": ...}          -> lifecycle events (call.initiated,
     *    tool.invocation, call.completed, ...); tool.invocation is answered
     *    with the announcement text, call.completed records failures.
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

        // BYOM pre-greeting screening: speak the notice, then hang up.
        if (($event['event'] ?? null) === 'call.screening') {
            try {
                return response()->json($this->handleByomScreening($event));
            } catch (\Throwable $e) {
                Log::error('Voicebip screening handler failed.', ['exception' => $e->getMessage()]);

                return response()->json(['end_call' => false]);
            }
        }

        // BYOM conversation turn: deliver the notice, then hang up.
        if (($event['event'] ?? null) === 'turn') {
            try {
                return response()->json($this->handleByomTurn($event));
            } catch (\Throwable $e) {
                Log::error('Voicebip BYOM turn handler failed.', ['exception' => $e->getMessage()]);

                return response()->json([
                    'text' => 'This is an automated notice from Allsers.',
                    'end_call' => true,
                ]);
            }
        }

        // BYOM messaging (SMS/WhatsApp): an empty body is a valid ack.
        if (isset($event['inbound_message'])) {
            return response()->json([]);
        }

        $eventType = (string) ($event['event_type'] ?? '');

        if ($eventType === 'tool.invocation') {
            try {
                return response()->json($this->handleToolInvocation($event));
            } catch (\Throwable $e) {
                Log::error('Voicebip tool invocation failed.', [
                    'exception' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'This is an automated notice from Allsers. Please log in to view your latest service requests. Thank you.',
                ]);
            }
        }

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
     * The agent is on the line and asking what to say.
     *
     * @return array<string, mixed>
     */
    private function handleToolInvocation(array $event): array
    {
        if (($event['tool']['name'] ?? null) !== 'get_ping_announcement') {
            return ['error' => 'unknown_tool'];
        }

        $announcement = $this->findPendingAnnouncement($event);

        if (! $announcement) {
            return [
                'message' => 'This is an automated notice from Allsers. Please log in to Allsers to view your latest service requests. Thank you.',
            ];
        }

        $announcement->update([
            'status' => VoiceAnnouncement::STATUS_SPOKEN,
            'spoken_at' => now(),
            'call_id' => $announcement->call_id ?: ($event['call_id'] ?? null),
        ]);

        return ['message' => $announcement->message];
    }

    /**
     * BYOM screening fires just before the greeting. When a notice is
     * pending for this call, answer with it as the dynamic greeting and
     * end the call immediately — no turns needed, no model involved.
     * With no pending notice, simply allow the call (static greeting).
     *
     * @return array<string, mixed>
     */
    private function handleByomScreening(array $event): array
    {
        $announcement = $this->findPendingAnnouncement($event);

        if (! $announcement) {
            return ['end_call' => false];
        }

        $this->markSpoken($announcement, $event);

        return ['end_call' => true, 'text' => $announcement->message];
    }

    /**
     * BYOM turn: the callee (or an idle prompt response) produced speech.
     * Deliver the notice in one shot and hang up; if there is no notice
     * for this call, speak the generic Allsers notice and hang up.
     *
     * @return array<string, mixed>
     */
    private function handleByomTurn(array $event): array
    {
        $announcement = $this->findPendingAnnouncement($event);

        if ($announcement) {
            $this->markSpoken($announcement, $event);

            return ['text' => $announcement->message, 'end_call' => true];
        }

        return [
            'text' => 'This is an automated notice from Allsers. Please log in to Allsers to view your latest service requests. Thank you.',
            'end_call' => true,
        ];
    }

    /**
     * Record that the announcement went out for this call.
     */
    private function markSpoken(VoiceAnnouncement $announcement, array $event): void
    {
        $announcement->update([
            'status' => VoiceAnnouncement::STATUS_SPOKEN,
            'spoken_at' => now(),
            'call_id' => $announcement->call_id ?: ($event['call_id'] ?? null),
        ]);
    }

    /**
     * Resolve the announcement for this call: call_id first (exact),
     * then any number candidates from the envelope/arguments/BYOM payload.
     */
    private function findPendingAnnouncement(array $event): ?VoiceAnnouncement
    {
        $query = VoiceAnnouncement::query()
            ->where('status', VoiceAnnouncement::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id');

        $callId = $event['call_id'] ?? ($event['payload']['call_id'] ?? null);

        if (filled($callId)) {
            $announcement = (clone $query)->where('call_id', $callId)->first();

            if ($announcement) {
                return $announcement;
            }
        }

        $candidates = array_values(array_filter([
            // Envelope: remote party number = the artisan we dialed.
            $event['from'] ?? null,
            // BYOM turn/screening payload fields.
            $event['from_number'] ?? null,
            $event['to_number'] ?? null,
            // Tool arguments / payload candidates.
            $event['tool']['arguments']['to_number'] ?? null,
            $event['payload']['to_number'] ?? null,
        ]));

        if ($candidates !== []) {
            return (clone $query)->whereIn('to_number', $candidates)->first();
        }

        return null;
    }

    /**
     * The call ended. If the tool was never served the notice never got
     * spoken — record the failure for auditing.
     */
    private function handleCallCompleted(array $event): void
    {
        $callId = $event['payload']['call_id'] ?? ($event['call_id'] ?? null);

        if (blank($callId)) {
            return;
        }

        VoiceAnnouncement::where('call_id', $callId)
            ->where('status', VoiceAnnouncement::STATUS_PENDING)
            ->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
    }
}
