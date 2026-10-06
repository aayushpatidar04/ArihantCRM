<?php

namespace App\Http\Controllers\TeamAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * List customers for the current workspace.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $user->team;

        abort_unless(
            $team,
            403,
            'No workspace selected.'
        );

        $search = trim(
            (string) $request->input('search', '')
        );

        $customers = Customer::query()
            ->where(function ($query) use ($team) {
                $query
                    ->where('team_id', $team->id)
                    ->orWhereHas('oldOwner', function ($oldOwnerQuery) use ($team) {
                        $oldOwnerQuery->where('team_id', $team->id);
                    });
            })

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            |
            | Search by:
            | - Customer name
            | - Phone
            | - Email
            |
            */
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('bitrix_lead_id', 'like', "%{$search}%");
                });
            })

            ->with([
                'assignedTo:id,name,email',
                'oldOwner:id,name,email',
                'latestMessage',
            ])

            ->withCount([
                'messages as unread_messages_count' => function ($query) {
                    $query
                        ->where('direction', 'inbound')
                        ->whereNull('read_at');
                },
            ])

            ->orderByDesc('updated_at')

            ->paginate(20)

            ->withQueryString();

        return Inertia::render(
            'TeamAdmin/Customers/Index',
            [
                'customers' => $customers,

                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],

                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    /**
     * Show create form.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $user->team;

        abort_unless($team, 403, 'No workspace selected.');

        $executives = User::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->role('executive')
            ->select([
                'id',
                'name',
                'email',
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'TeamAdmin/Customers/Create',
            [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],

                'executives' => $executives,
            ]
        );
    }

    /**
     * Store customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $user->team;

        abort_unless($team, 403, 'No workspace selected.');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'size:12',
                'regex:/^[0-9]+$/',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(function ($query) use ($team) {
                        $query
                            ->where('team_id', $team->id)
                            ->where('is_active', true);
                    }),
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                    'blocked',
                ]),
            ],

            'tags' => [
                'nullable',
                'array',
            ],
        ]);

        $customer = Customer::where('phone', $validated['phone'])->first();

        if ($customer) {
            return redirect()->back()->with('error', 'Customer with this number already exist');
        }

        $bitrixResponse = null;

        try {
            $bitrixResponse = app(\App\Services\BitrixLeadService::class)
                ->fetchLeadAgentByMobile($validated['phone']);
        } catch (\Throwable $e) {
            Log::warning('Bitrix agent lookup failed during manual customer creation.', [
                'phone' => $validated['phone'],
                'error' => $e->getMessage(),
            ]);
        }

        /*
         * If Bitrix returned a lead/agent, block creation and ask the
         * user to use the "Fetch Bitrix lead" option instead.
         */
        if ($bitrixResponse && data_get($bitrixResponse, 'Agent.Id')) {
            return redirect()->back()->with(
                'error',
                'A lead with this number already exists in Bitrix. Please use the "Fetch Bitrix lead" option to import the existing lead.'
            );
        }

        Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,

            // Important:
            // Never accept team_id from the frontend.
            'team_id' => $team->id,

            'assigned_to' =>
                $validated['assigned_to'] ?? null,

            'notes' =>
                $validated['notes'] ?? null,

            'status' =>
                $validated['status'],

            'tags' =>
                $validated['tags'] ?? null,
        ]);

        try {
            $assignedUser = $validated['assigned_to']
                ? User::find($validated['assigned_to'])
                : null;

            $agentEmail = $assignedUser?->email ?? null;

            \Illuminate\Support\Facades\Http::withBasicAuth(
                'satapark.arihantcapital',
                'Arinant@12345'
            )
                ->acceptJson()
                ->timeout(15)
                ->post(
                    'https://inspection.arihantcapital.com/api/v1/CtC/whatsappLeadDataSave',
                    [
                        'MobileNo' => $validated['phone'],
                        'AssignedToID' => $assignedUser->bitrix_user_id ?? null,
                        'AgentEmail' => $agentEmail,
                    ]
                );

            Log::info('New customer pushed to Bitrix via inspection API.', [
                'customer_id' => $customer->id,
                'phone' => $validated['phone'],
                'assigned_to' => $assignedUser->bitrix_user_id,
                'agent_email' => $agentEmail,
            ]);

        } catch (\Throwable $e) {
            Log::warning('Failed to push new lead to Bitrix inspection API.', [
                'customer_id' => $customer->id,
                'phone' => $validated['phone'],
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('team-admin.customers.index')
            ->with(
                'success',
                'Customer created successfully.'
            );
    }

    /**
     * Show customer.
     */
    public function show(Request $request, Customer $customer): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $this->currentTeam($user);

        abort_unless($team, 403, 'No workspace selected.');

        abort_unless(
            (int) $customer->team_id === (int) $team->id || (int) optional($customer->oldOwner)->team_id === (int) $team->id,
            403
        );

        $customer->load([
            'team:id,name',
            'assignedTo:id,name,email,phone',
            'assignedTo.team:id,name',
            'oldOwner:id,name,email,phone',
            'oldOwner.team:id,name',
            'latestMessage',
        ]);

        $customer->loadCount([
            'messages',
            'documents',
        ]);

        $executives = User::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->role('executive')
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'team_id',
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'TeamAdmin/Customers/Show',
            [
                'customer' => $customer,
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'executives' => $executives,
            ]
        );
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request, Customer $customer): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $user->team;

        abort_unless($team, 403, 'No workspace selected.');

        abort_unless(
            (int) $customer->team_id === (int) $team->id,
            403
        );

        $executives = User::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->role('executive')
            ->select([
                'id',
                'name',
                'email',
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'TeamAdmin/Customers/Edit',
            [
                'customer' => $customer->load([
                    'assignedTo:id,name,email',
                    'oldOwner:id,name,email',
                ]),

                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],

                'executives' => $executives,
            ]
        );
    }

    /**
     * Update customer.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $user->team;

        abort_unless($team, 403, 'No workspace selected.');

        abort_unless(
            (int) $customer->team_id === (int) $team->id,
            403
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(function ($query) use ($team) {
                        $query
                            ->where('team_id', $team->id)
                            ->where('is_active', true);
                    }),
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                    'blocked',
                ]),
            ],

            'tags' => [
                'nullable',
                'array',
            ],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? $customer->assigned_to,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'],
            'tags' => $validated['tags'] ?? null,
        ]);

        return redirect()
            ->route(
                'team-admin.customers.show',
                $customer
            )
            ->with(
                'success',
                'Customer updated successfully.'
            );
    }

    /**
     * Delete customer.
     */
    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('team_admin'),
            403
        );

        $team = $user->team;

        abort_unless($team, 403, 'No workspace selected.');

        abort_unless(
            (int) $customer->team_id === (int) $team->id,
            403
        );

        $customer->delete();

        return redirect()
            ->route('team-admin.customers.index')
            ->with(
                'success',
                'Customer deleted successfully.'
            );
    }

    public function assign(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        $team = $this->currentTeam($user);

        abort_unless($team, 403, 'No workspace selected.');

        /*
         * Customer must belong to the current workspace.
         */
        abort_unless(
            (int) $customer->team_id === (int) $team->id,
            404
        );

        $validated = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $newOwner = User::query()
            ->whereKey($validated['assigned_to'])
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->role('executive')
            ->first();

        if (!$newOwner) {
            return back()->with(
                'error',
                'Selected executive is not available in this workspace.'
            );
        }

        /*
         * Nothing to change.
         */
        if (
            (int) $customer->assigned_to ===
            (int) $newOwner->id
        ) {
            return back()->with(
                'success',
                'Customer is already assigned to this executive.'
            );
        }

        DB::transaction(function () use ($customer, $newOwner) {
            /*
             * Preserve the current owner before changing it.
             */
            $previousOwnerId = $customer->assigned_to;

            $customer->update([
                'old_owner_id' => $previousOwnerId,
                'assigned_to' => $newOwner->id,

                /*
                 * IMPORTANT:
                 * Team always follows the assigned user's team.
                 */
                'team_id' => $newOwner->team_id,
            ]);
        });

        return back()->with(
            'success',
            "Customer assigned to {$newOwner->name}."
        );
    }

    protected function currentTeam(User $user): ?Team
    {
        return app(TeamWorkspaceService::class)
            ->currentTeam($user);
    }
}