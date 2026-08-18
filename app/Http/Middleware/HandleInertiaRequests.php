<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\TeamWorkspaceService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar_url' => $user->avatar_url,
                        'roles' => $user->getRoleNames()->values(),
                        'team_id' => $user->team_id,
                    ]
                    : null,
            ],

            'workspace' => function () use ($user) {

                if(!$user){
                    return [
                        'current_team' => null,
                        'teams' => [],
                    ];
                }

                if ($user && !$user->hasRole('team_admin')) {
                    return [
                        'current_team' => $user->team()->where('teams.is_active', true)->with([
                                                'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
                                            ])->first(),
                        'teams' => [],
                    ];
                }

                $service = app(
                    TeamWorkspaceService::class
                );

                $service->ensureValidWorkspace($user);
                $user->refresh();
                return [
                    'current_team' => $service
                        ->currentTeam($user),

                    'teams' => $service
                        ->accessibleTeams($user),
                ];
            },

            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'info' => fn() => $request->session()->get('info'),
            ],
        ];
    }
}
