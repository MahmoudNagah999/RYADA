<?php

namespace App\Filament\Resources\Sports;

use App\Domain\Sport\Models\Sport;
use App\Filament\Resources\Sports\Pages\CreateSport;
use App\Filament\Resources\Sports\Pages\EditSport;
use App\Filament\Resources\Sports\Pages\ListSports;
use App\Filament\Resources\Sports\Schemas\SportForm;
use App\Filament\Resources\Sports\Tables\SportsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SportResource extends Resource
{
    protected static ?string $model = Sport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    public static function getNavigationLabel(): string
    {
        return __('admin.sports.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.sports.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.sports.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return SportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SportsTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSports::route('/'),
            'create' => CreateSport::route('/create'),
            'edit' => EditSport::route('/{record}/edit'),
        ];
    }
}
