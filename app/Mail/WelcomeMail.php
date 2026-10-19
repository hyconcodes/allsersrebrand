<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public bool $isArtisan;

    /**
     * Whether the artisan should see the "complete your business info" section.
     */
    public bool $showBusinessPrompt;

    /**
     * profileCompletion() data (used by the artisan template).
     *
     * @var array<string, mixed>
     */
    public array $completion;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->isArtisan = $user->isArtisan();
        $this->completion = $user->profileCompletion();
        $this->showBusinessPrompt = $this->isArtisan && ! ($this->completion['is_complete'] ?? false);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Allsers, '.$this->user->name.'!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->isArtisan ? 'emails.welcome-artisan' : 'emails.welcome-guest',
        );
    }
}
