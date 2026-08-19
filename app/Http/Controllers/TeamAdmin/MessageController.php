<?php

namespace App\Http\Controllers\TeamAdmin;

use App\Http\Controllers\Controller;

use App\Events\MessageCreated;
use App\Events\MessageStatusUpdated;
use App\Jobs\SendWhatsappMediaJob;
use App\Jobs\SendWhatsappMessageJob;
use App\Jobs\SendWhatsappTemplateJob;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Message;
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
use RuntimeException;

class MessageController extends Controller
{
    public function __construct(
        protected MetaWhatsappService $whatsapp
    ) {
    }

    /**
     * WhatsApp inbox.
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

        $whatsappNumber = $team->whatsappNumber;

        $search = trim(
            (string) $request->input('search', '')
        );

        /*
         * Customers visible to this workspace:
         *
         * 1. Customer's assigned user belongs to current team
         * OR
         * 2. Customer's old owner belongs to current team
         *
         * Conversation itself is then filtered by the
         * current team's WhatsApp number.
         */
        $isTeamAdmin = $user->hasRole('team_admin');

        $customers = Customer::query()
            ->with([
                'assignedTo:id,name,team_id',
                'oldOwner:id,name,team_id',
            ])

            /*
            |--------------------------------------------------------------------------
            | Customer Access
            |--------------------------------------------------------------------------
            |
            | Team Admin:
            |   - Can access every customer belonging to the current team.
            |
            | Executive:
            |   - Can access customers assigned to them.
            |   - Can access customers previously owned by them.
            |
            */

            ->where(function ($query) use ($team, $user, $isTeamAdmin) {

                if ($isTeamAdmin) {
                    /*
                    * Team Admin can access every customer of this team,
                    * including unassigned customers.
                    */
                    $query->where('team_id', $team->id);

                    return;
                }

                /*
                * Executive access:
                *
                * Current owner
                */
                $query->whereHas('assignedTo', function ($q) use ($team, $user) {
                    $q->where('users.team_id', $team->id)
                        ->where('users.id', $user->id);
                })

                /*
                * Previous owner
                */
                ->orWhereHas('oldOwner', function ($q) use ($team, $user) {
                    $q->where('users.team_id', $team->id)
                        ->where('users.id', $user->id);
                });
            })

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
            | Unread Messages
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
            | Latest Message
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
            | Sort by Latest Message
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
            'TeamAdmin/Messages/Index',
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

    /**
     * Open a conversation.
     */
    public function show(Request $request, Customer $customer): Response
    {
        $user = $request->user();

        $team = $user->team;

        abort_unless($team, 403);

        abort_unless(
            $customer->assigned_to === $user->id ||
            $customer->old_owner_id === $user->id ||
            $customer->team_id === $team->id,
            403
        );

        $customer->load([
            'assignedTo:id,name,email',
            'oldOwner:id,name,email',
            'team:id,name,whatsapp_number_id',
            'team.whatsappNumber:id,phone_number,display_phone_number,verified_name,is_active',
        ]);

        $conversationNumberId = $request->integer(
            'whatsapp_number_id',
            $team->whatsapp_number_id
        );

        $messages = $customer->messages()
            ->with([
                'sentBy:id,name',
                'team:id,name',
                'whatsappNumber:id,phone_number,display_phone_number',
                'document',
            ])
            ->limit(30)
            ->get();

        $senderContextService = app(MessageSenderContextService::class);

        $messages = $messages->map(function (Message $message) use ($customer, $senderContextService) {
            if ($message->direction === 'outbound') {
                $message->sender_context = $senderContextService->getContext(
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
        })->values();

        /*
         * IMPORTANT:
         * 24-hour window is based ONLY on the latest inbound message.
         */
        $lastInboundMessage = $customer->messages()
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();

        $windowExpiresAt = $lastInboundMessage
            ? $lastInboundMessage->created_at->copy()->addHours(24)
            : null;

        $windowOpen = $windowExpiresAt
            ? now()->lt($windowExpiresAt)
            : false;

        /*
         * Templates belonging to the team's WhatsApp number.
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


        $totalMessages = Message::where(
            'customer_id',
            $customer->id
        )->count();

        $hasMoreMessages = $totalMessages > $messages->count();

        $nextCursor = $messages->first()?->id;

        return Inertia::render('TeamAdmin/Messages/Show', [
            'customer' => $customer,

            'messages' => $messages,

            'templates' => $templates,

            'messagePagination' => [
                'has_more' => $hasMoreMessages,
                'next_cursor' => $nextCursor,
            ],


            'conversation' => [
                'window_open' => $windowOpen,

                'window_expires_at' => $windowExpiresAt?->toIso8601String(),

                'last_inbound_at' =>
                    $lastInboundMessage?->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Send normal text message.
     */
    public function sendText(Request $request, Customer $customer): JsonResponse
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

        $this->authorizeCustomer(
            $customer,
            $team->id
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
            ]);
        });

