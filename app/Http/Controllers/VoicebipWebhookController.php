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
     * Handles the two events Allsers cares about:
     *  - tool.invocation -> the agent asks what to say to the number being
     *    called; answer with the pending announcement text (bare JSON, fed
     *    to the model verbatim, spoken, then hung up).
     *  - call.completed  -> record whether the announcement actually went
     *    out (tool never served = failed).
     *
     * Every other event is acknowledged with {"received": true} so
     * Voicebip's retry queue stays quiet, and no path ever 500s.
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
     * Resolve the announcement for this call: call_id first (exact),
     * then any number candidates from the envelope/arguments.
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
