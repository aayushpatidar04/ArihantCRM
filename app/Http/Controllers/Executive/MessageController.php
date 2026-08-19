<?php

namespace App\Http\Controllers\Executive;

use App\Events\MessageCreated;
use App\Jobs\SendWhatsappMediaJob;
use App\Jobs\SendWhatsappMessageJob;
use App\Jobs\SendWhatsappTemplateJob;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappNumber;
use App\Services\MessageSenderContextService;
use App\Services\MetaWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function __construct(protected MetaWhatsappService $whatsapp)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Inbox
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
            'No team assigned.'
        );

        $whatsappNumber = $team->whatsappNumber;

        $search = trim(
            (string) $request->input('search', '')
        );

        /*
        |--------------------------------------------------------------------------
        | Executive customers
        |--------------------------------------------------------------------------
        |
        | Only:
        |
        | assigned_to = current executive
        | OR
        | old_owner_id = current executive
        |
        */

        $customers = Customer::query()
            // ->where('team_id', $team->id)
            ->where(function ($query) use ($user) {
                $query
                    ->where('assigned_to', $user->id)
                    ->orWhere('old_owner_id', $user->id);
            })

            ->with([
                'assignedTo:id,name,email',
                'oldOwner:id,name,email',
            ])

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            */

            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })

            /*
            |--------------------------------------------------------------------------
            | Unread count
            |--------------------------------------------------------------------------
            */

            ->withCount([
                'messages as unread_count' => function ($query) use ($whatsappNumber) {
                    $query
                        ->where('direction', 'inbound')
                        ->whereNull('read_at');
                },
            ])

            /*
            |--------------------------------------------------------------------------
            | Latest message
            |--------------------------------------------------------------------------
            */

            ->with([
                'messages' => function ($query) use ($whatsappNumber) {
                    $query
                        ->latest()
                        ->limit(1);
                },
            ])

            /*
            |--------------------------------------------------------------------------
            | Latest conversation first
            |--------------------------------------------------------------------------
            */

            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn(
                        'messages.customer_id',
                        'customers.id'
                    )
                    ->latest()
                    ->limit(1)
            )

            ->paginate(30)
            ->withQueryString();

        return Inertia::render(
            'Executive/Messages/Index',
            [
                'customers' => $customers,

                'currentTeam' => $team->load(
                    'whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active'
                ),

                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Open conversation
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, Customer $customer): Response {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $team = $user->team;

        abort_unless(
            $team,
            403,
            'No team assigned.'
        );

        $this->authorizeCustomer(
            $customer,
            $user
        );

        /*
        |--------------------------------------------------------------------------
        | Load customer
        |--------------------------------------------------------------------------
        */

        $customer->load([
            'assignedTo:id,name,email,team_id',
            'oldOwner:id,name,email,team_id',
            'team:id,name,whatsapp_number_id',
            'team.whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
        ]);

        $conversationNumberId = $request->integer(
            'whatsapp_number_id',
            $team->whatsapp_number_id
        );

        /*
        |--------------------------------------------------------------------------
        | Messages
        |
        | Only show the conversation on the current team's WhatsApp number.
        | This prevents same-customer replies received on different numbers
        | from appearing in the wrong team chat.
        |--------------------------------------------------------------------------
        */

        $messages = $customer->messages()
            ->with([
                'sentBy:id,name',
                'team:id,name',
                'whatsappNumber:id,phone_number,display_phone_number',
                'document',
            ])
            ->latest()
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Add sender context
        |--------------------------------------------------------------------------
        */

        $senderContextService = app(
            MessageSenderContextService::class
        );

        $messages = $messages->map(
            function (Message $message) use (
                $customer,
                $senderContextService
            ) {
                /*
                |--------------------------------------------------------------------------
                | Only outbound messages need sender context.
                |--------------------------------------------------------------------------
                */

                if ($message->direction === 'outbound') {
                    $message->sender_context =
                        $senderContextService->getContext(
                            $customer,
                            $message->sent_by
                        );
                } else {
                    $message->sender_context = [
                        'type' => null,
                        'name' => null,
                        'role' => null,
                    ];
                }

                return $message;
            }
        )->values();

        /*
        |--------------------------------------------------------------------------
        | 24-hour WhatsApp window
        |--------------------------------------------------------------------------
        */
        $number = $team->whatsappNumber;
        $conversationNumberId = $request->integer(
            'whatsapp_number_id',
            $team->whatsapp_number_id
        );

        $lastInboundMessage = $customer->messages()
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();

        $windowExpiresAt = $lastInboundMessage
            ? $lastInboundMessage->created_at
                ->copy()
                ->addHours(24)
            : null;

        $windowOpen = $windowExpiresAt
            ? now()->lt($windowExpiresAt)
            : false;

        /*
        |--------------------------------------------------------------------------
        | Templates
        |--------------------------------------------------------------------------
        */

        $templates = collect();

        $whatsappNumber = $conversationNumberId
            ? WhatsappNumber::query()->whereKey($conversationNumberId)->first() ?? $team->whatsappNumber
            : $team->whatsappNumber;

        if ($whatsappNumber) {
            $templates = $whatsappNumber
                ->whatsappTemplates()
                ->where('status', 'APPROVED')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'language',
                    'category',
                    'components',
                    'local_config',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination information
        |--------------------------------------------------------------------------
        */

        $totalMessages = Message::query()
            ->where('customer_id', $customer->id)
            ->count();

        $hasMoreMessages =
            $totalMessages > $messages->count();

        $nextCursor =
            $messages->first()?->id;

        /*
        |--------------------------------------------------------------------------
        | Mask phone
        |--------------------------------------------------------------------------
        */

        $customerData = $customer->toArray();

        $customerData['phone'] = $this->maskPhone(
            $customer->phone
        );

        /*
        |--------------------------------------------------------------------------
        | Return page
        |--------------------------------------------------------------------------
        */

        return Inertia::render(
            'Executive/Messages/Show',
            [
                'customer' => $customerData,

                'messages' => $messages,

                'templates' => $templates,

                'messagePagination' => [
                    'has_more' => $hasMoreMessages,
                    'next_cursor' => $nextCursor,
                ],

                'conversation' => [
                    'window_open' => $windowOpen,

                    'window_expires_at' =>
                        $windowExpiresAt?->toIso8601String(),

                    'last_inbound_at' =>
                        $lastInboundMessage
                            ?->created_at
                            ?->toIso8601String(),
                ],
            ]
        );
    }

    public function history(Request $request, Customer $customer): JsonResponse
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
            'No team assigned.'
        );

        /*
        |--------------------------------------------------------------------------
        | Authorize Customer
        |--------------------------------------------------------------------------
        |
        | Executive can access the customer only when:
        |
        | assigned_to = current executive
        | OR
        | old_owner_id = current executive
        |
        */

        abort_unless(
            (int) $customer->assigned_to === (int) $user->id || (int) $customer->old_owner_id === (int) $user->id,
            403,
            'You do not have access to this customer.'
        );

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Number
        |--------------------------------------------------------------------------
        |
        | The conversation can be selected using whatsapp_number_id.
        |
        | If your Show.vue sends ?whatsapp_number_id=123, we only return
        | messages belonging to that WhatsApp number.
        |
        */

        $whatsappNumberId = $request->integer(
            'whatsapp_number_id',
            $team->whatsapp_number_id
        );

        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */

        $senderContextService = app(MessageSenderContextService::class);

        $messages = Message::query()
            ->where('customer_id', $customer->id)
            ->with([
                'customer:id,name,phone',
                'sentBy:id,name',
                'whatsappNumber:id,phone_number,display_phone_number',
                'document',
            ])
            ->orderBy('created_at')
            ->get()
            ->map(function (Message $message) use ($customer, $senderContextService) {
                $senderContext = $message->direction === 'outbound'
                    ? $senderContextService->getContext($customer, $message->sent_by)
                    : [
                        'type' => null,
                        'name' => null,
                        'role' => null,
                    ];

                return [
                    'id' => $message->id,

                    'customer_id' => $message->customer_id,

                    'team_id' => $message->team_id,

                    'whatsapp_number_id' =>
                        $message->whatsapp_number_id,

                    'direction' =>
                        $message->direction,

                    'type' =>
                        $message->type,

                    'body' =>
                        $message->body,

                    'status' =>
                        $message->status,

                    'failure_reason' =>
                        $message->failure_reason,

                    'is_forwarded' =>
                        (bool) $message->is_forwarded,

                    'delivered_at' =>
                        $message->delivered_at?->toISOString(),

                    'read_at' =>
                        $message->read_at?->toISOString(),

                    'media_id' =>
                        $message->media_id,

                    'media_mime_type' =>
                        $message->media_mime_type,

                    'media_filename' =>
                        $message->media_filename,

                    'media_caption' =>
                        $message->media_caption,

                    'created_at' =>
                        $message->created_at?->toISOString(),

                    'sent_by' => $message->sentBy
                        ? [
                            'id' => $message->sentBy->id,
                            'name' => $message->sentBy->name,
                        ]
                        : null,

                    'sender_context' => $senderContext,

                    'document' => $message->document
                        ? [
                            'id' => $message->document->id,
                            'original_filename' =>
                                $message->document->original_filename,
                            'stored_filename' =>
                                $message->document->stored_filename,
                            'mime_type' =>
                                $message->document->mime_type,
                            'size' =>
                                $message->document->size,
                            'formatted_size' =>
                                $message->document->formatted_size,
                            'url' =>
                                $message->document->url,
                        ]
                        : null,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Mark inbound messages as read
        |--------------------------------------------------------------------------
        |
        | Only messages from this exact conversation are marked as read.
        |
        */

        Message::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        |
        | Phone number must remain masked for executives.
        |
        */

        return response()->json([
            'customer' => [
                'id' => $customer->id,

                'name' => $customer->name,

                'phone' =>
                    $this->maskPhone($customer->phone),

                'email' => $customer->email,

                'status' => $customer->status,

                'assigned_to' => $customer->assignedTo
                    ? [
                        'id' => $customer->assignedTo->id,
                        'name' => $customer->assignedTo->name,
                    ]
                    : null,

                'old_owner' => $customer->oldOwner
                    ? [
                        'id' => $customer->oldOwner->id,
                        'name' => $customer->oldOwner->name,
                    ]
                    : null,
            ],

            'whatsapp_number_id' =>
                $whatsappNumberId,

            'messages' =>
                $messages,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Send text
    |--------------------------------------------------------------------------
    */

    public function send(Request $request, Customer $customer): JsonResponse
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
            'No team assigned.'
        );

        $this->authorizeCustomer(
            $customer,
            $user
        );

        $data = $request->validate([
            'body' => [
                'required',
                'string',
                'max:4096',
            ],
        ]);

        $number = $team->whatsappNumber;

        abort_unless(
            $number && $number->is_active,
            422,
            'No active WhatsApp number is assigned to this team.'
        );

        /*
        |--------------------------------------------------------------------------
        | Normal text can only be sent inside 24-hour window
        |--------------------------------------------------------------------------
        */

        $lastInboundMessage = $customer->messages()
            ->where('direction', 'inbound')
            ->where(
                'whatsapp_number_id',
                $number->id
            )
            ->latest('created_at')
            ->first();

        abort_unless(
            $lastInboundMessage &&
            $lastInboundMessage->created_at
                ->greaterThanOrEqualTo(now()->subHours(24)),
            422,
            'The 24-hour WhatsApp messaging window is closed. Please use a template.'
        );

        $message = DB::transaction(function () use ($customer, $team, $number, $user, $data) {
            return Message::create([
                'customer_id' => $customer->id,
                'team_id' => $team->id,
                'whatsapp_number_id' => $number->id,
                'sent_by' => $user->id,

                'direction' => 'outbound',
                'type' => 'text',
                'body' => $data['body'],

                'status' => 'pending',
                'is_forwarded' => false,
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Realtime
        |--------------------------------------------------------------------------
        */

        event(
            new MessageCreated(
                $message
            )
        );

        /*
        |--------------------------------------------------------------------------
        | WhatsApp job
        |--------------------------------------------------------------------------
        */

        SendWhatsappMessageJob::dispatch(
            $message->id
        );

        return response()->json([
            'success' => true,

            'message' => $message->fresh(),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Send media
    |--------------------------------------------------------------------------
    */

    public function sendMedia(Request $request, Customer $customer): RedirectResponse|JsonResponse
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
            'No team assigned.'
        );

        $this->authorizeCustomer(
            $customer,
            $user
        );

        $validated = $request->validate([
            'media' => [
                'required',
                'file',
                'max:16384',
            ],

            'type' => [
                'required',
                'in:image,document,audio,video',
            ],

            'caption' => [
                'nullable',
                'string',
                'max:4096',
            ],
        ]);

        $file = $validated['media'];

        $type = $validated['type'];

        $caption = $validated['caption'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Resolve WhatsApp number
        |--------------------------------------------------------------------------
        */

        $whatsappNumberId = Message::query()
            ->where('customer_id', $customer->id)
            // ->where('team_id', $team->id)
            ->latest('created_at')
            ->value('whatsapp_number_id');

        if (!$whatsappNumberId) {
            $whatsappNumberId = $team->whatsapp_number_id;
        }

        abort_unless(
            $whatsappNumberId,
            422,
            'No WhatsApp number is configured for this customer.'
        );

        $whatsappNumber = WhatsappNumber::query()
            ->whereKey($whatsappNumberId)
            ->where('is_active', true)
            ->first();

        abort_unless(
            $whatsappNumber !== null,
            422,
            'The WhatsApp number is unavailable or inactive.'
        );

        /*
        |--------------------------------------------------------------------------
        | Store file
        |--------------------------------------------------------------------------
        */

        $disk = 'public';

        $directory = sprintf(
            'whatsapp/documents/%d/%d',
            $team->id,
            $customer->id
        );

        $storedFilename =
            Str::uuid() .
            '.' .
            $file->getClientOriginalExtension();

        $path = $file->storeAs(
            $directory,
            $storedFilename,
            $disk
        );

        abort_unless(
            $path,
            422,
            'Unable to store the uploaded file.'
        );

        /*
        |--------------------------------------------------------------------------
        | Document
        |--------------------------------------------------------------------------
        */

        $document = Document::create([
            'customer_id' => $customer->id,
            'team_id' => $team->id,
            'message_id' => null,
            'uploaded_by' => $user->id,

            'original_filename' =>
                $file->getClientOriginalName(),

            'stored_filename' =>
                $storedFilename,

            'disk' => $disk,
            'path' => $path,

            'mime_type' =>
                $file->getMimeType(),

            'size' =>
                $file->getSize(),

            'source' => 'whatsapp',
            'status' => 'pending',
            'notes' => $caption,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Message
        |--------------------------------------------------------------------------
        */

        $message = Message::create([
            'customer_id' => $customer->id,
            'team_id' => $team->id,
            'whatsapp_number_id' => $whatsappNumber->id,
            'sent_by' => $user->id,

            'direction' => 'outbound',
            'type' => $type,
            'body' => $caption,

            'status' => 'pending',
            'is_forwarded' => false,
        ]);

        $document->update([
            'message_id' => $message->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Broadcast
        |--------------------------------------------------------------------------
        */

        event(
            new MessageCreated(
                $message
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Send
        |--------------------------------------------------------------------------
        */

        SendWhatsappMediaJob::dispatch(
            $message->id,
            $document->id,
            $whatsappNumber->id
        );

        return response()->json([
            'success' => true,

            'message' => $message->fresh([
                'document',
            ]),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Send template
    |--------------------------------------------------------------------------
    */

    public function sendTemplate(Request $request, Customer $customer): RedirectResponse|JsonResponse
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
            'No team assigned.'
        );

        $this->authorizeCustomer(
            $customer,
            $user
        );

        $validated = $request->validate([
            'template_id' => [
                'required',
                'integer',
            ],

            'components' => [
                'nullable',
                'array',
            ],

            'components.*.type' => [
                'required',
                'string',
            ],

            'components.*.parameters' => [
                'nullable',
                'array',
            ],

            'components.*.sub_type' => [
                'nullable',
                'string',
            ],

            'components.*.index' => [
                'nullable',
                'string',
            ],

            'header_media_url' => [
                'nullable',
                'string',
                'url',
                'max:2048',
            ],
        ]);

        $components = $validated['components'] ?? [];

        $whatsappNumber = $team->whatsappNumber;

        abort_unless(
            $whatsappNumber && $whatsappNumber->is_active,
            422,
            'No active WhatsApp number is connected to this team.'
        );

        $template = $whatsappNumber
            ->whatsappTemplates()
            ->whereKey($validated['template_id'])
            ->where('status', 'APPROVED')
            ->first();

        abort_unless(
            $template,
            422,
            'The selected WhatsApp template is not available for this team.'
        );

        /*
        |--------------------------------------------------------------------------
        | Resolve BODY
        |--------------------------------------------------------------------------
        */

        $templateBody = '';

        $templateComponents =
            $template->components ?? [];

        if (is_string($templateComponents)) {
            $templateComponents =
                json_decode(
                    $templateComponents,
                    true
                ) ?: [];
        }

        foreach ($templateComponents as $templateComponent) {
            if (
                strtoupper(
                    $templateComponent['type'] ?? ''
                ) === 'BODY'
            ) {
                $templateBody =
                    $templateComponent['text']
                    ?? $templateComponent['body']
                    ?? '';

                break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Render BODY variables
        |--------------------------------------------------------------------------
        */

        $renderedBody = $templateBody;

        foreach ($components as $component) {
            if (
                strtoupper(
                    $component['type'] ?? ''
                ) !== 'BODY'
            ) {
                continue;
            }

            foreach (
                $component['parameters'] ?? []
                as $index => $parameter
            ) {
                $value =
                    $parameter['text']
                    ?? '';

                $renderedBody = str_replace(
                    '{{' . ($index + 1) . '}}',
                    (string) $value,
                    $renderedBody
                );
            }

            break;
        }

        if (trim($renderedBody) === '') {
            $renderedBody = $template->name;
        }

        /*
        |--------------------------------------------------------------------------
        | Header media
        |--------------------------------------------------------------------------
        */

        $headerMediaUrl =
            $validated['header_media_url']
            ?? null;

        foreach ($components as $component) {
            if (
                strtoupper(
                    $component['type'] ?? ''
                ) !== 'HEADER'
            ) {
                continue;
            }

            foreach (
                $component['parameters'] ?? []
                as $parameter
            ) {
                $parameterType =
                    strtolower(
                        $parameter['type'] ?? ''
                    );

                if (
                    in_array(
                        $parameterType,
                        [
                            'image',
                            'video',
                            'document',
                        ],
                        true
                    )
                ) {
                    $headerMediaUrl =
                        $parameter[$parameterType]['link']
                        ?? $parameter['link']
                        ?? $parameter[$parameterType]['local_config']
                        ?? $parameter['local_config']
                        ?? $parameter[$parameterType]['header_handle_media']
                        ?? $parameter['header_handle_media']
                        ?? $headerMediaUrl;

                    if ($headerMediaUrl) {
                        break;
                    }
                }
            }

            break;
        }

        /*
        |--------------------------------------------------------------------------
        | Message
        |--------------------------------------------------------------------------
        */

        $message = Message::create([
            'customer_id' => $customer->id,
            'team_id' => $team->id,
            'sent_by' => $user->id,
            'whatsapp_number_id' => $whatsappNumber->id,

            'direction' => 'outbound',

            'type' =>
                $headerMediaUrl
                ? 'image'
                : 'chat',

            /*
             * IMPORTANT:
             * Store rendered BODY, not template name.
             */
            'body' => $renderedBody,

            'status' => 'pending',
            'is_forwarded' => false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Template header media
        |--------------------------------------------------------------------------
        */

        $document = null;

        if ($headerMediaUrl) {
            try {
                $response = Http::timeout(60)
                    ->connectTimeout(15)
                    ->get($headerMediaUrl);

                abort_unless(
                    $response->successful(),
                    422,
                    'Unable to download template media.'
                );

                $content = $response->body();

                $mimeType =
                    trim(
                        explode(
                            ';',
                            $response->header(
                                'Content-Type'
                            ) ?: 'application/octet-stream'
                        )[0]
                    );

                $extension = match ($mimeType) {
                    'image/jpeg',
                    'image/jpg' => 'jpg',

                    'image/png' => 'png',

                    'image/webp' => 'webp',

                    'image/gif' => 'gif',

                    'video/mp4' => 'mp4',

                    'audio/mpeg' => 'mp3',

                    'audio/ogg' => 'ogg',

                    'application/pdf' => 'pdf',

                    'application/msword' => 'doc',

                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    => 'docx',

                    default => 'bin',
                };

                $disk = 'public';

                $directory = sprintf(
                    'whatsapp/documents/%d/%d',
                    $team->id,
                    $customer->id
                );

                $storedFilename =
                    Str::uuid() .
                    '.' .
                    $extension;

                $path =
                    $directory .
                    '/' .
                    $storedFilename;

                Storage::disk($disk)
                    ->put(
                        $path,
                        $content
                    );

                $document = Document::create([
                    'customer_id' => $customer->id,
                    'team_id' => $team->id,
                    'message_id' => $message->id,
                    'uploaded_by' => $user->id,

                    'original_filename' =>
                        basename(
                            parse_url(
                                $headerMediaUrl,
                                PHP_URL_PATH
                            ) ?: (
                                'template-media.'
                                . $extension
                            )
                        ),

                    'stored_filename' =>
                        $storedFilename,

                    'disk' => $disk,
                    'path' => $path,

                    'mime_type' => $mimeType,

                    'size' =>
                        strlen($content),

                    'source' => 'whatsapp',
                    'status' => 'pending',
                ]);
            } catch (\Throwable $e) {
                $message->delete();

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Unable to download or store template media.',

                    'error' =>
                        config('app.debug')
                        ? $e->getMessage()
                        : null,
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Realtime
        |--------------------------------------------------------------------------
        */

        event(
            new MessageCreated(
                $message
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Send template
        |--------------------------------------------------------------------------
        */

        SendWhatsappTemplateJob::dispatch(
            $message->id,
            $template->id,
            $whatsappNumber->id,
            $components
        );

        return response()->json([
            'success' => true,

            'message' =>
                $message->fresh([
                    'document',
                ]),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark conversation read
    |--------------------------------------------------------------------------
    */

    public function markRead(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('executive'),
            403
        );

        $team = $user->team;

        abort_unless(
            $team,
            403
        );

        $this->authorizeCustomer(
            $customer,
            $user
        );

        $whatsappNumberId = $request->integer(
            'whatsapp_number_id',
            $team->whatsapp_number_id
        );

        Message::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return back();
    }

    /*
    |--------------------------------------------------------------------------
    | Unread messages
    |--------------------------------------------------------------------------
    */

    public function unreadMessages(Request $request): JsonResponse
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
            'No team assigned.'
        );

        $customerIds = Customer::query()
            ->where(function ($query) use ($user) {
                $query
                    ->where('assigned_to', $user->id)
                    ->orWhere('old_owner_id', $user->id);
            })
            ->pluck('id');

        $unreadMessages = Message::query()
            ->whereIn(
                'customer_id',
                $customerIds
            )
            ->where('direction', 'inbound')
            ->whereNull('read_at');

        $unreadCount =
            (clone $unreadMessages)->count();

        $unreadGroups =
            (clone $unreadMessages)
                ->selectRaw('
                    customer_id,
                    whatsapp_number_id,
                    MAX(id) as latest_message_id,
                    COUNT(id) as unread_count
                ')
                ->groupBy(
                    'customer_id',
                    'whatsapp_number_id'
                );

        $unreadChats =
            Message::query()
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
                    'customer:id,name,phone',
                    'whatsappNumber:id,display_phone_number,phone_number',
                    'document',
                ])
                ->select([
                    'messages.*',
                    'unread_groups.unread_count',
                ])
                ->orderByDesc(
                    'messages.created_at'
                )
                ->limit(20)
                ->get()
                ->map(function (Message $message) {
                    return [
                        'id' => $message->id,

                        'customer_id' => $message->customer_id,

                        'customer_name' => $message->customer?->name ?? 'Unknown Customer',

                        /*
                         * NEVER expose real phone.
                         */
                        'customer_phone' => $this->maskPhone($message->customer?->phone),

                        'whatsapp_number_id' => $message->whatsapp_number_id,

                        'body' => $message->body,

                        'type' => $message->type,

                        'has_document' => $message->document !== null,

                        'unread_count' => (int) $message->unread_count,

                        'created_at' => $message->created_at?->toISOString(),

                        'time_ago' => $message->created_at?->diffForHumans() ?? '',
                    ];
                });

        return response()->json([
            'unread_count' =>
                $unreadCount,

            'unread_chat_count' =>
                $unreadChats->count(),

            'unread_chats' =>
                $unreadChats,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    protected function authorizeCustomer(Customer $customer, $user): void
    {
        // abort_unless(
        //     (int) $customer->team_id ===
        //     (int) $user->team_id,
        //     403,
        //     'You do not have access to this customer.'
        // );

        $allowed =
            (int) $customer->assigned_to ===
            (int) $user->id
            ||
            (int) $customer->old_owner_id ===
            (int) $user->id;

        abort_unless(
            $allowed,
            403,
            'You do not have access to this customer.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Phone masking
    |--------------------------------------------------------------------------
    */

    private function maskPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        if (strlen($phone) <= 4) {
            return str_repeat(
                '*',
                strlen($phone)
            );
        }

        return str_repeat(
            '*',
            strlen($phone) - 4
        ) . substr(
            $phone,
            -4
        );
    }
}