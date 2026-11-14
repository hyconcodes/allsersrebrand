<?php

namespace App\Support;

use App\Models\Engagement;
use App\Models\User;
use App\Models\VoiceAnnouncement;
use App\Notifications\PingAvailability;
use App\Services\VoicebipClient;
use App\Services\VoicebipEventStream;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoiceCallAlert
{
    /**
     * Default greeting: the static generic notice. Configured on the
     * agent, restored after every notice call (Voicebip reads it at call
     * connect), and what inbound callers hear between notices.
     */
    public const NOTICE = 'This is an automated notice from Allsers.com, someone has just requested your services on Allsers.com. Please Log in to Allsers.com now to view the request and accept the job. Thank you.';

    /**
     * Ping availability chain: attempt 1 at ping time, then a callback
     * RETRY_AFTER_MINUTES after each unanswered call, until
     * MAX_ATTEMPTS calls total. Answer at any point -> the pinger is
     * notified "available" live (~1s after pickup) and the chain stops;
     * all attempts missed -> "unavailable, ping someone else".
     */
    public const RETRY_AFTER_MINUTES = 3;

    public const MAX_ATTEMPTS = 3;

    /**
     * Place a one-way voice notice call to the artisan when someone pings
     * them for services. The per-call dynamic message (pinger + task) is
     * PATCHed into the agent's greeting right before dialing — Voicebip
     * reads greeting_text when the call CONNECTS, so the callee hears the
     * dynamic notice as the greeting itself (that PATCH also applies the
     * 2s idle timeout + silent "." idle prompt, and the dial carries a
     * 60s max_duration hard cap). A dial lock serializes concurrent
     * notices so one ping's artisan can never hear another's name/task. A
     * background supervisor watches the live WebSocket event stream, hangs
     * up when call.idle_silence proves the notice finished, and marks the
     * announcement answered the moment the greeting frame proves pickup —
     * no turns, no audible idle prompts, no caller dependence.
     *
     * Missed attempts feed the callback chain via recordMissed() (webhook)
     * and the voice:dispatch-retries scheduler re-dials them; the pinger
     * hears back exactly once either way (PingAvailability).
     *
     * Fully isolated like PingAlert::send(): every guard failure or API
     * error is swallowed and logged so the ping flow is never affected.
     * Outside the CBN call window (08:00-20:00 Africa/Lagos) the call is
     * skipped and logged — the ping still goes through by email/notification.
     */
    public static function send(User $pinger, User $artisan, Engagement $engagement): void
    {
        $lock = null;

        try {
            if (! VoicebipClient::configured()) {
                return;
            }

            if (! $artisan->isArtisan()) {
                return;
            }

            if ($artisan->isBanned() || ! $artisan->hasAcceptedVoiceCalls()) {
                Log::info('Voicebip notice skipped (consent/ban).', [
                    'artisan_id' => $artisan->id,
                    'consent' => $artisan->voice_call_consent,
                ]);

                return;
            }

            $to = self::normalizeToE164($artisan->phone_number, $artisan->country_code);

            if ($to === null) {
                Log::info('Voicebip notice skipped (unusable phone number).', [
                    'artisan_id' => $artisan->id,
                    'phone' => $artisan->phone_number,
                ]);

                return;
            }

            if (! self::withinCallWindow()) {
                Log::info('Voicebip notice skipped (outside call window).', [
                    'artisan_id' => $artisan->id,
                    'to' => $to,
                    'window' => config('services.voicebip.call_window_start').'h-'.config('services.voicebip.call_window_end').'h '.config('services.voicebip.timezone'),
                ]);

                return;
            }

            // Debounce: never re-dial the same number while a fresh
            // announcement exists (spam pings in quick succession), nor
            // while a callback chain for an earlier ping is still live
            // (next_attempt_at pending). Rows are created before dialing,
            // so both states count.
            $hasFresh = VoiceAnnouncement::where('to_number', $to)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->where(function ($query) {
                    $query->whereIn('status', [VoiceAnnouncement::STATUS_PENDING, VoiceAnnouncement::STATUS_SPOKEN])
                        ->orWhereNotNull('next_attempt_at');
                })
                ->exists();

            if ($hasFresh) {
                return;
            }

            // The greeting is swapped per call, so only one notice may be
            // in flight: Voicebip reads greeting_text at answer, and the
            // lock is held until the supervisor finishes (or the TTL kicks
            // in if the process dies). A contended ping skips the voice
            // call (logged) — its email notification still goes out.
            $lock = Cache::lock('voicebip:notice-dial', 150);

            try {
                $lock->block(3);
            } catch (LockTimeoutException) {
                Log::info('Voicebip notice skipped (another notice dial in flight).', [
                    'artisan_id' => $artisan->id,
                ]);

                return;
            }

            // Audit row first (attempt 0 = not dialed yet; dial() bumps it
            // to 1 only when the call actually goes out), then the shared
            // dial path also used by 3-minute callbacks.
            $announcement = VoiceAnnouncement::create([
                'user_id' => $artisan->id,
                'engagement_id' => $engagement->id,
                'to_number' => $to,
                'message' => self::announcementText($pinger, $engagement),
                'status' => VoiceAnnouncement::STATUS_PENDING,
                'attempt' => 0,
                'expires_at' => now()->addMinutes(30),
            ]);

            if (! self::dial($announcement, $lock)) {
                $lock->release();
            }
        } catch (\Throwable $e) {
            if ($lock instanceof Lock) {
                try {
                    $lock->release();
                } catch (\Throwable) {
                    // Lock may already be released or expired — TTL covers it.
                }
            }

            Log::error('Voicebip notice call failed.', [
                'artisan_id' => $artisan->id,
                'engagement_id' => $engagement->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Shared dial path for attempt 1 (send) and 3-minute callbacks
     * (retry): bake the stored dynamic message into the greeting, dial,
     * bump the attempt counter, and hand the call to the WS supervisor.
     *
     * The lock must already be held; ownership passes to the supervisor
     * on success (released immediately under the testing environment).
     * Returns false with the row marked failed when the greeting patch or
     * the dial fails — the caller releases the lock.
     */
    protected static function dial(VoiceAnnouncement $announcement, Lock $lock): bool
    {
        if (! VoicebipClient::setGreeting($announcement->message)) {
            $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);

            return false;
        }

        $callId = VoicebipClient::initiateCall($announcement->to_number);

        if (! $callId) {
            $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
            VoicebipClient::setGreeting(self::NOTICE);

            return false;
        }

        // The greeting guarantees delivery at answer, so mark it spoken
        // now; the call.completed webhook downgrades never-answered calls
        // (duration < 6s) back to failed and feeds the callback chain.
        $announcement->update([
            'call_id' => $callId,
            'status' => VoiceAnnouncement::STATUS_SPOKEN,
            'spoken_at' => now(),
            'attempt' => $announcement->attempt + 1,
            'next_attempt_at' => null,
        ]);

        // Supervisor: watch the live WS event stream, mark answered on
        // pickup, hang up when call.idle_silence proves the notice
        // finished, restore the default greeting, release the lock.
        // Registered on app()->terminating() so it runs after the
        // response is sent and the ping never waits. (There is no
        // dispatch_after_response() helper in this Laravel version —
        // using it throws a fatal Error.)
        if (app()->environment('testing')) {
            $lock->release();
        } else {
            $announcementId = $announcement->id;

            app()->terminating(function () use ($lock, $callId, $announcementId) {
                self::superviseCall($lock, $callId, $announcementId);
            });
        }

        return true;
    }

    /**
     * Dial a due callback for an unanswered ping (called by the
     * voice:dispatch-retries scheduler). Re-applies consent/window/phone
     * guards before dialing:
     *  - consent withdrawn / banned / phone gone / outside the window ->
     *    the chain cannot continue, so the pinger is told "unavailable"
     *    now (per the window rule: a callback that cannot be placed ends
     *    the chain immediately).
     *  - dial lock contended or the API hiccuped -> leave next_attempt_at
     *    untouched; the next scheduler tick tries again until expiry.
     */
    public static function retry(VoiceAnnouncement $announcement): void
    {
        $lock = null;

        try {
            if (! VoicebipClient::configured()) {
                return;
            }

            $fresh = VoiceAnnouncement::whereKey($announcement->id)
                ->whereNull('answered_at')
                ->whereNull('pinger_notified')
                ->where('next_attempt_at', '<=', now())
                ->first();

            if (! $fresh) {
                return;
            }

            $artisan = $fresh->user;

            if (! $artisan || $artisan->isBanned() || ! $artisan->hasAcceptedVoiceCalls()) {
                Log::info('Voicebip callback ended (consent/ban).', [
                    'announcement_id' => $fresh->id,
                ]);

                self::finalizeUnavailable($fresh);

                return;
            }

            $to = self::normalizeToE164($artisan->phone_number, $artisan->country_code);

            if ($to === null) {
                Log::info('Voicebip callback ended (unusable phone number).', [
                    'announcement_id' => $fresh->id,
                ]);

                self::finalizeUnavailable($fresh);

                return;
            }

            if (! self::withinCallWindow()) {
                Log::info('Voicebip callback ended (outside call window).', [
                    'announcement_id' => $fresh->id,
                ]);

                self::finalizeUnavailable($fresh);

                return;
            }

            $fresh->update(['to_number' => $to]);

            $lock = Cache::lock('voicebip:notice-dial', 150);

            try {
                $lock->block(3);
            } catch (LockTimeoutException) {
                Log::info('Voicebip callback deferred (another notice dial in flight).', [
                    'announcement_id' => $fresh->id,
                ]);

                return;
            }

            if (! self::dial($fresh, $lock)) {
                $lock->release();

                // next_attempt_at stays due — the next scheduler tick
                // retries until the chain expires.
            }
        } catch (\Throwable $e) {
            if ($lock instanceof Lock) {
                try {
                    $lock->release();
                } catch (\Throwable) {
                    // Lock may already be released or expired — TTL covers it.
                }
            }

            Log::error('Voicebip callback failed.', [
                'announcement_id' => $announcement->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The artisan picked up (WS greeting frame or call.completed with
     * enough connected time). Claim answered_at exactly once, cancel any
     * pending callback, and tell the pinger "available" immediately.
     * Safe to call from both the supervisor and the webhook — the atomic
     * claim decides the winner.
     */
    public static function markAnswered(VoiceAnnouncement $announcement): void
    {
        try {
            $claimed = VoiceAnnouncement::whereKey($announcement->id)
                ->whereNull('answered_at')
                ->whereNull('pinger_notified')
                ->update([
                    'answered_at' => now(),
                    'next_attempt_at' => null,
                ]);

            if ($claimed === 0) {
                return;
            }

            self::notifyPinger($announcement, PingAvailability::AVAILABLE);

            Log::info('Voicebip notice answered; pinger notified.', [
                'announcement_id' => $announcement->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Voicebip answered-mark failed.', [
                'announcement_id' => $announcement->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * One call attempt ended without pickup. On attempts 1-2: schedule
     * the next callback 3 minutes out (from call end). On attempt 3: the
     * chain is over — tell the pinger "unavailable, ping someone else".
     */
    public static function recordMissed(VoiceAnnouncement $announcement): void
    {
        try {
            $fresh = VoiceAnnouncement::whereKey($announcement->id)
                ->whereNull('answered_at')
                ->whereNull('pinger_notified')
                ->first();

            if (! $fresh) {
                return;
            }

            if ($fresh->attempt >= self::MAX_ATTEMPTS) {
                self::finalizeUnavailable($fresh);

                return;
            }

            VoiceAnnouncement::whereKey($fresh->id)
                ->whereNull('answered_at')
                ->whereNull('pinger_notified')
                ->update(['next_attempt_at' => now()->addMinutes(self::RETRY_AFTER_MINUTES)]);

            Log::info('Voicebip notice missed; callback scheduled.', [
                'announcement_id' => $fresh->id,
                'attempt' => $fresh->attempt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Voicebip miss-record failed.', [
                'announcement_id' => $announcement->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The chain is over without an answer (3rd miss, window/consent/
     * phone made further calls impossible, or expiry). Tell the pinger
     * exactly once that the artisan is unavailable.
     */
    public static function finalizeUnavailable(VoiceAnnouncement $announcement): void
    {
        try {
            $claimed = VoiceAnnouncement::whereKey($announcement->id)
                ->whereNull('answered_at')
                ->whereNull('pinger_notified')
                ->update([
                    'pinger_notified' => PingAvailability::UNAVAILABLE,
                    'pinger_notified_at' => now(),
                    'next_attempt_at' => null,
                ]);

            if ($claimed === 0) {
                return;
            }

            self::notifyPinger($announcement, PingAvailability::UNAVAILABLE);

            Log::info('Voicebip chain finalized; pinger told artisan unavailable.', [
                'announcement_id' => $announcement->id,
                'attempt' => $announcement->attempt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Voicebip chain finalize failed.', [
                'announcement_id' => $announcement->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deliver a PingAvailability notification (in-app + web push + email)
     * to the pinger. Fully isolated: notification failures never affect
     * the call flow; the claiming update above already guaranteed this
     * runs at most once per announcement.
     */
    protected static function notifyPinger(VoiceAnnouncement $announcement, string $mode): void
    {
        try {
            $engagement = $announcement->engagement;
            $pinger = $engagement?->user;

            if (! $engagement || ! $pinger) {
                return;
            }

            $pinger->notify(new PingAvailability($announcement->user, $engagement, $mode));
        } catch (\Throwable $e) {
            Log::error('Voicebip pinger notification failed.', [
                'announcement_id' => $announcement->id,
                'mode' => $mode,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Background supervisor for one notice call: open the live WebSocket
     * event stream (GET /v1/calls/{id}/stream — the SSE transcript stream
     * flushes nothing until call end, so it cannot drive a live hang-up),
     * then:
     *  - first call.agent_response frame = greeting TTS starting = proof
     *    of pickup (~1s after answer) -> markAnswered() tells the pinger
     *    "available" live and cancels the callback chain;
     *  - call.idle_silence after that (callee silent since the notice,
     *    +2s) -> verified DELETE hang-up, before any idle-prompt cycle
     *    matters; also caches "greeted" so the webhook's idle backup may
     *    hang up if this supervisor dies.
     *
     * Layers, outermost first: WS idle -> verified DELETE (teardown ~5s,
     * callee hears nothing because the prompt text is "."), webhook idle
     * backup, never-cut safety deadline (greeting frame + len/10 + 8s —
     * bounds a caller who keeps talking), max_duration 60s hard cap.
     * Unanswered calls settle via the call.completed frame (no DELETE
     * needed) and feed recordMissed() through the webhook. Always
     * restores the default greeting and releases the dial lock. Never
     * throws.
     */
    protected static function superviseCall(Lock $lock, string $callId, int $announcementId): void
    {
        $started = microtime(true);
        $ended = false;
        $announcement = VoiceAnnouncement::find($announcementId);
        $message = $announcement?->message ?? self::NOTICE;

        try {
            @set_time_limit(120);
            ignore_user_abort(true);

            $stream = VoicebipEventStream::open($callId);

            if ($stream === null) {
                // No live frames: timed hang-up from dial (ring allowance
                // + TTS schedule). The webhook idle hook may beat us to it
                // — endCall is idempotent for a call already dead.
                $wait = (20 + (int) ceil(strlen($message) / 14) + 3) - (microtime(true) - $started);

                if ($wait > 0) {
                    sleep((int) ceil($wait));
                }

                $ended = VoicebipClient::endCall($callId);

                Log::info('Voicebip notice hang-up result (no stream).', [
                    'call_id' => $callId,
                    'ended' => $ended,
                ]);
            } else {
                $deadline = $started + 75;
                $greetedAt = null;

                while (($event = $stream->read($deadline)) !== null) {
                    $type = (string) ($event['event_type'] ?? '');

                    if ($type === 'call.agent_response' && $greetedAt === null) {
                        // First agent frame = the greeting TTS starting =
                        // the artisan picked up: tell the pinger now.
                        $greetedAt = microtime(true);
                        Cache::put('voicebip:greeted:'.$callId, true, 300);

                        if ($announcement) {
                            self::markAnswered($announcement);
                        }

                        // Never-cut safety deadline: 10 chars/sec (observed
                        // ~15) + 8s buffer, so even an endlessly talking
                        // callee cannot keep the call open past the notice.
                        $deadline = min($deadline, $greetedAt + (int) ceil(strlen($message) / 10) + 8);
                    } elseif ($type === 'call.idle_silence' && $greetedAt !== null && ! $ended) {
                        Log::info('Voicebip notice finished; hanging up.', [
                            'call_id' => $callId,
                        ]);

                        $ended = VoicebipClient::endCall($callId);

                        Log::info('Voicebip notice hang-up result.', [
                            'call_id' => $callId,
                            'ended' => $ended,
                        ]);

                        // Linger briefly for the call.completed frame.
                        $deadline = microtime(true) + 12;
                    } elseif ($type === 'call.completed') {
                        $ended = true;

                        break;
                    }
                }

                $stream->close();

                if (! $ended) {
                    // Deadline, closed stream, or failed DELETE — best-
                    // effort hang-up; idempotent when already dead.
                    Log::warning('Voicebip notice supervisor ended without a hang-up frame; best-effort hang-up.', [
                        'call_id' => $callId,
                    ]);

                    VoicebipClient::endCall($callId);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Voicebip notice supervisor failed; best-effort hang-up.', [
                'call_id' => $callId,
                'exception' => $e->getMessage(),
            ]);

            VoicebipClient::endCall($callId);
        } finally {
            try {
                VoicebipClient::setGreeting(self::NOTICE);
            } catch (\Throwable) {
                // Best effort; a stale dynamic greeting only affects
                // inbound calls until the next notice re-patches it.
            }

            try {
                $lock->release();
            } catch (\Throwable) {
                // Already released or expired — TTL covers it.
            }
        }
    }

    /**
     * The exact sentence the agent reads out during the call — pinger
     * name + task, spoken as the greeting.
     */
    public static function announcementText(User $pinger, Engagement $engagement): string
    {
        $task = Str::limit(trim((string) ($engagement->title ?? '')), 80, '');

        $line = sprintf(
            '%s has just requested your services on Allsers.com',
            trim($pinger->name) ?: 'Someone',
        );

        if ($task !== '') {
            $line .= ' for: '.$task;
        }

        return $line
            .'. Please Log in to Allsers.com now to view the request and accept the job. Thank you.';
    }

    /**
     * Normalize a stored phone number to E.164. Returns null when the
     * number is missing, malformed, or from outside Voicebip's reach.
     * Nigerian local formats (08012345678, +2348012345678, 2348012345678)
     * are converted; anything else is returned only if already in
     * international (+ leading) form.
     */
    public static function normalizeToE164(?string $phone, ?string $countryCode = 'NG'): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D+/', '', $raw);

            return (strlen($digits) >= 10 && strlen($digits) <= 15) ? '+'.$digits : null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '' || ! in_array(strtoupper($countryCode ?: 'NG'), ['NG', ''], true)) {
            return null;
        }

        // Local Nigerian: 08012345678 / 7012345678 → +234...
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+234'.$digits;
        }

        if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
            return '+'.$digits;
        }

        return null;
    }

    /**
     * Outbound calls are only permitted inside the agent's call window
     * (default 08:00-20:00 Africa/Lagos, CBN rule). Calls outside it are
     * rejected by Voicebip, so we skip early and log instead.
     */
    public static function withinCallWindow(): bool
    {
        $config = config('services.voicebip');
        $tz = $config['timezone'] ?? 'Africa/Lagos';
        $start = (int) ($config['call_window_start'] ?? 8);
        $end = (int) ($config['call_window_end'] ?? 20);

        try {
            $now = now($tz);
        } catch (\Throwable) {
            $now = now('UTC');
        }

        $hour = (int) $now->format('G');

        return $hour >= $start && $hour < $end;
    }
}
