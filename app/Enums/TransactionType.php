<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * What an owner records: the five kinds of PRD M2, Pindah dana between rekening,
 * and the Saldo awal that opens the books (PRD M0).
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
    case SaldoAwal = 'saldo_awal';

    public function label(): string
    {
        return match ($this) {
            self::Penjualan => 'Penjualan',
            self::Pembelian => 'Belanja',
            self::Biaya => 'Biaya',
            self::UtangPiutang => 'Utang-piutang',
            self::Modal => 'Modal',
            self::PindahDana => 'Pindah dana',
            self::SaldoAwal => 'Saldo awal',
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
            self::SaldoAwal => 'Saldo awal tersimpan.',
        };
    }

    /**
     * Payment methods the owner picks from for this type (PRD US-05: uang pribadi
     * only for purchases and expenses); none when the record decides it.
     *
     * @return list<PaymentMethod>
     */
    public function methods(): array
    {
        return match ($this) {
            self::Penjualan => [PaymentMethod::Tunai, PaymentMethod::NonTunai, PaymentMethod::Kredit],
            self::Pembelian, self::Biaya => PaymentMethod::cases(),
            self::UtangPiutang, self::Modal => [PaymentMethod::Tunai, PaymentMethod::NonTunai],
            // Never chosen: a transfer is paid the way its source rekening holds money, an opening balance by its kind.
            self::PindahDana, self::SaldoAwal => [],
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
