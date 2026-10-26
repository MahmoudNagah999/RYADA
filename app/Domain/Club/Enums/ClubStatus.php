<?php

namespace App\Domain\Club\Enums;

enum ClubStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
