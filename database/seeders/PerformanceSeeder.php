<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notification\Support\NotificationSchedule;
use App\Domain\Player\Models\Player;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Builds a realistic multi-tenant dataset for the #33 index review.
 *
 * It is deliberately NOT called from DatabaseSeeder: it writes hundreds of thousands of
 * rows and must only run on a throwaway database.
 *
 * Usage (PowerShell, throwaway MySQL database):
 *
 *     $env:DB_CONNECTION = 'mysql'
 *     $env:DB_DATABASE   = 'ryada_perf'
 *     $env:CACHE_STORE   = 'array'
 *     $env:QUEUE_CONNECTION = 'sync'
 *     php artisan migrate:fresh --force
 *     php artisan db:seed --class=PerformanceSeeder
 *
 * On the reference Windows/Herd machine (MySQL 8.0.46) a full run takes about 80-95
 * seconds. The data is deterministic for a given day: the random generator is seeded
 * with a fixed value and every date is expressed relative to today, so the shape (and the
 * EXPLAIN plans) stay stable while the actual calendar dates move with the run day.
 *
 * The seeder wants an empty database. It refuses to run in production and refuses to
 * run when a club already exists, so a second call can never double the dataset.
 */
class PerformanceSeeder extends Seeder
{
    private const RANDOM_SEED = 33;

    private const CHUNK = 800;

    /** @var list<string> */
    private array $firstNames = [
        'Ahmed', 'Mohamed', 'Mahmoud', 'Mostafa', 'Omar', 'Youssef', 'Karim', 'Hassan',
        'Sara', 'Nour', 'Mariam', 'Salma', 'Hana', 'Yara', 'Farida', 'Aya',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'Hassan', 'Ibrahim', 'Mahmoud', 'Ali', 'Sayed', 'Fathy', 'Nabil', 'Kamal',
        'El-Sherif', 'Abdelrahman', 'Mostafa', 'Zaki', 'Fouad', 'Rashad', 'Samir', 'Adel',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('PerformanceSeeder must never run in production.');
        }

        if (DB::table('clubs')->exists()) {
            throw new RuntimeException(
                'PerformanceSeeder expects an empty database. Run `php artisan migrate:fresh` first.'
            );
        }

        $startedAt = microtime(true);

        mt_srand(self::RANDOM_SEED);

        $today = Carbon::today();
        $now = Carbon::now();
        $passwordHash = Hash::make('password');

        // One transaction keeps the many chunked inserts cheap on MySQL (one fsync at
        // commit instead of one per chunk). A failure rolls the whole dataset back.
        DB::beginTransaction();

        try {
            $sports = $this->seedSports($now);
            $bigClubId = 1;

            /** @var array<int, list<int>> $usersByClub club id => user ids (owner first) */
            $usersByClub = [];
            /** @var array<int, array{owner: int, users: list<int>, coaches: list<int>}> $staffing */
            $staffing = [];

            $clubDefinitions = $this->clubDefinitions();
            $this->seedClubs($clubDefinitions, $now, $today);

            $this->seedRolesAndPermissions($now);

            $this->seedUsersAndRoles($clubDefinitions, $passwordHash, $now, $usersByClub, $staffing);

            $this->seedClubSports($clubDefinitions, $sports, $now);

            /** @var array<int, list<array{id: int, sport_id: int, price_cents: int, duration_months: int}>> $plansByClub */
            $plansByClub = [];
            $planId = 0;
            $this->seedMembershipPlans($clubDefinitions, $sports, $now, $plansByClub, $planId);

            /** @var array<int, list<array{id: int, sport_id: int}>> $playersByClub */
            $playersByClub = [];
            $playerId = 0;
            $this->seedPlayers($clubDefinitions, $now, $playersByClub, $playerId);

            $this->seedPlayerSports($playersByClub, $plansByClub, $now);

            /** @var array<int, list<array{id: int, sport_id: int, member_ids: list<int>, dates: list<string>}>> $groupsByClub */
            $groupsByClub = [];
            $groupId = 0;
            $scheduleId = 0;
            $this->seedTrainingGroups(
                $clubDefinitions,
                $playersByClub,
                $staffing,
                $now,
                $today,
                $groupsByClub,
                $groupId,
                $scheduleId,
            );

            /** @var array<int, array{0: int, 1: int}> $refundCandidates subscription id => [payment id, amount] */
            $refundCandidates = [];
            $subscriptionId = 0;
            $paymentId = 0;
            $refundId = 0;
            $receiptCounters = [];
            $refundCounters = [];

            $this->seedSubscriptionsAndPayments(
                $clubDefinitions,
                $playersByClub,
                $plansByClub,
                $staffing,
                $now,
                $today,
                $subscriptionId,
                $paymentId,
                $refundId,
                $receiptCounters,
                $refundCounters,
                $refundCandidates,
            );

            $this->seedDocumentSequences($clubDefinitions, $now, $receiptCounters, $refundCounters);

            $this->seedNotifications(
                $clubDefinitions,
                $usersByClub,
                $staffing,
                $subscriptionId,
                $today,
                $now,
            );

            $this->seedStaffInvitations($clubDefinitions, $staffing, $now);

            $this->seedActivityLog($clubDefinitions, $playersByClub, $staffing, $now);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $elapsed = microtime(true) - $startedAt;

        $this->printSummary($bigClubId, $elapsed);
    }

