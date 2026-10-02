<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $commentExcerpt;
    public $reason;

    public function __construct($user, string $commentExcerpt, ?string $reason = null)
    {
        $this->user = $user;
        $this->commentExcerpt = $commentExcerpt;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your comment was removed — Allsers',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comment-deleted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
