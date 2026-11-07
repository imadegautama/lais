<?php

namespace App\Enums;

use App\Concerns\EnumValues;

enum ContactType: string
{
    use EnumValues;

    case Pelanggan = 'pelanggan';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Pelanggan => 'Pelanggan',
            self::Supplier => 'Supplier',
        };
    }

    /**
     * Who is on the other side: customers buy, pay their debts, and owe at the start; suppliers everything else.
     */
    public static function for(TransactionType $type, ?TransactionKind $kind): self
    {
        return $type === TransactionType::Penjualan || in_array($kind, [TransactionKind::TerimaPiutang, TransactionKind::SaldoPiutang, TransactionKind::UangMuka], true)
            ? self::Pelanggan
            : self::Supplier;
    }
}
