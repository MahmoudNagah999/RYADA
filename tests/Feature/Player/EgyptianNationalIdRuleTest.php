<?php

use App\Http\Rules\EgyptianNationalId;
use Illuminate\Support\Facades\Validator;

function nationalIdErrors(string $value, string $locale): array
{
    app()->setLocale($locale);

    return Validator::make(
        ['national_id' => $value],
        ['national_id' => [new EgyptianNationalId]],
    )->errors()->get('national_id');
}

it('accepts a valid id', function () {
    expect(nationalIdErrors('31005150101234', 'en'))->toBe([]);
});

it('rejects an invalid id with a message in the active locale', function () {
    $en = nationalIdErrors('123', 'en');
    $ar = nationalIdErrors('123', 'ar');

    expect($en)->toHaveCount(1)
        ->and($ar)->toHaveCount(1)
        ->and($en[0])->not->toBe($ar[0])
        ->and($ar[0])->not->toContain('validation.');
});
