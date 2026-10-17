<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Example dataset for "Warung Bu Rina", used while the frontend is built
 * ahead of the real ledger. Every page that renders it shows a visible
 * "Data contoh" badge. Replaced by real queries in backend milestone M1.
 *
 * Numbers are derived from generated transactions (seeded, so they are
 * stable), which keeps profit, cash, and stock consistent with each other.
 */
final class DemoData
{
    private const TZ = 'Asia/Jakarta';

    private const PRODUCTS = [
        'nasgor' => ['name' => 'Nasi Goreng', 'price' => 15000, 'cost' => 7200, 'daily' => [12, 25], 'recipe' => ['beras' => 0.15, 'telur' => 1, 'minyak' => 0.02]],
        'miegor' => ['name' => 'Mie Goreng', 'price' => 14000, 'cost' => 6300, 'daily' => [6, 14], 'recipe' => ['mie' => 1, 'telur' => 1, 'minyak' => 0.02]],
        'esteh' => ['name' => 'Es Teh', 'price' => 5000, 'cost' => 1200, 'daily' => [15, 30], 'recipe' => ['teh' => 1, 'gula' => 0.03]],
        'air' => ['name' => 'Air Mineral 600 ml', 'price' => 4000, 'cost' => 2500, 'daily' => [4, 10], 'recipe' => ['air' => 1]],
        'kerupuk' => ['name' => 'Kerupuk', 'price' => 2000, 'cost' => 1000, 'daily' => [8, 16], 'recipe' => ['kerupuk' => 1]],
    ];

    private const MATERIALS = [
        'beras' => ['name' => 'Beras', 'unit' => 'kg', 'stock' => 9, 'minimum' => 5],
        'telur' => ['name' => 'Telur', 'unit' => 'butir', 'stock' => 40, 'minimum' => 30],
        'minyak' => ['name' => 'Minyak goreng', 'unit' => 'liter', 'stock' => 4, 'minimum' => 2],
        'mie' => ['name' => 'Mie telur', 'unit' => 'bungkus', 'stock' => 45, 'minimum' => 15],
        'teh' => ['name' => 'Teh celup', 'unit' => 'kantong', 'stock' => 90, 'minimum' => 50],
        'gula' => ['name' => 'Gula pasir', 'unit' => 'kg', 'stock' => 2.5, 'minimum' => 1],
        'air' => ['name' => 'Air Mineral 600 ml', 'unit' => 'botol', 'stock' => 9, 'minimum' => 12],
        'kerupuk' => ['name' => 'Kerupuk', 'unit' => 'bungkus', 'stock' => 80, 'minimum' => 20],
    ];

    private const OPENING = ['onHand' => 1_800_000, 'nonCash' => 1_200_000, 'inventory' => 1_500_000, 'equipment' => 4_000_000];

    private const PERIODS = [
        'hari_ini' => ['label' => 'Hari ini', 'comparison' => 'vs kemarin di jam yang sama'],
        'minggu_ini' => ['label' => 'Minggu ini', 'comparison' => 'vs minggu lalu'],
        'bulan_ini' => ['label' => 'Bulan ini', 'comparison' => 'vs bulan lalu'],
    ];

    private CarbonImmutable $now;

    /** @var list<array<string, mixed>> */
    private array $transactions = [];

