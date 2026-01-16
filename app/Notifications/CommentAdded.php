<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentAdded extends Notification
{
    use Queueable;

    protected $post;
    protected $commenter;
    protected $comment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Post $post, User $commenter, Comment $comment)
    {
        $this->post = $post;
        $this->commenter = $commenter;
        $this->comment = $comment;
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
            "New Comment!",
            $this->commenter->name . " commented on your post",
            route('posts.show', $this->post->post_id),
            [
                'type' => 'comment',
                'post_id' => $this->post->id,
                'commenter_id' => $this->commenter->id,
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
            'post_id' => $this->post->id,
            'commenter_id' => $this->commenter->id,
            'commenter_name' => $this->commenter->name,
            'comment_id' => $this->comment->id,
            'message' => 'commented on your post',
            'type' => 'comment',
        ];
    }
}
