<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Example dataset for "Warung Bu Rina", shown only in the living style guide
 * (/design-system) next to a visible "Data demo" badge. Seeded, so stable.
 */
final class DemoData
{
    private const PRODUCTS = [
        'nasgor' => ['name' => 'Nasi Goreng', 'price' => 15000, 'daily' => [12, 25]],
        'miegor' => ['name' => 'Mie Goreng', 'price' => 14000, 'daily' => [6, 14]],
        'esteh' => ['name' => 'Es Teh', 'price' => 5000, 'daily' => [15, 30]],
        'air' => ['name' => 'Air Mineral 600 ml', 'price' => 4000, 'daily' => [4, 10]],
        'kerupuk' => ['name' => 'Kerupuk', 'price' => 2000, 'daily' => [8, 16]],
    ];

    private CarbonImmutable $now;

    /** @var list<array<string, mixed>> */
    private array $transactions = [];

    public function __construct(?CarbonImmutable $now = null)
    {
        $this->now = $now ?? CarbonImmutable::now();
        $this->generate();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactions(int $limit): array
    {
        $rows = collect($this->transactions)
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
                'contact' => null,
                'voided' => false,
            ])
            ->all();

        return array_values($rows);
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
                $names = [];
                foreach ($lines as $key => $qty) {
                    $total += self::PRODUCTS[$key]['price'] * $qty;
                    $names[] = $qty.' '.self::PRODUCTS[$key]['name'];
                }
                $roll = mt_rand(1, 100);
                $method = $roll <= 60 ? 'tunai' : 'non_tunai';
                $source = $roll % 7 === 0 ? 'toko' : ($roll % 3 === 0 ? 'web' : 'wa');

                $this->push($sequence, $at, 'penjualan', implode(', ', $names), $method, $source, $total, 'in');
            }

            $this->push($sequence, $day->setTime(21, 0), 'biaya', 'Upah karyawan harian', 'tunai', 'web', 75_000, 'out');

            if ($i % 4 === 1) {
                $this->push($sequence, $day->setTime(9, 15), 'biaya', 'Isi ulang gas 3 kg', 'tunai', 'wa', 25_000, 'out');
            }

            if ($day->day === 5) {
                $this->push($sequence, $day->setTime(10, 0), 'biaya', 'Token listrik', 'non_tunai', 'web', 350_000, 'out');
            }

            if ($i % 2 === 1) {
                $this->push($sequence, $day->setTime(20, 10), 'modal', 'Ambil untuk keperluan rumah', 'tunai', 'wa', 150_000, 'out');
            }
        }

        // Credit records, shown without a +/− sign.
        $this->push($sequence, $this->now->subDays(9)->setTime(12, 40), 'penjualan', 'Pak Budi: 3 Nasi Goreng (belum bayar)', 'kredit', 'wa', 45_000, 'none');
        $this->push($sequence, $this->now->subDays(5)->setTime(8, 0), 'pembelian', 'Air mineral & kerupuk dari Toko Sumber Rejeki (belum dibayar)', 'kredit', 'web', 450_000, 'none');
    }

    private function push(int &$sequence, CarbonImmutable $at, string $type, string $description, string $method, string $source, int $amount, string $direction): void
    {
        if ($at > $this->now) {
            return;
        }

        $this->transactions[] = [
            'id' => $sequence++,
            'at' => $at,
            'type' => $type,
            'description' => $description,
            'method' => $method,
            'source' => $source,
            'amount' => $amount,
            'direction' => $direction,
        ];
    }

    /**
     * Sales per day, the last 30 days.
     *
     * @return list<array{date: string, sales: int}>
     */
    public function salesChart(): array
    {
        $byDay = collect($this->transactions)
            ->where('type', 'penjualan')
            ->groupBy(fn (array $t): string => $t['at']->toDateString())
            ->map(fn ($rows) => (int) $rows->sum('amount'));

        return array_map(function (int $back) use ($byDay) {
            $date = $this->now->subDays($back)->toDateString();

            return ['date' => $date, 'sales' => $byDay->get($date, 0)];
        }, range(29, 0));
    }
}
