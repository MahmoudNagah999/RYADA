<?php

namespace App\Filament\Resources\Activities;

use App\Domain\Audit\Models\Activity;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationLabel(): string
    {
        return __('admin.audit.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.audit.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.audit.plural');
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['club', 'causer']);
    }

    /** The audit log is append-only: nothing can be created, edited or deleted from the panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
            'index' => ListActivities::route('/'),
        ];
    }
}
