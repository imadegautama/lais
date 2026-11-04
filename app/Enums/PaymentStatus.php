<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * How much of an order is paid. Never stored: read from the transactions
 * that point to the order (Orders::paid).
 */
enum PaymentStatus: string
{
    use EnumValues;

    case Belum = 'belum';
    case Dp = 'dp';
    case Lunas = 'lunas';

    public static function for(int $paid, int $total): self
    {
        return match (true) {
            $paid <= 0 => self::Belum,
            $paid < $total => self::Dp,
            default => self::Lunas,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum bayar',
            self::Dp => 'DP',
            self::Lunas => 'Lunas',
        };
    }
}
