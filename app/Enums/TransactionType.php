<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * The five things an owner records (PRD M2).
 */
enum TransactionType: string
{
    use EnumValues;

    case Penjualan = 'penjualan';
    case Pembelian = 'pembelian';
    case Biaya = 'biaya';
    case UtangPiutang = 'utang_piutang';
    case Modal = 'modal';

    public function label(): string
    {
        return match ($this) {
            self::Penjualan => 'Penjualan',
            self::Pembelian => 'Belanja',
            self::Biaya => 'Biaya',
            self::UtangPiutang => 'Utang-piutang',
            self::Modal => 'Modal',
        };
    }

    /**
     * Toast title after saving.
     */
    public function savedMessage(): string
    {
        return match ($this) {
            self::Penjualan => 'Penjualan tersimpan.',
            self::Pembelian => 'Belanja tersimpan.',
            self::Biaya => 'Biaya tersimpan.',
            self::UtangPiutang => 'Pembayaran tersimpan.',
            self::Modal => 'Catatan modal tersimpan.',
        };
    }

    /**
     * Payment methods this type accepts (PRD US-05: uang pribadi only for purchases and expenses).
     *
     * @return list<PaymentMethod>
     */
    public function methods(): array
    {
        return match ($this) {
            self::Penjualan => [PaymentMethod::Tunai, PaymentMethod::NonTunai, PaymentMethod::Kredit],
            self::Pembelian, self::Biaya => PaymentMethod::cases(),
            self::UtangPiutang, self::Modal => [PaymentMethod::Tunai, PaymentMethod::NonTunai],
        };
    }

    /**
     * Sub-kinds of this type; a sale has none.
     *
     * @return list<TransactionKind>
     */
    public function kinds(): array
    {
        return array_values(array_filter(TransactionKind::cases(), fn (TransactionKind $kind) => $kind->type() === $this));
    }
}
