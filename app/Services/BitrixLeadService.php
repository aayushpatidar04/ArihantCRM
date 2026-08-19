<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BitrixLeadService
{
    /**
     * Fetch a lead from the Bitrix Lead API.
     *
     * @throws RequestException
     */
    public function fetchLead(int $leadId): array
    {
        $baseUrl = rtrim(
            (string) config('services.bitrix_leads.url'),
            '/'
        );

        $username = config(
            'services.bitrix_leads.username'
        );

        $password = config(
            'services.bitrix_leads.password'
        );

        if (!$username || !$password) {
            throw new RuntimeException(
                'Bitrix lead API credentials are not configured.'
            );
        }

        $url = "{$baseUrl}/{$leadId}";

        $response = Http::withBasicAuth(
            $username,
            $password
        )
            ->acceptJson()
            ->timeout(
                config(
                    'services.bitrix_leads.timeout',
                    20
                )
            )
            ->retry(
                2,
                500,
                throw: false
            )
            ->get($url);

        if (!$response->successful()) {
            Log::error(
                'Bitrix lead API request failed.',
                [
                    'lead_id' => $leadId,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]
            );

            $response->throw();
        }

        $lead = $response->json();

        if (!is_array($lead)) {
            throw new RuntimeException(
                "Invalid response received for lead {$leadId}."
            );
        }

        Log::info(
            'Bitrix lead fetched from API.',
            [
                'lead_id' => $leadId,

                'assigned_by_id' =>
                    $lead['AssignedById'] ?? null,

                'observers' =>
                    $lead['Observers'] ?? null,
            ]
        );

        return $lead;
    }

    /**
     * Fetch and synchronize a Bitrix lead.
     */
    public function fetchAndSync(
        int $leadId,
        string $source = 'manual'
    ): array {
        $lead = $this->fetchLead($leadId);

        return $this->syncLead(
            lead: $lead,
            source: $source
        );
    }

    /**
     * Synchronize an already fetched Bitrix lead.
     */
    public function syncLead(
        array $lead,
        string $source = 'webhook'
    ): array {
        $this->validateLead($lead);

        $leadId = (int) $lead['LeadId'];

        $assignedById = (int) $lead['AssignedById'];

        /*
         * ---------------------------------------------------------
         * CURRENT OWNER
         * ---------------------------------------------------------
         *
         * Bitrix AssignedById maps to:
         *
         * users.bitrix_user_id
         *
         * This user is the current owner/executive.
         */
        $assignedUser = User::query()
            ->where(
                'bitrix_user_id',
                (string) $assignedById
            )
            ->first();

        /*
         * Current owner is mandatory.
         *
         * We cannot determine the current team without
         * the assigned local user.
         */
        if (!$assignedUser) {
            throw ValidationException::withMessages([
                'lead_id' => [
                    "No local user is mapped to Bitrix AssignedById {$assignedById}.",
                ],
            ]);
        }

        /*
         * ---------------------------------------------------------
         * CURRENT TEAM
         * ---------------------------------------------------------
         *
         * The user's users.team_id is their PRIMARY team.
         *
         * We intentionally DO NOT use user_team_access here.
         *
         * user_team_access can contain multiple records for
         * team_admin users, but lead assignment must always use
         * the user's primary team.
         */
        $teamId = $assignedUser->team_id;

        if (!$teamId) {
            throw ValidationException::withMessages([
                'lead_id' => [
                    "User {$assignedUser->name} does not have a primary team.",
                ],
            ]);
        }

        /*
         * ---------------------------------------------------------
         * OLD OWNER
         * ---------------------------------------------------------
         *
         * Bitrix Observers represent the old owner.
         *
         * Observers may contain:
         *
         * - one ID
         * - multiple IDs
         * - comma separated IDs
         * - semicolon separated IDs
         * - pipe separated IDs
         *
         * We resolve the first matching local user.
         */
        $observerUser = $this->resolveObserverUser(
            $lead['Observers'] ?? null
        );

        /*
         * Observer is optional.
         *
         * If Bitrix has an observer but that user isn't mapped
         * locally, don't block the lead synchronization.
         */
        if (
            !empty($lead['Observers']) &&
            !$observerUser
        ) {
            Log::warning(
                'Bitrix lead observer could not be mapped to local user.',
                [
                    'lead_id' => $leadId,

                    'observers' =>
                        $lead['Observers'],
                ]
            );
        }

        /*
         * ---------------------------------------------------------
         * PHONE
         * ---------------------------------------------------------
         */
        $normalizedPhone = $this->normalizePhone(
            $lead['Phone'] ?? null
        );

        if (!$normalizedPhone) {
            throw ValidationException::withMessages([
                'lead_id' => [
                    'The lead does not contain a valid Indian mobile number.',
                ],
            ]);
        }

        /*
         * ---------------------------------------------------------
         * DATABASE TRANSACTION
         * ---------------------------------------------------------
         */
        return DB::transaction(
            function () use ($lead, $leadId, $assignedById, $assignedUser, $teamId, $observerUser, $normalizedPhone, $source) {
                /*
                 * -----------------------------------------------------
                 * FIND BY BITRIX LEAD ID
                 * -----------------------------------------------------
                 *
                 * This is the strongest relationship.
                 */
                $customerByLeadId = Customer::withoutGlobalScopes()
                    ->withTrashed()
                    ->where(
                        'bitrix_lead_id',
                        $leadId
                    )
                    ->first();

                /*
                 * -----------------------------------------------------
                 * FIND BY PHONE
                 * -----------------------------------------------------
                 *
                 * Used when a Bitrix lead does not yet have a local
                 * customer record.
                 */
                $customerByPhone = Customer::withoutGlobalScopes()
                    ->withTrashed()
                    ->where(
                        'phone',
                        $normalizedPhone
                    )
                    ->first();

                /*
                 * -----------------------------------------------------
                 * CONFLICT PROTECTION
                 * -----------------------------------------------------
                 *
                 * Example:
                 *
                 * Lead #100 -> Customer #10
                 * Phone     -> Customer #20
                 *
                 * Never merge automatically.
                 */
                if (
                    $customerByLeadId &&
                    $customerByPhone &&
                    $customerByLeadId->id !==
                    $customerByPhone->id
                ) {
                    throw ValidationException::withMessages([
                        'lead_id' => [
                            "Bitrix Lead {$leadId} is linked to customer " .
                            "#{$customerByLeadId->id}, but phone " .
                            "{$normalizedPhone} belongs to customer " .
                            "#{$customerByPhone->id}. " .
                            'The records were not modified.',
                        ],
                    ]);
                }

                /*
                 * Lead ID gets priority.
                 *
                 * If there is no customer by lead ID,
                 * use phone match.
                 */
                $customer = $customerByLeadId
                    ?? $customerByPhone;

                /*
                 * -----------------------------------------------------
                 * PREVIOUS VALUES
                 * -----------------------------------------------------
                 */
                $previousAssignedTo =
                    $customer?->assigned_to;

                $previousTeamId =
                    $customer?->team_id;

                $previousOldOwnerId =
                    $customer?->old_owner_id;

                /*
                 * -----------------------------------------------------
                 * NEW VALUES
                 * -----------------------------------------------------
                 */
                $assignedTo =
                    $assignedUser->id;

                $oldOwnerId =
                    $observerUser?->id;

                $values = [
                    /*
                     * Bitrix identification
                     */
                    'bitrix_lead_id' =>
                        $leadId,

                    'bitrix_assigned_by_id' =>
                        $assignedById,

                    /*
                     * Customer information
                     */
                    'name' => trim(
                        $lead['Name'] ?? ''
                    ) ?: 'Unknown',

                    'email' =>
                        $this->normalizeEmail(
                            $lead['Email'] ?? null
                        ),

                    'phone' =>
                        $normalizedPhone,

                    /*
                     * Current ownership
                     */
                    'assigned_to' =>
                        $assignedTo,

                    /*
                     * Current owner's PRIMARY team
                     */
                    'team_id' =>
                        $teamId,

                    /*
                     * Bitrix Observers = OLD OWNER
                     */
                    'old_owner_id' =>
                        $oldOwnerId,

                    /*
                     * Status
                     */
                    'status' =>
                        'active',

                    /*
                     * Bitrix timestamps
                     */
                    'bitrix_created_at' =>
                        $this->parseBitrixDate(
                            $lead['CreatedDate'] ?? null
                        ),

                    'bitrix_synced_at' =>
                        now(),

                    /*
                     * Keep Bitrix lead ID in notes as well.
                     *
                     * This is optional but keeps compatibility
                     * with old data.
                     */
                    'notes' =>
                        "Bitrix24 Lead ID: {$leadId}",
                ];

                /*
                 * -----------------------------------------------------
                 * UPDATE EXISTING CUSTOMER
                 * -----------------------------------------------------
                 */
                if ($customer) {
                    $customer->fill(
                        $values
                    );

                    $customer->save();

                    /*
                     * If the customer was soft deleted and Bitrix
                     * sends the lead again, restore the customer.
                     */
                    if (
                        method_exists(
                            $customer,
                            'trashed'
                        ) &&
                        $customer->trashed()
                    ) {
                        $customer->restore();
                    }

                    $action = 'updated';
                }

                /*
                 * -----------------------------------------------------
                 * CREATE NEW CUSTOMER
                 * -----------------------------------------------------
                 */ else {
                    $customer = Customer::create(
                        $values
                    );

                    $action = 'created';
                }

                /*
                 * -----------------------------------------------------
                 * LOG
                 * -----------------------------------------------------
                 */
                Log::info(
                    'Bitrix customer synchronized.',
                    [
                        'source' =>
                            $source,

                        'lead_id' =>
                            $leadId,

                        'customer_id' =>
                            $customer->id,

                        /*
                         * Bitrix ownership
                         */
                        'bitrix_assigned_by_id' =>
                            $assignedById,

                        /*
                         * Local ownership
                         */
                        'assigned_user_id' =>
                            $assignedTo,

                        'assigned_user_name' =>
                            $assignedUser->name,

                        /*
                         * Current team
                         */
                        'previous_team_id' =>
                            $previousTeamId,

                        'new_team_id' =>
                            $teamId,

                        /*
                         * Old owner
                         */
                        'previous_old_owner_id' =>
                            $previousOldOwnerId,

                        'new_old_owner_id' =>
                            $oldOwnerId,

                        /*
                         * Current owner
                         */
                        'previous_assigned_to' =>
                            $previousAssignedTo,

                        'new_assigned_to' =>
                            $assignedTo,

                        /*
                         * Change indicators
                         */
                        'assignment_changed' =>
                            (int) $previousAssignedTo !==
                            (int) $assignedTo,

                        'team_changed' =>
                            (int) $previousTeamId !==
                            (int) $teamId,

                        'old_owner_changed' =>
                            (int) $previousOldOwnerId !==
                            (int) $oldOwnerId,

                        'observer_found' =>
                            (bool) $observerUser,

                        'action' =>
                            $action,
                    ]
                );

                /*
                 * -----------------------------------------------------
                 * RETURN RESULT
                 * -----------------------------------------------------
                 */
                return [
                    'customer' =>
                        $customer->fresh([
                            'assignedTo',
                            'team',
                            'oldOwner',
                        ]),

                    'action' =>
                        $action,

                    'assignment_changed' =>
                        (int) $previousAssignedTo !==
                        (int) $assignedTo,

                    'team_changed' =>
                        (int) $previousTeamId !==
                        (int) $teamId,

                    'old_owner_changed' =>
                        (int) $previousOldOwnerId !==
                        (int) $oldOwnerId,

                    'assigned_user_missing' =>
                        false,

                    'observer_found' =>
                        (bool) $observerUser,
                ];
            }
        );
    }

    /**
     * Validate the minimum lead information required
     * to synchronize the lead.
     */
    private function validateLead(
        array $lead
    ): void {
        $errors = [];

        if (empty($lead['LeadId'])) {
            $errors['lead_id'][] =
                'LeadId is missing from the API response.';
        }

        if (empty($lead['AssignedById'])) {
            $errors['lead_id'][] =
                'AssignedById is missing from the API response.';
        }

        if ($errors) {
            throw ValidationException::withMessages(
                $errors
            );
        }
    }

    /**
     * Normalize an Indian mobile number.
     *
     * Output:
     *
     * 91XXXXXXXXXX
     */
    private function normalizePhone(
        mixed $phone
    ): ?string {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            (string) $phone
        );

        /*
         * 9876543210
         */
        if (strlen($digits) === 10) {
            $digits =
                '91' . $digits;
        }

        /*
         * 09876543210
         */ elseif (
            strlen($digits) === 11 &&
            str_starts_with(
                $digits,
                '0'
            )
        ) {
            $digits =
                '91' .
                substr(
                    $digits,
                    1
                );
        }

        /*
         * 0919876543210
         */ elseif (
            strlen($digits) === 13 &&
            str_starts_with(
                $digits,
                '091'
            )
        ) {
            $digits =
                substr(
                    $digits,
                    1
                );
        }

        /*
         * 00919876543210
         */ elseif (
            strlen($digits) === 14 &&
            str_starts_with(
                $digits,
                '0091'
            )
        ) {
            $digits =
                substr(
                    $digits,
                    2
                );
        }

        return preg_match(
            '/^91[6-9]\d{9}$/',
            $digits
        )
            ? $digits
            : null;
    }

    /**
     * Normalize email.
     */
    private function normalizeEmail(
        mixed $email
    ): ?string {
        $email = trim(
            (string) $email
        );

        if ($email === '') {
            return null;
        }

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
            ? strtolower($email)
            : null;
    }

    /**
     * Resolve Bitrix observer(s) to a local user.
     *
     * Bitrix may return:
     *
     * - 123
     * - "123"
     * - "123,456"
     * - "123;456"
     * - "123|456"
     * - [123, 456]
     *
     * The first matching local user is returned.
     */
    private function resolveObserverUser(
        mixed $observers
    ): ?User {
        if (
            $observers === null ||
            $observers === ''
        ) {
            return null;
        }

        /*
         * Already an array.
         */
        if (is_array($observers)) {
            $observerIds =
                $observers;
        }

        /*
         * String / single value.
         */ else {
            $observerIds =
                preg_split(
                    '/[,;|]+/',
                    (string) $observers
                );
        }

        $observerIds = collect(
            $observerIds
        )
            ->map(
                fn($value) =>
                trim(
                    (string) $value
                )
            )
            ->filter(
                fn($value) =>
                $value !== ''
            )
            ->unique()
            ->values();

        if ($observerIds->isEmpty()) {
            return null;
        }

        return User::query()
            ->whereIn(
                'bitrix_user_id',
                $observerIds->all()
            )
            ->first();
    }

    /**
     * Parse Bitrix date.
     */
    private function parseBitrixDate(
        mixed $date
    ): ?Carbon {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse(
                $date
            );
        } catch (\Throwable) {
            return null;
        }
    }
}