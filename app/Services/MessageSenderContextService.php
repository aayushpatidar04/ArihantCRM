<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;

class MessageSenderContextService
{
    /**
     * Determine who sent an outbound message in relation
     * to the current customer.
     *
     * Possible types:
     *
     * assigned
     * old_owner
     * team_admin
     */
    public function getContext(
        Customer $customer,
        ?int $senderId
    ): array {
        if (!$senderId) {
            return [
                'type' => null,
                'name' => null,
                'role' => null,
            ];
        }

        $senderId = (int) $senderId;

        /*
        |--------------------------------------------------------------------------
        | Load ownership relationships
        |--------------------------------------------------------------------------
        */

        $customer->loadMissing([
            'assignedTo:id,name,team_id',
            'oldOwner:id,name,team_id',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assigned Executive
        |--------------------------------------------------------------------------
        */

        if (
            $customer->assigned_to &&
            $senderId === (int) $customer->assigned_to
        ) {
            return [
                'type' => 'assigned',
                'name' => $customer->assignedTo?->name,
                'role' => 'Assigned Executive',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Old Owner
        |--------------------------------------------------------------------------
        */

        if (
            $customer->old_owner_id &&
            $senderId === (int) $customer->old_owner_id
        ) {
            return [
                'type' => 'old_owner',
                'name' => $customer->oldOwner?->name,
                'role' => 'Old Owner',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Team Admin
        |--------------------------------------------------------------------------
        |
        | We intentionally DO NOT care which team the admin belongs to.
        |
        | If the sender has the team_admin role, it is simply:
        |
        | team_admin
        |
        */

        $sender = User::query()
            ->select([
                'id',
                'name',
            ])
            ->role('team_admin')
            ->find($senderId);

        if ($sender) {
            return [
                'type' => 'team_admin',
                'name' => $sender->name,
                'role' => 'Team Admin',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Unknown sender
        |--------------------------------------------------------------------------
        */

        return [
            'type' => null,
            'name' => null,
            'role' => null,
        ];
    }
}