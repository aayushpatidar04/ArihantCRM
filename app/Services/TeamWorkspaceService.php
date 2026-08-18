<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class TeamWorkspaceService
{
    /**
     * Get teams this user is allowed to manage.
     */
    public function accessibleTeams(User $user): Collection
    {
        return $user
            ->accessibleTeams()
            ->where('teams.is_active', true)
            ->select([
                'teams.id',
                'teams.name',
                'teams.slug',
                'teams.whatsapp_number_id',
            ])
            ->with([
                'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
            ])
            ->orderBy('teams.name')
            ->get();
    }

    /**
     * Get the current workspace.
     */
    public function currentTeam(User $user): ?Team
    {
        return $user->team()
            ->where('teams.is_active', true)
            ->with([
                'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
            ])
            ->first();
    }


    /**
     * Switch current workspace.
     */
    public function switch(User $user, int $teamId): Team
    {
        $team = $user
            ->accessibleTeams()
            ->where('teams.id', $teamId)
            ->where('teams.is_active', true)
            ->first();

        if (!$team) {
            throw new RuntimeException(
                'You do not have access to this team.'
            );
        }

        $user->update([
            'team_id' => $team->id,
        ]);

        return $team;
    }

    /**
     * Ensure the user's current team is valid.
     *
     * Useful when a team was removed, deactivated,
     * or access was revoked.
     */
    public function ensureValidWorkspace(User $user): ?Team
    {
        if (!$user->team_id) {
            return $this->setFallbackWorkspace($user);
        }

        $current = $user
            ->accessibleTeams()
            ->where('teams.id', $user->team_id)
            ->where('teams.is_active', true)
            ->first();
        
        if ($current) {
            return $current;
        }

        return $this->setFallbackWorkspace($user);
    }

    /**
     * Select the first available accessible team.
     */
    protected function setFallbackWorkspace(User $user): ?Team
    {
        $team = $user
            ->accessibleTeams()
            ->where('teams.is_active', true)
            ->orderBy('teams.id')
            ->first();

        $user->update([
            'team_id' => $team?->id,
        ]);

        return $team;
    }
}