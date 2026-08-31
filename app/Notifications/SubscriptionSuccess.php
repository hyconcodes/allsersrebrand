<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SubscriptionSuccess extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('You\'re all set! 🎉')
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body('You will now receive real-time notifications for messages and inquiries.')
            ->data([
                'url' => route('notifications'),
                'type' => 'subscription_success',
            ])
            ->options(['TTL' => 86400]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Push notifications enabled successfully!',
            'type' => 'subscription_success',
        ];
    }
}