    public function __construct(?CarbonImmutable $now = null)
    {
        $this->now = $now ?? CarbonImmutable::now(self::TZ);
        $this->generate();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $periodKey = 'hari_ini', bool $empty = false): array
    {
        $periodKey = array_key_exists($periodKey, self::PERIODS) ? $periodKey : 'hari_ini';
        [$from, $to, $prevFrom, $prevTo] = $this->periodRange($periodKey);

        $base = [
            'isDemo' => true,
            'isEmpty' => $empty,
            'business' => ['name' => 'Warung Bu Rina', 'ownerName' => 'Bu Rina'],
            'period' => [
                'key' => $periodKey,
                'label' => self::PERIODS[$periodKey]['label'],
                'comparisonLabel' => self::PERIODS[$periodKey]['comparison'],
            ],
            'periods' => collect(self::PERIODS)->map(fn ($p, $key) => ['key' => $key, 'label' => $p['label']])->values()->all(),
        ];

        if ($empty) {
            return $base + $this->emptyDashboard();
        }

        $current = $this->totals($from, $to);
        $previous = $this->totals($prevFrom, $prevTo);
        $month = $this->totals($this->now->startOfMonth(), $this->now);
        $cash = $this->cashAt($this->now);

        return $base + [
            'summary' => [
                'netProfit' => ['value' => $current['netProfit'], 'previous' => $previous['netProfit']],
                'sales' => ['value' => $current['sales'], 'previous' => $previous['sales'], 'count' => $current['salesCount']],
                'cash' => ['total' => $cash['onHand'] + $cash['nonCash'], 'onHand' => $cash['onHand'], 'nonCash' => $cash['nonCash']],
                'personalDraw' => ['value' => $current['draws'], 'previous' => $previous['draws']],
            ],
            'actions' => $this->actions(),
            'wallets' => [
                'businessCash' => $cash['onHand'] + $cash['nonCash'],
                'drawnThisMonth' => $month['draws'],
                'netProfitThisMonth' => $month['netProfit'],
                'equityOpening' => $this->equityAt($this->now->startOfMonth()),
                'equityNow' => $this->equityAt($this->now),
            ],
            'health' => $this->health($month, $cash),
            'stockAlerts' => $this->stockAlerts(),
            'salesChart' => $this->salesChart(),
            'recentTransactions' => $this->recentTransactions(8),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactions(int $limit): array
    {
        return collect($this->transactions)
            ->filter(fn ($t) => $t['at'] <= $this->now)
            ->sortByDesc(fn ($t) => $t['at']->getTimestamp())
            ->take($limit)
            ->map(fn ($t) => [
                'id' => $t['id'],
                'occurredAt' => $t['at']->toIso8601String(),
                'type' => $t['type'],
                'description' => $t['description'],
                'method' => $t['method'],
                'source' => $t['source'],
                'amount' => $t['amount'],
                'direction' => $t['direction'],
            ])
            ->values()
            ->all();
    }

    private function generate(): void
    {
        mt_srand(2026);

        $start = $this->now->subMonthNoOverflow()->startOfMonth();
        $days = (int) $start->diffInDays($this->now->startOfDay()) + 1;
        $sequence = 1;

        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i);
            $weekend = $day->isWeekend() ? 1.3 : 1.0;

            // Ingredients bought every other morning, sized to what was sold.
            if ($i % 2 === 0) {
                $method = $i % 4 === 0 ? 'non_tunai' : 'tunai';
                $this->push($sequence, $day->setTime(6, 30), 'pembelian', 'Belanja bahan di pasar', $method, 'web', 520_000 + mt_rand(-6, 6) * 10_000, 'out');
            }

            $pool = [];
            foreach (self::PRODUCTS as $key => $product) {
                $units = (int) round(mt_rand(...$product['daily']) * $weekend);
                $pool = array_merge($pool, array_fill(0, $units, $key));
            }
            shuffle($pool);

            foreach (array_chunk($pool, 3) as $chunk) {
                $at = $day->setTime(mt_rand(7, 20), mt_rand(0, 59));
                $lines = array_count_values($chunk);
                $total = 0;
                $cost = 0;
                $names = [];
                foreach ($lines as $key => $qty) {
                    $total += self::PRODUCTS[$key]['price'] * $qty;
                    $cost += self::PRODUCTS[$key]['cost'] * $qty;
                    $names[] = $qty.' '.self::PRODUCTS[$key]['name'];
                }
                $roll = mt_rand(1, 100);
                $method = $roll <= 60 ? 'tunai' : 'non_tunai';
                $source = $roll % 7 === 0 ? 'toko' : ($roll % 3 === 0 ? 'web' : 'wa');

                $this->push($sequence, $at, 'penjualan', implode(', ', $names), $method, $source, $total, 'in', $cost, $lines);
            }

            $this->push($sequence, $day->setTime(21, 0), 'biaya', 'Upah karyawan harian', 'tunai', 'web', 75_000, 'out');

            if ($i % 4 === 1) {
                $this->push($sequence, $day->setTime(9, 15), 'biaya', 'Isi ulang gas 3 kg', 'tunai', 'wa', 25_000, 'out');
            }

            if ($day->day === 5) {
                $this->push($sequence, $day->setTime(10, 0), 'biaya', 'Token listrik', 'non_tunai', 'web', 350_000, 'out');
            }

            if ($i % 2 === 1) {
                $this->push($sequence, $day->setTime(20, 10), 'modal', 'Ambil untuk keperluan rumah', 'tunai', 'wa', 150_000, 'out', draw: true);
            }
        }

        // Explicit records that the action panel refers to.
        $this->push($sequence, $this->now->subDays(9)->setTime(12, 40), 'penjualan', 'Pak Budi: 3 Nasi Goreng (belum bayar)', 'kredit', 'wa', 45_000, 'none', 21_600, ['nasgor' => 3]);
        $this->push($sequence, $this->now->subDays(5)->setTime(8, 0), 'pembelian', 'Air mineral & kerupuk dari Toko Sumber Rejeki (belum dibayar)', 'kredit', 'web', 450_000, 'none');
    }

