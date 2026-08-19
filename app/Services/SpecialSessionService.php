<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Team;
use App\Models\WhatsappNumber;

class SpecialSessionService
{
    public const TEAM_SLUG = 'arihant-special-session';

    public function specialTeam(): ?Team
    {
        return Team::query()
            ->where('slug', self::TEAM_SLUG)
            ->with('whatsappNumber')
            ->first();
    }

    public function replyNumber(Customer $customer, Team $normalTeam): ?WhatsappNumber
    {
        $specialTeam = $this->specialTeam();
        $normalNumber = $normalTeam->whatsappNumber;
        $specialNumber = $specialTeam?->whatsappNumber;

        if (!$specialNumber || !$specialNumber->is_active) {
            return $normalNumber;
        }

        $lastInbound = $customer->messages()
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();

        $specialInboundIsOpen = false;

        if ($lastInbound) {
            // Check if it's from the special number
            if ($lastInbound->whatsapp_number_id === $specialNumber->id) {
                // Check if it's within the last 24 hours
                $specialInboundIsOpen = $lastInbound->created_at >= now()->subHours(24);
            }
        }


        return $specialInboundIsOpen
            ? $specialNumber
            : $normalNumber;
    }

    public function messageTeam(WhatsappNumber $number, Team $normalTeam): Team
    {
        $specialTeam = $this->specialTeam();

        return $specialTeam && (int) $specialTeam->whatsapp_number_id === (int) $number->id
            ? $specialTeam
            : $normalTeam;
    }
}
