<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * What an owner records: the five kinds of PRD M2, plus Pindah dana between rekening.
 */
enum TransactionType: string
{
    use EnumValues;

    case Penjualan = 'penjualan';
    case Pembelian = 'pembelian';
    case Biaya = 'biaya';
    case UtangPiutang = 'utang_piutang';
    case Modal = 'modal';
    case PindahDana = 'pindah_dana';

    public function label(): string
    {
        return match ($this) {
            self::Penjualan => 'Penjualan',
            self::Pembelian => 'Belanja',
            self::Biaya => 'Biaya',
            self::UtangPiutang => 'Utang-piutang',
            self::Modal => 'Modal',
            self::PindahDana => 'Pindah dana',
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
            self::PindahDana => 'Pindah dana tersimpan.',
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
            // A transfer's method is its source rekening's kind, never chosen.
            self::UtangPiutang, self::Modal, self::PindahDana => [PaymentMethod::Tunai, PaymentMethod::NonTunai],
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
