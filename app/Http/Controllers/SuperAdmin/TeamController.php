<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TeamController extends Controller
{
    private function authorizeSuperAdmin(): void
    {
        if (!Auth::user()->hasRole('super_admin')) {
            abort(403, 'Only super-admin can access this area.');
        }
    }
    public function index(Request $request): Response
    {
        $teams = Team::query()
            ->withCount([
                'users',
                'customers',
            ])
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request->string('search')->trim();

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%")
                            ->orWhere(
                                'external_department_id',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $request->filled('status'),
                function ($query) use ($request) {
                    $query->where(
                        'is_active',
                        $request->status === 'active'
                    );
                }
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Teams/Index', [
            'teams' => $teams,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }


    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Teams/Create');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:teams,name',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:teams,slug',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $slug = $validated['slug']
            ?? Str::slug($validated['name']);

        /*
         * Make sure generated slug is unique.
         */
        $originalSlug = $slug;
        $counter = 1;

        while (
            Team::where('slug', $slug)->exists()
        ) {
            $slug = $originalSlug . '-' . $counter++;
        }

        Team::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('superadmin.teams.index')
            ->with('success', 'Team created successfully.');
    }


    public function show(Team $team): Response
    {
        $team->loadCount([
            'accessibleUsers',
            'customers',
        ]);

        $team->load([
            'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
            'accessibleUsers' => function ($query) {
                $query
                    ->select(
                        'users.id',         // <-- prefix with table name
                        'users.name',
                        'users.email',
                        'users.phone',
                        'users.is_active',
                        'users.team_id'     // <-- prefix with table name
                    )
                    ->with('roles:id,name');
            },
        ]);
        return Inertia::render('SuperAdmin/Teams/Show', [
            'team' => $team,
        ]);
    }


    public function edit(Team $team): Response
    {
        $this->authorizeSuperAdmin();

        $team->load([
            'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
        ]);

        $teams = Team::query()
            ->whereKeyNot($team->id)
            ->where('is_active', true)
            ->select([
                'id',
                'name',
            ])
            ->orderBy('name')
            ->get();

        $whatsappNumbers = WhatsappNumber::query()
            ->where('is_active', true)
            ->select([
                'id',
                'phone_number',
                'display_phone_number',
                'verified_name',
                'is_active',
            ])
            ->orderBy('phone_number')
            ->get();

        return Inertia::render(
            'SuperAdmin/Teams/Edit',
            [
                'team' => $team,
                'teams' => $teams,
                'whatsappNumbers' => $whatsappNumbers,
            ]
        );
    }


    public function update(Request $request, Team $team)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:teams,name,' . $team->id,
            ],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:teams,slug,' . $team->id,
            ],

            'parent_team_id' => [
                'nullable',
                'integer',
                'exists:teams,id',
                'not_in:' . $team->id,
            ],

            'external_department_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'whatsapp_number_id' => [
                'nullable',
                'integer',
                'exists:whatsapp_numbers,id',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $team->update([
            'name' => $validated['name'],

            'slug' => $validated['slug']
                ?: Str::slug($validated['name']),

            'parent_team_id' =>
                $validated['parent_team_id'] ?? null,

            'external_department_id' =>
                $validated['external_department_id'] ?? null,

            'whatsapp_number_id' =>
                $validated['whatsapp_number_id'] ?? null,

            'is_active' =>
                $validated['is_active'],
        ]);

        return redirect()
            ->route('superadmin.teams.index')
            ->with(
                'success',
                'Team updated successfully.'
            );
    }


    public function destroy(Team $team)
    {
        /*
         * Do not allow deleting a team that still
         * has users/customers.
         */
        if ($team->users()->exists()) {
            return back()->with(
                'error',
                'Cannot delete a team that has users assigned.'
            );
        }

        if ($team->customers()->exists()) {
            return back()->with(
                'error',
                'Cannot delete a team that has customers assigned.'
            );
        }

        $team->delete();

        return redirect()
            ->route('superadmin.teams.index')
            ->with('success', 'Team deleted successfully.');
    }

    public function storeAdmin(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        DB::beginTransaction();

        try {
            /*
             * CASE 1:
             * Assign existing team_admin users to this team.
             */
            if ($request->filled('admin_ids')) {

                $validated = $request->validate([
                    'admin_ids' => ['array', 'min:1'],
                    'admin_ids.*' => [
                        'integer',
                        'exists:users,id',
                    ],
                ]);

                foreach ($validated['admin_ids'] as $adminId) {

                    $admin = User::role('team_admin')
                        ->where('id', $adminId)
                        ->firstOrFail();

                    /*
                     * Do NOT change users.team_id here.
                     *
                     * A team_admin can manage multiple teams.
                     * Access is controlled through user_team_access.
                     */
                    $admin->accessibleTeams()->syncWithoutDetaching([
                        $team->id,
                    ]);

                    if (is_null($admin->team_id)) {
                        $admin->update(['team_id' => $team->id,]);
                    }
                }

                DB::commit();

                return back()->with(
                    'success',
                    "Team admins updated successfully."
                );
            }

            /*
             * CASE 2:
             * Create a brand-new team_admin.
             */
            $data = $request->validate([
                'name' => ['required', 'string', 'max:191'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            $admin = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'team_id' => $team->id,
            ]);

            $admin->assignRole('team_admin');

            $admin->accessibleTeams()->attach($team->id);

            DB::commit();

            return back()->with(
                'success',
                "Team admin {$admin->email} added successfully."
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            report($e);

            return back()->with(
                'error',
                'Failed to update team admins: ' . $e->getMessage()
            );
        }
    }


    public function updateAdmin(Request $request, Team $team, User $user): RedirectResponse
    {

        $this->authorizeSuperAdmin();

        /*
         * Ensure this user is actually a team_admin.
         */
        if (!$user->hasRole('team_admin')) {
            abort(403);
        }

        /*
         * Ensure this admin has access to this team.
         */
        if (!$user->accessibleTeams()->whereKey($team->id)->exists()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'unique:users,email,' . $user->id,
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with(
            'success',
            'Team admin updated successfully.'
        );
    }


    public function destroyAdmin(Team $team, User $user): RedirectResponse
    {

        $this->authorizeSuperAdmin();
        if (!$user->hasRole('team_admin')) {
            return back()->with('error', 'This user is not a team admin.');
        }
        if (!$user->accessibleTeams()->where('teams.id', $team->id)->exists()) {
            return back()->with('error', 'This admin does not have access to this team.');
        }
        try {
            DB::transaction(function () use ($team, $user) {
                $user->accessibleTeams()->detach($team->id);
                if ((int) $user->team_id === (int) $team->id) {
                    $nextTeam = $user->accessibleTeams()->where('teams.is_active', true)->orderBy('teams.name')->first();
                    $user->update(['team_id' => $nextTeam?->id,]);
                }
            });
            return back()->with('success', "{$user->name} has been removed from {$team->name}.");
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to remove admin from the team: ' . $e->getMessage());
        }
    }


    public function availableAdmins(Team $team)
    {
        $this->authorizeSuperAdmin();

        $admins = User::role('team_admin')
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'is_active',
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($admin) use ($team) {

                $admin->checked = $admin->accessibleTeams()
                    ->whereKey($team->id)
                    ->exists();

                return $admin;
            });

        return response()->json($admins);
    }

    public function syncBitrix()
    {
        try {
            $exitCode = Artisan::call('bitrix:sync');

            $output = Artisan::output();

            if ($exitCode !== 0) {
                return back()->with(
                    'error',
                    'Bitrix synchronization failed.'
                );
            }

            return back()->with(
                'success',
                'Departments and agents synchronized successfully.'
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Bitrix synchronization failed: ' . $e->getMessage()
            );
        }
    }
}