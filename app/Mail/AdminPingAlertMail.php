<?php

namespace App\Mail;

use App\Models\Engagement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPingAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $pinger;

    public User $artisan;

    public Engagement $engagement;

    /**
     * Admin-side copy of a ping: shows BOTH parties' business and contact
     * info so an admin can reach out and keep the job moving.
     */
    public function __construct(User $pinger, User $artisan, Engagement $engagement)
    {
        $this->pinger = $pinger;
        $this->artisan = $artisan;
        $this->engagement = $engagement;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Ping on Allsers: {$this->pinger->name} → {$this->artisan->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ping-alert-admin',
        );
    }
}
