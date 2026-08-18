<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsappNumber;
use Carbon\Carbon;

class WhatsAppConversationService
{
    /**
     * Determine whether free-form WhatsApp messaging
     * is currently available for this customer + number.
     */
    public function getWindowStatus(
        Customer $customer,
        WhatsappNumber $whatsappNumber
    ): array {
        $lastInbound = Message::query()
            ->where('customer_id', $customer->id)
            ->where('whatsapp_number_id', $whatsappNumber->id)
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();

        if (!$lastInbound) {
            return [
                'open' => false,
                'last_inbound_at' => null,
                'expires_at' => null,
                'remaining_seconds' => 0,
            ];
        }

        $expiresAt = Carbon::parse($lastInbound->created_at)
            ->addHours(24);

        $open = now()->lt($expiresAt);

        return [
            'open' => $open,
            'last_inbound_at' => $lastInbound->created_at?->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
            'remaining_seconds' => $open
                ? max(0, now()->diffInSeconds($expiresAt, false))
                : 0,
        ];
    }

    /**
     * Get all WhatsApp numbers through which this
     * customer has an existing conversation.
     */
    public function conversationNumbers(Customer $customer)
    {
        return WhatsappNumber::query()
            ->whereHas('messages', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->where('is_active', true)
            ->get();
    }
}