    /**
     * @param  array<string, int>  $lines
     */
    private function push(int &$sequence, CarbonImmutable $at, string $type, string $description, string $method, string $source, int $amount, string $direction, int $cost = 0, array $lines = [], bool $draw = false): void
    {
        if ($at > $this->now) {
            return;
        }

        $this->transactions[] = [
            'id' => 'TX'.str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT),
            'at' => $at,
            'type' => $type,
            'description' => $description,
            'method' => $method,
            'source' => $source,
            'amount' => $amount,
            'direction' => $direction,
            'cost' => $cost,
            'lines' => $lines,
            'draw' => $draw,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function periodRange(string $key): array
    {
        $now = $this->now;

        return match ($key) {
            'minggu_ini' => [$now->startOfWeek(), $now, $now->subWeek()->startOfWeek(), $now->subWeek()],
            'bulan_ini' => [$now->startOfMonth(), $now, $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()],
            default => [$now->startOfDay(), $now, $now->subDay()->startOfDay(), $now->subDay()],
        };
    }

    /**
     * @return array{sales: int, salesCount: int, netProfit: int, draws: int, outflow: int}
     */
    private function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = collect($this->transactions)->filter(fn ($t) => $t['at'] >= $from && $t['at'] <= $to);
        $sales = $rows->where('type', 'penjualan');
        $expenses = $rows->where('type', 'biaya')->sum('amount');

        return [
            'sales' => (int) $sales->sum('amount'),
            'salesCount' => $sales->count(),
            'netProfit' => (int) ($sales->sum('amount') - $sales->sum('cost') - $expenses),
            'draws' => (int) $rows->where('draw', true)->sum('amount'),
            'outflow' => (int) ($expenses + $rows->where('type', 'pembelian')->sum('amount')),
        ];
    }

    /**
     * @return array{onHand: int, nonCash: int}
     */
    private function cashAt(CarbonImmutable $at): array
    {
        $cash = ['onHand' => self::OPENING['onHand'], 'nonCash' => self::OPENING['nonCash']];

        foreach ($this->transactions as $t) {
            if ($t['at'] > $at || $t['method'] === 'kredit') {
                continue;
            }

            $sign = $t['direction'] === 'in' ? 1 : -1;
            $cash[$t['method'] === 'non_tunai' ? 'nonCash' : 'onHand'] += $sign * $t['amount'];
        }

        return $cash;
    }

