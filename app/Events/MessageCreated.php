<?php

namespace App\Events;

use App\Models\Message;
use App\Services\MessageSenderContextService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Message $message
    ) {
        $this->message->loadMissing([
            'document',
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'whatsapp.team.' . $this->message->team_id
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        $messageData = $this->message->toArray();

        /*
        |--------------------------------------------------------------------------
        | Sender context
        |--------------------------------------------------------------------------
        */

        if ($this->message->direction === 'outbound') {
            $messageData['sender_context'] =
                app(MessageSenderContextService::class)
                    ->getContext(
                        $this->message->customer,
                        $this->message->sent_by
                    );
        } else {
            $messageData['sender_context'] = [
                'type' => null,
                'name' => null,
                'role' => null,
            ];
        }

        return [
            'message' => $messageData,
        ];
    }
}