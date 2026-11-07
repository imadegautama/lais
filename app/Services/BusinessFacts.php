<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Enums\ItemKind;
use App\Enums\Period;
use App\Enums\TransactionKind;
use App\Models\Business;
use App\Models\Item;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Everything the owner may ask about the business, worked out from the journal
 * and written as one sheet for the model to read. The model answers in words
 * but never computes: what it may quote is here, and grounded() refuses an
 * answer with a figure that is not. Phone numbers and photos stay out.
 */
class BusinessFacts
{
    /** Assumed not to move with sales, for the break-even point. */
    public const FIXED_COSTS = [TransactionKind::Sewa, TransactionKind::Upah, TransactionKind::ListrikAir];

    private const LISTED = 30;

    public function __construct(private Reports $reports, private Ledger $ledger, private Debts $debts, private Depreciation $depreciation) {}

    public function sheet(Business $business): string
    {
        return implode("\n\n", array_filter([
            $this->position($business),
            $this->month($business, Period::BulanIni, 'BULAN INI (belum penuh)'),
            $this->month($business, Period::BulanLalu, 'BULAN LALU'),
            $this->trend($business),
            $this->busiestDays($business),
            $this->products($business, Period::BulanIni, 'PRODUK TERJUAL BULAN INI'),
            $this->products($business, Period::BulanLalu, 'PRODUK TERJUAL BULAN LALU'),
            $this->catalog($business),
            $this->stock($business),
            $this->openBills($business, ContactType::Pelanggan, 'PIUTANG (uang pelanggan yang belum dibayar)'),
            $this->openBills($business, ContactType::Supplier, 'UTANG KE SUPPLIER'),
            $this->equipment($business),
            $this->health($business),
            $this->breakEvenSection($business),
        ]));
    }

    /**
     * Sales needed to cover the fixed costs, or null when no level of sales
     * can: nothing sold to measure by, or each sale loses money.
     */
    public static function breakEven(int $sales, int $cogs, int $variableCosts, int $fixedCosts): ?int
    {
        $margin = $sales - $cogs - $variableCosts;

        return $sales <= 0 || $margin <= 0 ? null : (int) ceil($fixedCosts * $sales / $margin);
    }

    /**
     * Whether every figure of the answer is on the sheet. Rupiah, percentages,
     * decimals, and numbers of three digits or more count; "30 hari" does not.
     */
    public static function grounded(string $answer, string $sheet): bool
    {
        return array_diff(self::figures($answer), self::figures($sheet)) === [];
    }

    /**
     * @return list<string>
     */
    private static function figures(string $text): array
    {
        preg_match_all('/\d{1,3}(?:\.\d{3})+(?:,\d+)?%?|\d+,\d+%?|\d+%|\d{3,}/u', $text, $matches);
        $years = fn (string $figure) => preg_match('/^20[2-3]\d$/', $figure) !== 1;

        return array_values(array_filter(array_map(fn (string $figure) => str_replace('.', '', $figure), $matches[0]), $years));
    }

    private function position(Business $business): string
    {
        $today = CarbonImmutable::now();
        $sheet = $this->reports->balanceSheet($business, $today);
        $balances = $this->ledger->moneyBalances($business);
        $accounts = $business->moneyAccounts()->orderBy('money_accounts.id')->get()
            ->map(fn ($account) => "{$account->name} ".Format::rupiah($balances[$account->id] ?? 0))->implode(', ');
        ['assets' => $assets, 'liabilities' => $owed, 'equity' => $equity] = $sheet;

        return implode("\n", [
            'POSISI HARI INI ('.Format::date($today).')',
            'Uang usaha (kas dan rekening) '.Format::rupiah($assets['cash'] + $assets['nonCash']).": {$accounts}",
            'Piutang '.Format::rupiah($assets['receivables']),
            'Nilai stok (persediaan) '.Format::rupiah($assets['inventory']),
            'Peralatan, nilai buku '.Format::rupiah($assets['equipment'] + $assets['accumulatedDepreciation']),
            'Total aset '.Format::rupiah($assets['total']),
            'Utang usaha '.Format::rupiah($owed['payables']).', uang muka pelanggan '.Format::rupiah($owed['customerDeposits']).', total kewajiban '.Format::rupiah($owed['total']),
            'Modal pemilik (aset dikurangi kewajiban) '.Format::rupiah($equity['total']).': modal disetor '.Format::rupiah($equity['capital']).', diambil untuk pribadi '.Format::rupiah(abs($equity['drawings'])).', laba terkumpul '.Format::rupiah($equity['profit']),
        ]);
    }

