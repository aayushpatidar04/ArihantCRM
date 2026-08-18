<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsappSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MetaWhatsappSettingController extends Controller
{
    private function authorizeSuperAdmin(): void
    {
        if (! Auth::user()->isSuperAdmin()) {
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

        $settings = MetaWhatsappSetting::query()
            ->withCount('whatsappNumbers')
            ->with([
                'whatsappNumbers:id,meta_whatsapp_setting_id,phone_number,display_phone_number,verified_name,is_active',
            ])
            ->when(
                $request->search,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('app_id', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $request->status === 'active',
                fn ($query) => $query->where('is_active', true)
            )
            ->when(
                $request->status === 'inactive',
                fn ($query) => $query->where('is_active', false)
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render(
            'SuperAdmin/MetaWhatsappSettings/Index',
            [
                'settings' => $settings,
                'filters' => $request->only([
                    'search',
                    'status',
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

        return Inertia::render(
            'SuperAdmin/MetaWhatsappSettings/Create'
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
            'name' => [
                'required',
                'string',
                'max:191',
            ],

            'app_id' => [
                'required',
                'string',
                'max:191',
                'unique:meta_whatsapp_settings,app_id',
            ],

            'app_secret' => [
                'required',
                'string',
                'max:1000',
            ],

            'verify_token' => [
                'required',
                'string',
                'max:255',
            ],

            'webhook_url' => [
                'nullable',
                'url',
                'max:1000',
            ],

            'is_active' => [
                'boolean',
            ],
        ]);

        DB::beginTransaction();

        try {
            $setting = MetaWhatsappSetting::create([
                'name' => $data['name'],
                'app_id' => $data['app_id'],
                'app_secret' => $data['app_secret'],
                'verify_token' => $data['verify_token'],
                'webhook_url' => $data['webhook_url'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            DB::commit();

            return redirect()
                ->route(
                    'superadmin.meta-whatsapp-settings.index'
                )
                ->with(
                    'success',
                    "Meta WhatsApp app '{$setting->name}' created successfully."
                );

        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Failed to create Meta WhatsApp app.'
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(MetaWhatsappSetting $metaWhatsappSetting): Response {
        $this->authorizeSuperAdmin();

        $metaWhatsappSetting->loadCount(
            'whatsappNumbers'
        );

        return Inertia::render(
            'SuperAdmin/MetaWhatsappSettings/Edit',
            [
                'setting' => [
                    'id' => $metaWhatsappSetting->id,
                    'name' => $metaWhatsappSetting->name,
                    'app_id' => $metaWhatsappSetting->app_id,
                    'webhook_url' => $metaWhatsappSetting->webhook_url,
                    'is_active' => $metaWhatsappSetting->is_active,
                    'whatsapp_numbers_count' =>
                        $metaWhatsappSetting->whatsapp_numbers_count,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, MetaWhatsappSetting $metaWhatsappSetting): RedirectResponse {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
            ],

            'app_id' => [
                'required',
                'string',
                'max:191',
                'unique:meta_whatsapp_settings,app_id,' .
                    $metaWhatsappSetting->id,
            ],

            'app_secret' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'access_token' => [
                'nullable',
                'string',
            ],

            'verify_token' => [
                'nullable',
                'string',
                'max:255',
            ],

            'webhook_url' => [
                'nullable',
                'url',
                'max:1000',
            ],

            'is_active' => [
                'boolean',
            ],
        ]);

        if (
            empty($data['app_secret'])
        ) {
            unset($data['app_secret']);
        }

        if (
            empty($data['access_token'])
        ) {
            unset($data['access_token']);
        }

        if (
            empty($data['verify_token'])
        ) {
            unset($data['verify_token']);
        }

        $metaWhatsappSetting->update($data);

        return back()->with(
            'success',
            'Meta WhatsApp settings updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(MetaWhatsappSetting $metaWhatsappSetting): RedirectResponse {
        $this->authorizeSuperAdmin();

        if (
            $metaWhatsappSetting
                ->whatsappNumbers()
                ->exists()
        ) {
            return back()->with(
                'error',
                'This Meta app cannot be deleted while WhatsApp numbers are connected to it.'
            );
        }

        $metaWhatsappSetting->delete();

        return redirect()
            ->route(
                'superadmin.meta-whatsapp-settings.index'
            )
            ->with(
                'success',
                'Meta WhatsApp app deleted successfully.'
            );
    }

    public function show(MetaWhatsappSetting $metaWhatsappSetting): Response {
        $this->authorizeSuperAdmin();

        $metaWhatsappSetting->load([
            'whatsappNumbers' => function ($query) {
                $query
                    ->select(
                        'id',
                        'meta_whatsapp_setting_id',
                        'phone_number_id',
                        'waba_id',
                        'business_account_id',
                        'phone_number',
                        'display_phone_number',
                        'verified_name',
                        'is_active',
                        'last_connected_at',
                        'last_webhook_at'
                    )
                    ->withCount('teams')
                    ->latest('id');
            },
        ]);

        return Inertia::render(
            'SuperAdmin/MetaWhatsappSettings/Show',
            [
                'metaWhatsappSetting' => [
                    'id' => $metaWhatsappSetting->id,
                    'name' => $metaWhatsappSetting->name,
                    'app_id' => $metaWhatsappSetting->app_id,
                    'business_portfolio_id' =>
                        $metaWhatsappSetting->business_portfolio_id,
                    'is_active' => $metaWhatsappSetting->is_active,
                    'created_at' => $metaWhatsappSetting->created_at,
                    'updated_at' => $metaWhatsappSetting->updated_at,

                    'whatsapp_numbers' =>
                        $metaWhatsappSetting->whatsappNumbers,
                ],
            ]
        );
    }
}