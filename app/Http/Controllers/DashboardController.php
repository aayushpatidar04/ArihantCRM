<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappNumber;
use App\Services\BitrixLeadService;
use App\Services\TeamWorkspaceService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{

    public function __construct(
        protected TeamWorkspaceService $workspaceService
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Super-admin gets a completely separate platform-level dashboard.
        // They never see customers, messages, or documents — only team health.
        if ($user->isSuperAdmin()) {
            return $this->superAdminDashboard();
        }

        // Team-level dashboard (team-admin)
        if ($user->isTeamAdmin()) {
            return $this->teamDashboard($request, $user);
        }

        if ($user->isExecutive()) {
            return $this->executiveDashboard($request);
        }
    }

    private function superAdminDashboard(): Response
    {
        $stats = [
            'total_teams' => Team::count(),

            'active_teams' => Team::where(
                'is_active',
                true
            )->count(),

            'total_users' => User::count(),

            'active_users' => User::where(
                'is_active',
                true
            )->count(),

            'total_whatsapp_numbers' => WhatsappNumber::count(),

            'active_whatsapp_numbers' => WhatsappNumber::where(
                'is_active',
                true
            )->count(),
        ];

        $teams = Team::withCount([
            'users',
        ])
            ->with([
                'whatsappNumber',
                'users' => function ($query) {
                    $query
                        ->whereHas('roles', function ($q) {
                            $q->where(
                                'name',
                                'team_admin'
                            );
                        })
                        ->select(
                            'id',
                            'name',
                            'email',
                            'team_id'
                        );
                },
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($team) {
                $admin = $team->users->first();

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'is_active' => $team->is_active,

                    'users_count' => $team->users_count,

                    'whatsapp_number' => $team->whatsappNumber
                        ? [
                            'id' => $team->whatsappNumber->id,
                            'name' => $team->whatsappNumber->verified_name,
                            'phone_number' =>
                                $team->whatsappNumber->display_phone_number,
                            'is_active' =>
                                $team->whatsappNumber->is_active,
                        ]
                        : null,

                    'admin' => $admin
                        ? [
                            'id' => $admin->id,
                            'name' => $admin->name,
                            'email' => $admin->email,
                        ]
                        : null,

                    'created_at' => $team->created_at,
                ];
            });

        // $teamGrowth = Team::select(
        //         DB::raw(
        //             "DATE_FORMAT(created_at, '%Y-%m') as month"
        //         ),
        //         DB::raw('COUNT(*) as total')
        //     )
        //     ->where(
        //         'created_at',
        //         '>=',
        //         now()->subMonths(6)->startOfMonth()
        //     )
        //     ->groupBy('month')
        //     ->orderBy('month')
        //     ->get();

        $teamGrowth = Team::select(
            DB::raw("strftime('%Y-%m', created_at) as month"),
            DB::raw('COUNT(*) as total')
        )
            ->where(
                'created_at',
                '>=',
                now()->subMonths(6)->startOfMonth()
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();


        return Inertia::render(
            'SuperAdmin/Dashboard',
            [
                'stats' => $stats,
                'teams' => $teams,
                'teamGrowth' => $teamGrowth,
            ]
        );
    }

    public function teamDashboard(Request $request, User $user): Response
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Resolve Current Team
        |--------------------------------------------------------------------------
        */

        $currentTeam = $this->workspaceService
            ->currentTeam($user);

        abort_unless(
            $currentTeam,
            403,
            'No active team workspace is available for your account.'
        );

        /*
        |--------------------------------------------------------------------------
        | Load Workspace Information
        |--------------------------------------------------------------------------
        */

        $currentTeam->load([
            'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active,last_connected_at,last_webhook_at,last_connection_check_at,last_connection_error',
        ]);

        $teamId = (int) $currentTeam->id;

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        $search = trim((string) $request->input('search', ''));

        /*
        |--------------------------------------------------------------------------
        | Base Customer Query
        |--------------------------------------------------------------------------
        */

        $customerQuery = Customer::query()
            ->where('team_id', $teamId);

        /*
        |--------------------------------------------------------------------------
        | Base Message Query
        |--------------------------------------------------------------------------
        */

        $messageQuery = Message::query()
            ->where('team_id', $teamId);

        /*
        |--------------------------------------------------------------------------
        | Dashboard Statistics
        |--------------------------------------------------------------------------
        */

        $stats = [
            'total_customers' => (clone $customerQuery)
                ->count(),

            'active_customers' => (clone $customerQuery)
                ->where('status', 'active')
                ->count(),

            'unread_messages' => (clone $messageQuery)
                ->where('direction', 'inbound')
                ->whereNull('read_at')
                ->count(),

            'messages_today' => (clone $messageQuery)
                ->whereDate('created_at', today())
                ->count(),

            'inbound_today' => (clone $messageQuery)
                ->where('direction', 'inbound')
                ->whereDate('created_at', today())
                ->count(),

            'outbound_today' => (clone $messageQuery)
                ->where('direction', 'outbound')
                ->whereDate('created_at', today())
                ->count(),

            'pending_documents' => Document::query()
                ->where('team_id', $teamId)
                ->where('status', 'pending')
                ->count(),

            'total_documents' => Document::query()
                ->where('team_id', $teamId)
                ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | 7 Day Message Chart
        |--------------------------------------------------------------------------
        |
        | We intentionally generate all 7 dates in PHP so that a day with
        | zero messages still appears in the chart.
        |
        */

        $chartStart = now()
            ->subDays(6)
            ->startOfDay();

        $chartEnd = now()->endOfDay();

        $messageChartRows = (clone $messageQuery)
            ->select([
                DB::raw('DATE(created_at) as date'),

                DB::raw('COUNT(*) as total'),

                DB::raw("
                SUM(
                    CASE
                        WHEN direction = 'inbound'
                        THEN 1
                        ELSE 0
                    END
                ) as inbound
            "),

                DB::raw("
                SUM(
                    CASE
                        WHEN direction = 'outbound'
                        THEN 1
                        ELSE 0
                    END
                ) as outbound
            "),
            ])
            ->whereBetween('created_at', [
                $chartStart,
                $chartEnd,
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $messageChart = collect();

        for ($i = 0; $i < 7; $i++) {
            $date = $chartStart
                ->copy()
                ->addDays($i);

            $dateKey = $date->format('Y-m-d');

            $row = $messageChartRows->get($dateKey);

            $messageChart->push([
                'date' => $dateKey,

                'label' => $date->format('D'),

                'display_date' => $date->format('d M'),

                'total' => (int) ($row->total ?? 0),

                'inbound' => (int) ($row->inbound ?? 0),

                'outbound' => (int) ($row->outbound ?? 0),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Recent Activity
        |--------------------------------------------------------------------------
        |
        | Latest message for every customer + WhatsApp number.
        |
        | We do NOT use session_id because this project does not have it.
        |
        */

        $latestMessageIds = (clone $messageQuery)
            ->selectRaw('MAX(messages.id)')
            ->groupBy([
                'messages.customer_id',
                'messages.whatsapp_number_id',
            ]);

        $recentMessages = (clone $messageQuery)
            ->with([
                'customer' => function ($query) {
                    $query->select([
                        'id',
                        'name',
                        'phone',
                        'assigned_to',
                        'old_owner_id',
                        'status',
                    ]);
                },

                'sentBy:id,name',

                'whatsappNumber:id,phone_number,display_phone_number,verified_name',

                'document',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {

                    $searchQuery
                        ->whereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })

                        ->orWhere(
                            'messages.body',
                            'like',
                            "%{$search}%"
                        );
                });
            })
            ->whereIn(
                'messages.id',
                $latestMessageIds
            )
            ->orderByDesc('messages.created_at')
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Recent Activity Transformation
        |--------------------------------------------------------------------------
        */

        $recentMessages->getCollection()->transform(
            function (Message $message) {

                return [
                    'id' => $message->id,

                    'customer_id' => $message->customer_id,

                    'customer_name' =>
                        $message->customer?->name
                        ?? 'Unknown Customer',

                    'customer_phone' =>
                        $message->customer?->phone,

                    'body' =>
                        $message->body,

                    'type' =>
                        $message->type,

                    'direction' =>
                        $message->direction,

                    'status' =>
                        $message->status,

                    'is_forwarded' =>
                        (bool) $message->is_forwarded,

                    'whatsapp_number_id' =>
                        $message->whatsapp_number_id,

                    'whatsapp_number' =>
                        $message->whatsappNumber?->display_phone_number
                        ?? $message->whatsappNumber?->phone_number,

                    'sent_by' =>
                        $message->sentBy?->name,

                    'has_document' =>
                        $message->document !== null,

                    'document' =>
                        $message->document
                        ? [
                            'id' => $message->document->id,
                            'filename' => $message->document->original_filename,
                            'mime_type' => $message->document->mime_type,
                            'url' => $message->document->url,
                            'size' => $message->document->formatted_size,
                        ]
                        : null,

                    'created_at' =>
                        $message->created_at?->toISOString(),

                    'time_ago' =>
                        $message->created_at
                        ? $message->created_at->diffForHumans()
                        : '',
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Information
        |--------------------------------------------------------------------------
        */

        $whatsappNumber = $currentTeam->whatsappNumber;

        $whatsappStatus = $whatsappNumber
            ? [
                'id' =>
                    $whatsappNumber->id,

                'phone_number' =>
                    $whatsappNumber->phone_number,

                'display_phone_number' =>
                    $whatsappNumber->display_phone_number,

                'verified_name' =>
                    $whatsappNumber->verified_name,

                'is_active' =>
                    (bool) $whatsappNumber->is_active,

                'last_connected_at' =>
                    $whatsappNumber->last_connected_at?->toISOString(),

                'last_webhook_at' =>
                    $whatsappNumber->last_webhook_at?->toISOString(),

                'last_connection_check_at' =>
                    $whatsappNumber->last_connection_check_at?->toISOString(),

                'last_connection_error' =>
                    $whatsappNumber->last_connection_error,
            ]
            : null;

        /*
        |--------------------------------------------------------------------------
        | Return Dashboard
        |--------------------------------------------------------------------------
        */

        return Inertia::render(
            'TeamAdmin/Dashboard',
            [
                /*
                 * Existing prop
                 */
                'team' => $currentTeam,

                /*
                 * Dashboard analytics
                 */
                'stats' => $stats,

                /*
                 * 7 day chart
                 */
                'messageChart' => $messageChart->values(),

                /*
                 * Latest activity
                 */
                'recentMessages' => $recentMessages,

                /*
                 * WhatsApp status
                 */
                'whatsappStatus' => $whatsappStatus,

                /*
                 * Search
                 */
                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    /**
     * Return unread WhatsApp conversations for Team Admin dashboard.
     */
    public function unreadMessages(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Current Team
        |--------------------------------------------------------------------------
        */

        $currentTeam = $this->workspaceService
            ->currentTeam($user);

        abort_unless(
            $currentTeam,
            403,
            'No active team workspace is available for your account.'
        );

        $teamId = (int) $currentTeam->id;

        /*
        |--------------------------------------------------------------------------
        | Unread inbound messages
        |--------------------------------------------------------------------------
        */

        $visibleUnreadMessages = Message::query()
            ->where('team_id', $teamId)
            ->where('direction', 'inbound')
            ->whereNull('read_at');

        /*
        |--------------------------------------------------------------------------
        | Total unread message count
        |--------------------------------------------------------------------------
        */

        $unreadCount = (clone $visibleUnreadMessages)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Group unread messages by conversation
        |--------------------------------------------------------------------------
        |
        | New project conversation identity:
        |
        | team_id
        | customer_id
        | whatsapp_number_id
        |
        */

        $unreadGroups = (clone $visibleUnreadMessages)
            ->selectRaw('
                messages.customer_id,
                messages.whatsapp_number_id,
                messages.team_id,
                MAX(messages.id) as latest_message_id,
                COUNT(messages.id) as unread_count
            ')
            ->groupBy(
                'messages.customer_id',
                'messages.whatsapp_number_id',
                'messages.team_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Get latest unread message per conversation
        |--------------------------------------------------------------------------
        */

        $unreadChats = Message::query()
            ->joinSub(
                $unreadGroups,
                'unread_groups',
                function ($join) {

                    $join->on(
                        'messages.id',
                        '=',
                        'unread_groups.latest_message_id'
                    );
                }
            )
            ->with([
                'customer:id,name,phone,assigned_to,old_owner_id,status',

                'sentBy:id,name',

                'whatsappNumber:id,phone_number,display_phone_number,verified_name',

                'document',
            ])
            ->select([
                'messages.*',

                'unread_groups.unread_count',
            ])
            ->orderByDesc('messages.created_at')
            ->limit(20)
            ->get()
            ->map(function (Message $message) {

                return [
                    /*
                     * Conversation key.
                     *
                     * No session_id.
                     */
                    'conversation_key' =>
                        $message->team_id
                        . ':'
                        . $message->customer_id
                        . ':'
                        . $message->whatsapp_number_id,

                    'latest_message_id' =>
                        $message->id,

                    'customer_id' =>
                        $message->customer_id,

                    'customer_name' =>
                        $message->customer?->name
                        ?? 'Unknown Customer',

                    'customer_phone' =>
                        $message->customer?->phone,

                    'team_id' =>
                        $message->team_id,

                    'whatsapp_number_id' =>
                        $message->whatsapp_number_id,

                    'whatsapp_number' =>
                        $message->whatsappNumber?->display_phone_number
                        ?? $message->whatsappNumber?->phone_number,

                    'body' =>
                        $message->body,

                    'type' =>
                        $message->type,

                    'status' =>
                        $message->status,

                    'has_document' =>
                        $message->document !== null,

                    'document' =>
                        $message->document
                        ? [
                            'id' => $message->document->id,
                            'filename' => $message->document->original_filename,
                            'mime_type' => $message->document->mime_type,
                            'url' => $message->document->url,
                            'size' => $message->document->formatted_size,
                        ]
                        : null,

                    'unread_count' =>
                        (int) $message->unread_count,

                    'created_at' =>
                        $message->created_at?->toISOString(),

                    'time_ago' =>
                        $message->created_at
                        ? $message->created_at->diffForHumans()
                        : '',
                ];
            });

        return response()->json([
            /*
             * Total individual unread messages.
             */
            'unread_count' =>
                $unreadCount,

            /*
             * Number of conversations with unread messages.
             */
            'unread_chat_count' =>
                $unreadChats->count(),

            /*
             * Latest unread message per conversation.
             */
            'unread_chats' =>
                $unreadChats->values(),
        ]);
    }

    public function executiveDashboard(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Current Team
        |--------------------------------------------------------------------------
        */

        $team = $user->team;

        abort_unless(
            $team,
            403,
            'No team is assigned to your account.'
        );

        /*
        |--------------------------------------------------------------------------
        | Executive customer scope
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | We do NOT use team_id alone here.
        |
        | An executive must only see customers assigned to them or
        | customers where they are the old owner.
        |
        */

        $customerScope = function ($query) use ($user, $team) {
            $query
                // ->where('team_id', $team->id)
                ->where(function ($q) use ($user) {
                    $q
                        ->where('assigned_to', $user->id)
                        ->orWhere('old_owner_id', $user->id);
                });
        };

        /*
        |--------------------------------------------------------------------------
        | Base Customer Query
        |--------------------------------------------------------------------------
        */

        $baseCustomerQuery = Customer::query();

        $baseCustomerQuery->where(
            function ($query) use ($customerScope) {
                $customerScope($query);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Visible Message Query
        |--------------------------------------------------------------------------
        |
        | Messages are restricted through customers accessible to this
        | executive.
        |
        */

        $visibleMessages = Message::query()
            // ->where('team_id', $team->id)
            ->whereHas(
                'customer',
                function ($query) use ($user, $team) {
                    $query
                        // ->where('team_id', $team->id)
                        ->where(function ($q) use ($user) {
                            $q
                                ->where('assigned_to', $user->id)
                                ->orWhere('old_owner_id', $user->id);
                        });
                }
            );
        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        $stats = [
            /*
             * Total customers currently assigned to or previously owned
             * by this executive.
             */
            'total_customers' => (clone $baseCustomerQuery)
                ->count(),

            /*
             * Active customers.
             */
            'active_customers' => (clone $baseCustomerQuery)
                ->where('status', 'active')
                ->count(),

            /*
             * Total unread inbound messages.
             */
            'unread_messages' => (clone $visibleMessages)
                ->where('direction', 'inbound')
                ->whereNull('read_at')
                ->count(),

            /*
             * All messages received/sent today for accessible customers.
             */
            'messages_today' => (clone $visibleMessages)
                ->whereDate(
                    'created_at',
                    today()
                )
                ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Recent Messages
        |--------------------------------------------------------------------------
        |
        | One latest message per customer.
        |
        */

        $latestVisibleMessageIds = (clone $visibleMessages)
            ->selectRaw(
                'MAX(messages.id)'
            )
            ->groupBy(
                'messages.customer_id'
            );

        $recentMessages = (clone $visibleMessages)
            ->with([
                /*
                 * IMPORTANT:
                 *
                 * Do not expose phone here.
                 *
                 * We explicitly select customer fields without phone.
                 */
                'customer' => function ($query) use ($user, $team) {
                    $query
                        ->select([
                            'id',
                            'team_id',
                            'name',
                            'email',
                            'assigned_to',
                            'old_owner_id',
                            'status',
                        ])
                        // ->where('team_id', $team->id)
                        ->where(function ($q) use ($user) {
                            $q
                                ->where(
                                    'assigned_to',
                                    $user->id
                                )
                                ->orWhere(
                                    'old_owner_id',
                                    $user->id
                                );
                        })
                        ->with([
                            'assignedTo:id,name',
                            'oldOwner:id,name',
                        ]);
                },

                'sentBy:id,name',

                'document',
            ])
            ->whereIn(
                'messages.id',
                $latestVisibleMessageIds
            )
            ->orderByDesc(
                'messages.created_at'
            )
            ->limit(10)
            ->get()
            ->map(function (Message $message) {
                return [
                    'id' => $message->id,

                    'customer' => $message->customer
                        ? [
                            'id' =>
                                $message->customer->id,

                            'name' =>
                                $message->customer->name,

                            'email' =>
                                $message->customer->email,

                            'status' =>
                                $message->customer->status,

                            'assigned_to' =>
                                $message->customer
                                    ->assignedTo?->name,

                            'old_owner' =>
                                $message->customer
                                    ->oldOwner?->name,
                        ]
                        : null,

                    'direction' =>
                        $message->direction,

                    'type' =>
                        $message->type,

                    'body' =>
                        $message->body,

                    'status' =>
                        $message->status,

                    'read_at' =>
                        $message->read_at?->toISOString(),

                    'created_at' =>
                        $message->created_at?->toISOString(),

                    'time_ago' =>
                        $message->created_at
                        ? $message->created_at
                            ->diffForHumans()
                        : null,

                    'has_document' =>
                        $message->document !== null,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Message Chart
        |--------------------------------------------------------------------------
        |
        | Last 7 days.
        |
        | Only messages belonging to customers accessible to this
        | executive are included.
        |
        */

        $messageChart = (clone $visibleMessages)
            ->select(
                DB::raw(
                    'DATE(created_at) as date'
                ),

                DB::raw(
                    'COUNT(*) as total'
                ),

                DB::raw(
                    "SUM(
                        CASE
                            WHEN direction = 'inbound'
                            THEN 1
                            ELSE 0
                        END
                    ) as inbound"
                ),

                DB::raw(
                    "SUM(
                        CASE
                            WHEN direction = 'outbound'
                            THEN 1
                            ELSE 0
                        END
                    ) as outbound"
                )
            )
            ->whereBetween(
                'created_at',
                [
                    now()
                        ->subDays(6)
                        ->startOfDay(),

                    now()->endOfDay(),
                ]
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Information
        |--------------------------------------------------------------------------
        */

        $whatsappNumber = $team
            ->whatsappNumber()
            ->select([
                'id',
                'phone_number',
                'display_phone_number',
                'verified_name',
                'is_active',
                'last_connected_at',
                'last_webhook_at',
                'last_connection_check_at',
                'last_connection_error',
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Return Dashboard
        |--------------------------------------------------------------------------
        */
        return Inertia::render(
            'Executive/Dashboard',
            [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,

                    'whatsapp_number' => $whatsappNumber,
                ],

                'stats' => $stats,

                'recentMessages' =>
                    $recentMessages,

                'messageChart' =>
                    $messageChart,
            ]
        );
    }

    public function fetchBitrixLead(
        Request $request,
        BitrixLeadService $bitrixLeadService
    ): RedirectResponse {
        $validated = $request->validate([
            'lead_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        try {
            $result = $bitrixLeadService->fetchAndSync(
                leadId: (int) $validated['lead_id'],
                source: 'manual'
            );

            /** @var \App\Models\Customer $customer */
            $customer = $result['customer'];

            /*
             * ---------------------------------------------------------
             * BASE SUCCESS MESSAGE
             * ---------------------------------------------------------
             */
            if ($result['action'] === 'created') {
                $message =
                    "Lead {$validated['lead_id']} was fetched and " .
                    "customer {$customer->name} was created.";
            } else {
                $message =
                    "Lead {$validated['lead_id']} was fetched and " .
                    "customer {$customer->name} was updated.";
            }

            /*
             * ---------------------------------------------------------
             * ASSIGNED USER CHANGE
             * ---------------------------------------------------------
             */
            if ($result['assignment_changed']) {
                $message .=
                    ' Assigned executive was updated.';
            }

            /*
             * ---------------------------------------------------------
             * TEAM CHANGE
             * ---------------------------------------------------------
             */
            if ($result['team_changed']) {
                $message .=
                    ' Customer team was updated.';
            }

            /*
             * ---------------------------------------------------------
             * OLD OWNER CHANGE
             * ---------------------------------------------------------
             */
            if ($result['old_owner_changed']) {
                $message .=
                    ' Old owner was updated.';
            }

            return back()->with(
                'success',
                $message
            );
        }

        /*
         * Validation errors from BitrixLeadService.
         *
         * Example:
         *
         * No local user mapped to AssignedById.
         * User has no primary team.
         * Invalid phone.
         */ catch (ValidationException $e) {
            throw $e;
        }

        /*
         * Bitrix API failed.
         */ catch (RequestException $e) {
            Log::error(
                'Manual Bitrix lead fetch failed.',
                [
                    'lead_id' =>
                        $validated['lead_id'],

                    'status' =>
                        $e->response?->status(),

                    'message' =>
                        $e->getMessage(),

                    'user_id' =>
                        $request->user()?->id,
                ]
            );

            return back()->withErrors([
                'lead_id' =>
                    'Unable to fetch this lead from Bitrix. ' .
                    'Please verify the Lead ID and try again.',
            ]);
        }

        /*
         * Any unexpected error.
         */ catch (\Throwable $e) {
            Log::error(
                'Manual Bitrix lead synchronization failed.',
                [
                    'lead_id' =>
                        $validated['lead_id'],

                    'user_id' =>
                        $request->user()?->id,

                    'message' =>
                        $e->getMessage(),

                    'exception' =>
                        $e,
                ]
            );

            return back()->withErrors([
                'lead_id' =>
                    'The lead could not be synchronized: ' .
                    $e->getMessage(),
            ]);
        }
    }

}
