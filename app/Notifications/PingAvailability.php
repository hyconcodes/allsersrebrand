<?php

namespace App\Notifications;

use App\Models\Engagement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells the pinger how the voice-notice chain for one ping ended:
 * AVAILABLE  — the artisan picked up a notice call (sent live, ~1s after
 *              answer); no further callbacks run for the ping.
 * UNAVAILABLE — all 3 call attempts went unanswered (or the chain could
 *              no longer continue: window over / consent withdrawn), so
 *              the pinger should ping someone else.
 *
 * The mode string doubles as the voice_announcements.pinger_notified
 * value, which every claiming update guards on for exactly-once delivery.
 */
class PingAvailability extends Notification
{
    public const AVAILABLE = 'available';

    public const UNAVAILABLE = 'unavailable';

    use Queueable;

    /** @var User|null */
    public $artisan;

    /** @var Engagement */
    public $engagement;

    /** @var string */
    public $mode;

    public function __construct(?User $artisan, Engagement $engagement, string $mode)
    {
        $this->artisan = $artisan;
        $this->engagement = $engagement;
        $this->mode = $mode;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Allsers: ping update')
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon.ico')
            ->body($this->pushBody())
            ->data([
                'url' => route('chat', $this->engagement->conversation_id),
                'type' => 'ping_availability',
                'mode' => $this->mode,
                'engagement_id' => $this->engagement->id,
                'artisan_id' => $this->engagement->artisan_id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $artisanName = $this->artisanName();
        $task = trim((string) ($this->engagement->title ?? ''));

        $mail = (new MailMessage)
            ->subject($this->mode === self::AVAILABLE
                ? "{$artisanName} is available for your ping"
                : "{$artisanName} did not answer your ping");

        if ($this->mode === self::AVAILABLE) {
            $mail->line("Good news — {$artisanName} picked up your call"
                .($task !== '' ? " about \"{$task}\"" : '').'.')
                ->line('They are available for the work — watch your chat for their reply.')
                ->action('Open Chat', route('chat', $this->engagement->conversation_id));
        } else {
            $mail->line("We called {$artisanName} 3 times"
                .($task !== '' ? " about \"{$task}\"" : '').' but there was no answer.')
                ->line('They may be unavailable right now — you can ping someone else.')
                ->action('Open Chat', route('chat', $this->engagement->conversation_id));
        }

        return $mail->line('Thank you for using Allsers.com.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ping_availability',
            'mode' => $this->mode,
            'message' => $this->pushBody(),
            'engagement_id' => $this->engagement->id,
            'artisan_id' => $this->engagement->artisan_id,
            'artisan_name' => $this->artisanName(),
            'conversation_id' => $this->engagement->conversation_id,
            'url' => route('chat', $this->engagement->conversation_id),
        ];
    }

    private function pushBody(): string
    {
        $name = $this->artisanName();

        return $this->mode === self::AVAILABLE
            ? "{$name} picked up your ping — they are available for the work."
            : "{$name} did not answer after 3 call attempts — they may be unavailable. You can ping someone else.";
    }

    private function artisanName(): string
    {
        return trim((string) ($this->artisan?->name ?? '')) ?: 'The artisan you pinged';
    }
}
