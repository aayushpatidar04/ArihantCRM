<?php

namespace App\Jobs;

use App\Events\MessageStatusUpdated;
use App\Models\Message;
use App\Models\WhatsappNumber;
use App\Models\WhatsappTemplate;
use App\Services\MetaWhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsappTemplateJob implements ShouldQueue
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
        public int $templateId,
        public int $whatsappNumberId,
        public array $components = [],
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
         * Do not send twice if already successfully
         * delivered.
         */
        if (
            in_array(
                $message->status,
                [
                    'sent',
                    'delivered',
                    'read',
                ],
                true
            )
        ) {
            return;
        }

        $message->update([
            'status' => 'pending',
            'failure_reason' => null,
        ]);

        $message->refresh();

        event(
            new MessageStatusUpdated($message)
        );

        $whatsappNumber =
            WhatsappNumber::query()
                ->find($this->whatsappNumberId);

        $template =
            WhatsappTemplate::query()
                ->find($this->templateId);

        if (
            !$whatsappNumber ||
            !$template
        ) {
            $message->update([
                'status' => 'failed',

                'failure_reason' =>
                    'Missing WhatsApp number or template for delivery.',
            ]);

            $message->refresh();

            event(
                new MessageStatusUpdated($message)
            );

            return;
        }

        /*
         * Security:
         *
         * The template must belong to the same
         * WhatsApp number used for sending.
         */
        if (
            (int) $template->whatsapp_number_id !==
            (int) $whatsappNumber->id
        ) {
            $message->update([
                'status' => 'failed',

                'failure_reason' =>
                    'Template does not belong to the selected WhatsApp number.',
            ]);

            $message->refresh();

            event(
                new MessageStatusUpdated($message)
            );

            return;
        }

        /*
         * Only approved templates should be sent.
         */
        if (
            strtoupper((string) $template->status) !==
            'APPROVED'
        ) {
            $message->update([
                'status' => 'failed',

                'failure_reason' =>
                    'WhatsApp template is not approved.',
            ]);

            $message->refresh();

            event(
                new MessageStatusUpdated($message)
            );

            return;
        }

        try {
            $response = $whatsapp->sendTemplate(
                $whatsappNumber,
                $message->customer->phone,
                $template,
                $this->components,
            );

            $whatsappMessageId =
                data_get(
                    $response,
                    'messages.0.id'
                );

            $message->update([
                'whatsapp_message_id' =>
                    $whatsappMessageId,

                'status' => 'sent',

                'failure_reason' => null,
            ]);

            $message->customer->update([
                'last_contacted_at' => now(),
            ]);

            $message->refresh();

            event(
                new MessageStatusUpdated($message)
            );
        } catch (Throwable $e) {
            Log::error(
                'WhatsApp template failed',
                [
                    'message_id' =>
                        $message->id,

                    'template_id' =>
                        $template->id,

                    'whatsapp_number_id' =>
                        $whatsappNumber->id,

                    'components' =>
                        $this->components,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            $message->update([
                'status' => 'failed',

                'failure_reason' =>
                    $e->getMessage(),
            ]);

            $message->refresh();

            event(
                new MessageStatusUpdated($message)
            );
        }
    }
}