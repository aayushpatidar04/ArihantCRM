<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsappSetting;
use App\Models\Team;
use App\Models\WhatsappNumber;
use App\Models\WhatsappTemplate;
use App\Services\MetaWhatsappService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WhatsAppNumberController extends Controller
{

    public function __construct(
        private MetaWhatsappService $metaWhatsappService
    ) {
    }

    private function authorizeSuperAdmin(): void
    {
        if (!Auth::user()->isSuperAdmin()) {
            abort(403, 'Only super-admin can access this area.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $numbers = WhatsappNumber::query()
            ->with([
                'metaWhatsappSetting:id,name,app_id',
                'teams:id,name,whatsapp_number_id',
            ])
            ->when(
                $request->search,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where(
                            'phone_number',
                            'like',
                            "%{$search}%"
                        )
                            ->orWhere(
                                'display_phone_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'verified_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'phone_number_id',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $request->status === 'active',
                fn($query) => $query->where('is_active', true)
            )
            ->when(
                $request->status === 'inactive',
                fn($query) => $query->where('is_active', false)
            )
            ->when(
                $request->team === 'assigned',
                fn($query) => $query->whereHas('teams')
            )
            ->when(
                $request->team === 'unassigned',
                fn($query) => $query->whereDoesntHave('teams')
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render(
            'SuperAdmin/WhatsAppNumbers/Index',
            [
                'numbers' => $numbers,

                'filters' => $request->only([
                    'search',
                    'status',
                    'team',
                ]),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(): Response
    {
        $this->authorizeSuperAdmin();

        $settings = MetaWhatsappSetting::active()
            ->select(
                'id',
                'name',
                'app_id'
            )
            ->orderBy('name')
            ->get();

        $teams = Team::query()
            ->where('is_active', true)
            ->select(
                'id',
                'name',
                'whatsapp_number_id'
            )
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'SuperAdmin/WhatsAppNumbers/Create',
            [
                'metaSettings' => $settings,
                'teams' => $teams,
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
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'meta_whatsapp_setting_id' => [
                'required',
                'integer',
                'exists:meta_whatsapp_settings,id',
            ],

            'phone_number_id' => [
                'required',
                'string',
                'max:191',
                'unique:whatsapp_numbers,phone_number_id',
            ],

            'waba_id' => [
                'nullable',
                'string',
                'max:191',
            ],

            'business_account_id' => [
                'nullable',
                'string',
                'max:191',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:30',
                'unique:whatsapp_numbers,phone_number',
            ],

            'display_phone_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'verified_name' => [
                'nullable',
                'string',
                'max:191',
            ],

            'access_token' => [
                'required',
                'string',
            ],

            'team_id' => [
                'nullable',
                'integer',
                'exists:teams,id',
            ],

            'is_active' => [
                'boolean',
            ],
        ]);

        DB::beginTransaction();

        try {
            if (!empty($data['team_id'])) {
                $team = Team::query()
                    ->lockForUpdate()
                    ->findOrFail($data['team_id']);

                if ($team->whatsapp_number_id) {
                    return back()
                        ->withInput()
                        ->with(
                            'error',
                            "Team '{$team->name}' already has a WhatsApp number assigned."
                        );
                }
            }

            $number = WhatsappNumber::create([
                'meta_whatsapp_setting_id' =>
                    $data['meta_whatsapp_setting_id'],

                'phone_number_id' =>
                    $data['phone_number_id'],

                'waba_id' =>
                    $data['waba_id'] ?? null,

                'business_account_id' =>
                    $data['business_account_id'] ?? null,

                'phone_number' =>
                    $data['phone_number'],

                'display_phone_number' =>
                    $data['display_phone_number'] ?? null,

                'verified_name' =>
                    $data['verified_name'] ?? null,

                'access_token' =>
                    $data['access_token'],

                'is_active' =>
                    $data['is_active'] ?? true,
            ]);

            if (!empty($data['team_id'])) {
                Team::whereKey($data['team_id'])
                    ->update([
                        'whatsapp_number_id' => $number->id,
                    ]);
            }

            DB::commit();

            return redirect()
                ->route(
                    'superadmin.whatsapp-numbers.show',
                    $number
                )
                ->with(
                    'success',
                    'WhatsApp number added successfully.'
                );

        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Failed to add WhatsApp number: ' .
                    $e->getMessage()
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(WhatsappNumber $whatsappNumber): Response
    {
        $this->authorizeSuperAdmin();

        $whatsappNumber->load([
            'metaWhatsappSetting:id,name,app_id,is_active',
            'teams:id,name,whatsapp_number_id',
        ]);

        $teams = Team::query()
            ->where('is_active', true)
            ->select(
                'id',
                'name',
                'whatsapp_number_id'
            )
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'SuperAdmin/WhatsAppNumbers/Show',
            [
                'whatsappNumber' => [
                    'id' =>
                        $whatsappNumber->id,

                    'phone_number_id' =>
                        $whatsappNumber->phone_number_id,

                    'waba_id' =>
                        $whatsappNumber->waba_id,

                    'business_account_id' =>
                        $whatsappNumber->business_account_id,

                    'phone_number' =>
                        $whatsappNumber->phone_number,

                    'display_phone_number' =>
                        $whatsappNumber->display_phone_number,

                    'verified_name' =>
                        $whatsappNumber->verified_name,

                    'is_active' =>
                        $whatsappNumber->is_active,

                    'last_connected_at' =>
                        $whatsappNumber->last_connected_at,

                    'last_webhook_at' =>
                        $whatsappNumber->last_webhook_at,

                    'created_at' =>
                        $whatsappNumber->created_at,

                    'updated_at' =>
                        $whatsappNumber->updated_at,

                    'meta_whatsapp_setting' =>
                        $whatsappNumber->metaWhatsappSetting,

                    'teams' => $whatsappNumber->teams,

                    'quality_rating' => $whatsappNumber->quality_rating,
                    'code_verification_status' => $whatsappNumber->code_verification_status,
                    'last_connection_check_at' => $whatsappNumber->last_connection_check_at
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(WhatsappNumber $whatsappNumber): Response
    {
        $this->authorizeSuperAdmin();

        $whatsappNumber->load(
            'metaWhatsappSetting:id,name,app_id'
        );

        $settings = MetaWhatsappSetting::active()
            ->select(
                'id',
                'name',
                'app_id'
            )
            ->orderBy('name')
            ->get();

        $teams = Team::query()
            ->where('is_active', true)
            ->select(
                'id',
                'name',
                'whatsapp_number_id'
            )
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'SuperAdmin/WhatsAppNumbers/Edit',
            [
                'whatsappNumber' => [
                    'id' =>
                        $whatsappNumber->id,

                    'meta_whatsapp_setting_id' =>
                        $whatsappNumber->meta_whatsapp_setting_id,

                    'phone_number_id' =>
                        $whatsappNumber->phone_number_id,

                    'waba_id' =>
                        $whatsappNumber->waba_id,

                    'business_account_id' =>
                        $whatsappNumber->business_account_id,

                    'phone_number' =>
                        $whatsappNumber->phone_number,

                    'display_phone_number' =>
                        $whatsappNumber->display_phone_number,

                    'verified_name' =>
                        $whatsappNumber->verified_name,

                    'is_active' =>
                        $whatsappNumber->is_active,
                ],

                'metaSettings' => $settings,
                'teams' => $teams,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, WhatsappNumber $whatsappNumber): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'meta_whatsapp_setting_id' => [
                'required',
                'integer',
                'exists:meta_whatsapp_settings,id',
            ],

            'phone_number_id' => [
                'required',
                'string',
                'max:191',
                'unique:whatsapp_numbers,phone_number_id,' .
                $whatsappNumber->id,
            ],

            'waba_id' => [
                'nullable',
                'string',
                'max:191',
            ],

            'business_account_id' => [
                'nullable',
                'string',
                'max:191',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:30',
                'unique:whatsapp_numbers,phone_number,' .
                $whatsappNumber->id,
            ],

            'display_phone_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'verified_name' => [
                'nullable',
                'string',
                'max:191',
            ],

            'access_token' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'boolean',
            ],
        ]);

        // dd([
        //     'data_access_token' => $data['access_token'] ?? null,
        //     'model_before' => $whatsappNumber->access_token,
        // ]);

        if (empty($data['access_token'])) {
            unset($data['access_token']);
        }

        $whatsappNumber->update($data);

        return back()->with(
            'success',
            'WhatsApp number updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(WhatsappNumber $whatsappNumber): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        DB::transaction(function () use ($whatsappNumber) {

            Team::where(
                'whatsapp_number_id',
                $whatsappNumber->id
            )->update([
                        'whatsapp_number_id' => null,
                    ]);

            $whatsappNumber->delete();
        });

        return redirect()
            ->route(
                'superadmin.whatsapp-numbers.index'
            )
            ->with(
                'success',
                'WhatsApp number removed successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle
    |--------------------------------------------------------------------------
    */

    public function toggleActive(WhatsappNumber $whatsappNumber): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $whatsappNumber->update([
            'is_active' =>
                !$whatsappNumber->is_active,
        ]);

        return back()->with(
            'success',
            $whatsappNumber->is_active
            ? 'WhatsApp number activated.'
            : 'WhatsApp number deactivated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Assign Team
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Test Meta Connection
    |--------------------------------------------------------------------------
    */

    public function testConnection(WhatsappNumber $whatsappNumber, MetaWhatsappService $metaWhatsappService): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $result = $metaWhatsappService->testConnection(
                $whatsappNumber
            );

            $whatsappNumber->update([
                'display_phone_number' =>
                    $result['display_phone_number']
                    ?? $whatsappNumber->display_phone_number,

                'verified_name' =>
                    $result['verified_name']
                    ?? $whatsappNumber->verified_name,

                'quality_rating' => $result['quality_rating']
                    ?? $whatsappNumber->quality_rating,

                'code_verification_status' => $result['code_verification_status']
                    ?? $whatsappNumber->code_verification_status,

                'last_connected_at' => now(),
                'last_connection_check_at' => now(),

                'last_connection_error' => null,
            ]);

            return back()->with(
                'success',
                'WhatsApp number successfully verified with Meta.'
            );

        } catch (Throwable $e) {

            report($e);

            $whatsappNumber->update([
                'last_connection_error' => $e->getMessage(),
            ]);

            return back()->with(
                'error',
                'Unable to connect to Meta: ' .
                $e->getMessage()
            );
        }
    }

    public function syncTemplates(WhatsappNumber $whatsappNumber, MetaWhatsappService $metaWhatsappService): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $count = $metaWhatsappService->syncTemplates(
                $whatsappNumber
            );

            return back()->with(
                'success',
                "{$count} WhatsApp templates synchronized from Meta."
            );

        } catch (Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to synchronize templates: ' . $e->getMessage()
            );
        }
    }

    public function sendTemplate(Request $request, WhatsAppNumber $whatsappNumber, WhatsappTemplate $whatsappTemplate): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        /*
         * Security:
         *
         * Make sure the selected template actually belongs
         * to the WhatsApp number we are sending from.
         */
        if (
            $whatsappTemplate->whatsapp_number_id
            !== $whatsappNumber->id
        ) {
            abort(404);
        }

        $data = $request->validate([
            'to' => [
                'required',
                'string',
                'max:30',
            ],

            'components' => [
                'nullable',
                'array',
            ],

            'components.*.type' => [
                'required_with:components',
                'string',
                'in:header,body,button',
            ],

            'components.*.parameters' => [
                'nullable',
                'array',
            ],
        ]);

        try {
            $result = $this->metaWhatsappService->sendTemplate(
                $whatsappNumber,
                $data['to'],
                $whatsappTemplate,
                $data['components'] ?? []
            );

            /*
             * Meta normally returns:
             *
             * messages: [
             *     [
             *         "id" => "wamid...."
             *     ]
             * ]
             */
            $wamid = data_get(
                $result,
                'messages.0.id'
            );

            /*
             * For the initial Super Admin test, we don't
             * necessarily have a customer_id.
             *
             * Therefore don't force creation in messages yet
             * unless your messages.customer_id is nullable.
             */
            Log::info(
                'WhatsApp template sent successfully.',
                [
                    'whatsapp_number_id' => $whatsappNumber->id,
                    'template_id' => $whatsappTemplate->id,
                    'template_name' => $whatsappTemplate->name,
                    'to' => $data['to'],
                    'wamid' => $wamid,
                ]
            );

            return back()->with(
                'success',
                'Template sent successfully.'
                . ($wamid ? " Message ID: {$wamid}" : '')
            );

        } catch (Throwable $e) {

            report($e);

            return back()->with(
                'error',
                'Failed to send template: '
                . $e->getMessage()
            );
        }
    }
}