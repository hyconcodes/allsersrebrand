<?php

namespace App\Support;

use App\Models\Engagement;
use App\Models\User;
use App\Models\VoiceAnnouncement;
use App\Services\VoicebipClient;
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
     * Place a one-way voice notice call to the artisan when someone pings
     * them for services. The per-call dynamic message (pinger + task) is
     * PATCHed into the agent's greeting right before dialing — Voicebip
     * reads greeting_text when the call CONNECTS, so the callee hears the
     * dynamic notice as the greeting itself. A dial lock serializes
     * concurrent notices so one ping's artisan can never hear another's
     * name/task. A background supervisor watches the SSE transcript
     * stream, hangs up when the greeting finishes, restores the default
     * greeting and releases the lock — no turns, no idle prompts.
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
            // announcement exists (spam pings in quick succession). Rows are
            // created before dialing, so both states count.
            $hasFresh = VoiceAnnouncement::where('to_number', $to)
                ->whereIn('status', [VoiceAnnouncement::STATUS_PENDING, VoiceAnnouncement::STATUS_SPOKEN])
                ->where('created_at', '>=', now()->subMinutes(15))
                ->exists();

            if ($hasFresh) {
                return;
            }

            $message = self::announcementText($pinger, $engagement);

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

            // Audit row first, then bake the dynamic message into the
            // greeting, then dial.
            $announcement = VoiceAnnouncement::create([
                'user_id' => $artisan->id,
                'engagement_id' => $engagement->id,
                'to_number' => $to,
                'message' => $message,
                'status' => VoiceAnnouncement::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
            ]);

            if (! VoicebipClient::setGreeting($message)) {
                $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
                $lock->release();

                return;
            }

            $callId = VoicebipClient::initiateCall($to);

            if (! $callId) {
                $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
                VoicebipClient::setGreeting(self::NOTICE);
                $lock->release();

                return;
            }

            // The greeting guarantees delivery at answer, so mark it
            // spoken now; the call.completed webhook downgrades never-
            // answered calls (duration < 6s) back to failed.
            $announcement->update([
                'call_id' => $callId,
                'status' => VoiceAnnouncement::STATUS_SPOKEN,
                'spoken_at' => now(),
            ]);

            // Supervisor: watch the SSE transcript for the agent frame (the
            // greeting TTS finishing), hang up, restore the default
            // greeting, release the lock. Registered on app()->terminating()
            // so it runs after the response is sent and the ping never
            // waits. (There is no dispatch_after_response() helper in this
            // Laravel version — using it throws a fatal Error.)
            if (app()->environment('testing')) {
                $lock->release();
            } else {
                app()->terminating(function () use ($lock, $callId, $message) {
                    self::superviseCall($lock, $callId, $message);
                });
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
     * Background supervisor for one notice call: open the SSE transcript
     * stream, wait for the agent frame (the greeting just finished), then
     * hang up — so the call drops ~1s after "Thank you." instead of the
     * callee pressing end. Fallbacks: deadline hang-up for unanswered
     * calls, timed hang-up when the stream is unavailable. Always restores
     * the default greeting and releases the dial lock. Never throws.
     *
     * Why SSE: GET /v1/calls/{id} status freezes at "initiated" during
     * live calls (never rings/in_progress), call.initiated fires at dial
     * (not answer), and there is no answer event — the agent transcript
     * frame is the only reliable "greeting finished" signal.
     */
    protected static function superviseCall(Lock $lock, string $callId, string $message): void
    {
        $started = microtime(true);
        $ended = false;

        try {
            @set_time_limit(120);
            ignore_user_abort(true);

            $client = new \GuzzleHttp\Client([
                // NO total transfer timeout: `timeout` is a whole-request
                // cap and the SSE stream legitimately lives ~25s until the
                // greeting frame — a 10s cap killed it on prod (cURL 28).
                'timeout' => 0,
                'connect_timeout' => 5,
            ]);

            $response = $client->request('GET', rtrim(config('services.voicebip.endpoint'), '/').'/calls/'.$callId.'/transcript/stream', [
                'query' => ['token' => config('services.voicebip.api_key')],
                'stream' => true,
                // The stream is silent for the whole ring + greeting TTS
                // (~35s+ worst case) before the first frame — a stall
                // limit below that would abort mid-ring.
                'read_timeout' => 60,
            ]);

            $stream = $response->getBody();
            $deadline = microtime(true) + 90;
            $buffer = '';

            while (! $stream->eof() && microtime(true) < $deadline) {
                $chunk = $stream->read(1024);

                if ($chunk === '' || $chunk === null) {
                    continue;
                }

                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $frame = json_decode(trim(substr($line, 5)), true);

                    if (! is_array($frame)) {
                        continue;
                    }

                    if (($frame['role'] ?? null) === 'agent') {
                        Log::info('Voicebip notice greeting finished; hanging up.', [
                            'call_id' => $callId,
                        ]);

                        sleep(1);

                        $ended = VoicebipClient::endCall($callId);

                        Log::info('Voicebip notice hang-up result.', [
                            'call_id' => $callId,
                            'ended' => $ended,
                        ]);

                        break 2;
                    }
                }
            }

            if (! $ended) {
                // Stream closed (call already over) or deadline hit (never
                // answered / no frame) — best-effort end; idempotent when
                // the call is already gone.
                Log::warning('Voicebip notice supervisor ended without greeting frame; best-effort hang-up.', [
                    'call_id' => $callId,
                ]);

                VoicebipClient::endCall($callId);
            }

            $stream->close();
        } catch (\Throwable $e) {
            Log::error('Voicebip notice supervisor failed; timed fallback hang-up.', [
                'call_id' => $callId,
                'exception' => $e->getMessage(),
            ]);

            // Stream unavailable: ring allowance + TTS schedule from dial,
            // so the call still ends shortly after the notice would have.
            $fallbackAt = $started + 20 + (int) ceil(strlen($message) / 14) + 3;
            $wait = $fallbackAt - microtime(true);

            if ($wait > 0) {
                sleep((int) ceil($wait));
            }

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
