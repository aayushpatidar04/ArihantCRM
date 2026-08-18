<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewInboundMessage implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Message $message
    ) {
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
        return 'message.received';
    }

    public function broadcastWith(): array
    {
        $this->message->load([
            'customer',
            'document',
            'sentBy:id,name',
            'team:id,name',
            'whatsappNumber:id,phone_number,display_phone_number',
        ]);

        return [
            'message' => $this->message,
        ];
    }
}