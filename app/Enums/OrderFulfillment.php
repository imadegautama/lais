<?php

namespace App\Enums;

use App\Concerns\EnumValues;

enum OrderFulfillment: string
{
    use EnumValues;

    case Ambil = 'ambil';
    case Antar = 'antar';

    public function label(): string
    {
        return match ($this) {
            self::Ambil => 'Ambil sendiri',
            self::Antar => 'Diantar',
        };
    }
}
