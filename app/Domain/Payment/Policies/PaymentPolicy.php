<?php

namespace App\Domain\Payment\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Payment\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::PaymentsView->value);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->sameClub($user, $payment)
            && $user->checkPermissionTo(PermissionName::PaymentsView->value);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::PaymentsManage->value);
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->sameClub($user, $payment)
            && $user->checkPermissionTo(PermissionName::PaymentsManage->value);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->sameClub($user, $payment)
            && $user->checkPermissionTo(PermissionName::PaymentsRefund->value);
    }

    private function sameClub(User $user, Payment $payment): bool
    {
        return $user->club_id === $payment->club_id;
    }
}
