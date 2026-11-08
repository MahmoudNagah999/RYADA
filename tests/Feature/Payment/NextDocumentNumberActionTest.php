<?php

use App\Domain\Payment\Actions\NextDocumentNumberAction;
use App\Domain\Payment\Enums\DocumentKind;
use App\Domain\Payment\Models\DocumentSequence;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function payDocInClub(TenantFixture $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

function payDocNext(TenantFixture $tenant, DocumentKind $kind): int
{
    return payDocInClub($tenant, fn () => app(NextDocumentNumberAction::class)->execute($kind));
}

function payDocSequenceCount(TenantFixture $tenant, DocumentKind $kind): int
{
    return DocumentSequence::query()
        ->withoutGlobalScopes()
        ->where('club_id', $tenant->club->id)
        ->where('kind', $kind->value)
        ->count();
}

it('allocates increasing numbers per club and independently', function () {
    $first = TenantFixture::create();
    $second = TenantFixture::create();

    expect(payDocNext($first, DocumentKind::Receipt))->toBe(1)
        ->and(payDocNext($first, DocumentKind::Receipt))->toBe(2)
        ->and(payDocNext($second, DocumentKind::Receipt))->toBe(1)
        ->and(payDocNext($first, DocumentKind::Refund))->toBe(1);

    expect(payDocSequenceCount($first, DocumentKind::Receipt))->toBe(1)
        ->and(payDocSequenceCount($second, DocumentKind::Receipt))->toBe(1);
});

it('reuses a pre-existing sequence row instead of inserting a duplicate', function () {
    $tenant = TenantFixture::create();

    payDocInClub($tenant, fn () => DocumentSequence::query()->create([
        'kind' => DocumentKind::Receipt,
        'last_number' => 41,
    ]));

    expect(payDocNext($tenant, DocumentKind::Receipt))->toBe(42)
        ->and(payDocSequenceCount($tenant, DocumentKind::Receipt))->toBe(1);
});
