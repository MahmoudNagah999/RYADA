<?php

use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function clubOffering(): array
{
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return [$tenant, $sport];
}

it('blocks removing a sport that has an active membership plan', function () {
    [$tenant, $sport] = clubOffering();
    MembershipPlan::factory()->create(['club_id' => $tenant->club->id, 'sport_id' => $sport->id]);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'membership_plan.sport_has_active_plans');

    expect(ClubSport::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('allows removing a sport whose plans are all inactive', function () {
    [$tenant, $sport] = clubOffering();
    MembershipPlan::factory()->inactive()->create(['club_id' => $tenant->club->id, 'sport_id' => $sport->id]);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNoContent();
});

it('ignores active plans of another club', function () {
    [$tenant, $sport] = clubOffering();
    $other = TenantFixture::create();
    MembershipPlan::factory()->create(['club_id' => $other->club->id, 'sport_id' => $sport->id]);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNoContent();
});
