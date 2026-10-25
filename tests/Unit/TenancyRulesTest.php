<?php

use Symfony\Component\Finder\Finder;

it('never bypasses the tenant scope in club-facing code', function () {
    $root = dirname(__DIR__, 2).'/app';

    $files = Finder::create()
        ->files()
        ->name('*.php')
        ->in([$root.'/Http', $root.'/Domain'])
        ->notPath('Shared/Tenancy');

    $offenders = [];

    foreach ($files as $file) {
        if (str_contains($file->getContents(), 'withoutGlobalScope')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
