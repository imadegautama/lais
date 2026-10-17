<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Sub-kind of a transaction: what was bought, which expense, which way a debt
 * was settled, or which way capital moved.
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
    case Setor = 'setor';
    case Prive = 'prive';

    public function type(): TransactionType
    {
        return match ($this) {
            self::Barang, self::Peralatan => TransactionType::Pembelian,
            self::TerimaPiutang, self::BayarUtang => TransactionType::UtangPiutang,
            self::Setor, self::Prive => TransactionType::Modal,
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
            self::Setor => 'Setor modal',
            self::Prive => 'Ambil untuk pribadi',
        };
    }
}
