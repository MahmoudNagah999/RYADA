<?php

use App\Domain\Club\Models\Club;
use App\Filament\Resources\Clubs\Pages\ViewClub;
use App\Filament\Resources\Clubs\RelationManagers\UsersRelationManager;
use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Filament::setCurrentPanel('admin');
});

it('shows only the users of the club being viewed', function () {
    $club = Club::factory()->create();
    $otherClub = Club::factory()->create();

    $users = User::factory()->count(2)->forClub($club)->create();
    $otherUsers = User::factory()->count(2)->forClub($otherClub)->create();

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => ViewClub::class,
    ])
        ->assertCanSeeTableRecords($users)
        ->assertCanNotSeeTableRecords($otherUsers);
});
