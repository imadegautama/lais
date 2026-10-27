<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Which part of the business an audit-log entry is about; stored as the
 * activity's log name and used to filter the Log aktivitas page.
 */
enum ActivityCategory: string
{
    use EnumValues;

    case Transaksi = 'transaksi';
    case Produk = 'produk';
    case Kontak = 'kontak';
    case Rekening = 'rekening';
    case Akun = 'akun';

    public function label(): string
    {
        return match ($this) {
            self::Transaksi => 'Transaksi',
            self::Produk => 'Produk',
            self::Kontak => 'Kontak',
            self::Rekening => 'Rekening',
            self::Akun => 'Akun',
        };
    }
}
