<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Staff\Actions\InviteStaffAction;
use App\Domain\Staff\DTOs\InviteStaffData;
use App\Domain\Staff\Models\StaffInvitation;
use App\Domain\Subscription\Models\Plan;
use App\Mail\StaffInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('sends an Arabic invitation email and stores only the token hash', function () {
    Mail::fake();
    $owner = TenantFixture::create(RoleName::Owner);

    $invitation = app(InviteStaffAction::class)->execute(
        $owner->club,
        $owner->user,
        InviteStaffData::fromArray([
            'email' => 'coach@example.test',
            'role' => RoleName::Coach->value,
            'locale' => 'ar',
        ]),
    );

    $plainToken = null;
    Mail::assertQueued(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$plainToken): bool {
        parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $query);
        $plainToken = $query['token'] ?? null;

        return $mail->locale === 'ar'
            && str_contains($mail->render(), 'تمت دعوتك للانضمام');
    });

    expect($plainToken)->toBeString()
        ->and($invitation->token_hash)->toBe(hash('sha256', $plainToken))
        ->and($invitation->token_hash)->not->toBe($plainToken)
        ->and(Activity::query()->where('description', 'staff.invited')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'staff.invited')->first()->properties->toArray())
        ->not->toHaveKey('token');
});

it('allows an owner to send an invitation', function () {
    Mail::fake();
    $owner = TenantFixture::create(RoleName::Owner);

    $this->withToken($owner->token)
        ->postJson('/api/v1/staff/invitations', [
            'email' => 'new-staff@example.test',
            'role' => RoleName::Staff->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new-staff@example.test')
        ->assertJsonPath('data.role', RoleName::Staff->value);

    Mail::assertQueued(StaffInvitationMail::class);
});

it('returns 403 when Staff, Coach, or Referee sends an invitation', function (RoleName $role) {
    Mail::fake();
    $actor = TenantFixture::create($role);

    $this->withToken($actor->token)
        ->postJson('/api/v1/staff/invitations', [
            'email' => 'new-staff@example.test',
            'role' => RoleName::Staff->value,
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    Mail::assertNothingQueued();
})->with([
    'staff' => RoleName::Staff,
    'coach' => RoleName::Coach,
    'referee' => RoleName::Referee,
]);

it('lists only unexpired pending invitations for the current club', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $otherClub = TenantFixture::create(RoleName::Owner);

    $pending = StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'pending@example.test',
        'expires_at' => now()->addDay(),
    ]);
    StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'expired@example.test',
        'expires_at' => now()->subMinute(),
    ]);
    StaffInvitation::factory()->for($otherClub->club)->create([
        'email' => 'other@example.test',
    ]);

    $this->withToken($owner->token)
        ->getJson('/api/v1/staff/invitations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonMissing(['email' => 'expired@example.test'])
        ->assertJsonMissing(['email' => 'other@example.test']);
});

it('allows Staff to view invitations', function () {
    $staff = TenantFixture::create(RoleName::Staff);
    $invitation = StaffInvitation::factory()->for($staff->club)->create();

    $this->withToken($staff->token)
        ->getJson('/api/v1/staff/invitations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invitation->id);
});

it('returns 403 when Coach or Referee views invitations', function (RoleName $role) {
    $actor = TenantFixture::create($role);

    $this->withToken($actor->token)
        ->getJson('/api/v1/staff/invitations')
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
})->with([
    'coach' => RoleName::Coach,
    'referee' => RoleName::Referee,
]);

it('returns 404 when revoking another club invitation', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $otherClub = TenantFixture::create(RoleName::Owner);
    $invitation = StaffInvitation::factory()->for($otherClub->club)->create();

    $this->withToken($owner->token)
        ->deleteJson("/api/v1/staff/invitations/{$invitation->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'http.not_found');
});

it('returns 403 when Staff, Coach, or Referee revokes an invitation', function (RoleName $role) {
    $actor = TenantFixture::create($role);
    $invitation = StaffInvitation::factory()->for($actor->club)->create();

    $this->withToken($actor->token)
        ->deleteJson("/api/v1/staff/invitations/{$invitation->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    expect($invitation->fresh()->revoked_at)->toBeNull();
})->with([
    'staff' => RoleName::Staff,
    'coach' => RoleName::Coach,
    'referee' => RoleName::Referee,
]);

it('revokes an invitation and records the event', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $invitation = StaffInvitation::factory()->for($owner->club)->create();

    $this->withToken($owner->token)
        ->deleteJson("/api/v1/staff/invitations/{$invitation->id}")
        ->assertNoContent();

    expect($invitation->fresh()->revoked_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'staff.invitation_revoked')->count())->toBe(1);
});

it('rejects duplicate pending invitations', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'duplicate@example.test',
        'expires_at' => now()->addDay(),
    ]);

    $this->withToken($owner->token)
        ->postJson('/api/v1/staff/invitations', [
            'email' => 'duplicate@example.test',
            'role' => RoleName::Coach->value,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'staff.invitation_pending');
});

