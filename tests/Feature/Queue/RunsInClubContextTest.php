<?php

use App\Domain\Club\Models\Club;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Queue\RecordingClubJob;

uses(RefreshDatabase::class);

beforeEach(fn () => RecordingClubJob::reset());

function queueSeedPlayers(Club $club, int $count): void
{
    app(CurrentClub::class)->runAs($club, function () use ($club, $count): void {
        Player::factory()->count($count)->create(['club_id' => $club->id]);
    });
}

it('runs the job inside the club it was dispatched for, with no request around it', function () {
    $a = Club::factory()->create();
    $b = Club::factory()->create();
    queueSeedPlayers($a, 2);
    queueSeedPlayers($b, 5);

    expect(app(CurrentClub::class)->get())->toBeNull();

    RecordingClubJob::dispatch($a->id);

    expect(RecordingClubJob::$runs)->toHaveCount(1)
        ->and(RecordingClubJob::$runs[0]['club_id'])->toBe($a->id)
        ->and(RecordingClubJob::$runs[0]['players'])->toBe(2)
        ->and(RecordingClubJob::$runs[0]['team_id'])->toBe($a->id)
        ->and(app(CurrentClub::class)->get())->toBeNull();
});

it('gives each job only the data of its own club', function () {
    $a = Club::factory()->create();
    $b = Club::factory()->create();
    queueSeedPlayers($a, 2);
    queueSeedPlayers($b, 5);

    RecordingClubJob::dispatch($a->id);
    RecordingClubJob::dispatch($b->id);

    expect(array_column(RecordingClubJob::$runs, 'players'))->toBe([2, 5])
        ->and(array_column(RecordingClubJob::$runs, 'club_id'))->toBe([$a->id, $b->id]);
});

it('restores the previous club and roles team after the job', function () {
    $outer = Club::factory()->create();
    $other = Club::factory()->create();
    setPermissionsTeamId($outer->id);

    app(CurrentClub::class)->runAs($outer, function () use ($other, $outer): void {
        RecordingClubJob::dispatch($other->id);

        expect(app(CurrentClub::class)->get()?->id)->toBe($outer->id)
            ->and(getPermissionsTeamId())->toBe($outer->id);
    });

    expect(RecordingClubJob::$runs[0]['club_id'])->toBe($other->id);
});

it('drops a job whose club no longer exists', function () {
    $club = Club::factory()->create();
    $id = $club->id;
    $club->delete();

    RecordingClubJob::dispatch($id);

    expect(RecordingClubJob::$runs)->toBe([]);
});
