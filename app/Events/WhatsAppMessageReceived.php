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
        $teamIds = [$this->message->team_id];

        if ($this->message->whatsapp_number_id) {
            $this->message->loadMissing('whatsappNumber.teams');

            $teamIds = $this->message->whatsappNumber?->teams()
                ->pluck('teams.id')
                ->all() ?? $teamIds;
        }

        $teamIds = array_values(array_unique(array_filter(array_map('intval', $teamIds))));

        if ($teamIds === []) {
            $teamIds = [$this->message->team_id];
        }

        $channels = [];

        foreach ($teamIds as $teamId) {
            $channels[] = new PrivateChannel('team.' . $teamId . '.messages');
        }

        $channels[] = new PrivateChannel('customer.' . $this->message->customer_id . '.messages');

        return $channels;
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