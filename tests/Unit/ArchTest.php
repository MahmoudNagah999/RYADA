<?php

arch('domain layer does not depend on the http layer')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

arch('no debugging statements are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
