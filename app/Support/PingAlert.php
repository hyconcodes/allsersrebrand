<?php

namespace App\Support;

use App\Mail\AdminPingAlertMail;
use App\Models\Engagement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PingAlert
{
    /**
     * Send the admin-side copy of a ping to every registered admin
     * ping-alert email. Fully isolated: any failure is swallowed so the
     * users' ping flow is never affected.
     */
    public static function send(User $pinger, User $artisan, Engagement $engagement): void
    {
        $recipients = SystemSetting::pingAlertEmailAddresses();

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->queue(new AdminPingAlertMail($pinger, $artisan, $engagement));
            } catch (\Throwable $e) {
                Log::error('Could not queue admin ping alert.', [
                    'to' => $email,
                    'engagement_id' => $engagement->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
