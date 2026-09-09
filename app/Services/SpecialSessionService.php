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

        // if ($lastInbound) {
        //     // Check if it's from the special number
        //     if ($lastInbound->whatsapp_number_id === $specialNumber->id) {
        //         // Check if it's within the last 24 hours
        //         $specialInboundIsOpen = $lastInbound->created_at >= now()->subHours(24);
        //     }
        // }


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

    /**
     * Return the 24-hour window expiry and open status
     * for the number that replyNumber() would route to.
     *
     * The window is based on the latest inbound message on
     * the reply number — whether that's the team's normal
     * number or the special-session number. So if the customer's
     * last inbound came from the special-session number and
     * is within 24 hours, the window is treated as open here
     * and a normal text can be sent from that special number.
     */
    public function windowInfo(Customer $customer, Team $normalTeam): array
    {
        $replyNumber = $this->replyNumber($customer, $normalTeam);

        if (!$replyNumber) {
            return [
                'window_open' => false,
                'window_expires_at' => null,
                'last_inbound_at' => null,
            ];
        }

        $lastInbound = $customer->messages()
            ->where('direction', 'inbound')
            ->where('whatsapp_number_id', $replyNumber->id)
            ->latest('created_at')
            ->first();

        $windowExpiresAt = $lastInbound
            ? $lastInbound->created_at->copy()->addHours(24)
            : null;

        return [
            'window_open' => $windowExpiresAt
                ? now()->lt($windowExpiresAt)
                : false,
            'window_expires_at' => $windowExpiresAt?->toIso8601String(),
            'last_inbound_at' => $lastInbound?->created_at?->toIso8601String(),
        ];
    }
}
