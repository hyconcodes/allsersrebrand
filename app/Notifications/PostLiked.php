<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PostLiked extends Notification
{
    use Queueable;

    protected $post;
    protected $liker;

    public function __construct(Post $post, User $liker)
    {
        $this->post = $post;
        $this->liker = $liker;
    }

    public function via(object $notifiable): array
    {
        // OneSignal disabled — use native Web Push
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("New Like!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body($this->liker->name . " liked your post")
            ->data([
                'url' => route('posts.show', $this->post->post_id),
                'type' => 'like',
                'post_id' => $this->post->id,
                'liker_id' => $this->liker->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'liker_id' => $this->liker->id,
            'liker_name' => $this->liker->name,
            'message' => 'liked your post',
            'type' => 'like',
        ];
    }
}
