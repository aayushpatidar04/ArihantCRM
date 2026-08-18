<?php

namespace App\Http\Controllers\TeamAdmin;

use App\Http\Controllers\Controller;
use App\Services\TeamWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        protected TeamWorkspaceService $workspaceService
    ) {}

    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $validated = $request->validate([
            'team_id' => [
                'required',
                'integer',
            ],
        ]);

        try {
            $team = $this->workspaceService->switch(
                $user,
                $validated['team_id']
            );

            return back()->with(
                'success',
                "Workspace switched to {$team->name}."
            );

        } catch (\RuntimeException $e) {

            return back()->with(
                'error',
                $e->getMessage()
            );
        }
    }
}