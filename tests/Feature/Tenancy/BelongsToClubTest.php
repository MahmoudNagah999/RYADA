<?php

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Tenancy\TenantNote;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Temporary table: no implicit commit, so RefreshDatabase keeps working.
    // It lives for the whole connection, so create it only once.
    static $created = false;

    if (! $created) {
        Schema::create('tenant_notes', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->string('title');
            $table->timestamps();
        });

        $created = true;
    }

    $this->clubA = Club::factory()->create();
    $this->clubB = Club::factory()->create();
    $this->current = app(CurrentClub::class);
});

it('fills club_id from the current club on create', function () {
    $note = $this->current->runAs($this->clubA, fn () => TenantNote::create(['title' => 'A note']));

    expect($note->club_id)->toBe($this->clubA->id);
});

it('only returns rows of the current club', function () {
    $this->current->runAs($this->clubA, fn () => TenantNote::create(['title' => 'A note']));
    $this->current->runAs($this->clubB, fn () => TenantNote::create(['title' => 'B note']));

    $titles = $this->current->runAs($this->clubA, fn () => TenantNote::pluck('title')->all());

    expect($titles)->toBe(['A note']);
});

it('cannot find a row of another club by id', function () {
    $noteB = $this->current->runAs($this->clubB, fn () => TenantNote::create(['title' => 'B note']));

    $found = $this->current->runAs($this->clubA, fn () => TenantNote::find($noteB->id));

    expect($found)->toBeNull();
});

it('returns nothing when there is no current club', function () {
    $this->current->runAs($this->clubA, fn () => TenantNote::create(['title' => 'A note']));

    expect(TenantNote::count())->toBe(0);
});

it('refuses to create a row without a current club', function () {
    TenantNote::create(['title' => 'Orphan']);
})->throws(LogicException::class);

it('restores the previous club after runAs', function () {
    $this->current->set($this->clubA);

    $this->current->runAs($this->clubB, fn () => expect($this->current->id())->toBe($this->clubB->id));

    expect($this->current->id())->toBe($this->clubA->id);
});

it('exposes the club relation', function () {
    $note = $this->current->runAs($this->clubA, fn () => TenantNote::create(['title' => 'A note']));

    $club = $this->current->runAs($this->clubA, fn () => $note->club);

    expect($club->is($this->clubA))->toBeTrue();
});
