<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use App\Models\Account;

/**
 * Where the business keeps its money: the one cash box, or a bank or e-wallet
 * account. Each rekening is a detail of 1101 or 1102; the chart of accounts
 * itself never changes.
 */
enum MoneyAccountKind: string
{
    use EnumValues;

    case Tunai = 'tunai';
    case NonTunai = 'non_tunai';

    public function label(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::NonTunai => 'Non-tunai',
        };
    }

    /**
     * The account code its money sits in.
     */
    public function account(): string
    {
        return match ($this) {
            self::Tunai => Account::CASH,
            self::NonTunai => Account::NON_CASH,
        };
    }

    public function method(): PaymentMethod
    {
        return match ($this) {
            self::Tunai => PaymentMethod::Tunai,
            self::NonTunai => PaymentMethod::NonTunai,
        };
    }
}
