<?php

use App\Domain\Player\Actions\ParseEgyptianNationalIdAction;
use App\Domain\Player\Enums\Gender;

it('derives birth date and gender from a valid id', function (string $id, string $date, Gender $gender) {
    $info = (new ParseEgyptianNationalIdAction)->execute($id);

    expect($info)->not->toBeNull()
        ->and($info->birthDate->toDateString())->toBe($date)
        ->and($info->gender)->toBe($gender);
})->with([
    'male born in 2000s' => ['31005150101234', '2010-05-15', Gender::Male],
    'female born in 2000s' => ['31005150101245', '2010-05-15', Gender::Female],
    'male born in 1900s' => ['29901010100011', '1999-01-01', Gender::Male],
]);

it('rejects an invalid id', function (string $id) {
    expect((new ParseEgyptianNationalIdAction)->execute($id))->toBeNull();
})->with([
    'too short' => ['3100515010123'],
    'too long' => ['310051501012345'],
    'non digits' => ['3100515010123a'],
    'arabic indic digits' => ['٣١٠٠٥١٥٠١٠١٢٣٤'],
    'unknown century' => ['41005150101234'],
    'impossible date' => ['31002300101234'],
    'future date' => ['39905150101234'],
    'empty' => [''],
]);
