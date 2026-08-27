<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class UserTagged extends Notification
{
    public $post;
    public $tagger;

    public function __construct(Post $post, User $tagger)
    {
        $this->post = $post;
        $this->tagger = $tagger;
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("You were Tagged!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body($this->tagger->name . " tagged you in a post")
            ->data([
                'url' => route('posts.show', $this->post->post_id),
                'type' => 'user_tagged',
                'post_id' => $this->post->id,
                'tagger_id' => $this->tagger->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_tagged',
            'message' => 'tagged you in a post.',
            'post_id' => $this->post->id,
            'tagger_id' => $this->tagger->id,
            'tagger_name' => $this->tagger->name,
            'tagger_avatar' => $this->tagger->profile_picture_url,
            'link' => route('artisan.profile', $this->post->user),
        ];
    }
}
