<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $team = $user->team;

        abort_unless(
            $team,
            403,
            'No workspace assigned.'
        );

        $search = trim(
            (string) $request->input('search', '')
        );

        $customers = Customer::query()
            ->where(function ($query) use ($user) {
                $query
                    ->where('assigned_to', $user->id)
                    ->orWhere('old_owner_id', $user->id);
            })

            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            )->orWhere(
                                'phone',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )

            ->select([
                'id',
                'team_id',
                'assigned_to',
                'old_owner_id',
                'name',
                'email',
                'status',
                'last_contacted_at',
                'created_at',
                'updated_at',
            ])

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
            'Executive/Customers/Index',
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

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $team = $user->team;

        abort_unless(
            $team,
            403,
            'No workspace assigned.'
        );

        return Inertia::render(
            'Executive/Customers/Create',
            [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $team = $user->team;

        abort_unless(
            $team,
            403,
            'No workspace assigned.'
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Executive-created customer
        |--------------------------------------------------------------------------
        |
        | The customer automatically belongs to:
        |
        | - Current executive
        | - Current executive's team
        |
        | The executive cannot choose another team/user.
        |
        */

        $customer = Customer::create([
            'team_id' => $team->id,

            'assigned_to' => $user->id,

            'old_owner_id' => null,

            'name' => $validated['name'],

            'email' => $validated['email'] ?? null,

            'phone' => $validated['phone'],

            'status' => 'active',
        ]);

        return redirect()
            ->route(
                'executive.customers.show',
                $customer
            )
            ->with(
                'success',
                'Customer created successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, Customer $customer): Response {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $this->authorizeCustomer(
            $customer,
            $user->id
        );

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Do NOT return the complete Customer model here.
        |
        | That would expose:
        |
        | customer.phone
        |
        | to the browser.
        |
        */

        $customer->load([
            'assignedTo:id,name,email',
            'oldOwner:id,name,email',
        ]);

        $customerData = [
            'id' => $customer->id,

            'name' => $customer->name,

            'email' => $customer->email,

            'status' => $customer->status,

            'last_contacted_at' => $customer->last_contacted_at,

            'created_at' => $customer->created_at,

            'updated_at' => $customer->updated_at,

            'assigned_to' => $customer->assignedTo
                ? [
                    'id' => $customer->assignedTo->id,
                    'name' => $customer->assignedTo->name,
                    'email' => $customer->assignedTo->email,
                ]
                : null,

            'old_owner' => $customer->oldOwner
                ? [
                    'id' => $customer->oldOwner->id,
                    'name' => $customer->oldOwner->name,
                    'email' => $customer->oldOwner->email,
                ]
                : null,
        ];

        return Inertia::render(
            'Executive/Customers/Show',
            [
                'customer' => $customerData,

                'team' => [
                    'id' => $customer->team_id,
                    'name' => $customer->team?->name,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Customer $customer): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $this->authorizeCustomer(
            $customer,
            $user->id
        );

        /*
        |--------------------------------------------------------------------------
        | ONLY these two fields are accepted.
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
        ]);

        return redirect()
            ->back()
            ->with(
                'success',
                'Customer updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    protected function authorizeCustomer(Customer $customer, int $userId): void {
        $allowed = (
            (int) $customer->assigned_to === $userId
            ||
            (int) $customer->old_owner_id === $userId
        );

        abort_unless(
            $allowed,
            403,
            'You do not have access to this customer.'
        );
    }
}