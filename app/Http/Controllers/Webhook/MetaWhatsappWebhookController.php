<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\MetaWhatsappWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class MetaWhatsappWebhookController extends Controller
{
    public function __construct(
        protected MetaWhatsappWebhookService $webhookService
    ) {
    }

    /**
     * Meta webhook verification.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if (
            $mode !== 'subscribe' ||
            empty($verifyToken) ||
            empty($challenge)
        ) {
            return response('Forbidden', 403);
        }

        try {
            $this->webhookService->verifyToken(
                $verifyToken
            );
            return response(
                $challenge,
                200
            );
        } catch (RuntimeException $e) {
            return response(
                'Forbidden',
                403
            );
        }
    }

    /**
     * Receive Meta webhook events.
     */
    public function handle(Request $request): Response
    {
        // $signature = $request->header(
        //     'X-Hub-Signature-256'
        // );

        // if (!$signature) {
        //     return response('Missing signature', 401);
        // }
        $payload = $request->getContent();
        \Log::info('Meta Webhook Received', [
            'size' => strlen($payload),
            'content_type' => $request->header('Content-Type'),
        ]);

        try {
            $this->webhookService->handle(
                $payload,
                // $signature
            );

            /*
             * Meta expects a fast 200 response.
             */
            \Log::info('Webhook processed successfully');
            return response('EVENT_RECEIVED', 200);

        } catch (RuntimeException $e) {

            \Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            report($e);

            /*
             * Do not expose internal details to Meta.
             */
            return response('Webhook processing failed', 500);

        } catch (\Illuminate\Database\QueryException $e) {

            /*
             * Handle database errors (e.g., duplicate key violations).
             * These can occur due to race conditions but are usually handled
             * internally. Log but don't crash.
             */
            $isDuplicateError = $e->getCode() === '23000' || 
                                str_contains($e->getMessage(), 'Duplicate entry');

            $logLevel = $isDuplicateError ? 'warning' : 'error';
            $logMessage = $isDuplicateError 
                ? 'Database duplicate detected (likely race condition, already handled internally)'
                : 'Database query error in webhook processing';

            \Log::channel('single')->$logLevel($logMessage, [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);

            if (!$isDuplicateError) {
                report($e);
            }

            /*
             * Still return 200 to Meta - the data was likely partially processed.
             * Meta will retry if needed.
             */
            return response('EVENT_RECEIVED', 200);

        } catch (\Throwable $e) {

            \Log::error('Unexpected error in webhook processing', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            report($e);

            /*
             * Return 200 to Meta to avoid retry loops.
             */
            return response('EVENT_RECEIVED', 200);
        }
    }
}