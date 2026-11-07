<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * The numbers an owner can ask for in a chat (ReportAnswers::figure).
 */
enum ReportMetric: string
{
    use EnumValues;

    case Penjualan = 'penjualan';
    case Laba = 'laba';
    case Hpp = 'hpp';
    case Biaya = 'biaya';
    case Kas = 'kas';
    case Piutang = 'piutang';
    case Utang = 'utang';
    case JumlahTransaksi = 'jumlah_transaksi';
}
