<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Why stock moved.
 */
enum StockReason: string
{
    use EnumValues;

    case StokAwal = 'stok_awal';
    case Koreksi = 'koreksi';
    case Penjualan = 'penjualan';
    case Pembelian = 'pembelian';
    case Pembatalan = 'pembatalan';

    public function label(): string
    {
        return match ($this) {
            self::StokAwal => 'Stok awal',
            self::Koreksi => 'Koreksi stok',
            self::Penjualan => 'Terjual',
            self::Pembelian => 'Belanja',
            self::Pembatalan => 'Dibatalkan',
        };
    }
}
