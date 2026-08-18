<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappNumber;
use App\Models\WhatsappTemplate;
use App\Services\MetaWhatsappService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WhatsAppTemplateController extends Controller
{
    private function authorizeSuperAdmin(): void
    {
        if (! Auth::user()->hasRole('super_admin')) {
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

        $templates = WhatsappTemplate::query()
            ->with([
                'whatsappNumber:id,phone_number,display_phone_number,verified_name',
            ])
            ->when(
                $request->search,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('language', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%")
                            ->orWhereHas(
                                'whatsappNumber',
                                function ($numberQuery) use ($search) {
                                    $numberQuery
                                        ->where(
                                            'phone_number',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'display_phone_number',
                                            'like',
                                            "%{$search}%"
                                        );
                                }
                            );
                    });
                }
            )
            ->when(
                $request->status,
                fn ($query, $status) =>
                    $query->where('status', $status)
            )
            ->when(
                $request->category,
                fn ($query, $category) =>
                    $query->where('category', $category)
            )
            ->when(
                $request->enabled === 'yes',
                fn ($query) =>
                    $query->where('is_enabled', true)
            )
            ->when(
                $request->enabled === 'no',
                fn ($query) =>
                    $query->where('is_enabled', false)
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render(
            'SuperAdmin/WhatsAppTemplates/Index',
            [
                'templates' => $templates,

                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                    'category' => $request->category,
                    'enabled' => $request->enabled,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(WhatsappTemplate $whatsappTemplate): Response {
        $this->authorizeSuperAdmin();

        $whatsappTemplate->load([
            'whatsappNumber' => function ($query) {
                $query->select(
                    'id',
                    'phone_number',
                    'display_phone_number',
                    'verified_name',
                    'phone_number_id',
                    'waba_id',
                    'meta_whatsapp_setting_id'
                );
            },
        ]);

        return Inertia::render(
            'SuperAdmin/WhatsAppTemplates/Show',
            [
                'template' => $whatsappTemplate,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(WhatsappTemplate $whatsappTemplate): Response {
        $this->authorizeSuperAdmin();

        $whatsappTemplate->load([
            'whatsappNumber:id,phone_number,display_phone_number,verified_name',
        ]);

        return Inertia::render(
            'SuperAdmin/WhatsAppTemplates/Edit',
            [
                'template' => $whatsappTemplate,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update local configuration
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, WhatsappTemplate $whatsappTemplate): RedirectResponse {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'is_enabled' => [
                'required',
                'boolean',
            ],

            'header_media_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'variables' => [
                'nullable',
                'array',
            ],

            'variables.*' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $oldConfig = $whatsappTemplate->local_config ?? [];

        $localConfig = [
            'header_media_url' =>
                $data['header_media_url'] ?? null,

            'variables' =>
                $data['variables'] ?? [],
        ];

        $whatsappTemplate->update([
            'is_enabled' => $data['is_enabled'],
            'local_config' => $localConfig,
        ]);

        return redirect()
            ->route(
                'superadmin.whatsapp-templates.show',
                $whatsappTemplate
            )
            ->with(
                'success',
                'Template configuration updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle
    |--------------------------------------------------------------------------
    */

    public function toggle(WhatsappTemplate $whatsappTemplate): RedirectResponse {
        $this->authorizeSuperAdmin();

        $whatsappTemplate->update([
            'is_enabled' => ! $whatsappTemplate->is_enabled,
        ]);

        return back()->with(
            'success',
            $whatsappTemplate->is_enabled
                ? 'Template enabled.'
                : 'Template disabled.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */

    public function sync(WhatsappNumber $whatsappNumber): RedirectResponse {
        $this->authorizeSuperAdmin();

        try {
            $count = app(
                MetaWhatsappService::class
            )->syncTemplates($whatsappNumber);

            return back()->with(
                'success',
                "{$count} template(s) synchronized successfully."
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Template synchronization failed: ' .
                    $e->getMessage()
            );
        }
    }

    public function sendTest(Request $request, WhatsappNumber $whatsappNumber, WhatsappTemplate $whatsappTemplate): RedirectResponse {
        $this->authorizeSuperAdmin();

        // Security: template must belong to this WhatsApp number.
        if ($whatsappTemplate->whatsapp_number_id !== $whatsappNumber->id) {
            abort(404);
        }

        if (! $whatsappNumber->is_active) {
            return back()->with(
                'error',
                'This WhatsApp number is inactive.'
            );
        }

        if ($whatsappTemplate->status !== 'APPROVED') {
            return back()->with(
                'error',
                'Only approved WhatsApp templates can be sent.'
            );
        }

        $data = $request->validate([
            'to' => [
                'required',
                'string',
                'max:30',
            ],

            'body_variables' => [
                'nullable',
                'array',
            ],

            'body_variables.*' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'header_variables' => [
                'nullable',
                'array',
            ],

            'header_variables.*' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'header_media_url' => [
                'nullable',
                'url',
                'max:2048',
            ],
        ]);

        try {
            $components = $whatsappTemplate->components ?? [];
            $localConfig = $whatsappTemplate->local_config ?? [];
            $templateComponents = [];

            /*
             * BODY variables
             */
            $bodyVariables = $data['body_variables'] ?? [];

            if (empty($bodyVariables) && ! empty($localConfig['variables'])) {
                $bodyVariables = $localConfig['variables'];
            }
            
            if (! empty($bodyVariables)) {
                $parameters = [];

                foreach ($bodyVariables as $value) {
                    $parameters[] = [
                        'type' => 'text',
                        'text' => $value,
                    ];
                }

                $templateComponents[] = [
                    'type' => 'body',
                    'parameters' => $parameters,
                ];
            }

            /*
             * HEADER
             *
             * For now we support IMAGE media.
             * Later we can extend this for VIDEO / DOCUMENT.
             */
            $headerComponent = collect($components)->firstWhere('type', 'HEADER');
            $headerMediaUrl = $data['header_media_url'] ?? null;


            if (! $headerMediaUrl && ! empty($localConfig['header_media_url'])) {
                $headerMediaUrl = $localConfig['header_media_url'];
            }

            if (! $headerMediaUrl && $headerComponent) {
                $headerMediaUrl = $headerComponent['example']['header_handle'][0] ?? null;
            }

            if ($headerMediaUrl) {
                $headerComponent = collect($components)
                    ->firstWhere('type', 'HEADER');

                $format = strtoupper(
                    $headerComponent['format'] ?? 'IMAGE'
                );

                $mediaType = strtolower($format);

                if (! in_array(
                    $mediaType,
                    ['image', 'video', 'document']
                )) {
                    return back()->with(
                        'error',
                        'Unsupported template header media type.'
                    );
                }

                $templateComponents[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => $mediaType,
                            $mediaType => [
                                'link' => $headerMediaUrl,
                            ],
                        ],
                    ],
                ];
            }

            /*
             * Meta Graph API version
             */
            $version = config(
                'whatsapp.graph_version',
                'v25.0'
            );

            $endpoint =
                "https://graph.facebook.com/{$version}/"
                . "{$whatsappNumber->phone_number_id}/messages";

            /*
             * Meta WhatsApp template payload
             */
            $payload = [
                'messaging_product' => 'whatsapp',

                'to' => $data['to'],

                'type' => 'template',

                'template' => [
                    'name' =>
                        $whatsappTemplate->name,

                    'language' => [
                        'code' =>
                            $whatsappTemplate->language,
                    ],
                ],
            ];

            if (! empty($templateComponents)) {
                $payload['template']['components'] =
                    $templateComponents;
            }

            $response = Http::withToken(
                $whatsappNumber->access_token
            )
                ->acceptJson()
                ->timeout(30)
                ->post(
                    $endpoint,
                    $payload
                );

            if ($response->failed()) {
                $message =
                    $response->json('error.message')
                    ?? 'Meta API rejected the message.';

                return back()->with(
                    'error',
                    'Template send failed: ' . $message
                );
            }

            $result = $response->json();

            /*
             * Meta returns the WhatsApp message ID.
             */
            $metaMessageId =
                $result['messages'][0]['id']
                ?? null;

            /*
             * We don't have a customer/team context
             * during Super Admin testing, so we don't
             * create a normal customer message here yet.
             *
             * We can later introduce a dedicated
             * test_messages table or test recipient flow.
             */

            return back()->with(
                'success',
                'Template sent successfully.'
                . ($metaMessageId
                    ? " Message ID: {$metaMessageId}"
                    : '')
            );

        } catch (Throwable $e) {

            report($e);

            return back()->with(
                'error',
                'Unable to send template: '
                . $e->getMessage()
            );
        }
    }


}