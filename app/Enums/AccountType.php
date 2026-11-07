<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * The groups of the chart of accounts, in the order reports list them.
 */
enum AccountType: string
{
    use EnumValues;

    case Aset = 'aset';
    case Kewajiban = 'kewajiban';
    case Ekuitas = 'ekuitas';
    case Pendapatan = 'pendapatan';
    case Beban = 'beban';

    public function label(): string
    {
        return match ($this) {
            self::Aset => 'Aset',
            self::Kewajiban => 'Kewajiban',
            self::Ekuitas => 'Modal',
            self::Pendapatan => 'Pendapatan',
            self::Beban => 'Beban',
        };
    }

    /**
     * Where a business's own accounts of this type start; the fixed accounts
     * stay below it. Only income and expenses can be added: they reach the
     * books through the profit alone, so no other report needs to know them.
     */
    public function firstOwnCode(): ?int
    {
        return match ($this) {
            self::Pendapatan => 4201,
            self::Beban => 6501,
            default => null,
        };
    }
}
