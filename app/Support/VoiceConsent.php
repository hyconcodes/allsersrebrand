<?php

namespace App\Support;

use App\Mail\VoiceNoticeConsentMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VoiceConsent
{
    /**
     * Persist a voice-call consent decision and send the confirmation
     * email on the first transition to "accepted".
     *
     * Shared by the profile settings section and the on-login consent
     * modal so both behave identically. Never throws — a failed email is
     * logged, never surfaces to the user.
     */
    public static function save(User $user, string $consent, string $language = 'en'): void
    {
        $newlyAccepted = $user->voice_call_consent !== 'accepted' && $consent === 'accepted';

        $user->fill([
            'voice_call_language' => $language,
            'voice_call_consent' => $consent,
            'voice_call_consent_at' => now(),
        ])->save();

        if ($newlyAccepted) {
            try {
                Mail::to($user->email)->send(new VoiceNoticeConsentMail($user));
            } catch (\Throwable $e) {
                Log::error('Voice notice consent email failed.', [
                    'user_id' => $user->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
