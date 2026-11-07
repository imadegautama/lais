<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Enums\ItemKind;
use App\Enums\Period;
use App\Enums\ReportMetric;
use App\Enums\TransactionKind;
use App\Models\Business;
use App\Models\Item;
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
        'depreciation' => ['', 'Penyusutan peralatan, biaya tanpa uang keluar'],
        'payables' => ['Bayar utang ke supplier', 'Belanja yang belum dibayar ke supplier'],
        'customerDeposits' => ['Uang muka pesanan terpakai', 'Uang muka pesanan masuk'],
        'deposits' => ['', 'Setoran modal dari uang pribadi'],
        'drawings' => ['Diambil untuk pribadi', ''],
    ];

    /**
     * What one step of the reconciliation did to cash, in the owner's words.
     */
    public static function stepLabel(string $key, int $amount): string
    {
        [$out, $in] = self::STEPS[$key] ?? [$key, $key];

        return $amount < 0 ? $out : $in;
    }

    /** Target margins offered when the owner names none. */
    private const MARGINS = [30, 40, 50];

    public function __construct(private Reports $reports, private Ledger $ledger, private Debts $debts) {}

    /**
     * One number the owner asked for, and the page that shows it. Sales,
     * profit, and costs are for the period; money on hand, piutang, and utang
     * are what stands now.
     *
     * @return array{reply: string, link: array{label: string, href: string}}
     */
    public function figure(Business $business, ReportMetric $metric, Period $period): array
    {
        $when = mb_strtolower($period->label());
        $pnl = $this->reports->profitAndLoss($business, $period->start(), $period->end());
        $count = $this->reports->salesCount($business, $period->start(), $period->end());

        $reply = match ($metric) {
            ReportMetric::Penjualan => "Penjualan {$when} ".Format::rupiah($pnl['sales'])." dari {$count} transaksi.",
            ReportMetric::JumlahTransaksi => ucfirst($when)." ada {$count} penjualan, senilai ".Format::rupiah($pnl['sales']).'.',
            ReportMetric::Laba => ($pnl['netProfit'] < 0 ? 'Rugi '.$when.' '.Format::rupiah(-$pnl['netProfit']) : "Laba bersih {$when} ".Format::rupiah($pnl['netProfit'])).".\n"
                .'Penjualan '.Format::rupiah($pnl['sales']).', modal barang (HPP) '.Format::rupiah($pnl['cogs']).', biaya '.Format::rupiah($pnl['expenses'] + $pnl['stockDifference']).'.',
            ReportMetric::Hpp => "HPP {$when} ".Format::rupiah($pnl['cogs']).' dari penjualan '.Format::rupiah($pnl['sales']).', jadi laba kotornya '.Format::rupiah($pnl['grossProfit']).'.',
            ReportMetric::Biaya => $this->expenses($business, $period, $when),
            ReportMetric::Kas => $this->money($business),
            ReportMetric::Piutang => $this->open($business, ContactType::Pelanggan),
            ReportMetric::Utang => $this->open($business, ContactType::Supplier),
        };

        return ['reply' => $reply, 'link' => match ($metric) {
            ReportMetric::Kas => ['label' => 'Buka Kas & rekening', 'href' => route('money-accounts.index', absolute: false)],
            ReportMetric::Piutang => ['label' => 'Buka Utang-piutang', 'href' => route('debts.index', absolute: false)],
            ReportMetric::Utang => ['label' => 'Buka Utang-piutang', 'href' => route('debts.index', ['jenis' => 'utang'], absolute: false)],
            default => ['label' => 'Buka Laba rugi', 'href' => route('reports.profit-loss', ['periode' => $period->value], absolute: false)],
        }];
    }

    /**
     * What one product costs, what it earns now, and the price for a target
     * margin. Every number comes from the catalog, never from the model.
     *
     * @return array{reply: string, link: array{label: string, href: string}}
     */
    public function price(Item $item, ?float $margin): array
    {
        $item->loadMissing('recipeLines.material');
        $link = ['label' => 'Buka produk', 'href' => route('items.edit', $item, absolute: false)];
        $cost = $item->unitCost();

        if ($cost <= 0) {
            return ['reply' => "Biaya {$item->name} belum tercatat, jadi HPP belum bisa dihitung. Lengkapi resep atau catat pembeliannya dulu.", 'link' => $link];
        }

        $lines = [($item->kind === ItemKind::Komposisi ? 'HPP ' : 'Biaya rata-rata ')."{$item->name} ".Format::rupiah($cost)." per {$item->unit}."];

        if ($item->kind === ItemKind::Komposisi) {
            $lines[0] .= ' Ini dari resep, belum termasuk biaya seperti gas dan upah.';
        }

        if (! $item->kind->isSold()) {
            return ['reply' => $lines[0], 'link' => $link];
        }

        if ($item->price) {
            $lines[] = 'Harga jual sekarang '.Format::rupiah($item->price).', margin '.Format::rupiah($item->price - $cost).' ('.Format::decimalInput((float) $item->marginPercent()).'%).';
        }

        if ($margin !== null) {
            $lines[] = 'Supaya margin '.Format::decimalInput($margin).'%, harga jual minimal '.Format::rupiah(Item::priceForMargin($cost, $margin)).'.';
        } else {
            $lines[] = "Saran harga jual:\n".implode("\n", array_map(
                fn (int $target) => "• Margin {$target}%: ".Format::rupiah(Item::priceForMargin($cost, $target)),
                self::MARGINS,
            ));
        }

        return ['reply' => implode("\n", $lines), 'link' => $link];
    }

    private function expenses(Business $business, Period $period, string $when): string
    {
        $kinds = $this->reports->expensesByKind($business, $period->start(), $period->end());

        if ($kinds === []) {
            return "Belum ada biaya {$when}.";
        }

        arsort($kinds);
        $lines = array_map(fn (string $kind, int $amount) => '• '.(TransactionKind::tryFrom($kind)?->label() ?? $kind).': '.Format::rupiah($amount), array_keys($kinds), $kinds);

        return "Biaya {$when} ".Format::rupiah((int) array_sum($kinds)).":\n".implode("\n", $lines);
    }

    private function money(Business $business): string
    {
        $balances = $this->ledger->moneyBalances($business);
        $lines = $business->moneyAccounts()->orderBy('money_accounts.id')->get()
            ->map(fn ($account) => "• {$account->name}: ".Format::rupiah($balances[$account->id] ?? 0));

        return 'Uang usaha sekarang '.Format::rupiah((int) array_sum($balances)).":\n".$lines->implode("\n");
    }

    /**
     * Who still owes the business (Pelanggan), or whom it still owes (Supplier).
     */
    private function open(Business $business, ContactType $type): string
    {
        $word = $type === ContactType::Pelanggan ? 'piutang' : 'utang';
        $rows = $this->debts->summary($business, $type);

        if ($rows === []) {
            return "Tidak ada {$word} yang belum dibayar.";
        }

        $lines = array_map(fn (array $row) => "• {$row['contact']->name}: ".Format::rupiah($row['balance']), array_slice($rows, 0, 5));
        $more = count($rows) > 5 ? "\n• dan ".(count($rows) - 5).' lainnya' : '';

        return ucfirst($word).' sekarang '.Format::rupiah((int) array_sum(array_column($rows, 'balance'))).":\n".implode("\n", $lines).$more;
    }

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
            $label = self::stepLabel($step['key'], $step['amount']);

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
