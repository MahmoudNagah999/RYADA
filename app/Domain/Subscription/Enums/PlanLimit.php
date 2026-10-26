<?php

namespace App\Domain\Subscription\Enums;

enum PlanLimit: string
{
    case Players = 'players';
    case Coaches = 'coaches';

    /** The `plans` column that holds this limit. */
    public function column(): string
    {
        return match ($this) {
            self::Players => 'max_players',
            self::Coaches => 'max_coaches',
        };
    }

    /** Key in the errors lang files (lang/en and lang/ar). */
    public function errorCode(): string
    {
        return match ($this) {
            self::Players => 'plan.players_limit_reached',
            self::Coaches => 'plan.coaches_limit_reached',
        };
    }
}
