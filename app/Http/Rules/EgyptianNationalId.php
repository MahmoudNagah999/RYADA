<?php

namespace App\Http\Rules;

use App\Domain\Player\Actions\ParseEgyptianNationalIdAction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EgyptianNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $info = is_scalar($value)
            ? app(ParseEgyptianNationalIdAction::class)->execute((string) $value)
            : null;

        if ($info === null) {
            $fail('validation.national_id')->translate();
        }
    }
}
