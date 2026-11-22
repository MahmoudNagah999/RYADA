<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Indexes added by the #33 review
|--------------------------------------------------------------------------
|
| These tests only check the schema: the index exists after migrations and its
| first column is club_id (ADR-001). They never assert EXPLAIN output, because
| the plan belongs to MySQL and changes with statistics, not with the schema.
|
| The activity log is the one deliberate exception: it is a platform-wide audit
| table with a nullable club_id, read by the super-admin with no current club, so
| its created_at index cannot start with club_id.
|
*/

/**
 * @return array{name: string, columns: list<string>, type: string, unique: bool, primary: bool}
 */
function idxIndex(string $table, string $name): array
{
    $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);

    expect($index)->not->toBeNull("Index {$name} is missing on {$table}.");

    return $index;
}

it('keeps a club-first index for the players gender filter', function (): void {
    $index = idxIndex('players', 'players_club_id_gender_index');

    expect($index['columns'])->toBe(['club_id', 'gender']);
    expect($index['unique'])->toBeFalse();
});

it('keeps a club-first index for the attendance sheet', function (): void {
    $index = idxIndex('training_attendances', 'training_attendances_club_id_group_date_index');

    expect($index['columns'])->toBe(['club_id', 'training_group_id', 'date']);
    expect($index['unique'])->toBeFalse();
});

it('indexes the activity log by created_at for the cross-tenant admin list', function (): void {
    $index = idxIndex('activity_log', 'activity_log_created_at_index');

    expect($index['columns'])->toBe(['created_at']);
    expect($index['unique'])->toBeFalse();
});
