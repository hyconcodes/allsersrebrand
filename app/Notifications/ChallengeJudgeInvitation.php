<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ChallengeJudgeInvitation extends Notification
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
            ->title("Challenge Invitation!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body("You have been invited to judge the " . $this->challenge->title . " challenge.")
            ->data([
                'url' => route('challenges.show', $this->challenge->custom_link),
                'type' => 'challenge_invitation',
                'challenge_id' => $this->challenge->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to judge a challenge!')
            ->line('You have been cordially invited to be a judge for the challenge: ' . $this->challenge->title)
            ->action('View Challenge', route('challenges.show', $this->challenge->custom_link))
            ->line('Thank you for being a vital part of our community!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'challenge_id' => $this->challenge->id,
            'title' => $this->challenge->title,
            'message' => 'You have been invited to judge the ' . $this->challenge->title . ' challenge.',
            'link' => route('challenges.show', $this->challenge->custom_link),
            'type' => 'challenge_invitation'
        ];
    }
}
