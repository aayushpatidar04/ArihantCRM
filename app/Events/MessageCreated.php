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

        return array_map(
            fn (int $teamId) => new PrivateChannel('whatsapp.team.' . $teamId),
            $teamIds
        );
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