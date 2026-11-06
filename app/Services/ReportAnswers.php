<?php

namespace App\Services;

use App\Models\Business;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Answers about the books in plain words, read from the journal, never
 * written by the model (PRD US-14). The model only recognises the
 * question; Tanya AI on the web and the bot on WhatsApp say the same.
 */
class ReportAnswers
{
    /** What each step of the reconciliation means for cash, in the owner's words. */
    private const STEPS = [
        'inventory' => ['Uang jadi stok (belanja bahan/barang)', 'Stok terjual tanpa belanja lagi'],
        'receivables' => ['Penjualan yang belum dibayar pelanggan', 'Pelanggan membayar tagihan lama'],
        'equipment' => ['Beli peralatan', 'Peralatan berkurang'],
        'payables' => ['Bayar utang ke supplier', 'Belanja yang belum dibayar ke supplier'],
        'customerDeposits' => ['Uang muka pesanan terpakai', 'Uang muka pesanan masuk'],
        'deposits' => ['', 'Setoran modal dari uang pribadi'],
        'drawings' => ['Diambil untuk pribadi', ''],
    ];

    public function __construct(private Reports $reports) {}

    /**
     * Asks why profit and cash differ: names both, as a question. Read
     * without the model, so the answer never waits on it.
     */
    public static function asksProfitVsCash(string $message): bool
    {
        $text = mb_strtolower($message);

        return preg_match('/\b(laba|untung)\b/u', $text) === 1
            && preg_match('/\b(kas|uang|duit|saldo)\b/u', $text) === 1
            && preg_match('/\b(kenapa|mengapa|kok|beda|bedanya|tapi|tetapi|padahal|selisih)\b|\?/u', $text) === 1;
    }

    /**
     * "Kenapa laba naik tapi kas turun?": this month's profit, the change in
     * cash, and each reason they differ (Reports::cashReconciliation).
     */
    public function profitVsCash(Business $business): string
    {
        $from = CarbonImmutable::now()->startOfMonth();
        $recon = $this->reports->cashReconciliation($business, $from);
        $change = $recon['cashChange'];
        $lines = [];

        foreach ($recon['steps'] as $step) {
            [$out, $in] = self::STEPS[$step['key']] ?? [$step['key'], $step['key']];
            $label = $step['amount'] < 0 ? $out : $in;

            if ($label !== '') {
                $lines[] = '• '.$label.': '.($step['amount'] < 0 ? '−' : '+').Format::rupiah(abs($step['amount']));
            }
        }

        $profit = $recon['netProfit'] < 0 ? 'rugi '.Format::rupiah(-$recon['netProfit']) : 'laba bersih '.Format::rupiah($recon['netProfit']);
        $head = "Bulan ini {$profit}, sedangkan uang usaha "
            .($change === 0 ? 'tetap' : ($change > 0 ? 'naik ' : 'turun ').Format::rupiah(abs($change))).'.';

        return $lines === []
            ? "{$head} Tidak ada selisih: semua laba masuk sebagai uang."
            : "{$head}\nBedanya karena:\n".implode("\n", $lines)."\nLaba dihitung dari penjualan dikurangi modal barang dan biaya; kas dari uang yang benar-benar masuk dan keluar.";
    }
}
