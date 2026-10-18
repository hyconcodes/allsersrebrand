<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return parent::toMail($notifiable)->mailer('mailtrap');
    }
}