it('rejects an invitation email already used by any club', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    User::factory()->create(['email' => 'registered@example.test']);

    $this->withToken($owner->token)
        ->postJson('/api/v1/staff/invitations', [
            'email' => 'registered@example.test',
            'role' => RoleName::Staff->value,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff.email_taken');
});

it('rejects an Owner invitation with its business-rule code', function () {
    $owner = TenantFixture::create(RoleName::Owner);

    $this->withToken($owner->token)
        ->postJson('/api/v1/staff/invitations', [
            'email' => 'owner-role@example.test',
            'role' => RoleName::Owner->value,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff.owner_role_not_assignable');
});

it('accepts a staff invitation, creates a club user, and returns a usable token', function () {
    Mail::fake();
    $owner = TenantFixture::create(RoleName::Owner);
    $invitation = app(InviteStaffAction::class)->execute(
        $owner->club,
        $owner->user,
        InviteStaffData::fromArray([
            'email' => 'joined@example.test',
            'role' => RoleName::Staff->value,
            'locale' => 'en',
        ]),
    );
    $plainToken = null;
    Mail::assertQueued(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$plainToken): bool {
        parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $query);
        $plainToken = $query['token'] ?? null;

        return is_string($plainToken);
    });

    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $plainToken,
        'name' => 'New Staff',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ], ['Accept-Language' => 'ar'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'joined@example.test')
        ->assertJsonPath('data.locale', 'ar')
        ->assertJsonStructure(['meta' => ['token', 'abilities']]);

    $user = User::query()->where('email', 'joined@example.test')->firstOrFail();
    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);

    expect($user->club_id)->toBe($owner->club->id)
        ->and($user->hasRole(RoleName::Staff->value))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'staff.joined')->count())->toBe(1);

    setPermissionsTeamId($previousTeamId);
    $this->app['auth']->forgetGuards();

    $this->withToken($response->json('meta.token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('cannot accept the same invitation token twice', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $plainToken = Str::random(64);
    StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'one-use@example.test',
        'role' => RoleName::Referee->value,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $payload = [
        'token' => $plainToken,
        'name' => 'One Use',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ];

    $this->postJson('/api/v1/auth/accept-invitation', $payload)->assertCreated();

    $this->postJson('/api/v1/auth/accept-invitation', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff.invitation_invalid');
});

it('returns one generic error for unknown, expired, revoked, and used invitation tokens', function (?string $state) {
    $owner = TenantFixture::create(RoleName::Owner);
    $plainToken = Str::random(64);

    if ($state !== null) {
        $attributes = [
            'email' => 'invalid-'.$state.'@example.test',
            'token_hash' => hash('sha256', $plainToken),
        ];

        if ($state === 'expired') {
            $attributes['expires_at'] = now()->subMinute();
        } elseif ($state === 'revoked') {
            $attributes['revoked_at'] = now();
        } elseif ($state === 'used') {
            $attributes['accepted_at'] = now();
        }

        StaffInvitation::factory()->for($owner->club)->create($attributes);
    }

    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $plainToken,
        'name' => 'Someone',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff.invitation_invalid');
})->with([
    'unknown token' => [null],
    'expired token' => ['expired'],
    'revoked token' => ['revoked'],
    'used token' => ['used'],
]);

it('blocks coach invitation acceptance at the plan limit', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $plan = Plan::factory()->create(['max_coaches' => 1]);
    $owner->club->plan()->associate($plan);
    $owner->club->save();

    $coach = User::factory()->forClub($owner->club)->create();
    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $coach->assignRole(RoleName::Coach->value);
    setPermissionsTeamId($previousTeamId);

    $plainToken = Str::random(64);
    StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'over-limit@example.test',
        'role' => RoleName::Coach->value,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $plainToken,
        'name' => 'Extra Coach',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'plan.coaches_limit_reached');

    expect(User::query()->where('email', 'over-limit@example.test')->exists())->toBeFalse();
});

it('allows coach invitation acceptance with no coach limit', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $plan = Plan::factory()->unlimited()->create();
    $owner->club->plan()->associate($plan);
    $owner->club->save();
    $plainToken = Str::random(64);

    StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'unlimited-coach@example.test',
        'role' => RoleName::Coach->value,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $plainToken,
        'name' => 'Unlimited Coach',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'unlimited-coach@example.test');
});

it('does not count staff toward the coach plan limit', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $plan = Plan::factory()->create(['max_coaches' => 0]);
    $owner->club->plan()->associate($plan);
    $owner->club->save();
    $plainToken = Str::random(64);

    StaffInvitation::factory()->for($owner->club)->create([
        'email' => 'non-coach@example.test',
        'role' => RoleName::Staff->value,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $plainToken,
        'name' => 'Non Coach',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'non-coach@example.test');
});

it('throttles invitation acceptance independently by IP', function () {
    $response = null;

    for ($attempt = 0; $attempt < 11; $attempt++) {
        $response = $this->postJson('/api/v1/auth/accept-invitation', []);
    }

    $response->assertTooManyRequests()
        ->assertJsonPath('code', 'http.too_many_requests');
});
