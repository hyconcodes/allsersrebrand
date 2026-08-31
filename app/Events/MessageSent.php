<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing(['user', 'conversation.users']);
    }

    public function broadcastOn(): array
    {
        $channels = [];

        $conversation = $this->message->conversation;
        if ($conversation) {
            foreach ($conversation->users as $user) {
                if ($user->id !== $this->message->user_id) {
                    $channels[] = new PrivateChannel('user.' . $user->id);
                }
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->user;
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->user_id,
            'sender_name' => $sender?->name ?? 'Someone',
            'sender_avatar' => $sender?->profile_picture_url,
            'content' => $this->message->content ?? '',
            'image_path' => $this->message->image_path,
            'document_path' => $this->message->document_path,
            'type' => $this->message->type ?? 'text',
            'url' => route('chat', $this->message->conversation_id),
        ];
    }
}
