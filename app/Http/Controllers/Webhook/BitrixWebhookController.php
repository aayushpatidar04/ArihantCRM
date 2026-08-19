<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\BitrixLeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BitrixWebhookController extends Controller
{

    public function __construct(
	    private readonly BitrixLeadService $bitrixLeadService
    ) {}

    public function leads(Request $request)
    {
        $event = $request->input('event');

        $leadId = $request->input(
            'data.FIELDS.ID'
        );

        Log::info(
            'Bitrix24 Webhook received',
            [
                'event' => $event,
                'lead_id' => $leadId,
            ]
        );

        if (!$leadId) {
            Log::warning(
                'Bitrix24 Webhook: No Lead ID found.'
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Missing Lead ID',
            ], 400);
        }

        /*
         * ---------------------------------------------------------
         * DELETE
         * ---------------------------------------------------------
         */
        if ($event === 'ONCRMLEADDELETE') {
            return $this->handleLeadDelete(
                (int) $leadId
            );
        }

        /*
         * ---------------------------------------------------------
         * CREATE / UPDATE
         * ---------------------------------------------------------
         */
        if (
            in_array(
                $event,
                [
                    'ONCRMLEADADD',
                    'ONCRMLEADUPDATE',
                ],
                true
            )
        ) {
            return $this->handleLeadUpsert(
                (int) $leadId
            );
        }

        Log::warning(
            'Bitrix24 Webhook: Unknown event type',
            [
                'event' => $event,
            ]
        );

        return response()->json([
            'status' => 'ignored',
            'message' => 'Unknown event type',
        ], 200);
    }

    private function handleLeadUpsert(
        int $leadId
    ): \Illuminate\Http\JsonResponse {
        try {
            $result = $this->bitrixLeadService
                ->fetchAndSync(
                    leadId: $leadId,
                    source: 'webhook'
                );

            $customer = $result['customer'];

            return response()->json([
                'status' =>
                    'success',

                'message' =>
                    "Lead {$leadId} processed successfully.",

                'customer_id' =>
                    $customer->id,

                'action' =>
                    $result['action'],

                'assignment_changed' =>
                    $result['assignment_changed'],

                'team_changed' =>
                    $result['team_changed'],
            ]);
        } catch (ValidationException $e) {
            Log::warning(
                'Bitrix webhook lead validation failed',
                [
                    'lead_id' =>
                        $leadId,

                    'errors' =>
                        $e->errors(),
                ]
            );

            /*
             * Return 200.
             *
             * Otherwise Bitrix may repeatedly retry
             * a lead that cannot be mapped locally.
             */
            return response()->json([
                'status' =>
                    'skipped',

                'message' =>
                    collect($e->errors())
                        ->flatten()
                        ->first(),
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'Bitrix webhook lead synchronization failed',
                [
                    'lead_id' =>
                        $leadId,

                    'message' =>
                        $e->getMessage(),

                    'exception' =>
                        $e,
                ]
            );

            return response()->json([
                'status' =>
                    'error',

                'message' =>
                    'Failed to synchronize lead.',
            ], 500);
        }
    }

    private function handleLeadDelete(
        int $leadId
    ): \Illuminate\Http\JsonResponse {
        $customer = Customer::withoutGlobalScopes()
            ->where(
                'bitrix_lead_id',
                $leadId
            )
            ->first();

        if (!$customer) {
            Log::warning(
                "No customer found for deleted lead {$leadId}"
            );

            return response()->json([
                'status' =>
                    'skipped',

                'message' =>
                    "No customer found for lead {$leadId}",
            ]);
        }

        $customer->delete();

        Log::info(
            "Customer soft-deleted for lead {$leadId}",
            [
                'customer_id' =>
                    $customer->id,
            ]
        );

        return response()->json([
            'status' =>
                'success',

            'message' =>
                "Lead {$leadId} deleted, customer soft-deleted",

            'customer_id' =>
                $customer->id,
        ]);
    }

}