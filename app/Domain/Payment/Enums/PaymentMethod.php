<?php

namespace App\Domain\Payment\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';
}
