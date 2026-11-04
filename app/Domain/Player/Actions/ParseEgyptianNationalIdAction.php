<?php

namespace App\Domain\Player\Actions;

use App\Domain\Player\DTOs\NationalIdInfo;
use App\Domain\Player\Enums\Gender;
use Carbon\CarbonImmutable;

class ParseEgyptianNationalIdAction
{
    /**
     * Returns the data derived from an Egyptian national ID, or null when the
     * number is not a plausible ID (length, century, impossible or future date).
     *
     * The governorate code and the check digit are not verified on purpose.
     */
    public function execute(string $nationalId): ?NationalIdInfo
    {
        if (! preg_match('/^[0-9]{14}$/', $nationalId)) {
            return null;
        }

        $century = match ($nationalId[0]) {
            '2' => 1900,
            '3' => 2000,
            default => null,
        };

        if ($century === null) {
            return null;
        }

        $year = $century + (int) substr($nationalId, 1, 2);
        $month = (int) substr($nationalId, 3, 2);
        $day = (int) substr($nationalId, 5, 2);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $birthDate = CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC');

        if ($birthDate->isFuture()) {
            return null;
        }

        $gender = ((int) $nationalId[12]) % 2 === 1 ? Gender::Male : Gender::Female;

        return new NationalIdInfo($birthDate, $gender);
    }
}
