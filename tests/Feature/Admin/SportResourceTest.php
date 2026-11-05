<?php

use App\Domain\Sport\Models\Sport;
use App\Filament\Resources\Sports\Pages\CreateSport;
use App\Filament\Resources\Sports\Pages\EditSport;
use App\Filament\Resources\Sports\Pages\ListSports;
use App\Filament\Resources\Sports\SportResource;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('lists sports', function () {
    $sports = Sport::factory()->count(2)->create();

    Livewire::test(ListSports::class)->assertCanSeeTableRecords($sports);
});

it('creates a sport from the panel', function () {
    Livewire::test(CreateSport::class)
        ->fillForm([
            'slug' => 'archery',
            'name.en' => 'Archery',
            'name.ar' => 'الرماية',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sport::query()->where('slug', 'archery')->exists())->toBeTrue();
});

it('rejects a duplicate or malformed slug', function () {
    Sport::factory()->create(['slug' => 'archery']);

    Livewire::test(CreateSport::class)
        ->fillForm(['slug' => 'archery', 'name.en' => 'A', 'name.ar' => 'ا'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    Livewire::test(CreateSport::class)
        ->fillForm(['slug' => 'Bad Slug', 'name.en' => 'A', 'name.ar' => 'ا'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('edits names and deactivates but never changes the slug', function () {
    $sport = Sport::factory()->create(['slug' => 'archery']);

    Livewire::test(EditSport::class, ['record' => $sport->getRouteKey()])
        ->fillForm(['name.en' => 'Archery Pro', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $sport->refresh();
    expect($sport->slug)->toBe('archery')
        ->and($sport->getTranslation('name', 'en'))->toBe('Archery Pro')
        ->and($sport->is_active)->toBeFalse();
});

it('does not allow deleting sports', function () {
    expect(SportResource::canDelete(Sport::factory()->create()))->toBeFalse();
});
