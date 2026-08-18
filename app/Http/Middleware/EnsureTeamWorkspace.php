<?php

namespace App\Http\Middleware;

use App\Services\TeamWorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamWorkspace
{
    public function __construct(
        protected TeamWorkspaceService $workspaceService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {

        $user = $request->user();

        if (
            $user &&
            $user->hasRole('team_admin')
        ) {
            $this->workspaceService
                ->ensureValidWorkspace($user);
        }

        return $next($request);
    }
}