<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sender;
    public $recipient;
    public $messageText;

    /**
     * Create a new message instance.
     */
    public function __construct(User $sender, User $recipient)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;

        $messages = [
            "Hi {$recipient->name}! Someone is absolutely loving your work on Allsers and wants to chat about your services. Head back to the app to connect and catch this opportunity!",
            "Great news, {$recipient->name}! A potential client just pinged you on Allsers. They're interested in your professional skills. Don't keep them waiting—check your messages now!",
            "Hello {$recipient->name}! Your talent is getting noticed. {$sender->name} just reached out regarding your services. Jump back onto Allsers to start the conversation.",
            "Hey {$recipient->name}, looks like a new project might be heading your way! Someone is interested in hiring you. Log in to Allsers to see the details and secure the deal.",
            "Wonderful day, {$recipient->name}! You've got a new inquiry on Allsers for your amazing craftsmanship. Let's get to work—head over to the dashboard to chat with them!"
        ];

        $this->messageText = $messages[array_rand($messages)];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Service Inquiry on Allsers!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.service-inquiry',
        );
    }
}
