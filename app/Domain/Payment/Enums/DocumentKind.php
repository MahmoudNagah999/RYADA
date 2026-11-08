<?php

namespace App\Domain\Payment\Enums;

enum DocumentKind: string
{
    case Receipt = 'receipt';
    case Refund = 'refund';
}