        event(new MessageCreated($message));
        /*
         * Send asynchronously.
         */
        SendWhatsappMessageJob::dispatch(
            $message->id
        );

        /*
         * Return immediately.
         */
        $freshMessage = $message->fresh();

        $freshMessage->sender_context = app(MessageSenderContextService::class)->getContext(
            $customer,
            $freshMessage->sent_by
        );

        return response()->json([
            'success' => true,
            'message' => $freshMessage,
        ], 201);
    }

    public function sendMedia(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Resolve current workspace
        |--------------------------------------------------------------------------
        */

        $team = $user->team;

        if (!$team) {
            $message = $request->expectsJson()
                ? response()->json([
                    'success' => false,
                    'message' => 'No workspace is selected.',
                ], 422)
                : back()->with('error', 'No workspace is selected.');

            return $message;
        }

        /*
        |--------------------------------------------------------------------------
        | Customer must belong to current workspace
        |--------------------------------------------------------------------------
        */

        if ((int) $customer->team_id !== (int) $team->id) {
            abort(403);
        }

        $isJsonRequest = $request->expectsJson();

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

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
        |
        | Customer conversation is associated with a WhatsApp number
        | through previous messages.
        |
        */

        $whatsappNumberId = Message::query()
            ->where('customer_id', $customer->id)
            ->where('team_id', $team->id)
            ->whereNotNull('whatsapp_number_id')
            ->latest('id')
            ->value('whatsapp_number_id');

        /*
        |--------------------------------------------------------------------------
        | Fallback to workspace configured number
        |--------------------------------------------------------------------------
        */

        if (!$whatsappNumberId) {
            $whatsappNumberId = $team->whatsapp_number_id ?? null;
        }

        if (!$whatsappNumberId) {
            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No WhatsApp number is configured for this workspace.',
                ], 422);
            }

            return back()->with(
                'error',
                'No WhatsApp number is configured for this workspace.'
            );
        }

        $whatsappNumber = WhatsappNumber::query()
            ->whereKey($whatsappNumberId)
            ->where('is_active', true)
            ->first();

        if (!$whatsappNumber) {
            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'The WhatsApp number is unavailable or inactive.',
                ], 422);
            }

            return back()->with(
                'error',
                'The WhatsApp number is unavailable or inactive.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Store local file
        |--------------------------------------------------------------------------
        */

        $disk = 'public';

        $directory = sprintf(
            'whatsapp/documents/%d/%d',
            $team->id,
            $customer->id
        );

        $storedFilename =
            Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs(
            $directory,
            $storedFilename,
            $disk
        );

        if (!$path) {
            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to store the uploaded file.',
                ], 422);
            }

            return back()->with(
                'error',
                'Unable to store the uploaded file.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Document
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

            'encryption_key_id' => null,
        ]);

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

        event(new MessageCreated($message));

        SendWhatsappMediaJob::dispatch(
            $message->id,
            $document->id,
            $whatsappNumber->id,
        );

        if ($isJsonRequest) {
            return response()->json([
                'success' => true,
                'message' => $message->fresh(),
            ], 201);
        }

        return back()->with(
            'success',
            'Media sent successfully.'
        );
    }

    /**
     * Send WhatsApp template.
     */
    public function sendTemplate(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Resolve current workspace
        |--------------------------------------------------------------------------
        */

        $team = $user->team;

        if (!$team) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No workspace is selected.',
                ], 422);
            }

            return back()->with(
                'error',
                'No workspace is selected.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Customer must belong to current workspace
        |--------------------------------------------------------------------------
        */

        abort_unless(
            (int) $customer->team_id === (int) $team->id,
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Validate request
        |--------------------------------------------------------------------------
        */

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

            /*
            |--------------------------------------------------------------------------
            | Optional header media URL
            |--------------------------------------------------------------------------
            |
            | Vue may send this when the header media URL is explicitly available.
            |
            */

            'header_media_url' => [
                'nullable',
                'string',
                'url',
                'max:2048',
            ],
        ]);

        $components = $validated['components'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Resolve WhatsApp number
        |--------------------------------------------------------------------------
        */

        $whatsappNumber = $team->whatsappNumber;

        if (!$whatsappNumber) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No WhatsApp number is connected to this team.',
                ], 422);
            }

            return back()->with(
                'error',
                'No WhatsApp number is connected to this team.'
            );
        }

        if (!$whatsappNumber->is_active) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The team WhatsApp number is inactive.',
                ], 422);
            }

            return back()->with(
                'error',
                'The team WhatsApp number is inactive.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Load template
        |--------------------------------------------------------------------------
        */

        $template = $whatsappNumber
            ->whatsappTemplates()
            ->whereKey($validated['template_id'])
            ->first();

        if (!$template) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'The selected WhatsApp template is not available for this team.',
                ], 422);
            }

            return back()->with(
                'error',
                'The selected WhatsApp template is not available for this team.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find BODY component from template
        |--------------------------------------------------------------------------
        */

        $templateBody = '';

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Adjust this according to your actual WhatsappTemplate DB structure.
        |
        | If your template has:
        |
        |     $template->components
        |
        | and components is JSON, this will work.
        |
        */

        $templateComponents = $template->components ?? [];

        if (is_string($templateComponents)) {
            $templateComponents = json_decode(
                $templateComponents,
                true
            ) ?: [];
        }

        if (is_array($templateComponents)) {
            foreach ($templateComponents as $templateComponent) {
                if (
                    strtoupper($templateComponent['type'] ?? '') === 'BODY'
                ) {
                    $templateBody =
                        $templateComponent['text']
                        ?? $templateComponent['body']
                        ?? '';

                    break;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Render BODY variables
        |--------------------------------------------------------------------------
        |
        | Example template:
        |
        | Hello {{1}}, your order {{2}} is ready.
        |
        | Vue sends:
        |
        | BODY
        | parameters:
        | [
        |     ['type' => 'text', 'text' => 'Aayush'],
        |     ['type' => 'text', 'text' => 'ORD-1001'],
        | ]
        |
        | Result:
        |
        | Hello Aayush, your order ORD-1001 is ready.
        |
        */

        $renderedBody = $templateBody;

        foreach ($components as $component) {
            if (
                strtoupper($component['type'] ?? '') !== 'BODY'
            ) {
                continue;
            }

            $parameters = $component['parameters'] ?? [];

            foreach ($parameters as $index => $parameter) {
                $position = $index + 1;

                $value = '';

                /*
                |--------------------------------------------------------------------------
                | Text parameter
                |--------------------------------------------------------------------------
                */

                if (
                    isset($parameter['type']) &&
                    $parameter['type'] === 'text'
                ) {
                    $value = $parameter['text'] ?? '';
                }

                /*
                |--------------------------------------------------------------------------
                | Fallback
                |--------------------------------------------------------------------------
                |
                | In case Vue sends:
                |
                | ['text' => 'Aayush']
                |
                */

                if ($value === '' && isset($parameter['text'])) {
                    $value = $parameter['text'];
                }

                /*
                |--------------------------------------------------------------------------
                | Replace {{1}}, {{2}}, etc.
                |--------------------------------------------------------------------------
                */

                $renderedBody = str_replace(
                    '{{' . $position . '}}',
                    (string) $value,
                    $renderedBody
                );
            }

            break;
        }

        /*
        |--------------------------------------------------------------------------
        | If template body could not be found, fallback to template name
        |--------------------------------------------------------------------------
        |
        | This is only a safety fallback.
        |
        */

        if (trim($renderedBody) === '') {
            $renderedBody = $template->name;
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve HEADER media
        |--------------------------------------------------------------------------
        */

        $headerMediaUrl = null;

        foreach ($components as $component) {
            if (
                strtoupper($component['type'] ?? '') !== 'HEADER'
            ) {
                continue;
            }

            $parameters = $component['parameters'] ?? [];

            foreach ($parameters as $parameter) {
                /*
                |--------------------------------------------------------------------------
                | IMAGE / VIDEO / DOCUMENT
                |--------------------------------------------------------------------------
                */

                $parameterType = strtolower(
                    $parameter['type'] ?? ''
                );

                if (
                    in_array(
                        $parameterType,
                        ['image', 'video', 'document'],
                        true
                    )
                ) {
                    /*
                    |--------------------------------------------------------------------------
                    | 1. Request-provided media URL
                    |--------------------------------------------------------------------------
                    */

                    $headerMediaUrl =
                        $parameter[$parameterType]['link']
                        ?? $parameter['link']
                        ?? null;

                    if ($headerMediaUrl) {
                        break;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 2. local_config
                    |--------------------------------------------------------------------------
                    */

                    $headerMediaUrl =
                        $parameter[$parameterType]['local_config']
                        ?? $parameter['local_config']
                        ?? null;

                    if ($headerMediaUrl) {
                        break;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 3. header_handle_media
                    |--------------------------------------------------------------------------
                    */

                    $headerMediaUrl =
                        $parameter[$parameterType]['header_handle_media']
                        ?? $parameter['header_handle_media']
                        ?? null;

                    if ($headerMediaUrl) {
                        break;
                    }
                }
            }

            break;
        }

        /*
        |--------------------------------------------------------------------------
        | Also allow direct request header_media_url
        |--------------------------------------------------------------------------
        */

        if (
            !$headerMediaUrl &&
            !empty($validated['header_media_url'])
        ) {
            $headerMediaUrl = $validated['header_media_url'];
        }

        /*
        |--------------------------------------------------------------------------
        | Create Message FIRST
        |--------------------------------------------------------------------------
        |
        | Document needs message_id, so message must exist first.
        |
        */

        $message = Message::create([
            'customer_id' => $customer->id,
            'team_id' => $team->id,
            'sent_by' => $user->id,
            'whatsapp_number_id' => $whatsappNumber->id,

            'direction' => 'outbound',

            /*
            |--------------------------------------------------------------------------
            | Template with media still has a message type.
            |--------------------------------------------------------------------------
            */

            'type' => $headerMediaUrl ? 'image' : 'chat',

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            | Save actual rendered BODY instead of template name.
            |--------------------------------------------------------------------------
            */

            'body' => $renderedBody,

            'status' => 'pending',

            'is_forwarded' => false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create local Document for HEADER media
        |--------------------------------------------------------------------------
        */

        $document = null;

        if ($headerMediaUrl) {
            try {
                /*
                |--------------------------------------------------------------------------
                | Download media
                |--------------------------------------------------------------------------
                */

                $httpResponse = \Illuminate\Support\Facades\Http::timeout(60)
                    ->connectTimeout(15)
                    ->get($headerMediaUrl);

                if (!$httpResponse->successful()) {
                    throw new \RuntimeException(
                        'Unable to download template header media.'
                    );
                }

                $mediaContent = $httpResponse->body();

                /*
                |--------------------------------------------------------------------------
                | Determine MIME type
                |--------------------------------------------------------------------------
                */

                $mimeType =
                    $httpResponse->header('Content-Type')
                    ?: 'application/octet-stream';

                /*
                |--------------------------------------------------------------------------
                | Remove charset from MIME
                |--------------------------------------------------------------------------
                */

                $mimeType = trim(
                    explode(';', $mimeType)[0]
                );

                /*
                |--------------------------------------------------------------------------
                | Determine extension
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | Directory
                |--------------------------------------------------------------------------
                */

                $disk = 'public';

                $directory = sprintf(
                    'whatsapp/documents/%d/%d',
                    $team->id,
                    $customer->id
                );

                /*
                |--------------------------------------------------------------------------
                | Filename
                |--------------------------------------------------------------------------
                */

                $storedFilename =
                    Str::uuid() . '.' . $extension;

                /*
                |--------------------------------------------------------------------------
                | Store downloaded file
                |--------------------------------------------------------------------------
                */

                $path = $directory . '/' . $storedFilename;

                $stored = \Illuminate\Support\Facades\Storage::disk($disk)
                    ->put(
                        $path,
                        $mediaContent
                    );

                if (!$stored) {
                    throw new \RuntimeException(
                        'Unable to store downloaded template media.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Original filename
                |--------------------------------------------------------------------------
                */

                $originalFilename =
                    basename(
                        parse_url(
                            $headerMediaUrl,
                            PHP_URL_PATH
                        ) ?: ('template-media.' . $extension)
                    );

                if (
                    !$originalFilename ||
                    $originalFilename === '/'
                ) {
                    $originalFilename =
                        'template-media.' . $extension;
                }

                /*
                |--------------------------------------------------------------------------
                | Create Document
                |--------------------------------------------------------------------------
                */

                $document = Document::create([
                    'customer_id' => $customer->id,

                    'team_id' => $team->id,

                    /*
                    |--------------------------------------------------------------------------
                    | IMPORTANT:
                    | Link document to message.
                    |--------------------------------------------------------------------------
                    */

                    'message_id' => $message->id,

                    'uploaded_by' => $user->id,

                    'original_filename' => $originalFilename,

                    'stored_filename' => $storedFilename,

                    'disk' => $disk,

                    'path' => $path,

                    'mime_type' => $mimeType,

                    'size' => strlen($mediaContent),

                    'source' => 'whatsapp',

                    'status' => 'pending',

                    'notes' => null,

                    'encryption_key_id' => null,
                ]);
            } catch (\Throwable $e) {
                /*
                |--------------------------------------------------------------------------
                | Delete message if media preparation fails
                |--------------------------------------------------------------------------
                |
                | We don't want a pending message in DB if the media could
                | not be downloaded/stored.
                |
                */

                $message->delete();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Unable to download or store the template header media.',
                        'error' => config('app.debug')
                            ? $e->getMessage()
                            : null,
                    ], 422);
                }

                return back()->with(
                    'error',
                    'Unable to download or store the template header media.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Broadcast message
        |--------------------------------------------------------------------------
        */

        event(
            new MessageCreated(
                $message->fresh()
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Dispatch WhatsApp template job
        |--------------------------------------------------------------------------
        */

        SendWhatsappTemplateJob::dispatch(
            $message->id,
            $template->id,
            $whatsappNumber->id,
            $components
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,

                'message' => $message->fresh(),

                'document' => $document?->fresh(),
            ], 201);
        }

        return back()->with(
            'success',
            'Template message sent successfully.'
        );
    }

    /**
     * Mark conversation read.
     */
    public function markRead(Request $request, Customer $customer): RedirectResponse
    {
        $team = $request->user()->team;

        abort_unless(
            $team,
            403
        );

        $this->authorizeCustomer(
            $customer,
            $team->id
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

    protected function authorizeCustomer(Customer $customer, int $teamId): void{
        $user = request()->user();

        /*
        |--------------------------------------------------------------------------
        | Team Admin
        |--------------------------------------------------------------------------
        |
        | Team Admin can access every customer belonging to the current team,
        | even if the customer has not been assigned to any executive yet.
        |
        */

        if ($user->hasRole('team_admin')) {
            abort_unless(
                (int) $customer->team_id === $teamId,
                403,
                'You do not have access to this customer.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Executives
        |--------------------------------------------------------------------------
        |
        | Executives can access the customer only when:
        |
        | 1. They are the current assigned owner, OR
        | 2. They are the previous owner.
        |
        | The relationship checks also ensure the owner belongs to
        | the current team.
        |
        */

        $allowed = $customer
            ->assignedTo()
            ->where('users.id', $user->id)
            ->where('users.team_id', $teamId)
            ->exists();

        if (!$allowed) {
            $allowed = $customer
                ->oldOwner()
                ->where('users.id', $user->id)
                ->where('users.team_id', $teamId)
                ->exists();
        }

        abort_unless(
            $allowed,
            403,
            'You do not have access to this customer.'
        );
    }

    public function history(Request $request, Customer $customer): JsonResponse
    {
        $user = $request->user();

        $team = $user->team;

        abort_unless($team, 403);

        abort_unless(
            $customer->assigned_to === $user->id ||
            $customer->old_owner_id === $user->id ||
            $customer->team_id === $team->id,
            403
        );

        $limit = min(
            max((int) $request->input('limit', 30), 1),
            100
        );

        $query = $customer->messages()
            ->with([
                'sentBy:id,name',
                'team:id,name',
                'whatsappNumber:id,phone_number,display_phone_number',
                'document',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                $request->input('search')
            );

            $query->where(function ($q) use ($search) {
                $q->where(
                    'body',
                    'like',
                    '%' . $search . '%'
                );
            });

            $messages = $query
                ->latest('id')
                ->limit($limit)
                ->get()
                ->sortBy('id')
                ->values();

            $senderContextService = app(MessageSenderContextService::class);

            $messages = $messages->map(function (Message $message) use ($customer, $senderContextService) {
                if ($message->direction === 'outbound') {
                    $message->sender_context = $senderContextService->getContext(
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
            })->values();

            return response()->json([
                'messages' => $messages,
                'has_more' => false,
                'next_cursor' => null,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Jump to date
        |--------------------------------------------------------------------------
        */

        if ($request->filled('date')) {
            $date = $request->input('date');

            $messages = $query
                ->whereDate('created_at', $date)
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $senderContextService = app(MessageSenderContextService::class);

            $messages = $messages->map(function (Message $message) use ($customer, $senderContextService) {
                if ($message->direction === 'outbound') {
                    $message->sender_context = $senderContextService->getContext(
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
            })->values();

            /*
             * We want to know whether there are
             * messages older than this date.
             */
            $firstMessage = $messages->first();

            $hasMore = false;

            if ($firstMessage) {
                $hasMore = $customer->messages()
                    ->where('id', '<', $firstMessage->id)
                    ->exists();
            }

            return response()->json([
                'messages' => $messages,
                'has_more' => $hasMore,
                'next_cursor' =>
                    $firstMessage?->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Older messages
        |--------------------------------------------------------------------------
        */

        if ($request->filled('before_id')) {
            $beforeId = (int) $request->input(
                'before_id'
            );

            $messages = $query
                ->where('id', '<', $beforeId)
                ->latest('id')
                ->limit($limit + 1)
                ->get();

            $hasMore = $messages->count() > $limit;

            $messages = $messages
                ->take($limit)
                ->sortBy('id')
                ->values();

            $senderContextService = app(MessageSenderContextService::class);

            $messages = $messages->map(function (Message $message) use ($customer, $senderContextService) {
                if ($message->direction === 'outbound') {
                    $message->sender_context = $senderContextService->getContext(
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
            })->values();

            return response()->json([
                'messages' => $messages,

                'has_more' => $hasMore,

                'next_cursor' =>
                    $messages->first()?->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Default
        |--------------------------------------------------------------------------
        */

        $messages = $query
            ->latest('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $senderContextService = app(MessageSenderContextService::class);

        $messages = $messages->map(function (Message $message) use ($customer, $senderContextService) {
            if ($message->direction === 'outbound') {
                $message->sender_context = $senderContextService->getContext(
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
        })->values();

        return response()->json([
            'messages' => $messages,

            'has_more' =>
                $customer->messages()
                    ->where(
                        'id',
                        '<',
                        $messages->first()?->id ?? PHP_INT_MAX
                    )
                    ->exists(),

            'next_cursor' =>
                $messages->first()?->id,
        ]);
    }
}