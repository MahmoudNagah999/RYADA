<?php

use App\Domain\Membership\Actions\CalculateMembershipAmountAction;
use App\Domain\Membership\DTOs\MembershipDiscount;
use App\Domain\Shared\Exceptions\BusinessRuleException;

function calculateAmount(int $price, ?MembershipDiscount $discount = null): array
{
    $result = (new CalculateMembershipAmountAction)->execute($price, $discount);

    return [$result->priceCents, $result->discountCents, $result->totalCents];
}

it('returns the full price when there is no discount', function () {
    expect(calculateAmount(50000))->toBe([50000, 0, 50000]);
});

it('applies a percent discount', function () {
    expect(calculateAmount(50000, MembershipDiscount::percent(10)))->toBe([50000, 5000, 45000])
        ->and(calculateAmount(50000, MembershipDiscount::percent(25)))->toBe([50000, 12500, 37500]);
});

it('rounds a percent discount half up to the cent', function () {
    // 10% of 1005 = 100.5 -> 101
    expect(calculateAmount(1005, MembershipDiscount::percent(10)))->toBe([1005, 101, 904])
        // 10% of 1004 = 100.4 -> 100
        ->and(calculateAmount(1004, MembershipDiscount::percent(10)))->toBe([1004, 100, 904]);
});

it('accepts the boundaries 0 and 100 percent', function () {
    expect(calculateAmount(50000, MembershipDiscount::percent(0)))->toBe([50000, 0, 50000])
        ->and(calculateAmount(50000, MembershipDiscount::percent(100)))->toBe([50000, 50000, 0]);
});

it('applies a fixed discount, including one equal to the price', function () {
    expect(calculateAmount(50000, MembershipDiscount::fixed(7500)))->toBe([50000, 7500, 42500])
        ->and(calculateAmount(50000, MembershipDiscount::fixed(50000)))->toBe([50000, 50000, 0])
        ->and(calculateAmount(50000, MembershipDiscount::fixed(0)))->toBe([50000, 0, 50000]);
});

it('handles a free plan', function () {
    expect(calculateAmount(0))->toBe([0, 0, 0])
        ->and(calculateAmount(0, MembershipDiscount::percent(50)))->toBe([0, 0, 0]);
});

it('does not overflow on large amounts', function () {
    expect(calculateAmount(2_000_000_000, MembershipDiscount::percent(15)))
        ->toBe([2_000_000_000, 300_000_000, 1_700_000_000]);
});

it('rejects invalid discounts', function (MembershipDiscount $discount) {
    calculateAmount(50000, $discount);
})->with([
    'percent over 100' => fn () => MembershipDiscount::percent(101),
    'negative percent' => fn () => MembershipDiscount::percent(-1),
    'negative fixed' => fn () => MembershipDiscount::fixed(-1),
    'fixed over price' => fn () => MembershipDiscount::fixed(50001),
])->throws(BusinessRuleException::class);

it('rejects a fixed discount on a free plan', function () {
    calculateAmount(0, MembershipDiscount::fixed(1));
})->throws(BusinessRuleException::class);

it('rejects a negative price', function () {
    calculateAmount(-1);
})->throws(BusinessRuleException::class);
