<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Allsers password reset code')
            ->greeting('Hello '.($notifiable->name ?? 'there').'!')
            ->line('Use the code below to choose a new password for your account.')
            ->line('')
            ->line('Reset code: '.$this->code)
            ->line('This code expires in 15 minutes.')
            ->line('Enter this code on the reset page to complete your password change.')
            ->action('Reset My Password', route('password.code', ['email' => $notifiable->email]));
    }
}
