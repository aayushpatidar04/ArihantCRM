<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\MetaWhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [
        5,
        15,
        30,
    ];

    public function __construct(
        public int $messageId
    ) {
    }

    public function handle(
        MetaWhatsappService $whatsapp
    ): void {
        $message = Message::query()
            ->with([
                'customer',
                'whatsappNumber',
            ])
            ->find($this->messageId);

        if (!$message) {
            return;
        }

        /*
         * Don't send an already completed message again.
         */
        if (
            in_array(
                $message->status,
                ['sent', 'delivered', 'read'],
                true
            )
        ) {
            return;
        }

        /*
         * Mark as sending.
         */
        $message->update([
            'status' => 'pending',
        ]);

        /*
         * Notify UI.
         */
        $message->refresh();

        event(new \App\Events\MessageStatusUpdated($message));

        try {

            $response = $whatsapp->sendText(
                $message->whatsappNumber,
                $message->customer->phone,
                $message->body
            );

            $whatsappMessageId = data_get(
                $response,
                'messages.0.id'
            );

            $message->update([
                'whatsapp_message_id' => $whatsappMessageId,
                'status' => 'sent',
                'failure_reason' => null,
            ]);

            /*
             * Update customer activity.
             */
            $message->customer->update([
                'last_contacted_at' => now(),
            ]);

            /*
             * Notify frontend.
             */
            $message->refresh();

            event(
                new \App\Events\MessageStatusUpdated($message)
            );

        } catch (Throwable $e) {

            Log::error(
                'WhatsApp message failed',
                [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]
            );

            /*
             * Don't throw if you don't want Laravel's
             * retry mechanism to run.
             */
            $message->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            $message->refresh();

            event(
                new \App\Events\MessageStatusUpdated($message)
            );
        }
    }
}