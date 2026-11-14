<?php

namespace App\Console\Commands;

use App\Models\VoiceAnnouncement;
use App\Support\VoiceCallAlert;
use Illuminate\Console\Command;

/**
 * Drives the ping availability chain between ping-time call 1 and the
 * pinger's final "artisan unavailable" message:
 *
 *  1. Dials every callback whose next_attempt_at is due (missed call ->
 *     +3 minutes, up to 3 attempts total), re-checking consent, phone
 *     and the CBN window first — a callback that can no longer be placed
 *     ends the chain with an "unavailable" notification immediately.
 *  2. Settles chains that never resolved before expiry (webhook outage,
 *     stuck first call) so the pinger is never left without an answer.
 *     Bounded to recently-expired rows so deploying this command never
 *     blasts notifications for old pings.
 *
 * Scheduled every minute with withoutOverlapping(); long rings simply
 * push remaining due rows to the next tick. Idempotent: chain state is
 * claimed atomically, so duplicate ticks or manual runs are harmless.
 */
class DispatchVoiceRetries extends Command
{
    protected $signature = 'voice:dispatch-retries';

    protected $description = 'Dial due voice-notice callbacks for unanswered pings and settle expired chains';

    public function handle(): int
    {
        $due = VoiceAnnouncement::query()
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->whereNull('answered_at')
            ->whereNull('pinger_notified')
            ->where('expires_at', '>', now())
            ->orderBy('next_attempt_at')
            ->limit(3)
            ->get();

        foreach ($due as $announcement) {
            VoiceCallAlert::retry($announcement);
        }

        VoiceAnnouncement::query()
            ->whereNotNull('call_id')
            ->whereNull('answered_at')
            ->whereNull('pinger_notified')
            ->where('expires_at', '<=', now())
            ->where('expires_at', '>=', now()->subHour())
            ->orderBy('expires_at')
            ->limit(20)
            ->get()
            ->each(fn (VoiceAnnouncement $announcement) => VoiceCallAlert::finalizeUnavailable($announcement));

        return self::SUCCESS;
    }
}
