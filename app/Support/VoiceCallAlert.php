<?php

namespace App\Support;

use App\Models\Engagement;
use App\Models\User;
use App\Models\VoiceAnnouncement;
use App\Services\VoicebipClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoiceCallAlert
{
    /**
     * Place a one-way voice notice call to the artisan when someone pings
     * them for services: "{name} has requested your services on Allsers —
     * log in and accept the job."
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
            // announcement is still pending (spam pings in quick succession).
            $hasFreshPending = VoiceAnnouncement::where('to_number', $to)
                ->where('status', VoiceAnnouncement::STATUS_PENDING)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->exists();

            if ($hasFreshPending) {
                return;
            }

            $message = self::announcementText($pinger, $engagement);

            $announcement = VoiceAnnouncement::create([
                'user_id' => $artisan->id,
                'engagement_id' => $engagement->id,
                'to_number' => $to,
                'message' => $message,
                'status' => VoiceAnnouncement::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
            ]);

            $callId = VoicebipClient::initiateCall($to);

            if ($callId) {
                $announcement->update(['call_id' => $callId]);
            } else {
                $announcement->update(['status' => VoiceAnnouncement::STATUS_FAILED]);
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
