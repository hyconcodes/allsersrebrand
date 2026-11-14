<?php

namespace App\Support;

use App\Models\Engagement;
use App\Models\User;
use App\Models\VoiceAnnouncement;
use App\Services\VoicebipClient;
use Illuminate\Support\Facades\Log;

class VoiceCallAlert
{
    /**
     * The static notice spoken as the agent's greeting on every notice
     * call. Configured once on the agent (greeting_text) — the code never
     * swaps it, so concurrent calls can never cross-deliver.
     */
    public const NOTICE = 'This is an automated notice from Allsers.com, someone has just requested your services on Allsers.com. Please Log in to Allsers.com now to view the request and accept the job. Thank you.';

    /**
     * Place a one-way voice notice call to the artisan when someone pings
     * them for services. The agent's static greeting IS the announcement;
     * a background supervisor watches the SSE transcript stream and hangs
     * up the moment the greeting finishes — no turns, no idle prompts, no
     * conversational anything.
     *
     * Fully isolated like PingAlert::send(): every guard failure or API
     * error is swallowed and logged so the ping flow is never affected.
     * Outside the CBN call window (08:00-20:00 Africa/Lagos) the call is
     * skipped and logged — the ping still goes through by email/notification.
     */
    public static function send(User $pinger, User $artisan, Engagement $engagement): void
    {
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

            // Audit row first (closes the double-ping race), then dial.
            $announcement = VoiceAnnouncement::create([
                'user_id' => $artisan->id,
                'engagement_id' => $engagement->id,
                'to_number' => $to,
                'message' => self::NOTICE,
                'status' => VoiceAnnouncement::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
            ]);

            $callId = VoicebipClient::initiateCall($to);

            if (! $callId) {
                $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);

                return;
            }

            // The static greeting guarantees delivery at answer, so mark it
            // spoken now; the call.completed webhook downgrades never-
            // answered calls (duration < 6s) back to failed.
            $announcement->update([
                'call_id' => $callId,
                'status' => VoiceAnnouncement::STATUS_SPOKEN,
                'spoken_at' => now(),
            ]);

            // Supervisor: watch the SSE transcript for the agent frame (the
            // greeting TTS finishing) and hang up. Registered on
            // app()->terminating() so it runs after the response is sent
            // and the ping never waits. (There is no dispatch_after_response()
            // helper in this Laravel version — using it throws a fatal Error.)
            if (! app()->environment('testing')) {
                app()->terminating(function () use ($callId) {
                    self::superviseCall($callId);
                });
            }
        } catch (\Throwable $e) {
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
     * calls, timed hang-up when the stream is unavailable. Never throws.
     *
     * Why SSE: GET /v1/calls/{id} status freezes at "initiated" during
     * live calls (never rings/in_progress), call.initiated fires at dial
     * (not answer), and there is no answer event — the agent transcript
     * frame is the only reliable "greeting finished" signal.
     */
    protected static function superviseCall(string $callId): void
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
            $fallbackAt = $started + 20 + (int) ceil(strlen(self::NOTICE) / 14) + 3;
            $wait = $fallbackAt - microtime(true);

            if ($wait > 0) {
                sleep((int) ceil($wait));
            }

            VoicebipClient::endCall($callId);
        }
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
