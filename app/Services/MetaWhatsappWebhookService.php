<?php

namespace App\Services;

use App\Events\MessageStatusUpdated;
use App\Events\NewInboundMessage;
use App\Events\WhatsAppMessageReceived;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Message;
use App\Models\MetaWhatsappSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MetaWhatsappWebhookService
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
    | Verify Webhook
    |--------------------------------------------------------------------------
    */

    public function verifyToken(string $verifyToken): MetaWhatsappSetting
    {
        $setting = MetaWhatsappSetting::query()
            ->where('is_active', true)
            ->get()
            ->first(
                fn (MetaWhatsappSetting $setting): bool => hash_equals(
                    $setting->verify_token,
                    $verifyToken
                )
            );

        if (!$setting) {
            throw new RuntimeException(
                'Invalid Meta WhatsApp verify token.'
            );
        }

        return $setting;
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Webhook
    |--------------------------------------------------------------------------
    */

    public function handle(string $payload, 
        // $signature
    ): void {
        Log::debug('Meta Webhook Processing', [
            'payload_length' => strlen($payload),
            'timestamp' => now(),
        ]);

        $data = json_decode(
            $payload,
            true
        );

        if (!is_array($data)) {
            Log::error('Invalid Meta webhook payload', [
                'payload' => $payload,
                'json_error' => json_last_error_msg(),
            ]);
            throw new RuntimeException(
                'Invalid Meta webhook payload: ' . json_last_error_msg()
            );
        }

        foreach ($data['entry'] ?? [] as $entry) {

            foreach ($entry['changes'] ?? [] as $change) {

                $changeField = $change['field'] ?? null;
                if ($changeField !== 'messages') {
                    Log::debug('Skipping non-message change', [
                        'field' => $changeField,
                    ]);
                    continue;
                }

                $value = $change['value'] ?? [];

                $phoneNumberId =
                    $value['metadata']['phone_number_id']
                    ?? null;

                if (!$phoneNumberId) {
                    Log::warning('Phone number ID missing in webhook change');
                    continue;
                }

                $whatsappNumber = WhatsappNumber::query()
                    ->where(
                        'phone_number_id',
                        $phoneNumberId
                    )
                    ->with('metaWhatsappSetting')
                    ->first();

                if (!$whatsappNumber) {
                    Log::warning('WhatsApp number not found', [
                        'phone_number_id' => $phoneNumberId,
                    ]);
                    continue;
                }

                $setting =
                    $whatsappNumber->metaWhatsappSetting;

                if (!$setting || !$setting->is_active) {
                    Log::warning('Meta WhatsApp setting not active', [
                        'whatsapp_number_id' => $whatsappNumber->id,
                        'has_setting' => (bool) $setting,
                        'is_active' => $setting?->is_active,
                    ]);
                    continue;
                }

                /*
                 * Signature verification can be enabled here.
                 */
                $appSecret = $setting->app_secret;
                // $this->verifySignature(
                //     $payload,
                //     $signature,
                //     $appSecret
                // );

                $setting->update([
                    'last_webhook_at' => now(),
                ]);

                /*
                 * Inbound messages.
                 */
                $messageCount = count($value['messages'] ?? []);
                Log::info('Processing inbound messages from webhook', [
                    'count' => $messageCount,
                    'whatsapp_number_id' => $whatsappNumber->id,
                ]);

                foreach ($value['messages'] ?? [] as $message) {

                    $contact = $this->findContact(
                        $value,
                        $message
                    );

                    $this->processInboundMessage(
                        $message,
                        $contact,
                        $whatsappNumber
                    );
                }

                /*
                 * Delivery/read/failure updates.
                 */
                $statusCount = count($value['statuses'] ?? []);
                Log::info('Processing status updates from webhook', [
                    'count' => $statusCount,
                    'whatsapp_number_id' => $whatsappNumber->id,
                ]);

                foreach ($value['statuses'] ?? [] as $status) {

                    $this->processStatus(
                        $status,
                        $whatsappNumber
                    );
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Signature
    |--------------------------------------------------------------------------
    */

    protected function verifySignature(string $payload, string $signature, string $appSecret): void {
        if (!$appSecret) {
            throw new RuntimeException(
                'Meta app secret is missing.'
            );
        }

        if (!str_starts_with($signature, 'sha256=')) {
            throw new RuntimeException(
                'Invalid Meta webhook signature.'
            );
        }

        $receivedSignature =
            substr($signature, 7);

        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            $appSecret
        );

        if (
            !hash_equals(
                $expectedSignature,
                $receivedSignature
            )
        ) {
            throw new RuntimeException(
                'Invalid Meta webhook signature.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    */

    protected function findContact(array $value, array $message): array {
        $contacts = $value['contacts'] ?? [];

        if (empty($contacts)) {
            return [];
        }

        $messageFrom =
            $message['from']
            ?? null;

        foreach ($contacts as $contact) {

            if (
                isset($contact['wa_id']) &&
                $contact['wa_id'] === $messageFrom
            ) {
                return $contact;
            }
        }

        return $contacts[0] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Process Inbound Message
    |--------------------------------------------------------------------------
    */

    protected function processInboundMessage(array $message, array $contact, WhatsappNumber $whatsappNumber): void {
        $whatsappMessageId =
            $message['id'] ?? null;

        if (!$whatsappMessageId) {
            Log::warning('Inbound message missing ID', [
                'message' => $message,
            ]);
            return;
        }

        /*
         * Meta retries webhook events.
         */
        $alreadyExists = Message::query()
            ->where(
                'whatsapp_message_id',
                $whatsappMessageId
            )
            ->exists();

        if ($alreadyExists) {
            Log::debug('Duplicate inbound message ignored', [
                'whatsapp_message_id' => $whatsappMessageId,
            ]);
            return;
        }

        $phone =
            $message['from']
            ?? null;

        if (!$phone) {
            Log::warning('Inbound message missing phone number', [
                'whatsapp_message_id' => $whatsappMessageId,
            ]);
            return;
        }

        $customer = $this->resolveCustomer(
            $phone,
            $contact,
            $whatsappNumber
        );

        if (!$customer) {
            Log::warning('Could not resolve customer for inbound message', [
                'phone' => $phone,
                'whatsapp_message_id' => $whatsappMessageId,
                'whatsapp_number_id' => $whatsappNumber->id,
            ]);
            return;
        }

        Log::info('Processing inbound message', [
            'whatsapp_message_id' => $whatsappMessageId,
            'customer_id' => $customer->id,
            'phone' => $phone,
        ]);

        $specialTeam = Team::query()
            ->where('slug', 'arihant-special-session')
            ->where('whatsapp_number_id', $whatsappNumber->id)
            ->first();

        $type = $this->resolveMessageType(
            $message
        );

        if ($type === 'reaction') {
            $reactionMessage = $this->handleReaction(
                messageData: $message,
                customer: $customer,
                teamId: $specialTeam?->id ?? $customer->team_id
            );

            if (!$reactionMessage) {
                return;
            }

            /*
            * A reaction is an inbound WhatsApp interaction,
            * so it refreshes the 24-hour customer service window.
            */
            $customer->update([
                'last_contacted_at' => now(),
            ]);

            /*
            * Load the same relationships used by normal
            * inbound messages.
            */
            $reactionMessage->load([
                'customer',
                'sentBy:id,name',
                'team:id,name',
                'whatsappNumber:id,phone_number,display_phone_number',
                'reactionToMessage',
            ]);

            /*
            * Broadcast the reaction so the currently open
            * chat updates immediately.
            */
            broadcast(
                new NewInboundMessage(
                    $reactionMessage
                )
            );

            broadcast(
                new WhatsAppMessageReceived(
                    $reactionMessage
                )
            );

            return;
        }

        $body = $this->resolveMessageBody(
            $message,
            $type
        );

        /*
         * Create the inbound message first.
         *
         * The Document needs message_id.
         */
        try {
            $dbMessage = Message::create([
                'customer_id' => $customer->id,

                /*
                 * Keep the current customer's assigned team.
                 */
                'team_id' => $specialTeam?->id ?? $customer->team_id,

                'whatsapp_number_id' =>
                    $whatsappNumber->id,

                'sent_by' => null,

                'whatsapp_message_id' =>
                    $whatsappMessageId,

                'direction' => 'inbound',

                'type' => $type,

                'body' => $body,
                'metadata' => $message,
                'status' => 'delivered',
            ]);

            Log::info('Inbound message created', [
                'message_id' => $dbMessage->id,
                'whatsapp_message_id' => $whatsappMessageId,
                'type' => $type,
                'customer_id' => $customer->id,
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            /*
             * If message creation fails due to duplicate, it means
             * another webhook process already created it.
             * Fetch and use that message instead.
             */
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                Log::info('Message already created by concurrent request, using existing message', [
                    'whatsapp_message_id' => $whatsappMessageId,
                ]);

                $dbMessage = Message::query()
                    ->where('whatsapp_message_id', $whatsappMessageId)
                    ->first();

                if (!$dbMessage) {
                    Log::error('Could not find concurrent message after duplicate error', [
                        'whatsapp_message_id' => $whatsappMessageId,
                        'error' => $e->getMessage(),
                    ]);
                    return;
                }
            } else {
                Log::error('Failed to create inbound message', [
                    'whatsapp_message_id' => $whatsappMessageId,
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
                throw $e;
            }
        }

        /*
         * Media messages need to be downloaded from Meta
         * and stored locally.
         */
        if ($this->isMediaType($type)) {

            try {

                $this->storeInboundMedia(
                    $dbMessage,
                    $message,
                    $whatsappNumber
                );

            } catch (\Throwable $e) {

                /*
                 * Do not lose the inbound Message simply because
                 * media downloading failed.
                 */
                report($e);

                $dbMessage->update([
                    'failure_reason' =>
                        'Unable to download inbound media: '
                        . $e->getMessage(),
                ]);
            }
        }

        $dbMessage->load([
            'customer',
            'document',
            'sentBy:id,name',
            'team:id,name',
            'whatsappNumber:id,phone_number,display_phone_number',
        ]);

        /*
         * Customer replied, therefore the 24-hour window starts/
         * refreshes from this inbound message.
         */
        $customer->update([
            'last_contacted_at' => now(),
        ]);

        broadcast(
            new NewInboundMessage(
                $dbMessage
            )
        );
        broadcast(
            new WhatsAppMessageReceived(
                $dbMessage
            )
        );
    }

    private function handleReaction(
        array $messageData,
        Customer $customer,
        ?int $teamId = null
    ): ?Message {
        $reaction = $messageData['reaction'] ?? null;

        $targetWhatsappMessageId =
            $reaction['message_id'] ?? null;

        $emoji =
            $reaction['emoji'] ?? null;

        if (!$targetWhatsappMessageId) {
            return null;
        }

        /*
        * ---------------------------------------------------------
        * FIND ORIGINAL MESSAGE
        * ---------------------------------------------------------
        */

        $originalMessage = Message::query()
            ->where(
                'whatsapp_message_id',
                $targetWhatsappMessageId
            )
            ->first();

        if (!$originalMessage) {
            return null;
        }

        /*
        * ---------------------------------------------------------
        * REMOVE PREVIOUS REACTION FROM SAME CUSTOMER
        * ON SAME MESSAGE
        * ---------------------------------------------------------
        *
        * One WhatsApp number can have only one reaction
        * on a particular message.
        */

        Message::query()
            ->where(
                'customer_id',
                $customer->id
            )
            ->where(
                'reaction_to_message_id',
                $originalMessage->id
            )
            ->where(
                'type',
                'reaction'
            )
            ->delete();

        /*
        * ---------------------------------------------------------
        * CREATE NEW REACTION
        * ---------------------------------------------------------
        */

        $reactionMessage = Message::create([
            'customer_id' =>
                $customer->id,

            'team_id' =>
                $teamId ?? $originalMessage->team_id,

            'sent_by' =>
                null,

            'whatsapp_number_id' =>
                $originalMessage->whatsapp_number_id,

            'whatsapp_message_id' =>
                $messageData['id'] ?? null,

            'direction' =>
                'inbound',

            /*
            * Keeping emoji in body is useful for fallback
            * and makes debugging easier.
            */
            'body' =>
                $emoji,

            'status' =>
                'received',

            'type' =>
                'reaction',

            'reaction_to_message_id' =>
                $originalMessage->id,

            /*
            * Store complete webhook data.
            */
            'metadata' => $messageData,
        ]);

        return $reactionMessage;
    }

    /*
    |--------------------------------------------------------------------------
    | Media Detection
    |--------------------------------------------------------------------------
    */

    protected function isMediaType(
        string $type
    ): bool {
        return in_array(
            $type,
            [
                'image',
                'document',
                'audio',
                'video',
                'sticker',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Inbound Media
    |--------------------------------------------------------------------------
    */

    protected function storeInboundMedia(
        Message $message,
        array $metaMessage,
        WhatsappNumber $whatsappNumber
    ): ?Document {
        $type =
            $message->type;

        /*
         * Extract media information from the Meta payload.
         */
        $mediaData =
            $metaMessage[$type]
            ?? null;

        if (!is_array($mediaData)) {
            throw new RuntimeException(
                'Media information is missing from Meta webhook.'
            );
        }

        $mediaId =
            $mediaData['id']
            ?? null;

        if (!$mediaId) {
            throw new RuntimeException(
                'Meta media ID is missing.'
            );
        }

        /*
         * Ask Meta for the temporary media URL.
         */
        $mediaInfo = $this->getMediaInfo(
            $mediaId,
            $whatsappNumber
        );

        $downloadUrl =
            $mediaInfo['url']
            ?? null;

        if (!$downloadUrl) {
            throw new RuntimeException(
                'Meta did not return a media download URL.'
            );
        }

        /*
         * Download the actual media.
         */
        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->accept('*/*')
            ->timeout(60)
            ->get($downloadUrl);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Unable to download media from Meta.'
            );
        }

        $contents =
            $response->body();

        if ($contents === '') {
            throw new RuntimeException(
                'Meta returned an empty media file.'
            );
        }

        /*
         * Resolve MIME type.
         *
         * Prefer Meta's MIME type, otherwise use the
         * downloaded response's content type.
         */
        $mimeType =
            $mediaInfo['mime_type']
            ?? $response->header('Content-Type')
            ?? 'application/octet-stream';

        /*
         * Filename.
         */
        $originalFilename =
            $this->resolveInboundFilename(
                $type,
                $mediaData,
                $mimeType
            );

        /*
         * Determine extension.
         */
        $extension =
            pathinfo(
                $originalFilename,
                PATHINFO_EXTENSION
            );

        if (!$extension) {
            $extension =
                $this->extensionFromMime(
                    $mimeType
                );
        }

        /*
         * Generate a safe storage filename.
         */
        $storedFilename =
            Str::uuid()->toString()
            . ($extension
                ? '.' . $extension
                : '');

        /*
         * Keep WhatsApp media separated by customer.
         *
         * Example:
         *
         * whatsapp/123/inbound/uuid.jpg
         */
        $directory =
            'whatsapp/'
            . $message->customer_id
            . '/inbound';

        $path =
            $directory
            . '/'
            . $storedFilename;

        /*
         * Store on public disk so Document::url works
         * with php artisan storage:link.
         */
        Storage::disk('public')->put(
            $path,
            $contents
        );

        /*
         * Create our Document record.
         */
        return Document::create([
            'customer_id' =>
                $message->customer_id,

            'team_id' =>
                $message->team_id,

            'message_id' =>
                $message->id,

            'uploaded_by' =>
                null,

            'original_filename' =>
                $originalFilename,

            'stored_filename' =>
                $storedFilename,

            'disk' =>
                'public',

            'path' =>
                $path,

            'mime_type' =>
                $mimeType,

            'size' =>
                strlen($contents),

            'source' =>
                'whatsapp',

            'status' =>
                'completed',

            'notes' =>
                null,

            'encryption_key_id' =>
                null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Media Information From Meta
    |--------------------------------------------------------------------------
    */

    protected function getMediaInfo(
        string $mediaId,
        WhatsappNumber $whatsappNumber
    ): array {
        if (!$whatsappNumber->access_token) {
            throw new RuntimeException(
                'WhatsApp access token is missing.'
            );
        }

        $response = Http::withToken(
            $whatsappNumber->access_token
        )
            ->acceptJson()
            ->timeout(30)
            ->get(
                "https://graph.facebook.com/{$this->graphVersion}/{$mediaId}"
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                $response->json('error.message')
                ?? 'Unable to fetch WhatsApp media information.'
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Filename
    |--------------------------------------------------------------------------
    */

    protected function resolveInboundFilename(
        string $type,
        array $mediaData,
        string $mimeType
    ): string {
        /*
         * Documents normally contain filename.
         */
        if (
            !empty($mediaData['filename'])
        ) {
            return $mediaData['filename'];
        }

        /*
         * Images/videos/audio/stickers don't normally
         * provide a filename.
         */
        $extension =
            $this->extensionFromMime(
                $mimeType
            );

        return $type
            . '_' . now()->format('Ymd_His')
            . ($extension
                ? '.' . $extension
                : '');
    }

    /*
    |--------------------------------------------------------------------------
    | MIME → Extension
    |--------------------------------------------------------------------------
    */

    protected function extensionFromMime(
        string $mimeType
    ): ?string {
        return match (strtolower($mimeType)) {

            'image/jpeg',
            'image/jpg' =>
            'jpg',

            'image/png' =>
            'png',

            'image/webp' =>
            'webp',

            'image/gif' =>
            'gif',

            'video/mp4' =>
            'mp4',

            'video/3gpp' =>
            '3gp',

            'audio/mpeg' =>
            'mp3',

            'audio/ogg' =>
            'ogg',

            'audio/aac' =>
            'aac',

            'audio/amr' =>
            'amr',

            'audio/opus' =>
            'opus',

            'application/pdf' =>
            'pdf',

            'application/msword' =>
            'doc',

            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' =>
            'docx',

            'application/vnd.ms-excel' =>
            'xls',

            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' =>
            'xlsx',

            default =>
            null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Customer
    |--------------------------------------------------------------------------
    */

    protected function resolveCustomer(
        string $phone,
        array $contact,
        WhatsappNumber $whatsappNumber
    ): ?Customer {
        $phone = preg_replace(
            '/[^0-9]/',
            '',
            $phone
        );

        if (!$phone) {
            return null;
        }

        /*
         * First, try to find existing customer with this phone.
         * Check both formats: with and without leading +
         */
        $customer = Customer::query()
            ->where(function ($query) use ($phone) {
                $query
                    ->where('phone', $phone)
                    ->orWhere('phone', '+' . $phone);
            })
            ->first();

        if ($customer) {
            \Log::debug('Found existing customer', [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ]);
            return $customer;
        }

        /*
         * Customer doesn't exist, create new one.
         * Use firstOrCreate to avoid race conditions when multiple
         * webhooks arrive simultaneously for the same phone number.
         */
        $assignment = $this->resolveRoundRobinAssignment($whatsappNumber);

        if (!$assignment) {
            \Log::warning('Could not resolve round-robin assignment', [
                'phone' => $phone,
                'whatsapp_number_id' => $whatsappNumber->id,
            ]);
            return null;
        }

        $name = $contact['profile']['name'] ?? $phone;

        try {
            /*
             * Use firstOrCreate with transaction to atomically handle
             * the race condition where two webhooks arrive for same phone.
             */
            $customer = DB::transaction(function () use ($name, $phone, $assignment) {
                /*
                 * Double-check inside transaction to catch any customer
                 * that was created between our last check and this transaction.
                 */
                $existing = Customer::query()
                    ->where(function ($query) use ($phone) {
                        $query
                            ->where('phone', $phone)
                            ->orWhere('phone', '+' . $phone);
                    })
                    ->first();

                if ($existing) {
                    \Log::debug('Customer created by concurrent request, using that', [
                        'customer_id' => $existing->id,
                        'phone' => $phone,
                    ]);
                    return $existing;
                }

                $newCustomer = Customer::create([
                    'name' => $name,
                    'phone' => $phone,
                    'team_id' => $assignment['team_id'],
                    'assigned_to' => $assignment['user_id'],
                    'status' => 'active',
                ]);

                \Log::info('Created new customer from webhook', [
                    'customer_id' => $newCustomer->id,
                    'phone' => $phone,
                    'name' => $name,
                ]);

                return $newCustomer;
            });

            return $customer;

        } catch (\Illuminate\Database\QueryException $e) {
            /*
             * Handle duplicate key errors gracefully.
             * This can still happen if there's a race condition at the database level.
             * In this case, fetch the customer that was created by the other process.
             */
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                \Log::warning('Duplicate customer creation detected, fetching existing customer', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);

                $customer = Customer::query()
                    ->where(function ($query) use ($phone) {
                        $query
                            ->where('phone', $phone)
                            ->orWhere('phone', '+' . $phone);
                    })
                    ->first();

                if ($customer) {
                    return $customer;
                }
            }

            \Log::error('Failed to create customer', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Message Type
    |--------------------------------------------------------------------------
    */

    protected function resolveMessageType(
        array $message
    ): string {
        $metaType =
            $message['type']
            ?? 'text';

        return match ($metaType) {

            'text' =>
            'text',

            'image' =>
            'image',

            'document' =>
            'document',

            'audio' =>
            'audio',

            'video' =>
            'video',

            'sticker' =>
            'sticker',

            'location' =>
            'location',

            'contacts' =>
            'contact',

            'reaction' =>
            'reaction',

            default =>
            'chat',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Body / Caption
    |--------------------------------------------------------------------------
    */

    protected function resolveMessageBody(
        array $message,
        string $type
    ): ?string {
        return match ($type) {

            'text' =>
            $message['text']['body']
            ?? null,

            'image' =>
            $message['image']['caption']
            ?? null,

            'document' =>
            $message['document']['caption']
            ?? null,

            'video' =>
            $message['video']['caption']
            ?? null,

            'audio' =>
            null,

            'sticker' =>
            null,

            'location' =>
            isset($message['location'])
            ? json_encode(
                $message['location']
            )
            : null,

            'contact' =>
            isset($message['contacts'])
            ? json_encode(
                $message['contacts']
            )
            : null,

            default =>
            null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Status Updates
    |--------------------------------------------------------------------------
    */

    protected function processStatus(
        array $status,
        WhatsappNumber $whatsappNumber
    ): void {
        $whatsappMessageId =
            $status['id'] ?? null;

        if (!$whatsappMessageId) {
            Log::warning('Status update missing message ID', [
                'status' => $status,
            ]);
            return;
        }

        $message = Message::query()
            ->where(
                'whatsapp_message_id',
                $whatsappMessageId
            )
            ->where(
                'whatsapp_number_id',
                $whatsappNumber->id
            )
            ->first();

        if (!$message) {
            Log::warning('Message not found for status update', [
                'whatsapp_message_id' => $whatsappMessageId,
                'whatsapp_number_id' => $whatsappNumber->id,
                'status' => $status['status'] ?? null,
                'timestamp' => $status['timestamp'] ?? null,
            ]);
            return;
        }

        $statusValue =
            $status['status'] ?? null;

        if (!$statusValue) {
            return;
        }

        $updates = [];

        switch ($statusValue) {

            case 'sent':

                $updates['status'] =
                    'sent';

                break;

            case 'delivered':

                $updates['status'] =
                    'delivered';

                $updates['delivered_at'] =
                    now();

                break;

            case 'read':

                $updates['status'] =
                    'read';

                $updates['read_at'] =
                    now();

                break;

            case 'failed':

                $updates['status'] =
                    'failed';

                $updates['failure_reason'] =
                    $this->extractFailureReason(
                        $status
                    );

                break;
        }

        if (!empty($updates)) {
            $message->update($updates);
            $message->refresh();

            Log::info('Message status updated', [
                'message_id' => $message->id,
                'whatsapp_message_id' => $message->whatsapp_message_id,
                'old_status' => $status['status'] ?? null,
                'new_status' => $updates['status'] ?? null,
            ]);

            broadcast(new MessageStatusUpdated($message));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Failure Reason
    |--------------------------------------------------------------------------
    */

    protected function extractFailureReason(
        array $status
    ): ?string {
        $errors =
            $status['errors'] ?? [];

        if (empty($errors)) {
            return null;
        }

        $first =
            $errors[0];

        return $first['title']
            ?? $first['message']
            ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Round Robin
    |--------------------------------------------------------------------------
    */

    protected function resolveRoundRobinAssignment(WhatsappNumber $whatsappNumber): ?array
    {
        $specialTeam = Team::query()
            ->where('slug', 'arihant-special-session')
            ->where('whatsapp_number_id', $whatsappNumber->id)
            ->first();

        $executives = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'executive');
            })
            ->when(
                !$specialTeam,
                function ($query) use ($whatsappNumber) {
                    $query->whereHas('team', function ($teamQuery) use ($whatsappNumber) {
                        $teamQuery
                            ->where('is_active', true)
                            ->where('whatsapp_number_id', $whatsappNumber->id);
                    });
                }
            )
            ->when(
                $specialTeam,
                function ($query) use ($whatsappNumber) {
                    // Arihant Special Session number
                    // Executive must belong to a team
                    // where this WhatsApp number is linked.
                    $query->whereHas('team', function ($teamQuery) use ($whatsappNumber) {
                        $teamQuery
                            ->where('is_active', true)
                            ->whereNotNull('whatsapp_number_id');
                    });
                }
            )
            ->orderBy('id')
            ->get(['id', 'team_id']);

        if ($executives->isEmpty()) {
            return null;
        }

        $lastAssignedUserId = Customer::query()
            ->whereIn('assigned_to', $executives->pluck('id'))
            ->latest('id')
            ->value('assigned_to');

        $currentIndex = $executives->search(
            fn (User $executive) => (int) $executive->id === (int) $lastAssignedUserId
        );

        $nextExecutive = $currentIndex === false
            ? $executives->first()
            : $executives->get(($currentIndex + 1) % $executives->count());

        return [
            'user_id' => $nextExecutive->id,
            'team_id' => $nextExecutive->team_id,
        ];
    }
}