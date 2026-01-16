<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceInquiry extends Notification
{
    use Queueable;

    protected $sender;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender)
    {
        $this->sender = $sender;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable->onesignal_player_id) {
            $this->sendPushNotification($notifiable);
        }

        return ['database'];
    }

    protected function sendPushNotification($notifiable)
    {
        $oneSignal = app(OneSignalService::class);
        $oneSignal->sendToUser(
            $notifiable->onesignal_player_id,
            "New Service Inquiry!",
            $this->sender->name . " is interested in your services and sent you a ping!",
            route('notifications'),
            [
                'type' => 'inquiry',
                'sender_id' => $this->sender->id,
            ]
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
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