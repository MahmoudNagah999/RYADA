<?php

namespace App\Domain\Auth\Enums;

enum TokenAbility: string
{
    case Read = 'read';
    case Write = 'write';
}
