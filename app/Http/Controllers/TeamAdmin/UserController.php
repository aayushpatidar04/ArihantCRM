<?php

namespace App\Http\Controllers\TeamAdmin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        protected TeamWorkspaceService $workspaceService
    ) {
    }

    /**
     * List users belonging to the current workspace.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $users = User::query()
            ->where('team_id', $team->id)
            ->with([
                'roles:id,name',
            ])
            ->when(
                $request->search,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'phone',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $request->status === 'active',
                fn ($query) => $query->where(
                    'is_active',
                    true
                )
            )
            ->when(
                $request->status === 'inactive',
                fn ($query) => $query->where(
                    'is_active',
                    false
                )
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render(
            'TeamAdmin/Users/Index',
            [
                'users' => $users,
                'team' => $team,
                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                ],
            ]
        );
    }

    /**
     * Create page.
     */
    public function create(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render(
            'TeamAdmin/Users/Create',
            [
                'team' => $team,
                'roles' => $this->availableRoles(),
            ]
        );
    }

    /**
     * Store user in current workspace.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'role' => [
                'required',
                'string',
                Rule::in($this->availableRoles()),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make(
                $validated['password']
            ),
            'team_id' => $team->id,
            'is_active' => $validated['is_active'],
        ]);

        $user->accessibleTeams()->attach($team->id);

        $user->syncRoles([
            $validated['role'],
        ]);

        return redirect()
            ->route('team-admin.users.index')
            ->with(
                'success',
                'User created successfully.'
            );
    }

    /**
     * Edit page.
     */
    public function edit(Request $request, User $user): Response {
        $team = $this->currentTeam($request);

        $this->ensureUserBelongsToTeam(
            $user,
            $team
        );

        return Inertia::render(
            'TeamAdmin/Users/Edit',
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames(),
                ],

                'team' => $team,

                'roles' => $this->availableRoles(),
            ]
        );
    }

    /**
     * Update user.
     */
    public function update(Request $request, User $user): RedirectResponse {
        $team = $this->currentTeam($request);

        $this->ensureUserBelongsToTeam(
            $user,
            $team
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique(
                    'users',
                    'phone'
                )->ignore($user->id),
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'role' => [
                'required',
                'string',
                Rule::in($this->availableRoles()),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        if (
            ! empty($validated['password'])
        ) {
            $user->update([
                'password' => Hash::make(
                    $validated['password']
                ),
            ]);
        }

        $user->syncRoles([
            $validated['role'],
        ]);

        return redirect()
            ->route('team-admin.users.index')
            ->with(
                'success',
                'User updated successfully.'
            );
    }

    /**
     * Delete user.
     */
    public function destroy(Request $request, User $user): RedirectResponse {
        $team = $this->currentTeam($request);

        $this->ensureUserBelongsToTeam(
            $user,
            $team
        );

        /*
         * Prevent Team Admin from deleting
         * their own account.
         */
        if ($user->id === $request->user()->id) {
            return back()->with(
                'error',
                'You cannot delete your own account.'
            );
        }

        $user->delete();

        return back()->with(
            'success',
            'User deleted successfully.'
        );
    }

    public function resetTwoFactor(User $user): RedirectResponse
    {
        /*
        * Make sure the Team Admin can only reset users
        * they are authorized to manage.
        *
        * If you already have authorization logic in this
        * controller, use the same logic here.
        */

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with(
            'success',
            "Two-factor authentication has been reset for {$user->name}. " .
            "The user will need to configure Google Authenticator again on the next login."
        );
    }

    /**
     * Resolve current workspace.
     */
    protected function currentTeam(Request $request): Team {
        $team = $this->workspaceService
            ->currentTeam(
                $request->user()
            );

        abort_unless(
            $team,
            403,
            'No workspace selected.'
        );

        return $team;
    }

    /**
     * Make sure the requested user belongs
     * to the current workspace.
     */
    protected function ensureUserBelongsToTeam(User $user, Team $team): void {
        abort_unless(
            (int) $user->team_id === (int) $team->id,
            403,
            'You do not have access to this user.'
        );
    }

    /**
     * Roles Team Admin is allowed to manage.
     */
    protected function availableRoles(): array
    {
        return [
            'executive',
        ];
    }
}