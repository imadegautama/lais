<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Where a DOKU Checkout page stands (App\Services\Doku). Only Lunas puts
 * money in the books; the others leave the order as it was.
 */
enum OnlinePaymentStatus: string
{
    use EnumValues;

    case Menunggu = 'menunggu';
    case Lunas = 'lunas';
    case Kedaluwarsa = 'kedaluwarsa';
    case Gagal = 'gagal';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pembayaran',
            self::Lunas => 'Dibayar online',
            self::Kedaluwarsa => 'Kedaluwarsa',
            self::Gagal => 'Gagal',
        };
    }
}