    private function month(Business $business, Period $period, string $title): string
    {
        [$from, $before] = [$period->start(), $period->end()];
        $pnl = $this->reports->profitAndLoss($business, $from, $before);
        $earlierFrom = $from->subMonthNoOverflow();
        $earlier = $this->reports->profitAndLoss($business, $earlierFrom, $from);
        $kinds = $this->reports->expensesByKind($business, $from, $before);
        arsort($kinds);
        $costs = implode(', ', array_map(fn (string $kind, int $amount) => (TransactionKind::tryFrom($kind)?->label() ?? $kind).' '.Format::rupiah($amount), array_keys($kinds), $kinds));
        $change = function (string $word, int $now, int $past) {
            $difference = $now - $past;
            $percent = $past === 0 ? '' : ' ('.(int) round(abs($difference) / abs($past) * 100).'%)';

            return "{$word} ".($difference < 0 ? 'turun ' : 'naik ').Format::rupiah(abs($difference)).$percent;
        };

        return implode("\n", array_filter([
            $title,
            'Penjualan '.Format::rupiah($pnl['sales']).' dari '.$this->reports->salesCount($business, $from, $before).' transaksi',
            'HPP '.Format::rupiah($pnl['cogs']).', laba kotor '.Format::rupiah($pnl['grossProfit']),
            'Biaya operasional '.Format::rupiah($pnl['expenses']).($costs === '' ? '' : ": {$costs}"),
            $pnl['depreciation'] === 0 ? '' : 'Penyusutan '.Format::rupiah($pnl['depreciation']),
            $pnl['stockDifference'] === 0 ? '' : 'Selisih persediaan '.Format::rupiah($pnl['stockDifference']),
            ($pnl['netProfit'] < 0 ? 'Rugi bersih ' : 'Laba bersih ').Format::rupiah(abs($pnl['netProfit'])),
            'Diambil untuk pribadi '.Format::rupiah($this->reports->drawings($business, $from, $before)),
            'Dibanding bulan sebelumnya: '.$change('penjualan', $pnl['sales'], $earlier['sales']).', '.$change('laba', $pnl['netProfit'], $earlier['netProfit']),
        ]));
    }

    private function trend(Business $business): string
    {
        $lines = array_map(fn (array $month) => "{$month['label']} ({$month['month']}): penjualan ".Format::rupiah($month['sales']).', laba bersih '.Format::rupiah($month['netProfit']), $this->reports->monthlyTrend($business));

        return "ENAM BULAN TERAKHIR\n".implode("\n", $lines);
    }

    private function busiestDays(Business $business): string
    {
        $days = array_filter($this->reports->salesByDay($business), fn (array $day) => $day['sales'] > 0);

        if ($days === []) {
            return '';
        }

        usort($days, fn (array $a, array $b) => $b['sales'] <=> $a['sales']);
        $lines = array_map(fn (array $day) => Format::date(CarbonImmutable::parse($day['date'])).' '.Format::rupiah($day['sales']), array_slice($days, 0, 3));

        return 'HARI PALING RAMAI 30 HARI TERAKHIR: '.implode('; ', $lines);
    }

    private function products(Business $business, Period $period, string $title): string
    {
        $rows = $this->reports->productPerformance($business, $period->start(), $period->end());

        if ($rows === []) {
            return '';
        }

        $byQty = $rows;
        usort($byQty, fn (array $a, array $b) => $b['qty'] <=> $a['qty']);
        $lines = array_map(fn (array $row) => "{$row['name']}: terjual ".Format::qty($row['qty'])." {$row['unit']}, penjualan ".Format::rupiah($row['sales']).', laba kotor '.Format::rupiah($row['profit']).($row['margin'] === null ? '' : ', margin '.Format::decimalInput($row['margin']).'%'), array_slice($rows, 0, self::LISTED));

        return "{$title} (paling untung {$rows[0]['name']}, paling banyak terjual {$byQty[0]['name']})\n".implode("\n", $lines);
    }

    private function catalog(Business $business): string
    {
        $items = $business->items()->with('recipeLines.material')->where('items.kind', '!=', ItemKind::Bahan)->orderBy('items.name')->limit(self::LISTED)->get();

        if ($items->isEmpty()) {
            return '';
        }

        return "HARGA JUAL DAN MODAL PER SATUAN\n".$items->map(function (Item $item) {
            $cost = (int) round($item->unitCost());

            return "{$item->name}: harga jual ".Format::rupiah((int) $item->price)." per {$item->unit}".($cost > 0 ? ', modal '.Format::rupiah($cost).', untung per satuan '.Format::rupiah((int) $item->price - $cost) : ', modal belum tercatat');
        })->implode("\n");
    }

