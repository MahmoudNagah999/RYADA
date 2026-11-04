<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Club\Models\Club;
use App\Domain\Player\Actions\CreatePlayerAction;
use App\Domain\Player\DTOs\CreatePlayerData;
use App\Domain\Player\Enums\Gender;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Subscription\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPlayerIn(Club $club, CreatePlayerData $data): Player
{
    return app(CurrentClub::class)->runAs(
        $club,
        fn () => app(CreatePlayerAction::class)->execute($club, $data),
    );
}

function playerData(
    ?string $nationalId = null,
    ?CarbonImmutable $birthDate = null,
    ?Gender $gender = null,
): CreatePlayerData {
    return new CreatePlayerData('Ahmed Ali', $nationalId, $birthDate, $gender, '01012345678');
}

it('derives birth date and gender from the national id and logs the event', function () {
    $club = Club::factory()->create();

    $player = createPlayerIn($club, playerData(nationalId: '31005150101234'));

    expect($player->club_id)->toBe($club->id)
        ->and($player->birth_date->toDateString())->toBe('2010-05-15')
        ->and($player->gender)->toBe(Gender::Male);

    $activity = Activity::query()->where('event', 'player.created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->club_id)->toBe($club->id)
        ->and($activity->subject_id)->toBe($player->id)
        ->and(json_encode($activity->properties))->not->toContain('31005150101234');
});

it('uses the given birth data when there is no national id', function () {
    $club = Club::factory()->create();

    $player = createPlayerIn($club, playerData(
        birthDate: CarbonImmutable::create(2012, 3, 1),
        gender: Gender::Female,
    ));

    expect($player->national_id)->toBeNull()
        ->and($player->birth_date->toDateString())->toBe('2012-03-01')
        ->and($player->gender)->toBe(Gender::Female);
});

it('requires birth data when there is no national id', function () {
    createPlayerIn(Club::factory()->create(), playerData());
})->throws(BusinessRuleException::class);

it('rejects birth data that conflicts with the national id', function () {
    createPlayerIn(Club::factory()->create(), playerData(
        nationalId: '31005150101234',
        birthDate: CarbonImmutable::create(2011, 1, 1),
    ));
})->throws(BusinessRuleException::class);

it('rejects an invalid national id', function () {
    createPlayerIn(Club::factory()->create(), playerData(nationalId: '123'));
})->throws(BusinessRuleException::class);

it('rejects a duplicate national id inside the same club only', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();

    createPlayerIn($clubA, playerData(nationalId: '31005150101234'));

    expect(fn () => createPlayerIn($clubA, playerData(nationalId: '31005150101234')))
        ->toThrow(BusinessRuleException::class);

    expect(createPlayerIn($clubB, playerData(nationalId: '31005150101234'))->club_id)
        ->toBe($clubB->id);
});

it('blocks creating a player beyond the plan limit', function () {
    $plan = Plan::factory()->create(['max_players' => 1]);
    $club = Club::factory()->create();
    $club->forceFill(['plan_id' => $plan->id])->save();

    createPlayerIn($club, playerData(nationalId: '31005150101234'));

    expect(fn () => createPlayerIn($club, playerData(nationalId: '31005150101245')))
        ->toThrow(BusinessRuleException::class);

    expect(Player::query()->withoutGlobalScopes()->where('club_id', $club->id)->count())->toBe(1);
});
