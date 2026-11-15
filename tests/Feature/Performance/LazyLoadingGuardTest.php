<?php

use App\Domain\Club\Models\Club;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * Two subscriptions of one club: the guard only fires for models hydrated as part
 * of a result set with more than one row, so a collection is required here.
 */
function lzySubscriptions(Club $club): void
{
    $plan = MembershipPlan::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    PlayerSubscription::factory()->count(2)->create([
        'club_id' => $club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
    ]);
}

it('throws when a relation is lazily loaded on a collection outside production', function () {
    $club = Club::factory()->create();
    lzySubscriptions($club);

    $lazyLoad = fn () => app(CurrentClub::class)->runAs(
        $club,
        fn () => PlayerSubscription::query()->get()->first()->player,
    );

    expect($lazyLoad)->toThrow(LazyLoadingViolationException::class);
});

it('logs a warning without model attributes instead of throwing in production', function () {
    $club = Club::factory()->create();
    lzySubscriptions($club);

    // The handler reads the environment when the violation happens, so simulating
    // production here is enough to exercise the production branch of our handler.
    app()->detectEnvironment(fn (): string => 'production');

    Log::spy();

    $player = app(CurrentClub::class)->runAs(
        $club,
        fn () => PlayerSubscription::query()->get()->first()->player,
    );

    expect($player)->toBeInstanceOf(Player::class);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Lazy loading violation.'
            // The exact context is the model class and the relation only: no attributes,
            // no ids and no personal data ever reach the log.
            && $context === [
                'model' => PlayerSubscription::class,
                'relation' => 'player',
            ]);
});
