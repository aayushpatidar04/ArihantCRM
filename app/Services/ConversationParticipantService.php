<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappNumber;

class ConversationParticipantService
{
    public function resolveInbound(Customer $customer, WhatsappNumber $number): array
    {
        $customer->loadMissing([
            'assignedTo:id,team_id',
            'oldOwner:id,team_id',
        ]);

        $assigned = $customer->assignedTo;
        $oldOwner = $customer->oldOwner;

        if ($assigned && (int) $assigned->team?->whatsapp_number_id === (int) $number->id) {
            return $this->snapshot($assigned);
        }

        if ($oldOwner && (int) $oldOwner->team?->whatsapp_number_id === (int) $number->id) {
            return $this->snapshot($oldOwner);
        }

        return $assigned
            ? $this->snapshot($assigned)
            : ($oldOwner ? $this->snapshot($oldOwner) : [
                'user_id' => null,
                'team_id' => $customer->team_id,
            ]);
    }

    protected function snapshot(User $user): array
    {
        $user->loadMissing('team:id,whatsapp_number_id');

        return [
            'user_id' => $user->id,
            'team_id' => $user->team_id,
        ];
    }
}
