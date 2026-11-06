<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\DTOs\AmountBreakdown;
use App\Domain\Membership\DTOs\MembershipDiscount;
use App\Domain\Membership\Enums\DiscountType;
use App\Domain\Shared\Exceptions\BusinessRuleException;

class CalculateMembershipAmountAction
{
    /**
     * Pure calculation, no database. Integer math only (amounts are in cents).
     * Percent discounts round half up to the nearest cent.
     *
     * @throws BusinessRuleException membership_plan.discount_invalid (422)
     */
    public function execute(int $priceCents, ?MembershipDiscount $discount = null): AmountBreakdown
    {
        if ($priceCents < 0) {
            throw new BusinessRuleException('membership_plan.discount_invalid', [], 422);
        }

        if ($discount === null) {
            return new AmountBreakdown($priceCents, 0, $priceCents);
        }

        $discountCents = match ($discount->type) {
            DiscountType::Percent => $this->percentOf($priceCents, $discount->value),
            DiscountType::Fixed => $this->fixed($priceCents, $discount->value),
        };

        return new AmountBreakdown($priceCents, $discountCents, $priceCents - $discountCents);
    }

    private function percentOf(int $priceCents, int $percent): int
    {
        if ($percent < 0 || $percent > 100) {
            throw new BusinessRuleException('membership_plan.discount_invalid', [], 422);
        }

        return intdiv($priceCents * $percent + 50, 100);
    }

    private function fixed(int $priceCents, int $cents): int
    {
        if ($cents < 0 || $cents > $priceCents) {
            throw new BusinessRuleException('membership_plan.discount_invalid', [], 422);
        }

        return $cents;
    }
}
