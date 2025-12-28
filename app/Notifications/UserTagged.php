<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserTagged extends Notification
{

    public $post;
    public $tagger;

    /**
     * Create a new notification instance.
     */
    public function __construct(Post $post, User $tagger)
    {
        $this->post = $post;
        $this->tagger = $tagger;
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
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