    /**
     * @return list<array{id: int, slug: string}>
     */
    private function seedSports(Carbon $now): array
    {
        $sports = [
            ['id' => 1, 'slug' => 'karate', 'en' => 'Karate', 'ar' => 'كاراتيه'],
            ['id' => 2, 'slug' => 'judo', 'en' => 'Judo', 'ar' => 'الجودو'],
            ['id' => 3, 'slug' => 'taekwondo', 'en' => 'Taekwondo', 'ar' => 'التايكوندو'],
            ['id' => 4, 'slug' => 'gymnastics', 'en' => 'Gymnastics', 'ar' => 'الجمباز'],
        ];

        $rows = [];

        foreach ($sports as $sport) {
            $rows[] = [
                'id' => $sport['id'],
                'slug' => $sport['slug'],
                'name' => json_encode(['en' => $sport['en'], 'ar' => $sport['ar']], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('sports', $rows);

        return array_map(fn (array $sport): array => [
            'id' => $sport['id'],
            'slug' => $sport['slug'],
        ], $sports);
    }

    /**
     * 20 clubs: id 1 is the big one, ids 2..20 are small. The sport ids each club owns
     * mirror "3 sports for the big club, 1-2 for the small ones".
     *
     * @return array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>
     */
    private function clubDefinitions(): array
    {
        $definitions = [];

        $definitions[1] = [
            'id' => 1,
            'players' => 5000,
            'sports' => [1, 2, 3],
            'staff' => 15,
            'groups' => 60,
            'notifications' => 2000,
            'activity' => 20000,
        ];

        for ($clubId = 2; $clubId <= 20; $clubId++) {
            $playerCount = 50 + (($clubId * 37) % 101); // 50..150, deterministic
            $sportCount = $clubId % 3 === 0 ? 2 : 1;

            $definitions[$clubId] = [
                'id' => $clubId,
                'players' => $playerCount,
                'sports' => array_slice([1, 2, 3, 4], ($clubId - 2) % 4, $sportCount),
                'staff' => 2,
                'groups' => max(1, intdiv($playerCount, 50)),
                'notifications' => 100,
                'activity' => 200,
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     */
    private function seedClubs(array $definitions, Carbon $now, Carbon $today): void
    {
        $rows = [];

        foreach ($definitions as $definition) {
            $id = $definition['id'];

            $rows[] = [
                'id' => $id,
                'name' => $id === 1 ? 'Performance Big Club' : sprintf('Performance Club %02d', $id),
                'slug' => sprintf('perf-club-%02d', $id),
                'status' => 'active',
                'suspended_at' => null,
                'logo_path' => null,
                'contact_email' => sprintf('club%02d@perf.ryada.test', $id),
                'contact_phone' => sprintf('010%08d', $id),
                'city' => 'Cairo',
                'address' => sprintf('%d Performance Street', $id),
                'plan_id' => null,
                // Noon, never midnight: a midnight timestamp can fall in a DST gap
                // (for example Africa/Cairo on the last Friday of April).
                'subscription_starts_at' => $now->copy()->subMonth()->setTime(12, 0),
                'subscription_ends_at' => $now->copy()->addMonths(6)->setTime(12, 0),
                'created_at' => $now->copy()->subMonths(8),
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('clubs', $rows);
    }

    private function seedRolesAndPermissions(Carbon $now): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $permissionRows = [];

        foreach (PermissionName::cases() as $permission) {
            $permissionRows[] = [
                'name' => $permission->value,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('permissions', $permissionRows);

        /** @var array<string, int> $permissionIds */
        $permissionIds = DB::table('permissions')->pluck('id', 'name')->all();

        $roleRows = [];
        $roleId = 0;

        /** @var array<int, array{id: int, name: string, club_id: int}> $roles */
        $roles = [];

        for ($clubId = 1; $clubId <= 20; $clubId++) {
            foreach (RoleName::cases() as $roleName) {
                $roleId++;
                $roles[$roleId] = ['id' => $roleId, 'name' => $roleName->value, 'club_id' => $clubId];

                $roleRows[] = [
                    'id' => $roleId,
                    'club_id' => $clubId,
                    'name' => $roleName->value,
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('roles', $roleRows);

        $pivotRows = [];

        foreach ($roles as $role) {
            $roleName = RoleName::from($role['name']);

            foreach ($roleName->permissions() as $permission) {
                $pivotRows[] = [
                    'permission_id' => $permissionIds[$permission->value],
                    'role_id' => $role['id'],
                ];
            }
        }

        foreach (array_chunk($pivotRows, self::CHUNK) as $chunk) {
            DB::table('role_has_permissions')->insert($chunk);
        }
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, list<int>>  $usersByClub
     * @param  array<int, array{owner: int, users: list<int>, coaches: list<int>}>  $staffing
     */
    private function seedUsersAndRoles(
        array $definitions,
        string $passwordHash,
        Carbon $now,
        array &$usersByClub,
        array &$staffing,
    ): void {
        $userRows = [];
        $userId = 0;

        /** @var array<int, array{id: int, club_id: int, role: RoleName}> $memberships */
        $memberships = [];

        /** @var array<int, array<string, int>> $roleIdsByClub club id => role name => role id */
        $roleIdsByClub = [];

        foreach (DB::table('roles')->get(['id', 'club_id', 'name']) as $role) {
            $roleIdsByClub[(int) $role->club_id][(string) $role->name] = (int) $role->id;
        }

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $userIds = [];
            $coachIds = [];

            $ownerId = ++$userId;
            $userRows[] = $this->userRow(
                $ownerId,
                $clubId,
                'Owner',
                $clubId,
                $passwordHash,
                $now,
            );
            $userIds[] = $ownerId;
            $memberships[] = ['id' => $ownerId, 'club_id' => $clubId, 'role' => RoleName::Owner];

            $roleCycle = [RoleName::Staff, RoleName::Coach, RoleName::Referee];

            for ($index = 1; $index <= $definition['staff']; $index++) {
                $staffId = ++$userId;
                $role = $roleCycle[($index - 1) % count($roleCycle)];

                $userRows[] = $this->userRow(
                    $staffId,
                    $clubId,
                    sprintf('Staff %02d', $index),
                    $clubId,
                    $passwordHash,
                    $now,
                );
                $userIds[] = $staffId;

                if ($role === RoleName::Coach) {
                    $coachIds[] = $staffId;
                }

                $memberships[] = ['id' => $staffId, 'club_id' => $clubId, 'role' => $role];
            }

            $usersByClub[$clubId] = $userIds;
            $staffing[$clubId] = [
                'owner' => $ownerId,
                'users' => $userIds,
                'coaches' => $coachIds,
            ];
        }

        $this->insertChunked('users', $userRows);

        $modelType = (new User)->getMorphClass();
        $pivotRows = [];

        foreach ($memberships as $membership) {
            $pivotRows[] = [
                'club_id' => $membership['club_id'],
                'role_id' => $roleIdsByClub[$membership['club_id']][$membership['role']->value],
                'model_type' => $modelType,
                'model_id' => $membership['id'],
            ];
        }

        foreach (array_chunk($pivotRows, self::CHUNK) as $chunk) {
            DB::table('model_has_roles')->insert($chunk);
        }
    }

    private function userRow(
        int $id,
        int $clubId,
        string $label,
        int $suffix,
        string $passwordHash,
        Carbon $now,
    ): array {
        return [
            'id' => $id,
            'club_id' => $clubId,
            'name' => $label.' '.$this->personName(),
            'email' => sprintf('club%02d.user%03d@perf.ryada.test', $suffix, $id),
            'email_verified_at' => $now,
            'password' => $passwordHash,
            'remember_token' => null,
            'locale' => $id % 2 === 0 ? 'ar' : 'en',
            'created_at' => $now->copy()->subMonths(7),
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  list<array{id: int, slug: string}>  $sports
     */
    private function seedClubSports(array $definitions, array $sports, Carbon $now): void
    {
        $rows = [];

        foreach ($definitions as $definition) {
            foreach ($definition['sports'] as $sportId) {
                $rows[] = [
                    'club_id' => $definition['id'],
                    'sport_id' => $sportId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('club_sport', $rows);
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  list<array{id: int, slug: string}>  $sports
     * @param  array<int, list<array{id: int, sport_id: int, price_cents: int, duration_months: int}>>  $plansByClub
     */
    private function seedMembershipPlans(
        array $definitions,
        array $sports,
        Carbon $now,
        array &$plansByClub,
        int &$planId,
    ): void {
        $rows = [];
        $durations = [1, 3, 6, 12];

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $plans = [];

            foreach ($definition['sports'] as $sportIndex => $sportId) {
                $planCount = $clubId === 1 ? 4 : 1;

                for ($index = 1; $index <= $planCount; $index++) {
                    $planId++;

                    $duration = $durations[($planId + $sportIndex) % count($durations)];
                    $price = ($clubId === 1 ? 40000 : 30000) + ($index * 5000);

                    $plans[] = [
                        'id' => $planId,
                        'sport_id' => $sportId,
                        'price_cents' => $price,
                        'duration_months' => $duration,
                    ];

                    $rows[] = [
                        'id' => $planId,
                        'club_id' => $clubId,
                        'sport_id' => $sportId,
                        'name' => sprintf('Plan %02d', $index),
                        'price_cents' => $price,
                        'currency' => 'EGP',
                        'duration_months' => $duration,
                        'is_active' => true,
                        'created_at' => $now->copy()->subMonths(6),
                        'updated_at' => $now,
                    ];
                }
            }

            $plansByClub[$clubId] = $plans;
        }

        $this->insertChunked('membership_plans', $rows);
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, list<array{id: int, sport_id: int, price_cents: int, duration_months: int}>>  $plansByClub
     * @param  array<int, list<array{id: int, sport_id: int}>>  $playersByClub
     */
    private function seedPlayers(
        array $definitions,
        Carbon $now,
        array &$playersByClub,
        int &$playerId,
    ): void {
        $genders = ['male', 'female'];
        $nationalCounter = 0;

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $players = [];
            $buffer = [];

            for ($index = 1; $index <= $definition['players']; $index++) {
                $playerId++;
                $nationalCounter++;

                // 14 digits: a stable prefix plus a globally unique counter.
                $nationalId = '2'.str_pad((string) $nationalCounter, 13, '0', STR_PAD_LEFT);

                $birthDate = $now->copy()
                    ->subYears(6 + ($index % 40))
                    ->subDays(($index * 7) % 365)
                    ->toDateString();

                $sportId = $definition['sports'][$index % count($definition['sports'])];

                $players[] = ['id' => $playerId, 'sport_id' => $sportId];

                $buffer[] = [
                    'id' => $playerId,
                    'club_id' => $clubId,
                    'name' => $this->personName(),
                    'national_id' => $nationalId,
                    'birth_date' => $birthDate,
                    'gender' => $genders[$index % 2],
                    'phone' => sprintf('011%08d', $nationalCounter % 100000000),
                    'created_at' => $now->copy()->subDays(($index * 3) % 180),
                    'updated_at' => $now->copy()->subDays(($index * 2) % 90),
                ];

                if (count($buffer) >= self::CHUNK) {
                    DB::table('players')->insert($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                DB::table('players')->insert($buffer);
            }

            $playersByClub[$clubId] = $players;
        }
    }

    /**
     * Each player is enrolled in their primary sport and, for roughly half of them, a
     * second sport of the same club. This feeds the `player_sport` overlap/enrolment checks.
     *
     * @param  array<int, list<array{id: int, sport_id: int}>>  $playersByClub
     * @param  array<int, list<array{id: int, sport_id: int, price_cents: int, duration_months: int}>>  $plansByClub
     */
    private function seedPlayerSports(array $playersByClub, array $plansByClub, Carbon $now): void
    {
        foreach ($playersByClub as $clubId => $players) {
            $clubSportIds = array_values(array_unique(array_map(
                static fn (array $plan): int => $plan['sport_id'],
                $plansByClub[$clubId],
            )));

            /** @var array<string, true> $seen */
            $seen = [];
            $buffer = [];

            foreach ($players as $index => $player) {
                $sportIds = [$player['sport_id']];

                if ($index % 2 === 0 && count($clubSportIds) > 1) {
                    $sportIds[] = $clubSportIds[($index + 1) % count($clubSportIds)];
                }

                foreach (array_unique($sportIds) as $sportId) {
                    $key = $player['id'].':'.$sportId;

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;

                    $buffer[] = [
                        'club_id' => $clubId,
                        'player_id' => $player['id'],
                        'sport_id' => $sportId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($buffer) >= self::CHUNK) {
                    DB::table('player_sport')->insert($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                DB::table('player_sport')->insert($buffer);
            }
        }
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, list<array{id: int, sport_id: int}>>  $playersByClub
     * @param  array<int, array{owner: int, users: list<int>, coaches: list<int>}>  $staffing
     * @param  array<int, list<array{id: int, sport_id: int, member_ids: list<int>, dates: list<string>}>>  $groupsByClub
     */
    private function seedTrainingGroups(
        array $definitions,
        array $playersByClub,
        array $staffing,
        Carbon $now,
        Carbon $today,
        array &$groupsByClub,
        int &$groupId,
        int &$scheduleId,
    ): void {
        $memberRows = [];
        $attendanceRows = [];

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $isBig = $clubId === 1;

            $players = $playersByClub[$clubId];
            $playersBySport = [];

            foreach ($players as $player) {
                $playersBySport[$player['sport_id']][] = $player['id'];
            }

            $sportIds = $definition['sports'];
            $groups = [];
            $groupsPerSport = (int) ceil($definition['groups'] / count($sportIds));
            $memberCount = $isBig ? 40 : min(20, max(5, intdiv($definition['players'], 3)));
            $dateCount = $isBig ? 42 : 20;

            $dates = [];

            for ($dateIndex = 0; $dateIndex < $dateCount; $dateIndex++) {
                $daysAgo = $isBig
                    ? (int) floor($dateIndex * 1.4)
                    : $dateIndex;

                $dates[] = $today->copy()->subDays($daysAgo)->toDateString();
            }

            foreach ($sportIds as $sportId) {
                $sportPlayers = $playersBySport[$sportId] ?? [];
                $coachPool = $staffing[$clubId]['coaches'];
                $coachId = $coachPool === []
                    ? null
                    : $coachPool[$sportId % count($coachPool)];

                for ($index = 1; $index <= $groupsPerSport; $index++) {
                    $groupId++;

                    // Written immediately so the attendance rows can reference it at once.
                    DB::table('training_groups')->insert([
                        'id' => $groupId,
                        'club_id' => $clubId,
                        'sport_id' => $sportId,
                        'coach_id' => $coachId,
                        'name' => sprintf('Group %02d', $index),
                        'is_active' => true,
                        'created_at' => $now->copy()->subMonths(5),
                        'updated_at' => $now,
                    ]);

                    // Two distinct weekly slots per group.
                    $slots = [
                        ['day' => ($index + $sportId) % 7, 'start' => '17:00:00', 'end' => '18:30:00'],
                        ['day' => ($index + $sportId + 2) % 7, 'start' => '19:00:00', 'end' => '20:30:00'],
                    ];

                    foreach ($slots as $slot) {
                        $scheduleId++;

                        DB::table('training_group_schedules')->insert([
                            'id' => $scheduleId,
                            'club_id' => $clubId,
                            'training_group_id' => $groupId,
                            'day_of_week' => $slot['day'],
                            'start_time' => $slot['start'],
                            'end_time' => $slot['end'],
                            'created_at' => $now->copy()->subMonths(5),
                            'updated_at' => $now,
                        ]);
                    }

                    $memberIds = array_slice(
                        $sportPlayers,
                        ($index * 7) % max(1, count($sportPlayers)),
                        $memberCount,
                    );

                    if (count($memberIds) < $memberCount && $sportPlayers !== []) {
                        $memberIds = array_slice($sportPlayers, 0, $memberCount);
                    }

                    foreach ($memberIds as $memberId) {
                        $memberRows[] = [
                            'club_id' => $clubId,
                            'training_group_id' => $groupId,
                            'player_id' => $memberId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    foreach ($dates as $date) {
                        foreach ($memberIds as $memberId) {
                            $mod = ($memberId + $groupId + (int) str_replace('-', '', $date)) % 100;
                            $status = $mod < 82 ? 'present' : ($mod < 94 ? 'absent' : 'excused');

                            $attendanceRows[] = [
                                'club_id' => $clubId,
                                'training_group_id' => $groupId,
                                'player_id' => $memberId,
                                'date' => $date,
                                'status' => $status,
                                'recorded_by' => $coachId,
                                'created_at' => $date.' 20:00:00',
                                'updated_at' => $date.' 20:00:00',
                            ];

                            if (count($attendanceRows) >= self::CHUNK) {
                                DB::table('training_attendances')->insert($attendanceRows);
                                $attendanceRows = [];
                            }
                        }
                    }

                    $groups[] = [
                        'id' => $groupId,
                        'sport_id' => $sportId,
                        'member_ids' => $memberIds,
                        'dates' => $dates,
                    ];
                }
            }

            $groupsByClub[$clubId] = $groups;
        }

        if ($attendanceRows !== []) {
            DB::table('training_attendances')->insert($attendanceRows);
        }

        $this->insertChunked('training_group_player', $memberRows);
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, list<array{id: int, sport_id: int}>>  $playersByClub
     * @param  array<int, list<array{id: int, sport_id: int, price_cents: int, duration_months: int}>>  $plansByClub
     * @param  array<int, array{owner: int, users: list<int>, coaches: list<int>}>  $staffing
     * @param  array<int, array{0: int, 1: int}>  $refundCandidates
     */
    private function seedSubscriptionsAndPayments(
        array $definitions,
        array $playersByClub,
        array $plansByClub,
        array $staffing,
        Carbon $now,
        Carbon $today,
        int &$subscriptionId,
        int &$paymentId,
        int &$refundId,
        array &$receiptCounters,
        array &$refundCounters,
        array &$refundCandidates,
    ): void {
        $subscriptionRows = [];
        $paymentRows = [];
        $refundRows = [];
        $refunded = 0;

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $isBig = $clubId === 1;
            $receiptCounters[$clubId] = 0;
            $refundCounters[$clubId] = 0;

            $plans = $plansByClub[$clubId];

            if ($plans === []) {
                continue;
            }

            $plansBySport = [];

            foreach ($plans as $plan) {
                $plansBySport[$plan['sport_id']][] = $plan;
            }

            $recorders = $staffing[$clubId]['users'];
            $ownerId = $staffing[$clubId]['owner'];

            foreach ($playersByClub[$clubId] as $playerIndex => $player) {
                $playerPlans = $plansBySport[$player['sport_id']] ?? $plans;
                $subscriptionsForPlayer = ($playerIndex % 5 < 2) ? 1 : 2;

                for ($number = 0; $number < $subscriptionsForPlayer; $number++) {
                    $subscriptionId++;
                    $plan = $playerPlans[($playerIndex + $number) % count($playerPlans)];
                    $mod = $subscriptionId % 100;

                    $duration = $plan['duration_months'];

                    if ($mod < 35) {
                        $startsOn = $today->copy()->subDays(mt_rand(0, 300));
                        $endsOn = $today->copy()->addDays(mt_rand(2, 300));
                        $cancelledAt = null;
                    } elseif ($mod < 55) {
                        $endsOn = $today->copy()->subDays(mt_rand(1, 180));
                        $startsOn = $endsOn->copy()->subMonthsNoOverflow($duration);
                        $cancelledAt = null;
                    } elseif ($mod < 65) {
                        $startsOn = $today->copy()->subDays(mt_rand(0, 200));
                        $endsOn = $today->copy()->addDays(mt_rand(1, 200));
                        $cancelledAt = $today->copy()->subDays(mt_rand(0, 30));
                    } elseif ($mod < 75) {
                        $startsOn = $today->copy()->subDays(mt_rand(10, 300));
                        $endsOn = $today->copy()->addDays(mt_rand(1, 7));
                        $cancelledAt = null;
                    } else {
                        $startsOn = $today->copy()->subDays(mt_rand(0, 300));
                        $endsOn = $today->copy()->addDays(mt_rand(2, 300));
                        $cancelledAt = null;
                    }

                    if ($endsOn->lessThanOrEqualTo($startsOn)) {
                        $endsOn = $startsOn->copy()->addMonthsNoOverflow($duration);
                    }

                    $price = $plan['price_cents'];
                    $total = $price;

                    $subscriptionRows[] = [
                        'id' => $subscriptionId,
                        'club_id' => $clubId,
                        'player_id' => $player['id'],
                        'membership_plan_id' => $plan['id'],
                        'starts_on' => $startsOn->toDateString(),
                        'ends_on' => $endsOn->toDateString(),
                        'price_cents' => $price,
                        'currency' => 'EGP',
                        'duration_months' => $duration,
                        'discount_type' => null,
                        'discount_value' => null,
                        'discount_cents' => 0,
                        'total_cents' => $total,
                        'cancelled_at' => $cancelledAt?->copy()->setTime(12, 0)->toDateTimeString(),
                        'cancelled_by' => $cancelledAt !== null ? $ownerId : null,
                        'renewed_from_id' => null,
                        'created_at' => $startsOn->copy()->setTime(12, 0)->toDateTimeString(),
                        'updated_at' => $now,
                    ];

                    $paymentMode = $mod;

                    if ($paymentMode < 25) {
                        $paymentCount = 0;
                    } elseif ($paymentMode < 40) {
                        $paymentCount = 1;
                    } elseif ($paymentMode < 94) {
                        $paymentCount = mt_rand(2, 3);
                    } else {
                        $paymentCount = 1;
                    }

                    $paidOnFloor = $startsOn->greaterThan($today) ? $today->copy() : $startsOn->copy();

                    for ($paymentIndex = 1; $paymentIndex <= $paymentCount; $paymentIndex++) {
                        $paymentId++;
                        $receiptCounters[$clubId]++;

                        if ($paymentMode >= 25 && $paymentMode < 40) {
                            $amount = (int) max(1, intdiv($total * mt_rand(40, 80), 100));
                        } else {
                            $amount = intdiv($total, $paymentCount);
                        }

                        $voided = mt_rand(1, 100) <= 5;

                        $paidOn = $paidOnFloor->copy()->addDays(mt_rand(0, 120));

                        if ($paidOn->greaterThan($today)) {
                            $paidOn = $today->copy();
                        }

                        $paymentRows[] = [
                            'id' => $paymentId,
                            'club_id' => $clubId,
                            'player_subscription_id' => $subscriptionId,
                            'receipt_number' => $receiptCounters[$clubId],
                            'amount_cents' => $amount,
                            'method' => ['cash', 'card', 'bank_transfer', 'mobile_wallet'][$paymentId % 4],
                            'paid_on' => $paidOn->toDateString(),
                            'reference' => null,
                            'note' => null,
                            'recorded_by' => $recorders[$paymentId % count($recorders)],
                            'voided_at' => $voided ? $paidOn->copy()->addDay()->setTime(12, 0)->toDateTimeString() : null,
                            'voided_by' => $voided ? $ownerId : null,
                            'void_reason' => $voided ? 'Voided for the performance dataset' : null,
                            'created_at' => $paidOn->copy()->setTime(12, 0)->toDateTimeString(),
                            'updated_at' => $now,
                        ];

                        if (count($paymentRows) >= self::CHUNK) {
                            // A payment references its subscription, so the subscription
                            // row must be in the database before the payment batch.
                            if ($subscriptionRows !== []) {
                                DB::table('player_subscriptions')->insert($subscriptionRows);
                                $subscriptionRows = [];
                            }

                            DB::table('payments')->insert($paymentRows);
                            $paymentRows = [];
                        }

                        if ($paymentMode >= 94 && ! $voided && $paymentIndex === 1) {
                            $refundCandidates[$clubId][$subscriptionId] = [$paymentId, $amount];
                        }
                    }
                }

                if (count($subscriptionRows) >= self::CHUNK) {
                    DB::table('player_subscriptions')->insert($subscriptionRows);
                    $subscriptionRows = [];
                }
            }

            // The big club keeps its refunds; small clubs get a proportional share.
            $refundQuota = $isBig ? 500 : max(1, (int) floor($definition['players'] * 0.1));

            // Flush the club's rows in FK order before writing the refunds.
            if ($subscriptionRows !== []) {
                DB::table('player_subscriptions')->insert($subscriptionRows);
                $subscriptionRows = [];
            }

            if ($paymentRows !== []) {
                DB::table('payments')->insert($paymentRows);
                $paymentRows = [];
            }

            foreach ($refundCandidates[$clubId] ?? [] as $candidateSubscriptionId => [$candidatePaymentId, $candidateAmount]) {
                if ($refundCounters[$clubId] >= $refundQuota) {
                    break;
                }

                $refundId++;
                $refundCounters[$clubId]++;

                $refundRows[] = [
                    'id' => $refundId,
                    'club_id' => $clubId,
                    'payment_id' => $candidatePaymentId,
                    'refund_number' => $refundCounters[$clubId],
                    'amount_cents' => (int) max(1, intdiv($candidateAmount * mt_rand(20, 50), 100)),
                    'method' => 'cash',
                    'refunded_on' => $today->toDateString(),
                    'reason' => 'Performance dataset refund',
                    'recorded_by' => $ownerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $refunded++;

                if (count($refundRows) >= self::CHUNK) {
                    DB::table('payment_refunds')->insert($refundRows);
                    $refundRows = [];
                }
            }
        }

        if ($refundRows !== []) {
            DB::table('payment_refunds')->insert($refundRows);
        }
    }

    /**
     * Keeps `document_sequences.last_number` equal to the highest number actually written,
     * so NextDocumentNumberAction continues from the right value.
     *
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, int>  $receiptCounters
     * @param  array<int, int>  $refundCounters
     */
    private function seedDocumentSequences(
        array $definitions,
        Carbon $now,
        array $receiptCounters,
        array $refundCounters,
    ): void {
        $rows = [];

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];

            $rows[] = [
                'club_id' => $clubId,
                'kind' => 'receipt',
                'last_number' => $receiptCounters[$clubId] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = [
                'club_id' => $clubId,
                'kind' => 'refund',
                'last_number' => $refundCounters[$clubId] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('document_sequences', $rows);
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, list<int>>  $usersByClub
     * @param  array<int, array{owner: int, users: list<int>, coaches: list<int>}>  $staffing
     */
    private function seedNotifications(
        array $definitions,
        array $usersByClub,
        array $staffing,
        int $lastSubscriptionId,
        Carbon $today,
        Carbon $now,
    ): void {
        $rows = [];
        $morphType = (new User)->getMorphClass();
        $sequence = 0;

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $recipients = $usersByClub[$clubId];

            for ($index = 1; $index <= $definition['notifications']; $index++) {
                $sequence++;
                $recipientId = $recipients[($index * 7) % count($recipients)];
                $createdAt = $now->copy()->subMinutes(($index * 13) % (60 * 24 * 60));

                $code = ['subscription.expiring', 'subscription.unpaid', 'club.plan_expiring', 'club.welcome'][$index % 4];

                $subscriptionId = max(1, $lastSubscriptionId - ($index % 400));

                $dedupKey = match ($code) {
                    'subscription.expiring' => NotificationSchedule::expiringDedupKey(
                        $subscriptionId,
                        $today->copy()->addDays(($index % 7) + 1)->toDateString(),
                    ),
                    'subscription.unpaid' => NotificationSchedule::unpaidDedupKey($subscriptionId, $today),
                    'club.plan_expiring' => NotificationSchedule::planExpiringDedupKey(
                        $clubId,
                        $today->copy()->addDays(($index % 60) + 1)->toDateString(),
                        [7, 1][$index % 2],
                    ),
                    default => null,
                };

                $rows[] = [
                    'id' => sprintf('%08x-%04x-%04x-%04x-%012x', $sequence, $clubId, $sequence % 65535, $recipientId % 65535, $sequence),
                    'type' => 'App\\Domain\\Notification\\Notifications\\SubscriptionExpiringNotification',
                    'notifiable_type' => $morphType,
                    'notifiable_id' => $recipientId,
                    'data' => json_encode([
                        'code' => $code,
                        'params' => ['days_left' => ($index % 7) + 1, 'subscription_id' => $subscriptionId],
                        'dedup_key' => $dedupKey,
                    ], JSON_UNESCAPED_UNICODE),
                    'read_at' => $index % 5 === 0 ? $createdAt->copy()->addHours(2)->toDateTimeString() : null,
                    'created_at' => $createdAt->toDateTimeString(),
                    'updated_at' => $createdAt->toDateTimeString(),
                ];

                if (count($rows) >= self::CHUNK) {
                    DB::table('notifications')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, array{owner: int, users: list<int>, coaches: list<int>}>  $staffing
     */
    private function seedStaffInvitations(array $definitions, array $staffing, Carbon $now): void
    {
        $rows = [];
        $sequence = 0;

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $invitedBy = $staffing[$clubId]['owner'];

            foreach ([1, 2, 3] as $index) {
                $sequence++;
                $rows[] = [
                    'club_id' => $clubId,
                    'email' => sprintf('invitee%03d.club%02d@perf.ryada.test', $sequence, $clubId),
                    'role' => ['coach', 'staff', 'referee'][$index % 3],
                    'token_hash' => hash('sha256', 'perf-invitation-'.$sequence),
                    'invited_by' => $invitedBy,
                    'expires_at' => $now->copy()->addDays(7)->toDateTimeString(),
                    'accepted_at' => null,
                    'revoked_at' => null,
                    'created_at' => $now->copy()->subHours($index),
                    'updated_at' => $now->copy()->subHours($index),
                ];
            }
        }

        $this->insertChunked('staff_invitations', $rows);
    }

    /**
     * @param  array<int, array{id: int, players: int, sports: list<int>, staff: int, groups: int, notifications: int, activity: int}>  $definitions
     * @param  array<int, list<array{id: int, sport_id: int}>>  $playersByClub
     * @param  array<int, array{owner: int, users: list<int>, coaches: list<int>}>  $staffing
     */
    private function seedActivityLog(
        array $definitions,
        array $playersByClub,
        array $staffing,
        Carbon $now,
    ): void {
        $events = [
            'player.created', 'player.updated', 'player.deleted', 'payment.recorded',
            'payment.voided', 'payment.refunded', 'player_subscription.created',
            'player_subscription.renewed', 'player_subscription.cancelled',
            'training_group.created', 'training_group.attendance_recorded',
            'staff.invited', 'staff.role_changed', 'blog.unknown',
        ];

        $morphType = (new User)->getMorphClass();
        $subjectType = (new Player)->getMorphClass();
        $rows = [];
        $sequence = 0;

        foreach ($definitions as $definition) {
            $clubId = $definition['id'];
            $players = $playersByClub[$clubId];
            $causer = $staffing[$clubId]['owner'];

            for ($index = 1; $index <= $definition['activity']; $index++) {
                $sequence++;
                $event = $events[($index + $clubId) % count($events)];
                $createdAt = $now->copy()->subMinutes(($index * 7) % (60 * 24 * 120));

                $rows[] = [
                    'log_name' => explode('.', $event)[0],
                    'description' => $event,
                    'subject_type' => $subjectType,
                    'subject_id' => $players[$index % count($players)]['id'],
                    'event' => $event,
                    'causer_type' => $morphType,
                    'causer_id' => $causer,
                    'club_id' => $clubId,
                    'attribute_changes' => null,
                    'properties' => json_encode(['sequence' => $sequence], JSON_UNESCAPED_UNICODE),
                    'created_at' => $createdAt->toDateTimeString(),
                    'updated_at' => $createdAt->toDateTimeString(),
                ];

                if (count($rows) >= self::CHUNK) {
                    DB::table('activity_log')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('activity_log')->insert($rows);
        }
    }

    private function printSummary(int $bigClubId, float $elapsed): void
    {
        if ($this->command === null) {
            return;
        }

        $tables = [
            'users', 'players', 'player_sport', 'membership_plans', 'training_groups',
            'training_group_schedules', 'training_group_player', 'training_attendances',
            'player_subscriptions', 'payments', 'payment_refunds', 'staff_invitations',
            'activity_log',
        ];

        $this->command->info(sprintf('PerformanceSeeder finished in %.1f s (seed %d).', $elapsed, self::RANDOM_SEED));
        $this->command->info(sprintf('Big club (id %d) row counts:', $bigClubId));

        foreach ($tables as $table) {
            $count = DB::table($table)->where('club_id', $bigClubId)->count();
            $this->command->line(sprintf('  %-28s %8d', $table, $count));
        }

        // `notifications` has no club_id: it belongs to users through the morph columns.
        $bigNotifications = DB::table('notifications')
            ->join('users', 'users.id', '=', 'notifications.notifiable_id')
            ->where('notifications.notifiable_type', (new User)->getMorphClass())
            ->where('users.club_id', $bigClubId)
            ->count();

        $this->command->line(sprintf('  %-28s %8d', 'notifications', $bigNotifications));
        $this->command->line(sprintf('  %-28s %8d', 'clubs', DB::table('clubs')->count()));
        $this->command->line(sprintf('  %-28s %8d', 'notifications (all)', DB::table('notifications')->count()));
        $this->command->line(sprintf('  %-28s %8d', 'activity_log (all)', DB::table('activity_log')->count()));
    }

    private function personName(): string
    {
        $first = $this->firstNames[mt_rand(0, count($this->firstNames) - 1)];
        $last = $this->lastNames[mt_rand(0, count($this->lastNames) - 1)];

        return $first.' '.$last;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
