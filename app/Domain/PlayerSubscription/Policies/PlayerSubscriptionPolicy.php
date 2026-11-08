<?php

namespace App\Domain\PlayerSubscription\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Models\User;

class PlayerSubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::PlayerSubscriptionsView->value);
    }

    public function view(User $user, PlayerSubscription $subscription): bool
    {
        return $this->sameClub($user, $subscription)
            && $user->checkPermissionTo(PermissionName::PlayerSubscriptionsView->value);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::PlayerSubscriptionsManage->value);
    }

    public function renew(User $user, PlayerSubscription $subscription): bool
    {
        return $this->sameClub($user, $subscription)
            && $user->checkPermissionTo(PermissionName::PlayerSubscriptionsManage->value);
    }

    public function cancel(User $user, PlayerSubscription $subscription): bool
    {
        return $this->sameClub($user, $subscription)
            && $user->checkPermissionTo(PermissionName::PlayerSubscriptionsManage->value);
    }

    private function sameClub(User $user, PlayerSubscription $subscription): bool
    {
        return $user->club_id === $subscription->club_id;
    }
}
