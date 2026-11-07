<?php

namespace App\Services;

use App\Support\Format;
use App\Support\ReportRange;

/**
 * The sentence that opens each report: what the numbers say, in the owner's
 * words. Written from templates and the journal's figures, never by a model.
 *
 * @phpstan-type Story array{tone: 'good'|'warning'|'bad'|'neutral', headline: string, detail: string}
 * @phpstan-type Figures array{sales: int, cogs: int, expenses: int, otherExpenses: int, depreciation: int, stockDifference: int, netProfit: int}
 */
class ReportStory
{
    /**
     * @param  Figures  $now  Reports::profitAndLoss
     * @param  Figures  $earlier  the same for the period before
     * @return Story
     */
    public function profit(array $now, array $earlier, ReportRange $range): array
    {
        $when = $range->phrase();
        $net = $now['netProfit'];

        if ($now['sales'] === 0 && $net === 0) {
            return self::story('neutral', "Belum ada penjualan atau biaya {$when}.", 'Laporan ini terisi sendiri dari catatan transaksi.');
        }

        $headline = ucfirst($when).($net < 0 ? ' rugi ' : ' untung ').Format::compact(abs($net)).'.';
        $earlierNet = $earlier['netProfit'];
        $past = ucfirst($range->priorPhrase()).($earlierNet < 0 ? ' rugi ' : ' untung ').Format::compact(abs($earlierNet)).'.';
        $cause = self::cause($now, $earlier);
        $detail = $earlier['sales'] === 0 && $earlierNet === 0 ? 'Belum ada pembanding dari periode sebelumnya.' : trim("{$past} {$cause}");

        return self::story(match (true) {
            $net < 0 => 'bad',
            $net < $earlierNet => 'warning',
            default => 'good',
        }, $headline, $detail);
    }

    /**
     * @param  array{assets: array<string, int>, liabilities: array<string, int>, equity: array<string, int>, balanced: bool}  $position
     * @return Story
     */
    public function position(array $position): array
    {
        $assets = $position['assets']['total'];

        if ($assets <= 0) {
            return self::story('neutral', 'Belum ada aset yang tercatat.', 'Laporan ini terisi setelah pembukuan dibuka.');
        }

        $own = (int) round($position['equity']['total'] / $assets * 100);
        $parts = [
            'kas dan rekening' => $position['assets']['cash'] + $position['assets']['nonCash'],
            'piutang' => $position['assets']['receivables'],
            'persediaan' => $position['assets']['inventory'],
            'peralatan' => $position['assets']['equipment'] + $position['assets']['accumulatedDepreciation'],
        ];
        arsort($parts);

        return self::story($own < 0 ? 'bad' : ($own < 50 ? 'warning' : 'good'),
            "Dari tiap Rp100 aset, Rp{$own} milik pemilik.",
            'Total aset '.Format::compact($assets).', paling banyak di '.array_key_first($parts).'. Yang masih harus dibayar '.Format::compact($position['liabilities']['total']).'.');
    }

    /**
     * @param  array{netProfit: int, steps: list<array{key: string, amount: int}>, cashChange: int, difference: int}  $reconciliation
     * @return Story
     */
    public function profitVsCash(array $reconciliation, ReportRange $range): array
    {
        $net = $reconciliation['netProfit'];
        $change = $reconciliation['cashChange'];
        $steps = $reconciliation['steps'];
        usort($steps, fn (array $a, array $b) => abs($b['amount']) <=> abs($a['amount']));
        $largest = $steps[0] ?? null;
        $headline = ucfirst($range->phrase()).($net < 0 ? ' rugi ' : ' untung ').Format::compact(abs($net)).', sedangkan uang usaha '
            .($change === 0 ? 'tetap' : ($change > 0 ? 'naik ' : 'turun ').Format::compact(abs($change))).'.';

        return self::story($net >= 0 && $change < 0 ? 'warning' : 'neutral', $headline, $largest === null || $largest['amount'] === 0
            ? 'Tidak ada selisih: semua laba masuk sebagai uang.'
            : 'Selisih terbesar: '.mb_strtolower(ReportAnswers::stepLabel($largest['key'], $largest['amount'])).' '.Format::compact(abs($largest['amount'])).'.');
    }

    /**
     * @param  array{opening: int, closing: int, totalIn: int, totalOut: int}  $book  Reports::cashBook
     * @return Story
     */
    public function cash(array $book, ReportRange $range): array
    {
        $change = $book['closing'] - $book['opening'];
        $when = $range->phrase();

        if ($book['totalIn'] === 0 && $book['totalOut'] === 0) {
            return self::story('neutral', "Tidak ada uang masuk atau keluar {$when}.", 'Saldo tetap '.Format::compact($book['closing']).'.');
        }

        return self::story($change < 0 ? 'warning' : 'good',
            'Uang usaha '.($change === 0 ? 'tetap' : ($change > 0 ? 'bertambah ' : 'berkurang ').Format::compact(abs($change)))." {$when}.",
            'Masuk '.Format::compact($book['totalIn']).', keluar '.Format::compact($book['totalOut']).'. Saldo akhir '.Format::compact($book['closing']).'.');
    }

