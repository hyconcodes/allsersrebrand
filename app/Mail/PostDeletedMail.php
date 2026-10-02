<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $postExcerpt;
    public $reason;

    public function __construct($user, string $postExcerpt, ?string $reason = null)
    {
        $this->user = $user;
        $this->postExcerpt = $postExcerpt;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your post was removed — Allsers',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.post-deleted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
