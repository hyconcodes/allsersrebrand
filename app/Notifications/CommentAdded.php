<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CommentAdded extends Notification
{
    use Queueable;

    protected $post;
    protected $commenter;
    protected $comment;

    public function __construct(Post $post, User $commenter, Comment $comment)
    {
        $this->post = $post;
        $this->commenter = $commenter;
        $this->comment = $comment;
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("New Comment!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body($this->commenter->name . " commented on your post")
            ->data([
                'url' => route('posts.show', $this->post->post_id),
                'type' => 'comment',
                'post_id' => $this->post->id,
                'commenter_id' => $this->commenter->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'commenter_id' => $this->commenter->id,
            'commenter_name' => $this->commenter->name,
            'comment_id' => $this->comment->id,
            'message' => 'commented on your post',
            'type' => 'comment',
        ];
    }
}
