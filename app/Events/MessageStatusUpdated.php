<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcastNow
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
        $customer = $this->message->customer;

        $teamIds = collect([
            $this->message->team_id,
            $customer?->team_id,
            $customer?->assignedTo?->team_id,
            $customer?->oldOwner?->team_id,
        ])
            ->filter()
            ->unique()
            ->values();

        return $teamIds
            ->map(function ($teamId) {
                return new PrivateChannel(
                    'whatsapp.team.' . $teamId
                );
            })
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'message.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toArray(),
        ];
    }
}