<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappNumber;

class ConversationAccessService
{
    /**
     * All team ids this user can currently operate in.
     */
    public function accessibleTeamIdsForUser(User $user): array
    {
        $teamIds = [$user->team_id];

        $teamIds = array_merge(
            $teamIds,
            $user->accessibleTeams()->pluck('teams.id')->all()
        );

        return array_values(
            array_unique(
                array_filter(
                    array_map('intval', $teamIds)
                )
            )
        );
    }

    /**
     * All team ids that are allowed to use a WhatsApp number.
     */
    public function teamIdsForWhatsappNumber(WhatsappNumber|int $whatsappNumber): array
    {
        $numberId = $whatsappNumber instanceof WhatsappNumber
            ? (int) $whatsappNumber->id
            : (int) $whatsappNumber;

        return Team::query()
            ->where('whatsapp_number_id', $numberId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * All WhatsApp numbers reachable by a user through their teams.
     */
    public function accessibleWhatsappNumberIdsForUser(User $user): array
    {
        $teamIds = $this->accessibleTeamIdsForUser($user);

        if ($teamIds === []) {
            return [];
        }

        return Team::query()
            ->whereIn('id', $teamIds)
            ->whereNotNull('whatsapp_number_id')
            ->pluck('whatsapp_number_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessCustomer(User $user, Customer $customer): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $teamIds = $this->accessibleTeamIdsForUser($user);

        if ($teamIds === []) {
            return false;
        }

        if (
            $customer->team_id &&
            in_array((int) $customer->team_id, $teamIds, true)
        ) {
            return true;
        }

        if (
            $customer->assigned_to &&
            (int) $customer->assigned_to === (int) $user->id
        ) {
            return true;
        }

        if (
            $customer->old_owner_id &&
            (int) $customer->old_owner_id === (int) $user->id
        ) {
            return true;
        }

        if ($user->hasRole('team_admin')) {
            $customerTeamId = (int) $customer->team_id;
            $assignedTeamId = $customer->assignedTo?->team_id ? (int) $customer->assignedTo->team_id : null;
            $oldOwnerTeamId = $customer->oldOwner?->team_id ? (int) $customer->oldOwner->team_id : null;

            return in_array($customerTeamId, $teamIds, true)
                || in_array($assignedTeamId, $teamIds, true)
                || in_array($oldOwnerTeamId, $teamIds, true);
        }

        return false;
    }

    public function canAccessMessage(User $user, Message $message): bool
    {
        if (! $this->canAccessCustomer($user, $message->customer)) {
            return false;
        }

        if (! $message->whatsapp_number_id) {
            return true;
        }

        $numberIds = $this->accessibleWhatsappNumberIdsForUser($user);

        return in_array((int) $message->whatsapp_number_id, $numberIds, true);
    }
}
