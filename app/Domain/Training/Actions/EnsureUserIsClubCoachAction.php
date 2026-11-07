<?php

namespace App\Domain\Training\Actions;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\User;

class EnsureUserIsClubCoachAction
{
    /**
     * `User` has no global scope, so the club is filtered explicitly. The role check uses the
     * current permissions team (set by the `tenant` middleware, or by the caller in jobs / tests).
     */
    public function execute(int $userId, int $clubId): User
    {
        $user = User::query()->where('club_id', $clubId)->find($userId);

        if ($user === null || ! $user->hasRole(RoleName::Coach->value)) {
            throw new BusinessRuleException('training_group.coach_invalid', [], 422);
        }

        return $user;
    }
}
