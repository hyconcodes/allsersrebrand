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
     * Greeting used when no notice is pending (manual/inbound calls).
     */
    public const DEFAULT_GREETING = 'Please hold for an automated notice from Allsers.';

    /**
     * Place a one-way voice notice call to the artisan when someone pings
     * them for services: "{name} has requested your services on Allsers —
     * log in and accept the job."
     *
     * The notice is baked into the agent's greeting right before dialing
     * (serialized by a lock so concurrent pings can never cross-deliver),
     * so the callee hears it the moment they answer — no conversational
     * turns, no idle prompts. A background supervisor waits for the
     * greeting to finish, hangs the call up, restores the default greeting
     * and releases the lock.
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
            // announcement is still pending (spam pings in quick succession).
            $hasFreshPending = VoiceAnnouncement::where('to_number', $to)
                ->where('status', VoiceAnnouncement::STATUS_PENDING)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->exists();

            if ($hasFreshPending) {
                return;
            }

            $message = self::announcementText($pinger, $engagement);

            // The greeting is swapped per call, so only one notice dial may
            // be in flight at a time (the lock is held until the background
            // supervisor finishes with the call).
            $lock = Cache::lock('voicebip:notice-dial', 90);

            try {
                $lock->block(3);
            } catch (LockTimeoutException) {
                Log::info('Voicebip notice skipped (another notice dial in flight).', [
                    'artisan_id' => $artisan->id,
                ]);

                return;
            }

            $announcement = VoiceAnnouncement::create([
                'user_id' => $artisan->id,
                'engagement_id' => $engagement->id,
                'to_number' => $to,
                'message' => $message,
                'status' => VoiceAnnouncement::STATUS_PENDING,
                'expires_at' => now()->addMinutes(5),
            ]);

            if (! VoicebipClient::setGreeting($message)) {
                $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
                $lock->release();

                return;
            }

            $callId = VoicebipClient::initiateCall($to);

            if (! $callId) {
                $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
                VoicebipClient::setGreeting(self::DEFAULT_GREETING);
                $lock->release();

                return;
            }

            $announcement->update(['call_id' => $callId]);

            // Supervisor: wait until answered (greeting consumed), let the
            // notice finish playing, hang up before any idle prompt or
            // conversational turn can happen, restore the greeting, release
            // the lock. Registered on app()->terminating() so it runs after
            // the response is sent and the ping never waits. (Note: there is
            // no dispatch_after_response() helper in this Laravel version —
            // using it here threw a fatal Error and the call never ended.)
            if (app()->environment('testing')) {
                $lock->release();
            } else {
                app()->terminating(function () use ($lock, $announcement, $callId, $message) {
                    self::superviseCall($lock, $announcement, $callId, $message);
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
     * Background supervisor for one notice call: poll until answered,
     * mark the announcement spoken, wait out the TTS playback, end the
     * call, restore the default greeting, release the dial lock.
     * Never throws; the lock TTL is the final safety net.
     */
    protected static function superviseCall(Lock $lock, VoiceAnnouncement $announcement, string $callId, string $message): void
    {
        @set_time_limit(120);
        ignore_user_abort(true);

        try {
            $answered = false;
            $terminal = null;
            $deadline = microtime(true) + 55;

            while (microtime(true) < $deadline) {
                $call = VoicebipClient::getCall($callId);
                $status = $call['status'] ?? null;

                if ($status === 'in_progress') {
                    $answered = true;
                    break;
                }

                if (in_array($status, ['completed', 'failed', 'no_answer', 'busy'], true)) {
                    $terminal = $call;
                    break;
                }

                usleep(1_000_000);
            }

            if ($answered) {
                // The greeting (which IS the notice) plays on answer.
                if ($announcement->status === VoiceAnnouncement::STATUS_PENDING) {
                    $announcement->update([
                        'status' => VoiceAnnouncement::STATUS_SPOKEN,
                        'spoken_at' => now(),
                    ]);
                }

                // Wait for the TTS to finish (≈14 chars/sec + margin),
                // then hang up cleanly — well before the 30s idle prompt.
                $seconds = min(45, max(5, (int) ceil(strlen($message) / 14) + 2));

                Log::info('Voicebip notice answered; ending call after TTS.', [
                    'call_id' => $callId,
                    'tts_seconds' => $seconds,
                ]);

                sleep($seconds);

                $ended = VoicebipClient::endCall($callId);

                Log::info('Voicebip notice hang-up result.', [
                    'call_id' => $callId,
                    'ended' => $ended,
                ]);
            } elseif ($terminal !== null) {
                // Ended before our poll saw it answered (screening hook or
                // a turn) — a connected duration of 6s+ means the notice
                // had time to play; shorter means it never did.
                if ($announcement->status === VoiceAnnouncement::STATUS_PENDING) {
                    $duration = (int) ($terminal['duration_seconds'] ?? 0);

                    $announcement->update([
                        'status' => $duration >= 6 ? VoiceAnnouncement::STATUS_SPOKEN : VoiceAnnouncement::STATUS_FAILED,
                        'spoken_at' => $duration >= 6 ? now() : null,
                    ]);
                }
            } else {
                // Could not read the call state at all (API unreachable for
                // the whole window) — best-effort hang-up so the callee is
                // never left waiting on the line.
                Log::warning('Voicebip notice supervisor could not read call state; best-effort end.', [
                    'call_id' => $callId,
                ]);

                VoicebipClient::endCall($callId);
            }
        } catch (\Throwable $e) {
            Log::error('Voicebip notice supervisor failed.', [
                'call_id' => $callId,
                'exception' => $e->getMessage(),
            ]);
        } finally {
            try {
                VoicebipClient::setGreeting(self::DEFAULT_GREETING);
            } catch (\Throwable) {
                // Best effort; a stale greeting only affects manual calls.
            }

            try {
                $lock->release();
            } catch (\Throwable) {
                // Already released or expired — TTL covers it.
            }
        }
    }

    /**
     * The exact sentence the agent reads out during the call.
     */
    public static function announcementText(User $pinger, Engagement $engagement): string
    {
        $task = Str::limit(trim((string) ($engagement->title ?? '')), 80, '');

        $line = sprintf(
            '%s has just requested your services on Allsers',
            trim($pinger->name) ?: 'Someone',
        );

        if ($task !== '') {
            $line .= ' for: '.$task;
        }

        return $line
            .'. Log in to Allsers now to view the request and accept the job. Thank you.';
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
