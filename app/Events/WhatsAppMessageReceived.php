<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Message $message
    ) {
        $this->message->load([
            'sentBy:id,name',
            'team:id,name',
            'whatsappNumber:id,phone_number,display_phone_number',
            'document',
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'team.' . $this->message->team_id . '.messages'
            ),

            new PrivateChannel(
                'customer.' . $this->message->customer_id . '.messages'
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toArray(),
        ];
    }
}