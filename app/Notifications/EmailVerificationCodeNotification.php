<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
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
            ->subject('Your Allsers email verification code')
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('Use the verification code below to confirm your email address.')
            ->line('')
            ->line('Verification code: ' . $this->code)
            ->line('This code expires in 15 minutes.')
            ->line('Enter this code on the verification page to complete your sign up.')
            ->action('Verify My Email', url('/email/verify'));
    }
}
