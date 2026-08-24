<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class BitrixSyncCommand extends Command
{
    protected $signature = 'bitrix:sync';

    protected $description = 'Synchronize Bitrix departments, users, teams and team access';

    /*
    |--------------------------------------------------------------------------
    | Runtime maps
    |--------------------------------------------------------------------------
    */

    /**
     * All Bitrix departments indexed by Bitrix department ID.
     *
     * @var array<string, array>
     */
    protected array $departments = [];

    /**
     * All Bitrix agents indexed by Bitrix user ID.
     *
     * @var array<string, array>
     */
    protected array $agents = [];

    /**
     * Agent => deepest/primary department.
     *
     * Example:
     *
     * [
     *     '101' => '12',
     *     '102' => '12',
     *     '103' => '18',
     * ]
     *
     * @var array<string, string>
     */
    protected array $agentPrimaryDept = [];

    /**
     * Department => all executives.
     *
     * Example:
     *
     * [
     *     '12' => ['101', '102', '103'],
     *     '18' => ['104', '105'],
     * ]
     *
     * @var array<string, array<int, string>>
     */
    protected array $agentsByDept = [];

    /**
     * Final departments which actually have executives.
     *
     * @var array<string, bool>
     */
    protected array $finalDepartments = [];

    /**
     * Local team IDs indexed by external Bitrix department ID.
     *
     * @var array<string, int>
     */
    protected array $teamMap = [];

    /**
     * Local user IDs indexed by Bitrix user ID.
     *
     * @var array<string, int>
     */
    protected array $userMap = [];

    /**
     * Bitrix department => local Team.
     *
     * @var array<string, Team>
     */
    protected array $teamModels = [];

    /*
    |--------------------------------------------------------------------------
    | Handle
    |--------------------------------------------------------------------------
    */

    public function handle(): int
    {
        $this->info('');
        $this->info('============================================================');
        $this->info('        BITRIX → WHATSAPP TEAM SYNCHRONIZATION');
        $this->info('============================================================');
        $this->info('');

        try {
            /*
             * --------------------------------------------------------------
             * 1. Fetch Bitrix departments
             * --------------------------------------------------------------
             */
            $this->info('Fetching Bitrix departments...');

            $departments = $this->fetchDepartments();

            if (empty($departments)) {
                $this->error('No departments received from Bitrix.');
                return self::FAILURE;
            }

            $this->buildDepartmentMap($departments);

            $this->info(
                'Departments received: ' . count($this->departments)
            );

            /*
             * --------------------------------------------------------------
             * 2. Fetch Bitrix agents/users
             * --------------------------------------------------------------
             */
            $this->info('Fetching Bitrix agents...');

            $agents = $this->fetchAgents();

            if (empty($agents)) {
                $this->error('No agents received from Bitrix.');
                return self::FAILURE;
            }

            $this->buildAgentMap($agents);

            $this->info(
                'Agents received: ' . count($this->agents)
            );

            /*
             * --------------------------------------------------------------
             * 3. Build agent → department mapping
             * --------------------------------------------------------------
             */
            $this->info('Building department / executive mapping...');

            $this->buildAgentDepartmentMapping();

            /*
             * --------------------------------------------------------------
             * 4. Determine departments which actually have executives
             * --------------------------------------------------------------
             */
            $this->buildFinalDepartments();

            $this->info(
                'Departments having executives: ' .
                count($this->finalDepartments)
            );

            /*
             * --------------------------------------------------------------
             * 5. Show final Bitrix mapping before changing DB
             * --------------------------------------------------------------
             */
            $this->displayFinalMapping();

            /*
             * --------------------------------------------------------------
             * 6. Synchronize everything inside one transaction
             * --------------------------------------------------------------
             */
            DB::transaction(function () {
                /*
                 * Sync users first so we have local user IDs available.
                 */
                $this->syncUsers();

                /*
                 * Sync teams and hierarchy.
                 */
                $this->syncTeams();

                /*
                 * Sync all user ↔ team memberships.
                 */
                $this->syncTeamAccess();

                /*
                 * Assign team_admin role.
                 */
                $this->syncTeamAdmins();

                /*
                 * Remove stale team memberships.
                 */
                $this->removeStaleTeamAccess();

                /*
                 * Remove users that are no longer active in Bitrix.
                 */
                $this->deactivateStaleUsers();

                /*
                 * Remove teams which no longer exist in Bitrix OR
                 * have no executives.
                 */
                $this->removeStaleTeams();
            });

            /*
             * --------------------------------------------------------------
             * 7. Final DB mapping
             * --------------------------------------------------------------
             */
            $this->displayDatabaseMapping();

            $this->newLine();

            $this->info('============================================================');
            $this->info('                 BITRIX SYNC COMPLETED');
            $this->info('============================================================');
            $this->newLine();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();

            $this->error('Bitrix synchronization failed.');
            $this->error($e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BITRIX API
    |--------------------------------------------------------------------------
    |
    | Keep all API-specific code here.
    |
    | Replace only these two methods with your existing Bitrix service
    | calls if the method names in your project are different.
    |
    */

    protected function fetchDepartments(): array
    {
        /*
         * Example expected Bitrix response:
         *
         * [
         *     [
         *         'id' => 12,
         *         'name' => 'Sales',
         *         'parentId' => 5,
         *         'HeadUserId' => 101,
         *     ],
         * ]
         *
         * OR if your service already returns the result of the API:
         *
         * return app(BitrixService::class)->getDepartments();
         */

        $service = app(\App\Services\BitrixService::class);

        $response = $service->fetchDepartments();

        return $this->extractBitrixItems($response);
    }

    protected function fetchAgents(): array
    {
        /*
         * Example expected Bitrix response:
         *
         * [
         *     [
         *         'id' => 101,
         *         'name' => 'John Doe',
         *         'email' => 'john@example.com',
         *         'phone' => '+919999999999',
         *         'active' => true,
         *         'department' => [12, 5],
         *     ],
         * ]
         */

        $service = app(\App\Services\BitrixService::class);

        $response = $service->fetchAgents();

        return $this->extractBitrixItems($response);
    }

    /**
     * Normalize common Bitrix API response formats.
     */
    protected function extractBitrixItems(mixed $response): array
    {
        if ($response instanceof \Illuminate\Http\Client\Response) {
            $response = $response->json();
        }

        if (is_object($response)) {
            $response = method_exists($response, 'toArray')
                ? $response->toArray()
                : (array) $response;
        }

        if (!is_array($response)) {
            return [];
        }

        /*
         * Common Bitrix formats:
         *
         * [
         *     'result' => [...]
         * ]
         *
         * [
         *     'items' => [...]
         * ]
         *
         * [
         *     'data' => [...]
         * ]
         */
        if (isset($response['result']) && is_array($response['result'])) {
            return $response['result'];
        }

        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        /*
         * Already a plain list.
         */
        return array_values($response);
    }

    /*
    |--------------------------------------------------------------------------
    | DEPARTMENT MAP
    |--------------------------------------------------------------------------
    */

    protected function buildDepartmentMap(array $departments): void
    {
        $this->departments = [];

        foreach ($departments as $department) {
            $id = $this->getDepartmentId($department);

            if ($id === null) {
                continue;
            }

            $this->departments[(string) $id] = $department;
        }
    }

    protected function getDepartmentId(array $department): ?string
    {
        if (
            isset($department['id']) &&
            $department['id'] !== ''
        ) {
            return (string) $department['id'];
        }

        if (
            isset($department['Id']) &&
            $department['Id'] !== ''
        ) {
            return (string) $department['Id'];
        }

        return null;
    }

    protected function getDepartmentName(array $department): string
    {
        return trim(
            (string) (
                $department['name']
                ?? $department['Name'] 
                ?? 'Department'
            )
        );
    }

    protected function getParentDepartmentId(array $department): ?string
    {
        $parentId =
            $department['parentId']
            ?? $department['parentID']
            ?? $department['parent_id']
            ?? $department['PARENT_ID']
            ?? null;

        if ($parentId === null || $parentId === '') {
            return null;
        }

        return (string) $parentId;
    }

    protected function getDepartmentHeadId(array $department): ?string
    {
        $headId =
            $department['HeadUserId']
            ?? $department['headUserId']
            ?? $department['HEAD_USER_ID']
            ?? $department['head_user_id']
            ?? null;

        if ($headId === null || $headId === '') {
            return null;
        }

        return (string) $headId;
    }

    /*
    |--------------------------------------------------------------------------
    | AGENT MAP
    |--------------------------------------------------------------------------
    */

    protected function buildAgentMap(array $agents): void
    {
        $this->agents = [];

        foreach ($agents as $agent) {
            $id = $this->getAgentId($agent);

            if ($id === null) {
                continue;
            }

            $this->agents[(string) $id] = $agent;
        }
    }

    protected function getAgentId(array $agent): ?string
    {
        if (
            isset($agent['id']) &&
            $agent['id'] !== ''
        ) {
            return (string) $agent['id'];
        }

        if (
            isset($agent['Id']) &&
            $agent['Id'] !== ''
        ) {
            return (string) $agent['Id'];
        }

        return null;
    }

    protected function getAgentName(array $agent): string
    {
        $name = $agent['name']
            ?? $agent['Name']
            ?? trim(
                ($agent['firstName'] ?? $agent['FIRST_NAME'] ?? '') .
                ' ' .
                ($agent['lastName'] ?? $agent['LAST_NAME'] ?? '')
            );

        return trim((string) $name) ?: 'Bitrix User';
    }

    protected function getAgentEmail(array $agent): string
    {
        return trim(
            (string) (
                $agent['email']
                ?? $agent['Email']
                ?? ''
            )
        );
    }

    protected function getAgentPhone(array $agent): ?string
    {
        $phone =
            $agent['phone']
            ?? $agent['PHONE']
            ?? $agent['Mobile']
            ?? $agent['MOBILE']
            ?? null;

        return $phone !== null
            ? trim((string) $phone)
            : null;
    }

    protected function isAgentActive(array $agent): bool
    {
        /*
         * Handle the common Bitrix formats.
         */

        if (array_key_exists('active', $agent)) {
            return filter_var(
                $agent['active'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
        }

        if (array_key_exists('Active', $agent)) {
            return filter_var(
                $agent['Active'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
        }

        if (array_key_exists('isActive', $agent)) {
            return filter_var(
                $agent['isActive'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
        }

        /*
         * If Bitrix endpoint only returns active agents,
         * consider them active.
         */
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | AGENT → DEPARTMENT
    |--------------------------------------------------------------------------
    */

    protected function buildAgentDepartmentMapping(): void
    {
        $this->agentPrimaryDept = [];
        $this->agentsByDept = [];

        foreach ($this->agents as $agentId => $agent) {
            /*
             * Ignore inactive agents completely from executive mapping.
             */
            if (!$this->isAgentActive($agent)) {
                continue;
            }

            $departmentIds = $this->getAgentDepartmentIds($agent);

            if (empty($departmentIds)) {
                continue;
            }

            /*
             * Keep only departments which actually exist in our
             * department API response.
             */
            $departmentIds = array_values(
                array_filter(
                    $departmentIds,
                    fn($id) => isset($this->departments[(string) $id])
                )
            );

            if (empty($departmentIds)) {
                continue;
            }

            /*
             * Find the deepest department.
             *
             * If an agent belongs to:
             *
             * Company
             *   └── Sales
             *        └── Enterprise Sales
             *
             * Enterprise Sales becomes the primary department.
             */
            $primaryDepartment = $this->findDeepestDepartment(
                $departmentIds
            );

            if ($primaryDepartment === null) {
                continue;
            }

            $this->agentPrimaryDept[$agentId] = $primaryDepartment;

            /*
             * The agent is an executive of the primary department.
             *
             * This is what controls whether a Team should exist.
             */
            $this->agentsByDept[$primaryDepartment] ??= [];

            $this->agentsByDept[$primaryDepartment][] = (string) $agentId;
        }
    }

    protected function getAgentDepartmentIds(array $agent): array
    {
        $departments =
            $agent['DepartmentIds']
            ?? $agent['departments']
            ?? $agent['DEPARTMENT']
            ?? $agent['DEPARTMENTS']
            ?? [];

        /*
         * Sometimes department can be a single scalar.
         */
        if (!is_array($departments)) {
            $departments = [$departments];
        }

        $ids = [];

        foreach ($departments as $department) {
            if (is_array($department)) {
                $id =
                    $department['id']
                    ?? $department['Id']
                    ?? null;
            } else {
                $id = $department;
            }

            if ($id !== null && $id !== '') {
                $ids[] = (string) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function findDeepestDepartment(array $departmentIds): ?string
    {
        $deepestId = null;
        $deepestLevel = -1;

        foreach ($departmentIds as $departmentId) {
            if (!isset($this->departments[$departmentId])) {
                continue;
            }

            $level = $this->getDepartmentDepth($departmentId);

            if ($level > $deepestLevel) {
                $deepestLevel = $level;
                $deepestId = $departmentId;
            }
        }

        return $deepestId;
    }

    protected function getDepartmentDepth(
        string $departmentId,
        array &$visited = []
    ): int {
        if (isset($visited[$departmentId])) {
            return 0;
        }

        if (!isset($this->departments[$departmentId])) {
            return 0;
        }

        $visited[$departmentId] = true;

        $parentId = $this->getParentDepartmentId(
            $this->departments[$departmentId]
        );

        if ($parentId === null) {
            return 0;
        }

        return 1 + $this->getDepartmentDepth(
            $parentId,
            $visited
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL DEPARTMENTS
    |--------------------------------------------------------------------------
    */

    protected function buildFinalDepartments(): void
    {
        $this->finalDepartments = [];

        foreach ($this->agentsByDept as $departmentId => $agentIds) {
            /*
             * Do not create empty departments.
             */
            if (empty($agentIds)) {
                continue;
            }

            /*
             * Make sure every department really exists in Bitrix.
             */
            if (!isset($this->departments[$departmentId])) {
                continue;
            }

            $this->finalDepartments[$departmentId] = true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | USERS
    |--------------------------------------------------------------------------
    */

    protected function syncUsers(): void
    {
        $this->info('Synchronizing users...');

        $role = Role::firstOrCreate([
            'name' => 'team_admin',
            'guard_name' => 'web',
        ]);

        foreach ($this->agents as $bitrixUserId => $agent) {
            $bitrixUserId = (string) $bitrixUserId;

            /*
             * Only create new users when Bitrix says they are active.
             */
            $isActive = $this->isAgentActive($agent);

            $user = User::withTrashed()
                ->where('bitrix_user_id', $bitrixUserId)
                ->first();

            /*
             * Existing soft-deleted user:
             * restore it only if Bitrix says active.
             */
            if ($user && $user->trashed() && $isActive) {
                $user->restore();
            }

            /*
             * New inactive Bitrix user:
             * do NOT create.
             */
            if (!$user && !$isActive) {
                continue;
            }

            /*
             * Existing user or new active user.
             */
            if (!$user) {
                $email = $this->getAgentEmail($agent);

                /*
                 * Email is required by your users table.
                 */
                if ($email === '') {
                    $email = 'bitrix_' . $bitrixUserId . '@invalid.local';
                }

                $user = new User();

                $user->bitrix_user_id = $bitrixUserId;
                $user->password = Hash::make('Arihant@123');
            }

            $user->name = $this->getAgentName($agent);

            $email = $this->getAgentEmail($agent);

            if ($email !== '') {
                $user->email = $email;
            } elseif (!$user->email) {
                $user->email =
                    'bitrix_' .
                    $bitrixUserId .
                    '@invalid.local';
            }

            $phone = $this->getAgentPhone($agent);

            if ($phone !== null) {
                $user->phone = $phone;
            }

            $user->is_active = $isActive ? 1 : 0;

            /*
             * team_id represents the user's PRIMARY team.
             *
             * We set this later after teams have been synchronized.
             */
            $user->save();

            $this->userMap[$bitrixUserId] = $user->id;
        }

        $this->info(
            'Users synchronized: ' . count($this->userMap)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TEAMS
    |--------------------------------------------------------------------------
    */

    protected function syncTeams(): void
    {
        $this->info('Synchronizing teams...');

        /*
         * First create/update all teams without parent_team_id.
         *
         * This is important because a child team's parent_team_id
         * requires the parent local Team ID.
         */
        foreach ($this->finalDepartments as $departmentId => $_) {
            $this->createOrUpdateTeam(
                (string) $departmentId
            );
        }

        /*
         * Now resolve parent_team_id.
         */
        foreach ($this->finalDepartments as $departmentId => $_) {
            $team = $this->teamModels[$departmentId];

            $parentDepartmentId = $this->getParentDepartmentId(
                $this->departments[$departmentId]
            );

            /*
             * If parent department is not an active final department,
             * parent_team_id remains NULL.
             */
            if (
                $parentDepartmentId === null ||
                !isset($this->finalDepartments[$parentDepartmentId])
            ) {
                $team->parent_team_id = null;
                $team->hierarchy_path = $this->buildHierarchyPath(
                    $departmentId
                );

                $team->save();

                continue;
            }

            $parentTeam = $this->teamModels[$parentDepartmentId] ?? null;

            if (!$parentTeam) {
                $team->parent_team_id = null;
            } else {
                $team->parent_team_id = $parentTeam->id;
            }

            $team->hierarchy_path = $this->buildHierarchyPath(
                $departmentId
            );

            $team->save();
        }
    }

    protected function createOrUpdateTeam(string $departmentId): Team
    {
        $department = $this->departments[$departmentId];

        $name = $this->getDepartmentName($department);

        /*
         * IMPORTANT:
         *
         * external_department_id is the Bitrix department ID.
         */
        $team = Team::withTrashed()
            ->where(
                'external_department_id',
                $departmentId
            )
            ->first();

        if ($team && $team->trashed()) {
            $team->restore();
        }

        if (!$team) {
            $team = new Team();

            $team->external_department_id = $departmentId;
        }

        $team->name = $name;
        $team->slug = $this->makeUniqueTeamSlug(
            $name,
            $team->id
        );
        $team->is_active = 1;

        /*
         * Parent is resolved in the second pass.
         */
        $team->save();

        $this->teamMap[$departmentId] = $team->id;
        $this->teamModels[$departmentId] = $team;

        return $team;
    }

    protected function makeUniqueTeamSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'team';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            Team::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn($query) =>
                    $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $counter++;

            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | HIERARCHY
    |--------------------------------------------------------------------------
    */

    protected function buildHierarchyPath(string $departmentId): string
    {
        $path = [];

        $currentId = $departmentId;
        $visited = [];

        while (
            $currentId !== null &&
            isset($this->departments[$currentId])
        ) {
            /*
             * Protect against malformed circular Bitrix hierarchy.
             */
            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;

            array_unshift($path, $currentId);

            $currentId = $this->getParentDepartmentId(
                $this->departments[$currentId]
            );

            /*
             * Only keep departments that have local Teams.
             *
             * Empty departments aren't created.
             */
            if (
                $currentId !== null &&
                !isset($this->finalDepartments[$currentId])
            ) {
                /*
                 * Continue walking the Bitrix hierarchy, because
                 * the eventual parent may be an active department.
                 */
                continue;
            }
        }

        return implode('/', $path);
    }

    /*
    |--------------------------------------------------------------------------
    | USER ↔ TEAM ACCESS
    |--------------------------------------------------------------------------
    */

    protected function syncTeamAccess(): void
    {
        $this->info('Synchronizing executive team access...');

        /*
         * IMPORTANT:
         *
         * Executives can belong to ONLY ONE team.
         *
         * Their primary/deepest Bitrix department is already calculated
         * in $this->agentPrimaryDept.
         *
         * Therefore:
         *
         * users.team_id
         *      =
         * primary local Team
         *
         * user_team_access
         *      =
         * exactly ONE record for the executive
         */

        foreach ($this->agentPrimaryDept as $bitrixUserId => $departmentId) {

            if (!isset($this->userMap[$bitrixUserId])) {
                continue;
            }

            if (!isset($this->teamMap[$departmentId])) {
                continue;
            }

            $userId = $this->userMap[$bitrixUserId];
            $teamId = $this->teamMap[$departmentId];

            /*
             * Update primary team.
             */
            User::where('id', $userId)
                ->update([
                    'team_id' => $teamId,
                ]);

            /*
             * Executive must have exactly ONE user_team_access record.
             *
             * Remove every existing access record first.
             */
            DB::table('user_team_access')
                ->where('user_id', $userId)
                ->delete();

            /*
             * Then create the single primary-team access record.
             */
            DB::table('user_team_access')->insert([
                'user_id' => $userId,
                'team_id' => $teamId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TEAM ADMIN
    |--------------------------------------------------------------------------
    */

    protected function syncTeamAdmins(): void
    {
        $this->info('Synchronizing team administrators...');

        $role = Role::firstOrCreate([
            'name' => 'team_admin',
            'guard_name' => 'web',
        ]);

        foreach ($this->finalDepartments as $departmentId => $_) {
            $team = $this->teamModels[$departmentId] ?? null;

            if (!$team) {
                continue;
            }

            $headUserId = $this->getDepartmentHeadId(
                $this->departments[$departmentId]
            );

            /*
             * No HeadUserId in Bitrix.
             */
            if ($headUserId === null) {
                continue;
            }

            /*
             * Head must be an active user that exists locally.
             */
            if (!isset($this->userMap[$headUserId])) {
                continue;
            }

            $user = User::find(
                $this->userMap[$headUserId]
            );

            if (!$user || !$user->is_active) {
                continue;
            }

            /*
             * team_admin is additive.
             *
             * We do NOT remove other roles from the user.
             */
            if (!$user->hasRole('team_admin')) {
                $user->assignRole($role);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STALE ACCESS
    |--------------------------------------------------------------------------
    */

    protected function removeStaleTeamAccess(): void
    {
        $this->info('Removing stale team access...');

        /*
         * Only memberships belonging to synchronized users are
         * considered here.
         */
        foreach ($this->userMap as $bitrixUserId => $userId) {
            $validTeamIds = [];

            foreach ($this->agentsByDept as $departmentId => $agentIds) {
                if (
                    in_array(
                        (string) $bitrixUserId,
                        array_map('strval', $agentIds),
                        true
                    ) &&
                    isset($this->teamMap[$departmentId])
                ) {
                    $validTeamIds[] =
                        $this->teamMap[$departmentId];
                }
            }

            /*
             * Remove memberships not present in Bitrix mapping.
             */
            $query = DB::table('user_team_access')
                ->where('user_id', $userId);

            if (empty($validTeamIds)) {
                $query->delete();
            } else {
                $query
                    ->whereNotIn('team_id', $validTeamIds)
                    ->delete();
            }

            /*
             * If primary team is no longer valid, use the first
             * valid team or NULL.
             */
            $user = User::find($userId);

            if (!$user) {
                continue;
            }

            if (
                $user->team_id !== null &&
                !in_array(
                    (int) $user->team_id,
                    array_map('intval', $validTeamIds),
                    true
                )
            ) {
                $user->team_id =
                    $validTeamIds[0] ?? null;

                $user->save();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STALE USERS
    |--------------------------------------------------------------------------
    */

    protected function deactivateStaleUsers(): void
    {
        $this->info('Deactivating stale Bitrix users...');

        /*
         * Users which were previously synced from Bitrix but are
         * no longer returned as active should be deactivated.
         *
         * We intentionally do NOT delete them.
         */
        $bitrixUserIds = array_keys($this->agents);

        User::query()
            ->whereNotNull('bitrix_user_id')
            ->whereNotIn(
                'bitrix_user_id',
                array_map('strval', $bitrixUserIds)
            )
            ->update([
                'is_active' => 0,
                'team_id' => null,
                'updated_at' => now(),
            ]);

        /*
         * Remove their team access.
         */
        $staleUserIds = User::query()
            ->whereNotNull('bitrix_user_id')
            ->where('is_active', 0)
            ->pluck('id');

        if ($staleUserIds->isNotEmpty()) {
            DB::table('user_team_access')
                ->whereIn('user_id', $staleUserIds)
                ->delete();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STALE TEAMS
    |--------------------------------------------------------------------------
    */

    protected function removeStaleTeams(): void
    {
        $this->info('Removing stale / empty teams...');

        /*
         * Any team created from Bitrix is identified through
         * external_department_id.
         */
        $teams = Team::query()
            ->whereNotNull('external_department_id')
            ->get();

        foreach ($teams as $team) {
            $departmentId =
                (string) $team->external_department_id;

            /*
             * Team does not exist in final Bitrix mapping.
             *
             * This covers:
             *
             * - deleted department
             * - department with no executives
             * - department which no longer qualifies as a Team
             */
            if (!isset($this->finalDepartments[$departmentId])) {
                /*
                 * Remove team access first.
                 */
                DB::table('user_team_access')
                    ->where('team_id', $team->id)
                    ->delete();

                /*
                 * Clear users' primary team.
                 */
                User::where('team_id', $team->id)
                    ->update([
                        'team_id' => null,
                    ]);

                /*
                 * User specifically requested permanent removal
                 * for empty teams.
                 */
                $team->forceDelete();

                unset(
                    $this->teamMap[$departmentId],
                    $this->teamModels[$departmentId]
                );

                continue;
            }

            /*
             * Safety check:
             *
             * A final department MUST have at least one executive.
             */
            $executiveCount =
                count(
                    $this->agentsByDept[$departmentId] ?? []
                );

            if ($executiveCount === 0) {
                DB::table('user_team_access')
                    ->where('team_id', $team->id)
                    ->delete();

                User::where('team_id', $team->id)
                    ->update([
                        'team_id' => null,
                    ]);

                $team->forceDelete();

                unset(
                    $this->teamMap[$departmentId],
                    $this->teamModels[$departmentId]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONSOLE OUTPUT
    |--------------------------------------------------------------------------
    */

    protected function displayFinalMapping(): void
    {
        $this->newLine();

        $this->info(
            '============================================================'
        );

        $this->info(
            '                 FINAL BITRIX MAPPING'
        );

        $this->info(
            '============================================================'
        );

        if (empty($this->finalDepartments)) {
            $this->warn('No departments have executives.');
            return;
        }

        foreach ($this->finalDepartments as $departmentId => $_) {
            $department =
                $this->departments[$departmentId];

            $name =
                $this->getDepartmentName($department);

            $parentId =
                $this->getParentDepartmentId($department);

            $headId =
                $this->getDepartmentHeadId($department);

            $this->newLine();

            $this->line(
                "<fg=cyan>Department:</> {$name}"
            );

            $this->line(
                "<fg=gray>Bitrix Department ID:</> {$departmentId}"
            );

            $this->line(
                "<fg=gray>Parent Department ID:</> " .
                ($parentId ?? 'NULL')
            );

            $this->line(
                "<fg=gray>HeadUserId:</> " .
                ($headId ?? 'NULL')
            );

            $agentIds =
                $this->agentsByDept[$departmentId] ?? [];

            $this->line(
                "<fg=green>Executives:</> " .
                count($agentIds)
            );

            foreach ($agentIds as $agentId) {
                $agent =
                    $this->agents[$agentId] ?? [];

                $agentName =
                    $this->getAgentName($agent);

                $this->line(
                    "    - {$agentName} " .
                    "(Bitrix ID: {$agentId})"
                );
            }
        }

        $this->newLine();

        $this->info(
            '============================================================'
        );
    }

    protected function displayDatabaseMapping(): void
    {
        $this->newLine();

        $this->info(
            '============================================================'
        );

        $this->info(
            '                 DATABASE TEAM MAP'
        );

        $this->info(
            '============================================================'
        );

        $teams = Team::query()
            ->whereNotNull('external_department_id')
            ->with([
                'parentTeam',
            ])
            ->orderBy('name')
            ->get();

        foreach ($teams as $team) {
            $this->newLine();

            $this->line(
                "<fg=cyan>Team:</> {$team->name}"
            );

            $this->line(
                "<fg=gray>Local Team ID:</> {$team->id}"
            );

            $this->line(
                "<fg=gray>External Department ID:</> " .
                $team->external_department_id
            );

            $this->line(
                "<fg=gray>Parent Team ID:</> " .
                ($team->parent_team_id ?? 'NULL')
            );

            $users = User::query()
                ->where('team_id', $team->id)
                ->where('is_active', 1)
                ->get();

            $this->line(
                "<fg=green>Primary Team Executives:</> " .
                $users->count()
            );

            foreach ($users as $user) {
                $this->line(
                    "    - {$user->name} " .
                    "(Bitrix ID: {$user->bitrix_user_id})"
                );
            }
        }

        $this->newLine();

        $this->info(
            '============================================================'
        );
    }
}