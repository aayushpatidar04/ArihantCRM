<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WhatsappNumber;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaWhatsappService
{
    private string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config(
            'services.meta.graph_version',
            'v25.0'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    */

    /**
     * Test a WhatsApp number against Meta Cloud API.
     */
    public function testConnection(WhatsappNumber $whatsappNumber): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->acceptJson()
            ->timeout(20)
            ->get(
                $this->graphUrl(
                    $whatsappNumber->phone_number_id
                ),
                [
                    'fields' => implode(',', [
                        'id',
                        'display_phone_number',
                        'verified_name',
                        'quality_rating',
                        'code_verification_status',
                    ]),
                ]
            );

        $this->throwMetaException(
            $response,
            'Unable to connect to Meta.'
        );

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    */

    /**
     * Fetch and synchronize WhatsApp templates belonging
     * to this WhatsApp Business Account.
     */
    public function syncTemplates(WhatsappNumber $whatsappNumber): int {
        $whatsappNumber->loadMissing(
            'metaWhatsappSetting'
        );

        if (!$whatsappNumber->metaWhatsappSetting) {
            throw new RuntimeException(
                'No Meta WhatsApp configuration is associated with this number.'
            );
        }

        if (!$whatsappNumber->waba_id) {
            throw new RuntimeException(
                'WABA ID is missing for this WhatsApp number.'
            );
        }

        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->acceptJson()
            ->timeout(30)
            ->get(
                $this->graphUrl(
                    $whatsappNumber->waba_id . '/message_templates'
                ),
                [
                    'limit' => 100,
                ]
            );

        $this->throwMetaException(
            $response,
            'Unable to fetch WhatsApp templates from Meta.'
        );

        $templates = $response->json('data', []);

        $count = 0;

        foreach ($templates as $template) {
            $language = $template['language'] ?? 'en_US';

            $existing = $whatsappNumber
                ->whatsappTemplates()
                ->where('name', $template['name'])
                ->where('language', $language)
                ->first();

            /*
             * Preserve our local configuration.
             *
             * Meta remains the source of truth for:
             * - template ID
             * - category
             * - status
             * - components
             * - language
             */
            $localConfig = $existing?->local_config ?? [];

            $whatsappNumber
                ->whatsappTemplates()
                ->updateOrCreate(
                    [
                        'name' => $template['name'],
                        'language' => $language,
                    ],
                    [
                        'template_id' => $template['id'] ?? null,
                        'category' => $template['category'] ?? null,
                        'status' => $template['status'] ?? null,
                        'components' => $template['components'] ?? [],
                        'local_config' => $localConfig,
                        'last_synced_at' => now(),
                    ]
                );

            $count++;
        }

        return $count;
    }

    /**
     * Send a WhatsApp template.
     *
     * This is kept compatible with the existing implementation
     * which is already tested and working.
     */
    public function sendTemplate(WhatsappNumber $whatsappNumber, string $to, WhatsappTemplate $template, array $components = []): array {
        $this->validateWhatsappNumber(
            $whatsappNumber
        );

        /*
        * Security:
        *
        * Template and WhatsApp number must match.
        */
        if (
            (int) $template->whatsapp_number_id !==
            (int) $whatsappNumber->id
        ) {
            throw new RuntimeException(
                'Template does not belong to this WhatsApp number.'
            );
        }

        /*
        * Only approved templates are allowed.
        */
        if (
            strtoupper((string) $template->status) !==
            'APPROVED'
        ) {
            throw new RuntimeException(
                'Only approved WhatsApp templates can be sent.'
            );
        }

        $to = $this->normalizePhoneNumber($to);

        $languageCode =
            $template->language ?: 'en_US';

        $payload = [
            'messaging_product' => 'whatsapp',

            'to' => $to,

            'type' => 'template',

            'template' => [
                'name' => $template->name,

                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        /*
        * Components have already been prepared
        * by the template preview.
        */
        if (!empty($components)) {
            $payload['template']['components'] =
                array_values($components);
        }

        return $this->sendMessageRequest(
            $whatsappNumber,
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Text Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Send a normal WhatsApp text message.
     *
     * This is intended for replies after the WhatsApp
     * conversation is inside the allowed messaging window.
     */
    public function sendText(WhatsappNumber $whatsappNumber, string $to, string $body, bool $previewUrl = false): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        $body = trim($body);

        if ($body === '') {
            throw new RuntimeException(
                'Message body cannot be empty.'
            );
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $body,
            ],
        ];

        return $this->sendMessageRequest(
            $whatsappNumber,
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Media Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Send an image message using an already uploaded
     * Meta media ID.
     */
    public function sendImage(WhatsappNumber $whatsappNumber, string $to, string $mediaId, ?string $caption = null): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        $image = [
            'id' => $mediaId,
        ];

        if ($caption !== null && trim($caption) !== '') {
            $image['caption'] = trim($caption);
        }

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'image',
                'image' => $image,
            ]
        );
    }

    /**
     * Send a document using an already uploaded
     * Meta media ID.
     */
    public function sendDocument(WhatsappNumber $whatsappNumber, string $to, string $mediaId, ?string $filename = null, ?string $caption = null): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        $document = [
            'id' => $mediaId,
        ];

        if (
            $filename !== null
            && trim($filename) !== ''
        ) {
            $document['filename'] = trim($filename);
        }

        if (
            $caption !== null
            && trim($caption) !== ''
        ) {
            $document['caption'] = trim($caption);
        }

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'document',
                'document' => $document,
            ]
        );
    }

    /**
     * Send audio using an already uploaded Meta media ID.
     */
    public function sendAudio(WhatsappNumber $whatsappNumber, string $to, string $mediaId): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'audio',
                'audio' => [
                    'id' => $mediaId,
                ],
            ]
        );
    }

    /**
     * Send video using an already uploaded Meta media ID.
     */
    public function sendVideo(WhatsappNumber $whatsappNumber, string $to, string $mediaId, ?string $caption = null): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        $video = [
            'id' => $mediaId,
        ];

        if (
            $caption !== null
            && trim($caption) !== ''
        ) {
            $video['caption'] = trim($caption);
        }

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'video',
                'video' => $video,
            ]
        );
    }

    public function uploadMedia(WhatsappNumber $whatsappNumber, string $filePath, string $mimeType): string {
        $this->validateWhatsappNumber($whatsappNumber);

        if (!is_file($filePath)) {
            throw new RuntimeException(
                'Media file does not exist.'
            );
        }

        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->acceptJson()
            ->timeout(60)
            ->attach(
                'file',
                fopen($filePath, 'r'),
                basename($filePath)
            )
            ->post(
                "https://graph.facebook.com/{$this->graphVersion}/{$whatsappNumber->phone_number_id}/media",
                [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error.message')
                ?? 'Unable to upload media to Meta.'
            );
        }

        $mediaId = $response->json('id');

        if (!$mediaId) {
            throw new RuntimeException(
                'Meta did not return a media ID.'
            );
        }

        return $mediaId;
    }

    /**
     * Send sticker using an already uploaded Meta media ID.
     */
    public function sendSticker(WhatsappNumber $whatsappNumber, string $to, string $mediaId): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'sticker',
                'sticker' => [
                    'id' => $mediaId,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Location
    |--------------------------------------------------------------------------
    */

    /**
     * Send a WhatsApp location.
     */
    public function sendLocation(WhatsappNumber $whatsappNumber, string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        if (
            $name !== null
            && trim($name) !== ''
        ) {
            $location['name'] = trim($name);
        }

        if (
            $address !== null
            && trim($address) !== ''
        ) {
            $location['address'] = trim($address);
        }

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'location',
                'location' => $location,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    */

    /**
     * Send WhatsApp contact message.
     *
     * $contacts must contain the structure expected by Meta.
     */
    public function sendContacts(WhatsappNumber $whatsappNumber, string $to, array $contacts): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $to = $this->normalizePhoneNumber($to);

        if (empty($contacts)) {
            throw new RuntimeException(
                'At least one contact is required.'
            );
        }

        return $this->sendMessageRequest(
            $whatsappNumber,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'contacts',
                'contacts' => $contacts,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generic Message Request
    |--------------------------------------------------------------------------
    */

    /**
     * Send a prepared payload to Meta.
     *
     * The response from Meta is returned untouched so the caller
     * can create the local Message using the returned message ID.
     */
    protected function sendMessageRequest(WhatsappNumber $whatsappNumber, array $payload): array {
        $this->validateWhatsappNumber($whatsappNumber);

        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->acceptJson()
            ->timeout(30)
            ->post(
                $this->graphUrl(
                    $whatsappNumber->phone_number_id . '/messages'
                ),
                $payload
            );

        $this->throwMetaException(
            $response,
            'Meta WhatsApp API request failed.'
        );

        return $response->json();
    }

    public function sendMessage(WhatsappNumber $whatsappNumber, string $to, string $type, mixed $content): array {
        if (! $whatsappNumber->is_active) {
            throw new RuntimeException(
                'This WhatsApp number is inactive.'
            );
        }

        if (empty($whatsappNumber->access_token)) {
            throw new RuntimeException(
                'WhatsApp access token is missing.'
            );
        }

        if (empty($whatsappNumber->phone_number_id)) {
            throw new RuntimeException(
                'WhatsApp Phone Number ID is missing.'
            );
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        if (! $to) {
            throw new RuntimeException(
                'Invalid recipient WhatsApp number.'
            );
        }

        $allowedTypes = [
            'text',
            'image',
            'document',
            'audio',
            'video',
            'sticker',
            'location',
            'contact',
        ];

        if (! in_array($type, $allowedTypes, true)) {
            throw new RuntimeException(
                'Unsupported WhatsApp message type.'
            );
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type,
        ];

        switch ($type) {
            case 'text':
                $payload['text'] = [
                    'body' => (string) $content,
                ];
                break;

            case 'image':
                $payload['image'] = [
                    'id' => $content,
                ];
                break;

            case 'document':
                $payload['document'] = [
                    'id' => $content,
                ];
                break;

            case 'audio':
                $payload['audio'] = [
                    'id' => $content,
                ];
                break;

            case 'video':
                $payload['video'] = [
                    'id' => $content,
                ];
                break;

            case 'sticker':
                $payload['sticker'] = [
                    'id' => $content,
                ];
                break;

            case 'location':
                $payload['location'] = $content;
                break;

            case 'contact':
                $payload['contacts'] = $content;
                break;
        }

        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->acceptJson()
            ->timeout(30)
            ->post(
                "https://graph.facebook.com/{$this->graphVersion}/{$whatsappNumber->phone_number_id}/messages",
                $payload
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('error.message')
                    ?? 'Meta WhatsApp API request failed.'
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Validate the WhatsApp number before making an API request.
     */
    protected function validateWhatsappNumber(WhatsappNumber $whatsappNumber): void {
        if (!$whatsappNumber->is_active) {
            throw new RuntimeException(
                'This WhatsApp number is inactive.'
            );
        }

        if (empty($whatsappNumber->access_token)) {
            throw new RuntimeException(
                'WhatsApp access token is missing.'
            );
        }

        if (empty($whatsappNumber->phone_number_id)) {
            throw new RuntimeException(
                'WhatsApp Phone Number ID is missing.'
            );
        }
    }

    /**
     * Normalize a recipient phone number.
     *
     * Meta expects an international number without
     * +, spaces, brackets or dashes.
     */
    protected function normalizePhoneNumber(string $phone): string {
        $phone = preg_replace(
            '/[^0-9]/',
            '',
            $phone
        );

        if (!$phone) {
            throw new RuntimeException(
                'Invalid recipient WhatsApp number.'
            );
        }

        return $phone;
    }

    /**
     * Generate Graph API URL.
     */
    protected function graphUrl(string $path): string {
        return sprintf(
            'https://graph.facebook.com/%s/%s',
            $this->graphVersion,
            ltrim($path, '/')
        );
    }

    /**
     * Convert Meta API failures into application exceptions.
     */
    protected function throwMetaException(Response $response,string $fallbackMessage): void {
        if ($response->successful()) {
            return;
        }

        $message = $response->json(
            'error.message'
        );

        $code = $response->json(
            'error.code'
        );

        $error = $message ?: $fallbackMessage;

        if ($code) {
            $error .= " (Meta error {$code})";
        }

        throw new RuntimeException($error);
    }

    public function templatesForNumber(?WhatsappNumber $number) {
        if (!$number) {
            return collect();
        }

        return WhatsappTemplate::query()
            /*
             * Replace this condition with the exact
             * relationship/column used by your synced
             * template table.
             */
            ->where('whatsapp_number_id', $number->id)
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get();
    }

    public function findTemplateForNumber(int $templateId, WhatsappNumber $number): ?WhatsappTemplate {
        return WhatsappTemplate::query()
            ->whereKey($templateId)
            ->where('waba_id', $number->waba_id)
            ->where('status', 'APPROVED')
            ->first();
    }

}