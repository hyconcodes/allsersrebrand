<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewReply extends Notification
{
    use Queueable;

    protected $comment;
    protected $replier;
    protected $reply;

    /**
     * Create a new notification instance.
     */
    public function __construct(Comment $comment, User $replier, Comment $reply)
    {
        $this->comment = $comment;
        $this->replier = $replier;
        $this->reply = $reply;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
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
