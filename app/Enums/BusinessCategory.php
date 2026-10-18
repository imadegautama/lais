<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * What the business sells (PRD §2.1 personas), asked in onboarding to offer
 * fitting starter products (App\Support\StarterCatalog).
 */
enum BusinessCategory: string
{
    use EnumValues;

    case Kuliner = 'kuliner';
    case Kelontong = 'kelontong';
    case Fashion = 'fashion';
    case Kerajinan = 'kerajinan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Kuliner => 'Kuliner',
            self::Kelontong => 'Kelontong',
            self::Fashion => 'Fashion',
            self::Kerajinan => 'Kerajinan',
            self::Lainnya => 'Lainnya',
        };
    }
}
