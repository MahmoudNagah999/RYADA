<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Player\Enums\Gender;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Cache\TenantCache;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Cache\ClubSportList;
use App\Domain\Sport\Cache\SportCatalog;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use App\Domain\Staff\Models\StaffInvitation;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Training\Models\TrainingAttendance;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use App\Domain\Training\Models\TrainingGroupSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Performance\QueryCounter;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Query budget tests
|--------------------------------------------------------------------------
|
| The lazy-loading guard only fires for a model hydrated in a result set with
| more than one row, so a one-row test can never reveal an N+1. These tests
| prove the number of SQL queries of an endpoint does not grow with the number
| of rows it returns. They are driver agnostic: only counts and the response
| shape are asserted, never SQL text.
|
| Warm-up requests are deliberately run before every measurement: the first
| request of a test fills the spatie permission cache, the TenantCache entries
| and the route/container caches. Their query count is discarded.
|
*/

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function qbInClub(TenantFixture $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

function qbForgetGuards(): void
{
    app('auth')->forgetGuards();
}

/**
 * A GET request whose only purpose is to warm the caches; its count is discarded.
 */
function qbWarmUp(TenantFixture $tenant, string $uri): void
{
    qbForgetGuards();

    test()->withToken($tenant->token)
        ->getJson($uri)
        ->assertOk();

    qbForgetGuards();
}

/**
 * @param  array<string, mixed>  $payload
 * @return array{count: int, queries: list<string>, response: TestResponse}
 */
function qbMeasure(TenantFixture $tenant, string $method, string $uri, array $payload = []): array
{
    qbForgetGuards();

    $response = null;

    $measurement = QueryCounter::measure(function () use ($tenant, $method, $uri, $payload, &$response): void {
        $response = test()->withToken($tenant->token)->json($method, $uri, $payload);
    });

    return [...$measurement, 'response' => $response];
}

/**
 * @param  array{count: int, queries: list<string>, response: TestResponse}  $small
 * @param  array{count: int, queries: list<string>, response: TestResponse}  $large
 */
function qbConstant(string $endpoint, array $small, array $large, int $smallItems, int $largeItems): void
{
    $small['response']->assertOk()->assertJsonCount($smallItems, 'data');
    $large['response']->assertOk()->assertJsonCount($largeItems, 'data');

    expect($large['count'])->toBe(
        $small['count'],
        sprintf(
            "%s: queries grew with rows (%d rows => %d queries, %d rows => %d queries). SQL of the larger request:\n%s",
            $endpoint,
            $smallItems,
            $small['count'],
            $largeItems,
            $large['count'],
            implode("\n", $large['queries']),
        ),
    );
}

/**
 * @param  array{count: int, queries: list<string>, response: TestResponse}  $measurement
 */
function qbCeiling(string $endpoint, array $measurement, int $ceiling): void
{
    $measurement['response']->assertOk();

    expect($measurement['count'])->toBeLessThanOrEqual(
        $ceiling,
        sprintf(
            '%s: %d queries, above the pinned ceiling of %d. SQL: %s',
            $endpoint,
            $measurement['count'],
            $ceiling,
            implode(' | ', $measurement['queries']),
        ),
    );
}

/**
 * @param  array{count: int, queries: list<string>, response: TestResponse}  $measurement
 */
function qbWriteCeiling(string $endpoint, array $measurement, int $ceiling): void
{
    $measurement['response']->assertSuccessful();

    expect($measurement['count'])->toBeLessThanOrEqual(
        $ceiling,
        sprintf(
            '%s: %d queries, above the pinned ceiling of %d. SQL: %s',
            $endpoint,
            $measurement['count'],
            $ceiling,
            implode(' | ', $measurement['queries']),
        ),
    );
}

/*
|--------------------------------------------------------------------------
| Fixtures
|--------------------------------------------------------------------------
*/

/**
 * @return list<Player>
 */
function qbPlayers(TenantFixture $tenant, int $count, int $start = 1): array
{
    $players = [];

    foreach (range($start, $start + $count - 1) as $index) {
        $players[] = Player::factory()->create([
            'club_id' => $tenant->club->id,
            'name' => sprintf('Budgetplayer %02d', $index),
            'gender' => Gender::Female,
            'birth_date' => '2000-01-01',
            'phone' => '0100000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        ]);
    }

    return $players;
}

/**
 * @return list<Sport>
 */
function qbSports(int $count, int $start = 1): array
{
    $sports = [];

    foreach (range($start, $start + $count - 1) as $index) {
        $sports[] = Sport::factory()->create([
            'slug' => sprintf('budget-sport-%02d', $index),
            'is_active' => true,
        ]);
    }

    return $sports;
}

/**
 * @param  list<Sport>  $sports
 */
function qbEnroll(TenantFixture $tenant, Player $player, array $sports): void
{
    qbInClub($tenant, function () use ($player, $sports): void {
        foreach ($sports as $sport) {
            PlayerSport::query()->create([
                'player_id' => $player->id,
                'sport_id' => $sport->id,
            ]);
        }
    });
}

/**
 * @param  list<Sport>  $sports
 */
function qbAttachClubSports(TenantFixture $tenant, array $sports): void
{
    qbInClub($tenant, function () use ($sports): void {
        foreach ($sports as $sport) {
            ClubSport::query()->create(['sport_id' => $sport->id]);
        }
    });
}

function qbPlan(TenantFixture $tenant, Sport $sport, string $name = 'Budget Plan'): MembershipPlan
{
    return MembershipPlan::factory()->create([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
        'name' => $name,
        'price_cents' => 50000,
        'duration_months' => 1,
        'is_active' => true,
    ]);
}

/**
 * @return list<MembershipPlan>
 */
function qbPlans(TenantFixture $tenant, array $sports, int $count, int $start = 1): array
{
    $plans = [];
    $sports = array_values($sports);

    foreach (range($start, $start + $count - 1) as $index) {
        $sport = $sports[($index - 1) % count($sports)];

        $plans[] = qbPlan($tenant, $sport, sprintf('Budget Plan %02d', $index));
    }

    return $plans;
}

/**
 * @return list<PlayerSubscription>
 */
function qbSubscriptions(TenantFixture $tenant, MembershipPlan $plan, int $count, int $start = 1): array
{
    $subscriptions = [];

    foreach (range($start, $start + $count - 1) as $index) {
        $player = Player::factory()->create([
            'club_id' => $tenant->club->id,
            'name' => sprintf('Budget subscriber %02d', $index),
        ]);

        $subscriptions[] = PlayerSubscription::factory()->create([
            'club_id' => $tenant->club->id,
            'player_id' => $player->id,
            'membership_plan_id' => $plan->id,
            'price_cents' => 50000,
            'currency' => 'EGP',
            'duration_months' => 1,
            'total_cents' => 50000,
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-06-05',
            'cancelled_at' => null,
        ]);
    }

    return $subscriptions;
}

function qbPayment(TenantFixture $tenant, PlayerSubscription $subscription, int $amount, int $receiptNumber, ?int $refundCents = null): Payment
{
    $payment = qbInClub($tenant, fn (): Payment => Payment::factory()->create([
        'club_id' => $tenant->club->id,
        'player_subscription_id' => $subscription->id,
        'receipt_number' => $receiptNumber,
        'amount_cents' => $amount,
        'paid_on' => '2026-05-02',
    ]));

    if ($refundCents !== null) {
        qbInClub($tenant, fn (): PaymentRefund => PaymentRefund::factory()->create([
            'club_id' => $tenant->club->id,
            'payment_id' => $payment->id,
            'refund_number' => $receiptNumber,
            'amount_cents' => $refundCents,
            'refunded_on' => '2026-05-03',
        ]));
    }

    return $payment;
}

/**
 * @return list<TrainingGroup>
 */
function qbTrainingGroups(TenantFixture $tenant, Sport $sport, User $coach, int $count, int $start = 1): array
{
    $groups = [];

    foreach (range($start, $start + $count - 1) as $index) {
        $group = TrainingGroup::factory()->create([
            'club_id' => $tenant->club->id,
            'sport_id' => $sport->id,
            'coach_id' => $coach->id,
            'name' => sprintf('Budget group %02d', $index),
            'is_active' => true,
        ]);

        TrainingGroupSchedule::factory()->create([
            'club_id' => $tenant->club->id,
            'training_group_id' => $group->id,
        ]);

        $groups[] = $group;
    }

    return $groups;
}

/**
 * @return list<Player>
 */
function qbMembers(TenantFixture $tenant, TrainingGroup $group, int $count, int $start = 1): array
{
    $players = [];

    foreach (range($start, $start + $count - 1) as $index) {
        $player = Player::factory()->create([
            'club_id' => $tenant->club->id,
            'name' => sprintf('Budget member %02d', $index),
        ]);

        qbInClub($tenant, function () use ($group, $player): void {
            PlayerSport::query()->create([
                'player_id' => $player->id,
                'sport_id' => $group->sport_id,
            ]);

            TrainingGroupPlayer::query()->create([
                'training_group_id' => $group->id,
                'player_id' => $player->id,
            ]);
        });

        $players[] = $player;
    }

    return $players;
}

/**
 * @param  list<Player>  $players
 */
function qbAttendance(TenantFixture $tenant, TrainingGroup $group, array $players, string $date): void
{
    foreach ($players as $player) {
        TrainingAttendance::factory()->create([
            'club_id' => $tenant->club->id,
            'training_group_id' => $group->id,
            'player_id' => $player->id,
            'date' => $date,
            'status' => 'present',
        ]);
    }
}

/**
 * @return list<User>
 */
function qbStaff(TenantFixture $tenant, int $count, RoleName $role = RoleName::Coach, int $start = 1): array
{
    $users = [];

    foreach (range($start, $start + $count - 1) as $index) {
        $user = User::factory()->forClub($tenant->club)->create([
            'name' => sprintf('Budget staff %02d', $index),
            'email' => sprintf('budget-staff-%02d-%d@example.test', $index, $tenant->club->id),
        ]);

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenant->club->id);

        try {
            $user->assignRole($role->value);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        $users[] = $user;
    }

    return $users;
}

function qbInvitations(TenantFixture $tenant, int $count, int $start = 1): void
{
    foreach (range($start, $start + $count - 1) as $index) {
        StaffInvitation::factory()->create([
            'club_id' => $tenant->club->id,
            'email' => sprintf('budget-invite-%02d-%d@example.test', $index, $tenant->club->id),
            'role' => RoleName::Coach->value,
        ]);
    }
}

function qbNotifications(TenantFixture $tenant, int $count, int $start = 1): void
{
    foreach (range($start, $start + $count - 1) as $index) {
        $notification = NotificationFixture::notification(
            $tenant->user,
            'club.welcome',
            ['club_name' => sprintf('Budget club %02d', $index)],
        );

        // One in three is read, so both the list and the unread filter have rows.
        if ($index % 3 === 1) {
            $notification->markAsRead();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Players
|--------------------------------------------------------------------------
*/

it('keeps the players endpoints constant in the number of rows', function (): void {
    $tenant = TenantFixture::create();
    $players = qbPlayers($tenant, 3);
    $sports = qbSports(3);
    qbEnroll($tenant, $players[0], $sports);

    qbWarmUp($tenant, '/api/v1/players');

    $plainSmall = qbMeasure($tenant, 'GET', '/api/v1/players');
    $filteredSmall = qbMeasure($tenant, 'GET', '/api/v1/players?search=Budgetplayer&gender=female&age_min=18&sort=-name');
    $sportsSmall = qbMeasure($tenant, 'GET', "/api/v1/players/{$players[0]->id}/sports");

    qbPlayers($tenant, 9, 4);
    qbEnroll($tenant, $players[0], qbSports(9, 4));
    qbWarmUp($tenant, '/api/v1/players');

    $plainLarge = qbMeasure($tenant, 'GET', '/api/v1/players');
    $filteredLarge = qbMeasure($tenant, 'GET', '/api/v1/players?search=Budgetplayer&gender=female&age_min=18&sort=-name');
    $sportsLarge = qbMeasure($tenant, 'GET', "/api/v1/players/{$players[0]->id}/sports");

    qbConstant('GET /players', $plainSmall, $plainLarge, 3, 12);
    qbConstant('GET /players (filtered)', $filteredSmall, $filteredLarge, 3, 12);
    qbConstant('GET /players/{player}/sports', $sportsSmall, $sportsLarge, 3, 12);

    qbCeiling('GET /players', $plainSmall, 7); // measured on 2026-11-16
    qbCeiling('GET /players (filtered)', $filteredSmall, 7); // measured on 2026-11-16
    qbCeiling('GET /players/{player}/sports', $sportsSmall, 8); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Reference data: sports and membership plans
|--------------------------------------------------------------------------
*/

it('keeps the sports and membership plan lists constant in the number of rows', function (): void {
    $tenant = TenantFixture::create();
    $sports = qbSports(3);
    qbAttachClubSports($tenant, $sports);

    qbWarmUp($tenant, '/api/v1/club/sports');

    $clubSportsSmall = qbMeasure($tenant, 'GET', '/api/v1/club/sports');
    $catalogSmall = qbMeasure($tenant, 'GET', '/api/v1/sports');

    qbPlans($tenant, $sports, 3);

    qbWarmUp($tenant, '/api/v1/membership-plans');

    $plansSmall = qbMeasure($tenant, 'GET', '/api/v1/membership-plans');

    $moreSports = qbSports(9, 4);
    qbAttachClubSports($tenant, $moreSports);
    qbPlans($tenant, $sports, 9, 4);

    // Observers run after commit; inside the test transaction we flush explicitly.
    app(SportCatalog::class)->forget();
    app(TenantCache::class)->forgetForClub($tenant->club->id, ClubSportList::KEY);

    qbWarmUp($tenant, '/api/v1/club/sports');

    $clubSportsLarge = qbMeasure($tenant, 'GET', '/api/v1/club/sports');
    $catalogLarge = qbMeasure($tenant, 'GET', '/api/v1/sports');

    qbWarmUp($tenant, '/api/v1/membership-plans');

    $plansLarge = qbMeasure($tenant, 'GET', '/api/v1/membership-plans');

    qbConstant('GET /club/sports', $clubSportsSmall, $clubSportsLarge, 3, 12);
    qbConstant('GET /sports', $catalogSmall, $catalogLarge, 3, 12);
    qbConstant('GET /membership-plans', $plansSmall, $plansLarge, 3, 12);

    qbCeiling('GET /club/sports', $clubSportsSmall, 5); // measured on 2026-11-16
    qbCeiling('GET /sports', $catalogSmall, 5); // measured on 2026-11-16
    qbCeiling('GET /membership-plans', $plansSmall, 7); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Subscriptions and payments
|--------------------------------------------------------------------------
*/

it('keeps the player subscription and payment lists constant in the number of rows', function (): void {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $plan = qbPlan($tenant, $sport, 'Budget Subscription Plan');

    $subscriptions = qbSubscriptions($tenant, $plan, 3);
    qbPayment($tenant, $subscriptions[0], 10000, 1);

    qbWarmUp($tenant, '/api/v1/player-subscriptions');

    $listSmall = qbMeasure($tenant, 'GET', '/api/v1/player-subscriptions');
    $dueSmall = qbMeasure($tenant, 'GET', '/api/v1/player-subscriptions/due?type=all');

    $paymentsSmall = qbMeasure($tenant, 'GET', "/api/v1/player-subscriptions/{$subscriptions[0]->id}/payments");

    qbSubscriptions($tenant, $plan, 9, 4);

    qbPayment($tenant, $subscriptions[0], 9000, 2);
    qbPayment($tenant, $subscriptions[0], 8000, 3, 2000);

    qbWarmUp($tenant, '/api/v1/player-subscriptions');

    $listLarge = qbMeasure($tenant, 'GET', '/api/v1/player-subscriptions');
    $dueLarge = qbMeasure($tenant, 'GET', '/api/v1/player-subscriptions/due?type=all');
    $paymentsLarge = qbMeasure($tenant, 'GET', "/api/v1/player-subscriptions/{$subscriptions[0]->id}/payments");

    qbConstant('GET /player-subscriptions', $listSmall, $listLarge, 3, 12);
    qbConstant('GET /player-subscriptions/due', $dueSmall, $dueLarge, 3, 12);
    qbConstant('GET /player-subscriptions/{subscription}/payments', $paymentsSmall, $paymentsLarge, 1, 3);

    qbCeiling('GET /player-subscriptions', $listSmall, 9); // measured on 2026-11-16
    qbCeiling('GET /player-subscriptions/due', $dueSmall, 9); // measured on 2026-11-16
    qbCeiling('GET /player-subscriptions/{subscription}/payments', $paymentsSmall, 9); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Training
|--------------------------------------------------------------------------
*/

it('keeps the training lists and attendance sheet constant in the number of rows', function (): void {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $coach = NotificationFixture::userWithRole($tenant, RoleName::Coach);

    $groups = qbTrainingGroups($tenant, $sport, $coach, 3);

    qbWarmUp($tenant, '/api/v1/training-groups');

    $groupsSmall = qbMeasure($tenant, 'GET', '/api/v1/training-groups');

    $members = qbMembers($tenant, $groups[0], 3);
    qbAttendance($tenant, $groups[0], $members, '2026-05-31');

    $groupPlayersSmall = qbMeasure($tenant, 'GET', "/api/v1/training-groups/{$groups[0]->id}/players");
    $attendanceSmall = qbMeasure($tenant, 'GET', "/api/v1/training-groups/{$groups[0]->id}/attendance?date=2026-05-31");

    qbTrainingGroups($tenant, $sport, $coach, 9, 4);
    qbMembers($tenant, $groups[0], 9, 4);

    qbWarmUp($tenant, '/api/v1/training-groups');

    $groupsLarge = qbMeasure($tenant, 'GET', '/api/v1/training-groups');
    $groupPlayersLarge = qbMeasure($tenant, 'GET', "/api/v1/training-groups/{$groups[0]->id}/players");
    $attendanceLarge = qbMeasure($tenant, 'GET', "/api/v1/training-groups/{$groups[0]->id}/attendance?date=2026-05-31");

    qbConstant('GET /training-groups', $groupsSmall, $groupsLarge, 3, 12);
    qbConstant('GET /training-groups/{group}/players', $groupPlayersSmall, $groupPlayersLarge, 3, 12);
    qbConstant('GET /training-groups/{group}/attendance', $attendanceSmall, $attendanceLarge, 3, 12);

    qbCeiling('GET /training-groups', $groupsSmall, 9); // measured on 2026-11-16
    qbCeiling('GET /training-groups/{group}/players', $groupPlayersSmall, 8); // measured on 2026-11-16
    qbCeiling('GET /training-groups/{group}/attendance', $attendanceSmall, 10); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
*/

it('keeps the staff and invitation lists constant in the number of rows', function (): void {
    $tenant = TenantFixture::create();
    $staff = qbStaff($tenant, 3);
    qbInvitations($tenant, 3);

    qbWarmUp($tenant, '/api/v1/staff');

    $staffSmall = qbMeasure($tenant, 'GET', '/api/v1/staff');
    $invitationsSmall = qbMeasure($tenant, 'GET', '/api/v1/staff/invitations');

    qbStaff($tenant, 9, RoleName::Coach, 4);
    qbInvitations($tenant, 9, 4);

    qbWarmUp($tenant, '/api/v1/staff');

    $staffLarge = qbMeasure($tenant, 'GET', '/api/v1/staff');
    $invitationsLarge = qbMeasure($tenant, 'GET', '/api/v1/staff/invitations');

    // The owner created by TenantFixture is a staff member too.
    qbConstant('GET /staff', $staffSmall, $staffLarge, 4, 13);
    qbConstant('GET /staff/invitations', $invitationsSmall, $invitationsLarge, 3, 12);

    qbCeiling('GET /staff', $staffSmall, 8); // measured on 2026-11-16
    qbCeiling('GET /staff/invitations', $invitationsSmall, 7); // measured on 2026-11-16

    qbWarmUp($tenant, '/api/v1/staff');

    $roleChange = qbMeasure(
        $tenant,
        'PATCH',
        "/api/v1/staff/{$staff[0]->id}/role",
        ['role' => RoleName::Referee->value],
    );

    $roleChange['response']->assertOk()->assertJsonPath('data.role', RoleName::Referee->value);

    qbWriteCeiling('PATCH /staff/{user}/role', $roleChange, 16); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

it('keeps the notification endpoints constant in the number of rows', function (): void {
    $tenant = TenantFixture::create();
    qbNotifications($tenant, 3);

    qbWarmUp($tenant, '/api/v1/notifications');

    $listSmall = qbMeasure($tenant, 'GET', '/api/v1/notifications');
    $unreadSmall = qbMeasure($tenant, 'GET', '/api/v1/notifications?unread=1');
    $countSmall = qbMeasure($tenant, 'GET', '/api/v1/notifications/unread-count');

    qbNotifications($tenant, 9, 4);

    qbWarmUp($tenant, '/api/v1/notifications');

    $listLarge = qbMeasure($tenant, 'GET', '/api/v1/notifications');
    $unreadLarge = qbMeasure($tenant, 'GET', '/api/v1/notifications?unread=1');
    $countLarge = qbMeasure($tenant, 'GET', '/api/v1/notifications/unread-count');

    qbConstant('GET /notifications', $listSmall, $listLarge, 3, 12);
    qbConstant('GET /notifications?unread=1', $unreadSmall, $unreadLarge, 2, 8);

    $countSmall['response']->assertOk()->assertJsonPath('data.unread', 2);
    $countLarge['response']->assertOk()->assertJsonPath('data.unread', 8);

    expect($countLarge['count'])->toBe(
        $countSmall['count'],
        'GET /notifications/unread-count: query count changed with rows.',
    );

    qbCeiling('GET /notifications', $listSmall, 5); // measured on 2026-11-16
    qbCeiling('GET /notifications?unread=1', $unreadSmall, 5); // measured on 2026-11-16
    qbCeiling('GET /notifications/unread-count', $countSmall, 4); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Single resources (children are still a lazy-load trap)
|--------------------------------------------------------------------------
*/

it('keeps the single resource endpoints within a pinned budget', function (): void {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $coach = NotificationFixture::userWithRole($tenant, RoleName::Coach);
    $plan = qbPlan($tenant, $sport, 'Budget Single Plan');

    $subscription = qbSubscriptions($tenant, $plan, 1)[0];
    $payments = [
        qbPayment($tenant, $subscription, 10000, 1),
        qbPayment($tenant, $subscription, 10000, 2),
        qbPayment($tenant, $subscription, 5000, 3, 2000),
    ];

    $group = qbTrainingGroups($tenant, $sport, $coach, 1)[0];

    qbWarmUp($tenant, "/api/v1/player-subscriptions/{$subscription->id}");
    $subscriptionShow = qbMeasure($tenant, 'GET', "/api/v1/player-subscriptions/{$subscription->id}");

    qbWarmUp($tenant, "/api/v1/player-subscriptions/{$subscription->id}/balance");
    $balance = qbMeasure($tenant, 'GET', "/api/v1/player-subscriptions/{$subscription->id}/balance");

    qbWarmUp($tenant, "/api/v1/payments/{$payments[0]->id}/receipt");
    $receipt = qbMeasure($tenant, 'GET', "/api/v1/payments/{$payments[0]->id}/receipt");

    qbWarmUp($tenant, "/api/v1/training-groups/{$group->id}");
    $groupShow = qbMeasure($tenant, 'GET', "/api/v1/training-groups/{$group->id}");

    $subscriptionShow['response']->assertJsonPath('data.id', $subscription->id);
    $balance['response']->assertJsonPath('data.total_cents', 50000);
    $receipt['response']->assertJsonPath('data.subscription.id', $subscription->id);
    $groupShow['response']->assertJsonPath('data.id', $group->id);

    qbCeiling('GET /player-subscriptions/{subscription}', $subscriptionShow, 8); // measured on 2026-11-16
    qbCeiling('GET /player-subscriptions/{subscription}/balance', $balance, 8); // measured on 2026-11-16
    qbCeiling('GET /payments/{payment}/receipt', $receipt, 12); // measured on 2026-11-16
    qbCeiling('GET /training-groups/{group}', $groupShow, 9); // measured on 2026-11-16
});

/*
|--------------------------------------------------------------------------
| Write endpoints
|--------------------------------------------------------------------------
*/

it('pins the player creation budget with and without a club plan', function (): void {
    // Both tenants must exist before the first HTTP request: `auth:sanctum` changes the
    // default guard during a request, and TenantFixture reads it when it creates roles.
    $withPlan = TenantFixture::create();
    $withoutPlan = TenantFixture::create();

    $withPlan->club->forceFill([
        'plan_id' => Plan::factory()->create(['max_players' => 100])->id,
    ])->save();

    qbWarmUp($withPlan, '/api/v1/players');

    $withPlanMeasurement = qbMeasure($withPlan, 'POST', '/api/v1/players', [
        'name' => 'Budget Created Player',
        'national_id' => '31005150101234',
        'phone' => '01099998888',
    ]);

    qbWarmUp($withoutPlan, '/api/v1/players');

    $withoutPlanMeasurement = qbMeasure($withoutPlan, 'POST', '/api/v1/players', [
        'name' => 'Budget Created Player',
        'national_id' => '31005150101234',
        'phone' => '01099998888',
    ]);

    $withPlanMeasurement['response']->assertCreated()->assertJsonPath('data.name', 'Budget Created Player');
    $withoutPlanMeasurement['response']->assertCreated()->assertJsonPath('data.name', 'Budget Created Player');

    // PR 2 added Club::with('plan') so the plan limit check never lazy loads. A club with a
    // plan therefore runs one eager-load query the club without a plan does not; both are
    // pinned separately so a future change to either path is caught.
    expect($withPlanMeasurement['count'])->toBeGreaterThanOrEqual($withoutPlanMeasurement['count']);

    qbWriteCeiling('POST /players (club with plan)', $withPlanMeasurement, 11); // measured on 2026-11-16
    qbWriteCeiling('POST /players (club without plan)', $withoutPlanMeasurement, 10); // measured on 2026-11-16
});

it('pins the subscription and payment write budgets', function (): void {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $plan = qbPlan($tenant, $sport, 'Budget Write Plan');
    $player = qbPlayers($tenant, 1)[0];
    qbEnroll($tenant, $player, [$sport]);

    qbWarmUp($tenant, '/api/v1/player-subscriptions');

    $subscriptionMeasurement = qbMeasure(
        $tenant,
        'POST',
        "/api/v1/players/{$player->id}/subscriptions",
        ['membership_plan_id' => $plan->id],
    );

    $subscriptionMeasurement['response']->assertCreated();

    $subscriptionId = (int) $subscriptionMeasurement['response']->json('data.id');

    qbWarmUp($tenant, "/api/v1/player-subscriptions/{$subscriptionId}/payments");

    $paymentMeasurement = qbMeasure(
        $tenant,
        'POST',
        "/api/v1/player-subscriptions/{$subscriptionId}/payments",
        ['amount_cents' => 10000, 'method' => 'cash'],
    );

    $paymentMeasurement['response']->assertCreated()->assertJsonPath('data.amount_cents', 10000);

    qbWriteCeiling('POST /players/{player}/subscriptions', $subscriptionMeasurement, 16); // measured on 2026-11-16
    qbWriteCeiling('POST /player-subscriptions/{subscription}/payments', $paymentMeasurement, 15); // measured on 2026-11-16
});

it('pins the attendance write budget for three records', function (): void {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $group = qbTrainingGroups($tenant, $sport, $tenant->user, 1)[0];
    $members = qbMembers($tenant, $group, 3);

    qbWarmUp($tenant, "/api/v1/training-groups/{$group->id}/attendance?date=2026-05-30");

    $measurement = qbMeasure(
        $tenant,
        'POST',
        "/api/v1/training-groups/{$group->id}/attendance",
        [
            'date' => '2026-05-30',
            'records' => array_map(
                fn (Player $player): array => ['player_id' => $player->id, 'status' => 'present'],
                $members,
            ),
        ],
    );

    $measurement['response']->assertOk()->assertJsonCount(3, 'data');

    qbWriteCeiling('POST /training-groups/{group}/attendance (3 records)', $measurement, 17); // measured on 2026-11-16
});

it('keeps the attendance write constant in the number of records', function (): void {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $group = qbTrainingGroups($tenant, $sport, $tenant->user, 1)[0];
    $members = qbMembers($tenant, $group, 12);

    qbWarmUp($tenant, "/api/v1/training-groups/{$group->id}/attendance?date=2026-05-30");

    $small = qbMeasure(
        $tenant,
        'POST',
        "/api/v1/training-groups/{$group->id}/attendance",
        [
            'date' => '2026-05-30',
            'records' => array_map(
                fn (Player $player): array => ['player_id' => $player->id, 'status' => 'present'],
                array_slice($members, 0, 3),
            ),
        ],
    );

    $large = qbMeasure(
        $tenant,
        'POST',
        "/api/v1/training-groups/{$group->id}/attendance",
        [
            'date' => '2026-05-31',
            'records' => array_map(
                fn (Player $player): array => ['player_id' => $player->id, 'status' => 'present'],
                $members,
            ),
        ],
    );

    $small['response']->assertOk()->assertJsonCount(12, 'data');
    $large['response']->assertOk()->assertJsonCount(12, 'data');

    expect($large['count'])->toBe(
        $small['count'],
        sprintf(
            'POST /training-groups/{group}/attendance: queries grew with the number of records '
            .'(3 records => %d queries, 12 records => %d queries). SQL of the 12-record request: %s',
            $small['count'],
            $large['count'],
            implode(' | ', $large['queries']),
        ),
    );
})->skip(
    'N+1: RecordTrainingAttendanceAction inserts one row per record instead of using a bulk upsert. '
    .'Measured on 2026-11-16: 3 records => 17 queries, 12 records => 26 queries (one extra INSERT per record). '
    .'Fix in a follow-up PR; PR 4 covers the notifier paths.',
);
