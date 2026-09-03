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
        \Log::info('Meta webhook verification request', [
            'query' => $request->query(),
        ]);
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
            \Log::info($challenge);
            return response(
                $challenge,
                200
            );
        } catch (RuntimeException $e) {
            \Log::info($e);
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

        try {
            $this->webhookService->handle(
                $request->getContent(),
                // $signature
            );

            /*
             * Meta expects a fast 200 response.
             */
            return response('EVENT_RECEIVED', 200);

        } catch (RuntimeException $e) {

            report($e);

            /*
             * Do not expose internal details to Meta.
             */
            return response('Webhook processing failed', 500);
        }
    }
}