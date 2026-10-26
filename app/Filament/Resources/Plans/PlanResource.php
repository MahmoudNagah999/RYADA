<?php

namespace App\Filament\Resources\Plans;

use App\Domain\Subscription\Models\Plan;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Plans\Schemas\PlanForm;
use App\Filament\Resources\Plans\Tables\PlansTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return __('admin.plans.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.plans.plural');
    }

    // Clubs reference plans with restrictOnDelete: deactivate a plan instead.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
