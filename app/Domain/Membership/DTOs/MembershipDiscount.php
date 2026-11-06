<?php

namespace App\Domain\Membership\DTOs;

use App\Domain\Membership\Enums\DiscountType;

final readonly class MembershipDiscount
{
    /**
     * @param  int  $value  Whole percent (0-100) for Percent, amount in cents for Fixed.
     */
    public function __construct(
        public DiscountType $type,
        public int $value,
    ) {}

    public static function percent(int $percent): self
    {
        return new self(DiscountType::Percent, $percent);
    }

    public static function fixed(int $cents): self
    {
        return new self(DiscountType::Fixed, $cents);
    }
}
