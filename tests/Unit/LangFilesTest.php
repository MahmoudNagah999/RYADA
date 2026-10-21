<?php

use Illuminate\Support\Arr;

$langPath = dirname(__DIR__, 2).'/lang';

$phpKeys = function (string $locale) use ($langPath): array {
    $keys = [];

    foreach (glob("{$langPath}/{$locale}/*.php") as $file) {
        $group = basename($file, '.php');

        foreach (Arr::dot(require $file) as $key => $value) {
            $keys["{$group}.{$key}"] = $value;
        }
    }

    ksort($keys);

    return $keys;
};

it('has the same php lang files in en and ar', function () use ($langPath) {
    $files = fn (string $locale) => array_map('basename', glob("{$langPath}/{$locale}/*.php"));

    expect($files('ar'))->toEqualCanonicalizing($files('en'));
});

it('has the same translation keys in en and ar php files', function () use ($phpKeys) {
    $en = array_keys($phpKeys('en'));
    $ar = array_keys($phpKeys('ar'));

    expect(array_values(array_diff($en, $ar)))->toBe([], 'Keys missing in ar')
        ->and(array_values(array_diff($ar, $en)))->toBe([], 'Keys missing in en');
});

it('has the same translation keys in en.json and ar.json', function () use ($langPath) {
    $en = array_keys(json_decode(file_get_contents("{$langPath}/en.json"), true));
    $ar = array_keys(json_decode(file_get_contents("{$langPath}/ar.json"), true));

    expect(array_values(array_diff($en, $ar)))->toBe([], 'Keys missing in ar.json')
        ->and(array_values(array_diff($ar, $en)))->toBe([], 'Keys missing in en.json');
});

it('keeps the same placeholders in our own translations', function () use ($phpKeys) {
    $own = ['errors'];

    $placeholders = function (string $text): array {
        preg_match_all('/:[A-Za-z_]+/', $text, $matches);

        $found = array_unique($matches[0]);
        sort($found);

        return $found;
    };

    $en = $phpKeys('en');
    $ar = $phpKeys('ar');

    foreach ($en as $key => $text) {
        if (! in_array(explode('.', $key)[0], $own, true) || ! is_string($text)) {
            continue;
        }

        expect($placeholders($ar[$key] ?? ''))->toBe($placeholders($text), "Placeholders differ for {$key}");
    }
});