    private function equityAt(CarbonImmutable $at): int
    {
        $opening = array_sum(self::OPENING);
        $start = collect($this->transactions)->min('at');
        $totals = $this->totals($start, $at);

        return $opening + $totals['netProfit'] - $totals['draws'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockAlerts(): array
    {
        $usage = array_fill_keys(array_keys(self::MATERIALS), 0.0);
        $weekAgo = $this->now->subDays(7);

        foreach ($this->transactions as $t) {
            if ($t['type'] !== 'penjualan' || $t['at'] < $weekAgo) {
                continue;
            }
            foreach ($t['lines'] as $product => $qty) {
                foreach (self::PRODUCTS[$product]['recipe'] as $material => $perUnit) {
                    $usage[$material] += $perUnit * $qty;
                }
            }
        }

        return collect(self::MATERIALS)
            ->map(function ($m, $key) use ($usage) {
                $perDay = $usage[$key] / 7;

                return [
                    'id' => $key,
                    'name' => $m['name'],
                    'unit' => $m['unit'],
                    'stock' => $m['stock'],
                    'minimum' => $m['minimum'],
                    'daysLeft' => $perDay > 0 ? round($m['stock'] / $perDay, 1) : null,
                ];
            })
            ->filter(fn ($m) => $m['stock'] < $m['minimum'] || ($m['daysLeft'] !== null && $m['daysLeft'] <= 3))
            ->sortBy('daysLeft')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actions(): array
    {
        // TODO(C9/C3/C11): point targets at the real pages once they exist.
        $soon = ['comingSoon' => true];
        $actions = [
            ['id' => 'order-a012', 'kind' => 'pesanan', 'title' => 'Pesanan A012 dari Sari menunggu konfirmasi', 'detail' => '2 Nasi Goreng, 1 Es Teh · ambil 12.30 · Rp35.000', 'actionLabel' => 'Terima pesanan', 'target' => $soon],
            ['id' => 'order-a013', 'kind' => 'pesanan', 'title' => 'Pesanan A013 dari Dimas menunggu konfirmasi', 'detail' => '4 Mie Goreng · diantar 13.00 · Rp56.000', 'actionLabel' => 'Terima pesanan', 'target' => $soon],
        ];

        foreach ($this->stockAlerts() as $alert) {
            if ($alert['daysLeft'] !== null && $alert['daysLeft'] <= 2) {
                $actions[] = [
                    'id' => 'stock-'.$alert['id'],
                    'kind' => 'stok',
                    'title' => "{$alert['name']} habis ±".str_replace('.', ',', (string) $alert['daysLeft']).' hari lagi',
                    'detail' => 'Sisa '.str_replace('.', ',', (string) $alert['stock'])." {$alert['unit']}, batas minimum {$alert['minimum']} {$alert['unit']}",
                    'actionLabel' => 'Catat belanja',
                    'target' => $soon,
                ];
            }
        }

        $actions[] = ['id' => 'receivable-budi', 'kind' => 'piutang', 'title' => 'Pak Budi belum bayar Rp45.000', 'detail' => 'Sudah 9 hari sejak 3 Nasi Goreng', 'actionLabel' => 'Kirim pengingat', 'target' => $soon];

        return $actions;
    }

    /**
     * @param  array{netProfit: int, draws: int}  $month
     * @param  array{onHand: int, nonCash: int}  $cash
     * @return list<array<string, mixed>>
     */
    private function health(array $month, array $cash): array
    {
        $monthlyOutflow = $this->totals($this->now->subDays(30), $this->now)['outflow'];
        $cashTotal = $cash['onHand'] + $cash['nonCash'];
        $debt = 450_000;
        $equity = $this->equityAt($this->now);
        $rp = fn (int $v) => 'Rp'.number_format($v, 0, ',', '.');

        return [
            ['key' => 'laba', 'label' => 'Usaha untung bulan ini', 'passed' => $month['netProfit'] > 0, 'detail' => 'Laba bersih '.$rp($month['netProfit'])],
            ['key' => 'kas', 'label' => 'Kas cukup untuk sebulan', 'passed' => $cashTotal >= $monthlyOutflow, 'detail' => 'Kas '.$rp($cashTotal).', pengeluaran 30 hari '.$rp($monthlyOutflow)],
            ['key' => 'utang', 'label' => 'Utang di bawah modal', 'passed' => $debt <= $equity, 'detail' => 'Utang '.$rp($debt).', modal '.$rp($equity)],
            ['key' => 'prive', 'label' => 'Ambil pribadi tidak melebihi laba', 'passed' => $month['draws'] <= $month['netProfit'], 'detail' => 'Diambil '.$rp($month['draws']).' dari laba '.$rp($month['netProfit'])],
        ];
    }

    /**
     * @return list<array{date: string, sales: int}>
     */
    private function salesChart(): array
    {
        $byDay = collect($this->transactions)
            ->where('type', 'penjualan')
            ->groupBy(fn ($t) => $t['at']->toDateString())
            ->map(fn ($rows) => (int) $rows->sum('amount'));

        return collect(range(29, 0))
            ->map(fn ($back) => $this->now->subDays($back)->toDateString())
            ->map(fn ($date) => ['date' => $date, 'sales' => $byDay->get($date, 0)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'summary' => [
                'netProfit' => ['value' => 0, 'previous' => null],
                'sales' => ['value' => 0, 'previous' => null, 'count' => 0],
                'cash' => ['total' => 0, 'onHand' => 0, 'nonCash' => 0],
                'personalDraw' => ['value' => 0, 'previous' => null],
            ],
            'actions' => [],
            'wallets' => ['businessCash' => 0, 'drawnThisMonth' => 0, 'netProfitThisMonth' => 0, 'equityOpening' => 0, 'equityNow' => 0],
            'health' => [],
            'stockAlerts' => [],
            'salesChart' => [],
            'recentTransactions' => [],
        ];
    }
}
