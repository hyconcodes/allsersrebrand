<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewReply extends Notification
{
    use Queueable;

    protected $comment;
    protected $replier;
    protected $reply;

    public function __construct(Comment $comment, User $replier, Comment $reply)
    {
        $this->comment = $comment;
        $this->replier = $replier;
        $this->reply = $reply;
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("New Reply!")
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body($this->replier->name . " replied to your comment")
            ->data([
                'url' => route('posts.show', $this->comment->post_id),
                'type' => 'reply',
                'post_id' => $this->comment->post_id,
                'replier_id' => $this->replier->id,
                'comment_id' => $this->comment->id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'replier_id' => $this->replier->id,
            'replier_name' => $this->replier->name,
            'reply_id' => $this->reply->id,
            'post_id' => $this->comment->post_id,
            'message' => 'replied to your comment',
            'type' => 'reply',
        ];
    }
}