    private function stock(Business $business): string
    {
        $items = $business->items()->stocked()->orderBy('items.name')->limit(100)->get();

        if ($items->isEmpty()) {
            return '';
        }

        $alerts = array_map(fn (array $alert) => $alert['name'].($alert['daysLeft'] === null ? '' : ' (perkiraan habis '.Format::decimalInput($alert['daysLeft']).' hari lagi)'), $this->reports->stockAlerts($business));
        $slow = array_column($this->reports->slowStock($business), 'name');

        return implode("\n", array_filter([
            'STOK SEKARANG',
            $items->map(fn (Item $item) => "{$item->name}: ".Format::qty((float) $item->stock)." {$item->unit}, nilai ".Format::rupiah($item->stock_value))->implode("\n"),
            'Stok menipis: '.($alerts === [] ? 'tidak ada' : implode(', ', $alerts)),
            'Tidak terjual 30 hari: '.($slow === [] ? 'tidak ada' : implode(', ', $slow)),
        ]));
    }

    private function openBills(Business $business, ContactType $type, string $title): string
    {
        $rows = $this->debts->summary($business, $type);

        if ($rows === []) {
            return "{$title}: tidak ada";
        }

        $lines = array_map(fn (array $row) => "{$row['contact']->name}: ".Format::rupiah($row['balance']).', sejak '.Format::date($row['since']), array_slice($rows, 0, self::LISTED));

        return "{$title}: total ".Format::rupiah((int) array_sum(array_column($rows, 'balance')))."\n".implode("\n", $lines);
    }

    private function equipment(Business $business): string
    {
        $assets = $this->depreciation->book($business);

        if ($assets === []) {
            return '';
        }

        return "PERALATAN\n".implode("\n", array_map(fn (array $asset) => "{$asset['name']}: harga ".Format::rupiah($asset['cost']).', sudah disusutkan '.Format::rupiah($asset['accumulated']).', nilai buku '.Format::rupiah($asset['bookValue']), $assets));
    }

    private function health(Business $business): string
    {
        return "CEK KESEHATAN USAHA BULAN INI\n".implode("\n", array_map(fn (array $check) => "{$check['label']}: ".($check['passed'] ? 'ya' : 'belum').". {$check['detail']}", $this->reports->healthChecks($business)));
    }

    /**
     * From the last full month, with its assumption spelled out so the answer
     * can repeat it: rent, wages, utilities, and depreciation stay the same
     * whatever is sold, and the other costs move with sales.
     */
    private function breakEvenSection(Business $business): string
    {
        [$from, $before] = [Period::BulanLalu->start(), Period::BulanLalu->end()];
        $pnl = $this->reports->profitAndLoss($business, $from, $before);
        $kinds = $this->reports->expensesByKind($business, $from, $before);
        $named = array_sum(array_map(fn (TransactionKind $kind) => $kinds[$kind->value] ?? 0, self::FIXED_COSTS));
        $fixed = (int) $named + $pnl['depreciation'];
        $variable = $pnl['expenses'] - (int) $named + $pnl['otherExpenses'] + $pnl['stockDifference'];
        $point = self::breakEven($pnl['sales'], $pnl['cogs'], $variable, $fixed);
        $title = 'BEP (TITIK IMPAS) DARI ANGKA BULAN LALU. Anggapan: biaya tetap adalah sewa, upah, listrik dan air, dan penyusutan; biaya lain ikut naik turun bersama penjualan.';

        if ($point === null || $fixed === 0) {
            return "{$title}\nBEP belum bisa dihitung: ".($pnl['sales'] <= 0 ? 'bulan lalu belum ada penjualan.' : ($fixed === 0 ? 'bulan lalu belum ada biaya tetap tercatat.' : 'penjualan bulan lalu belum menutup modal barang dan biaya tidak tetap.'));
        }

        $gap = $pnl['sales'] - $point;
        $lines = array_map(function (array $row) use ($fixed) {
            $perUnit = $row['qty'] > 0 ? $row['profit'] / $row['qty'] : 0;

            return $perUnit <= 0 ? '' : "{$row['name']}: ".Format::qty((float) ceil($fixed / $perUnit))." {$row['unit']} sebulan, kalau produk ini menutup semua biaya tetap sendirian (untung rata-rata ".Format::rupiah((int) round($perUnit))." per {$row['unit']})";
        }, array_slice($this->reports->productPerformance($business, $from, $before), 0, 10));

        return implode("\n", array_filter([
            $title,
            'Biaya tetap '.Format::rupiah($fixed).' sebulan, biaya tidak tetap '.Format::rupiah($variable),
            'BEP penjualan '.Format::rupiah($point).' sebulan',
            'Penjualan bulan lalu '.Format::rupiah($pnl['sales']).($gap >= 0 ? ', di atas BEP sebesar ' : ', di bawah BEP sebesar ').Format::rupiah(abs($gap)),
            ...$lines,
        ]));
    }
}
