<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ChallengeWinnerNotification extends Notification
{
    use Queueable;

    public $challenge;

    public function __construct($challenge)
    {
        $this->challenge = $challenge;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("Congratulations! You Won!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body("We are thrilled to announce that you have been selected as the winner of: " . $this->challenge->title)
            ->data([
                'url' => route('artisan.profile', $notifiable->username ?? ''),
                'type' => 'challenge_winner',
                'challenge_id' => $this->challenge->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Congratulations! You won the challenge!')
            ->line('We are thrilled to announce that you have been selected as the winner of: ' . $this->challenge->title)
            ->line('A unique badge has been added to your profile.')
            ->action('View Your Profile', route('artisan.profile', auth()->user()->username ?? ''))
            ->line('Keep up the amazing work!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'challenge_id' => $this->challenge->id,
            'title' => 'Challenge Winner!',
            'message' => 'Congratulations! You won the ' . $this->challenge->title . ' challenge.',
            'link' => route('artisan.profile', auth()->user()->username ?? ''),
            'type' => 'challenge_winner'
        ];
    }
}
