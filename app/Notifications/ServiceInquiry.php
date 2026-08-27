<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ServiceInquiry extends Notification
{
    use Queueable;

    protected $sender;

    public function __construct(User $sender)
    {
        $this->sender = $sender;
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("New Service Inquiry!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body($this->sender->name . " is interested in your services and sent you a ping!")
            ->data([
                'url' => route('notifications'),
                'type' => 'inquiry',
                'sender_id' => $this->sender->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'message' => 'is interested in your services and sent you a ping!',
            'type' => 'inquiry',
        ];
    }
}
