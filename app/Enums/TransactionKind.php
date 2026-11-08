<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use App\Models\Account;

/**
 * Sub-kind of a transaction: what was bought, which expense, which way a debt
 * was settled, which way capital moved, or what an opening balance holds.
 */
enum TransactionKind: string
{
    use EnumValues;

    case Barang = 'barang';
    case Peralatan = 'peralatan';
    case ListrikAir = 'listrik_air';
    case GasBbm = 'gas_bbm';
    case Sewa = 'sewa';
    case Upah = 'upah';
    case Transport = 'transport';
    case Pemasaran = 'pemasaran';
    case Lain = 'lain';
    case TerimaPiutang = 'terima_piutang';
    case BayarUtang = 'bayar_utang';
    // Paid on an order before it is done (PRD §4.4: Kas / Uang Muka Pesanan); recorded only from the order.
    case UangMuka = 'uang_muka';
    case Setor = 'setor';
    case Prive = 'prive';
    case SaldoKas = 'saldo_kas';
    case SaldoPeralatan = 'saldo_peralatan';
    case SaldoUtang = 'saldo_utang';
    case SaldoPiutang = 'saldo_piutang';

    public function type(): TransactionType
    {
        return match ($this) {
            self::Barang, self::Peralatan => TransactionType::Pembelian,
            self::TerimaPiutang, self::BayarUtang, self::UangMuka => TransactionType::UtangPiutang,
            self::Setor, self::Prive => TransactionType::Modal,
            self::SaldoKas, self::SaldoPeralatan, self::SaldoUtang, self::SaldoPiutang => TransactionType::SaldoAwal,
            default => TransactionType::Biaya,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Barang => 'Bahan / barang dagang',
            self::Peralatan => 'Peralatan',
            self::ListrikAir => 'Listrik & air',
            self::GasBbm => 'Gas & BBM',
            self::Sewa => 'Sewa',
            self::Upah => 'Upah karyawan',
            self::Transport => 'Transport & ongkir',
            self::Pemasaran => 'Pemasaran',
            self::Lain => 'Lain-lain',
            self::TerimaPiutang => 'Terima bayaran dari pelanggan',
            self::BayarUtang => 'Bayar utang ke supplier',
            self::UangMuka => 'Uang muka pesanan',
            self::Setor => 'Setor modal',
            self::Prive => 'Ambil untuk pribadi',
            self::SaldoKas => 'Kas & rekening',
            self::SaldoPeralatan => 'Peralatan',
            self::SaldoUtang => 'Utang ke supplier',
            self::SaldoPiutang => 'Piutang pelanggan',
        };
    }

    /**
     * The fixed expense account a biaya of this kind posts to, unless the
     * owner picks an account of their own. Null for kinds that are not biaya.
     */
    public function expenseAccount(): ?string
    {
        return match ($this) {
            self::ListrikAir => Account::UTILITIES,
            self::GasBbm => Account::FUEL,
            self::Sewa => Account::RENT,
            self::Upah => Account::WAGES,
            self::Transport => Account::TRANSPORT,
            self::Pemasaran => Account::MARKETING,
            self::Lain => Account::EXPENSES,
            default => null,
        };
    }

    /**
     * How an opening balance is "paid": cash in its rekening, equipment the
     * owner brought in, or a debt still open on either side. Never chosen.
     */
    public function openingMethod(): ?PaymentMethod
    {
        return match ($this) {
            self::SaldoPeralatan => PaymentMethod::Pribadi,
            self::SaldoUtang, self::SaldoPiutang => PaymentMethod::Kredit,
            // Kas follows its rekening; the other kinds are not opening balances.
            default => null,
        };
    }
}
