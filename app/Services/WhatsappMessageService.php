<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappNumber;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WhatsappMessageService
{
    public function __construct(
        protected MetaWhatsappService $metaWhatsappService
    ) {
    }

    public function canSendFreeform(Customer $customer, WhatsappNumber $whatsappNumber): bool {
        $lastInbound = $customer->messages()
            ->where(
                'whatsapp_number_id',
                $whatsappNumber->id
            )
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();

        if (!$lastInbound) {
            return false;
        }

        return $lastInbound->created_at
            ->greaterThanOrEqualTo(
                now()->subHours(24)
            );
    }

    public function sendText(Customer $customer, Team $team, WhatsappNumber $whatsappNumber, User $user, string $body): Message {
        if (
            !$this->canSendFreeform(
                $customer,
                $whatsappNumber
            )
        ) {
            throw new RuntimeException(
                'The 24-hour WhatsApp customer service window is closed. Please use a template.'
            );
        }

        $message = Message::create([
            'customer_id' => $customer->id,
            'team_id' => $team->id,
            'whatsapp_number_id' => $whatsappNumber->id,
            'sent_by' => $user->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $body,
            'status' => 'pending',
        ]);

        try {
            $response = $this->metaWhatsappService
                ->sendText(
                    $whatsappNumber,
                    $customer->phone,
                    $body
                );

            $message->update([
                'whatsapp_message_id' =>
                    data_get(
                        $response,
                        'messages.0.id'
                    ),
                'status' => 'sent',
            ]);

        } catch (\Throwable $e) {

            $message->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $message->fresh();
    }
}