<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use App\Models\Account;

enum PaymentMethod: string
{
    use EnumValues;

    case Tunai = 'tunai';
    case NonTunai = 'non_tunai';
    case Kredit = 'kredit';
    case Pribadi = 'pribadi';

    /**
     * Neutral wording; a purchase on credit reads "Belum dibayar" in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::NonTunai => 'Non-tunai',
            self::Kredit => 'Belum lunas',
            self::Pribadi => 'Uang pribadi',
        };
    }

    /**
     * Where the money comes from or goes to. Kredit has none: it is Piutang for
     * a sale and Utang otherwise, decided by the transaction.
     */
    public function cashAccount(): ?string
    {
        return match ($this) {
            self::Tunai => Account::CASH,
            self::NonTunai => Account::NON_CASH,
            self::Pribadi => Account::OWNER_CAPITAL,
            self::Kredit => null,
        };
    }
}
