<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Sport\Actions\CreateSportAction;
use App\Domain\Sport\Actions\UpdateSportAction;
use App\Domain\Sport\DTOs\SportData;
use App\Domain\Sport\Models\Sport;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('creates a sport and logs it', function () {
    $sport = app(CreateSportAction::class)->execute(
        new SportData('archery', ['en' => 'Archery', 'ar' => 'الرماية'], true),
    );

    expect($sport->getTranslations('name'))->toEqual(['en' => 'Archery', 'ar' => 'الرماية']);
    expect(Activity::query()->where('event', 'sport.created')->count())->toBe(1);
});

it('keeps the slug and logs only real changes on update', function () {
    $sport = Sport::factory()->create(['slug' => 'archery']);

    app(UpdateSportAction::class)->execute(
        $sport,
        new SportData('ignored-slug', ['en' => 'Archery 2', 'ar' => $sport->getTranslation('name', 'ar')], false),
    );

    expect($sport->fresh()->slug)->toBe('archery')
        ->and($sport->fresh()->is_active)->toBeFalse();

    $activity = Activity::query()->where('event', 'sport.updated')->firstOrFail();
    $changes = $activity->properties->get('changes');
    expect(array_keys($changes))->toEqualCanonicalizing(['name', 'is_active']);
    expect($changes['is_active'])->toEqual(['from' => true, 'to' => false])
        ->and($changes['name']['to']['en'])->toBe('Archery 2');
});

it('does not log an update with no changes', function () {
    $sport = Sport::factory()->create();

    app(UpdateSportAction::class)->execute(
        $sport,
        new SportData($sport->slug, $sport->getTranslations('name'), true),
    );

    expect(Activity::query()->where('event', 'sport.updated')->count())->toBe(0);
});
