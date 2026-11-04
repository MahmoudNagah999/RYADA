<?php

namespace App\Domain\Player\Actions;

use App\Domain\Player\Enums\Gender;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Carbon\CarbonImmutable;

class ResolvePlayerBirthDataAction
{
    public function __construct(
        private readonly ParseEgyptianNationalIdAction $parseNationalId,
    ) {}

    /**
     * @return array{0: CarbonImmutable, 1: Gender}
     */
    public function execute(?string $nationalId, ?CarbonImmutable $birthDate, ?Gender $gender): array
    {
        if ($nationalId !== null) {
            $info = $this->parseNationalId->execute($nationalId)
                ?? throw new BusinessRuleException('player.national_id_invalid', [], 422);

            $dateMismatch = $birthDate !== null && ! $birthDate->isSameDay($info->birthDate);
            $genderMismatch = $gender !== null && $gender !== $info->gender;

            if ($dateMismatch || $genderMismatch) {
                throw new BusinessRuleException('player.national_id_mismatch', [], 422);
            }

            return [$info->birthDate, $info->gender];
        }

        if ($birthDate === null || $gender === null) {
            throw new BusinessRuleException('player.birth_data_required', [], 422);
        }

        return [$birthDate, $gender];
    }
}
