<?php

namespace App\Jobs;

use App\Events\MessageStatusUpdated;
use App\Models\Document;
use App\Models\Message;
use App\Models\WhatsappNumber;
use App\Services\MetaWhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendWhatsappMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [
        5,
        15,
        30,
    ];

    public function __construct(
        public int $messageId,
        public int $documentId,
        public int $whatsappNumberId,
    ) {
    }

    public function handle(MetaWhatsappService $whatsapp): void
    {
        $message = Message::query()
            ->with([
                'customer',
                'whatsappNumber',
            ])
            ->find($this->messageId);

        if (!$message) {
            return;
        }

        if (in_array($message->status, ['sent', 'delivered', 'read'], true)) {
            return;
        }

        $message->update([
            'status' => 'pending',
            'failure_reason' => null,
        ]);

        $message->refresh();

        event(new MessageStatusUpdated($message));

        $document = Document::query()->find($this->documentId);
        $whatsappNumber = WhatsappNumber::query()->find($this->whatsappNumberId);

        if (!$document || !$whatsappNumber) {
            $message->update([
                'status' => 'failed',
                'failure_reason' => 'Missing media or WhatsApp number for delivery.',
            ]);

            $message->refresh();
            event(new MessageStatusUpdated($message));

            return;
        }

        try {
            $mediaId = $whatsapp->uploadMedia(
                $whatsappNumber,
                Storage::disk($document->disk ?: 'public')->path($document->path),
                $document->mime_type
            );

            if (!$mediaId) {
                throw new \RuntimeException('Meta did not return a media ID.');
            }

            $response = match ($message->type) {
                'image' => $whatsapp->sendImage(
                    $whatsappNumber,
                    $message->customer->phone,
                    $mediaId,
                    $message->body
                ),
                'video' => $whatsapp->sendVideo(
                    $whatsappNumber,
                    $message->customer->phone,
                    $mediaId,
                    $message->body
                ),
                'document' => $whatsapp->sendDocument(
                    $whatsappNumber,
                    $message->customer->phone,
                    $mediaId,
                    $document->original_filename,
                    $message->body
                ),
                'audio' => $whatsapp->sendAudio(
                    $whatsappNumber,
                    $message->customer->phone,
                    $mediaId
                ),
                default => throw new \RuntimeException('Unsupported WhatsApp media type.'),
            };

            $whatsappMessageId = data_get($response, 'messages.0.id');

            $message->update([
                'whatsapp_message_id' => $whatsappMessageId,
                'status' => 'sent',
                'failure_reason' => null,
            ]);

            $document->update([
                'message_id' => $message->id,
                'status' => 'sent',
            ]);

            $message->customer->update([
                'last_contacted_at' => now(),
            ]);

            $message->refresh();
            event(new MessageStatusUpdated($message));
        } catch (Throwable $e) {
            Log::error('WhatsApp media failed', [
                'message_id' => $message->id,
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $message->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            if ($document) {
                $document->update([
                    'status' => 'failed',
                    'notes' => $e->getMessage(),
                ]);
            }

            $message->refresh();
            event(new MessageStatusUpdated($message));
        }
    }
}
