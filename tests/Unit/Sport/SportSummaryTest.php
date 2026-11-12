<?php

use App\Domain\Sport\DTOs\SportSummary;

it('returns the name in the requested locale', function () {
    $sport = new SportSummary(1, 'karate', true, ['en' => 'Karate', 'ar' => 'كاراتيه']);

    expect($sport->name('ar', 'en'))->toBe('كاراتيه');
});

it('falls back when the locale has no name or an empty one', function () {
    $missing = new SportSummary(1, 'karate', true, ['en' => 'Karate']);
    $empty = new SportSummary(1, 'karate', true, ['en' => 'Karate', 'ar' => '']);

    expect($missing->name('ar', 'en'))->toBe('Karate')
        ->and($empty->name('ar', 'en'))->toBe('Karate');
});

it('returns an empty string when nothing matches', function () {
    expect((new SportSummary(1, 'karate', true, []))->name('ar', 'en'))->toBe('');
});