    /**
     * @param  list<array{name: string, profit: int}>  $products  Reports::productPerformance
     * @param  list<array{value: int}>  $slow  Reports::slowStock
     * @return Story
     */
    public function products(array $products, array $slow, ReportRange $range): array
    {
        $when = $range->phrase();
        $still = $slow === [] ? 'Semua stok masih bergerak.' : count($slow).' produk jarang laku menahan '.Format::compact((int) array_sum(array_column($slow, 'value'))).' di stok.';

        if ($products === []) {
            return self::story('neutral', "Belum ada produk terjual {$when}.", $still);
        }

        $total = (int) array_sum(array_column($products, 'profit'));
        $top = $products[0];
        $share = $total > 0 ? (int) round($top['profit'] / $total * 100) : null;

        return self::story($slow === [] ? 'good' : 'warning',
            $share === null ? "{$top['name']} paling untung {$when}." : "{$top['name']} menyumbang {$share}% laba kotor {$when}.",
            $still);
    }

    /**
     * @return Story
     */
    public function trialBalance(int $debit, int $credit): array
    {
        return $debit === $credit
            ? self::story('good', 'Pembukuan seimbang.', 'Total debit dan kredit sama-sama '.Format::rupiah($debit).'. Setiap rupiah yang masuk ke satu akun tercatat keluar dari akun lain.')
            : self::story('bad', 'Pembukuan belum seimbang.', 'Selisihnya '.Format::rupiah(abs($debit - $credit)).'. Cek mutasi di Buku besar.');
    }

    /**
     * @param  list<array{cost: int, accumulated: int, bookValue: int, lifeMonths: int|null}>  $assets  Depreciation::book
     * @return Story
     */
    public function assets(array $assets): array
    {
        if ($assets === []) {
            return self::story('neutral', 'Belum ada peralatan yang tercatat.', 'Peralatan muncul di sini setelah dicatat lewat Belanja.');
        }

        $unset = count(array_filter($assets, fn (array $asset) => $asset['lifeMonths'] === null));
        $cost = (int) array_sum(array_column($assets, 'cost'));
        $used = $cost > 0 ? (int) round(array_sum(array_column($assets, 'accumulated')) / $cost * 100) : 0;

        return self::story($unset > 0 ? 'warning' : 'good',
            'Peralatan bernilai '.Format::compact((int) array_sum(array_column($assets, 'bookValue'))).' sekarang.',
            $unset > 0 ? "{$unset} peralatan belum punya umur pakai, jadi belum disusutkan." : "Sudah disusutkan {$used}% dari harga belinya ".Format::compact($cost).'.');
    }

    /**
     * The opening of Pusat laporan: the profit, then where the money stands.
     *
     * @param  Figures  $now
     * @param  Figures  $earlier
     * @return Story
     */
    public function overview(array $now, array $earlier, int $money, int $owed, ReportRange $range): array
    {
        $story = $this->profit($now, $earlier, $range);
        $story['detail'] = trim($story['detail'].' Uang usaha sekarang '.Format::compact($money).($owed > 0 ? ', dan '.Format::compact($owed).' masih di pelanggan.' : '.'));

        return $story;
    }

    /**
     * What moved the profit most, in the direction it moved.
     *
     * @param  Figures  $now
     * @param  Figures  $earlier
     */
    private static function cause(array $now, array $earlier): string
    {
        $change = $now['netProfit'] - $earlier['netProfit'];

        if ($change === 0) {
            return '';
        }

        $costs = fn (array $report): int => $report['expenses'] + $report['otherExpenses'] + $report['depreciation'] + $report['stockDifference'];
        $effects = [
            'penjualan' => [$now['sales'] - $earlier['sales'], $now['sales'], $earlier['sales']],
            'modal barang' => [$earlier['cogs'] - $now['cogs'], $now['cogs'], $earlier['cogs']],
            'biaya' => [$costs($earlier) - $costs($now), $costs($now), $costs($earlier)],
        ];
        $matching = array_filter($effects, fn (array $effect) => $effect[0] !== 0 && ($effect[0] > 0) === ($change > 0));

        if ($matching === []) {
            return '';
        }

        uasort($matching, fn (array $a, array $b) => abs($b[0]) <=> abs($a[0]));
        $name = (string) array_key_first($matching);
        [, $current, $past] = (array) reset($matching);
        $difference = (int) $current - (int) $past;
        $percent = $past > 0 ? (int) round(abs($difference) / $past * 100) : null;
        // A huge percentage reads as noise, and "turun 100%" would say it fell to nothing when it did not.
        $moved = ($difference > 0 ? 'naik ' : 'turun ').match (true) {
            $percent === null, $percent > 200 => Format::compact(abs($difference)),
            $percent === 100 && $current > 0 => '99%',
            default => "{$percent}%",
        };

        return ($change < 0 ? 'Penyebab terbesar: ' : 'Pendorong terbesar: ')."{$name} {$moved}.";
    }

    /**
     * @param  'good'|'warning'|'bad'|'neutral'  $tone
     * @return Story
     */
    private static function story(string $tone, string $headline, string $detail): array
    {
        return ['tone' => $tone, 'headline' => $headline, 'detail' => $detail];
    }
}
