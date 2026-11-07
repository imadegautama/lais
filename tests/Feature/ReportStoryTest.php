<?php

namespace Tests\Feature;

use App\Enums\Period;
use App\Models\Business;
use App\Services\Reports;
use App\Services\ReportStory;
use App\Services\TransactionService;
use App\Support\Format;
use App\Support\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportStoryTest extends TestCase
{
    use RefreshDatabase;

    private const ZERO = ['sales' => 0, 'cogs' => 0, 'grossProfit' => 0, 'otherIncome' => 0, 'expenses' => 0, 'depreciation' => 0, 'otherExpenses' => 0, 'stockDifference' => 0, 'netProfit' => 0];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:00'));
    }

    public function test_a_range_comes_from_dates_or_falls_back_to_a_period(): void
    {
        $range = fn (array $query) => ReportRange::fromRequest(Request::create('/', 'GET', $query));

        $custom = $range(['dari' => '2026-11-01', 'sampai' => '2026-11-10']);
        $this->assertSame(['2026-11-01', '2026-11-11', '2026-10-22', '2026-11-01'], [
            $custom->from->toDateString(), $custom->before->toDateString(), $custom->priorFrom->toDateString(), $custom->priorBefore->toDateString(),
        ]);
        $this->assertSame(['1–10 November 2026', null], [$custom->label, $custom->period]);
        $this->assertSame('28 Oktober – 5 November 2026', $range(['dari' => '2026-10-28', 'sampai' => '2026-11-05'])->label);
        $this->assertSame(['dari' => '2026-11-01', 'sampai' => '2026-11-10'], $custom->query());

        // One day is compared with the day before it.
        $day = $range(['dari' => '2026-11-05', 'sampai' => '2026-11-05']);
        $this->assertSame(['5 November 2026', '2026-11-04'], [$day->label, $day->priorFrom->toDateString()]);

        // Backwards, in the future, unreadable, or half filled in: the quick period.
        foreach ([
            ['dari' => '2026-11-10', 'sampai' => '2026-11-01'],
            ['dari' => '2026-11-01', 'sampai' => '2026-12-31'],
            ['dari' => 'kemarin', 'sampai' => '2026-11-01'],
            ['dari' => '2026-02-31', 'sampai' => '2026-11-01'],
            ['dari' => '2026-11-01'],
        ] as $query) {
            $this->assertSame(Period::BulanIni, $range($query)->period);
        }

        $this->assertSame(Period::BulanLalu, $range(['periode' => 'bulan_lalu'])->period);
        $this->assertSame(['periode' => 'bulan_lalu'], $range(['periode' => 'bulan_lalu'])->query());
        $this->assertSame('2026-11-18', $range([])->lastDay()->toDateString());
    }

    public function test_every_report_reads_the_chosen_dates(): void
    {
        $business = Business::factory()->create();
        $service = app(TransactionService::class);
        $service->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 500000, 'occurred_at' => '2026-10-01 08:00']);
        $service->record($business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 40000, 'occurred_at' => '2026-11-03 08:00']);
        $service->record($business, ['type' => 'biaya', 'kind' => 'upah', 'method' => 'tunai', 'amount' => 70000, 'occurred_at' => '2026-11-12 08:00']);
        $dates = ['dari' => '2026-11-01', 'sampai' => '2026-11-05'];

        $this->actingAs($business->user)->get(route('reports.profit-loss', $dates))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.expenses', 40000)
            ->where('period.key', null)
            ->where('period.label', '1–5 November 2026')
            ->where('query', $dates)
            ->where('previousLabel', 'Periode sebelumnya'));
        $this->get(route('reports.cash-book', $dates))->assertInertia(fn (AssertableInertia $page) => $page->where('book.totalOut', 40000));
        $this->get(route('reports.notes', $dates))->assertInertia(fn (AssertableInertia $page) => $page->where('expensesByKind.sewa', 40000)->missing('expensesByKind.upah'));
        $this->get(route('reports.ledger', ['code' => '6101'] + $dates))->assertInertia(fn (AssertableInertia $page) => $page->where('ledger.closing', 40000));
        $this->get(route('reports.index', $dates))->assertInertia(fn (AssertableInertia $page) => $page->where('netProfit', -40000));
    }

    public function test_the_profit_story_names_the_result_and_what_moved_it(): void
    {
        $story = app(ReportStory::class);
        $range = ReportRange::forPeriod(Period::BulanIni);
        $now = ['sales' => 43000, 'cogs' => 13900, 'expenses' => 55000, 'netProfit' => -25900] + self::ZERO;
        $earlier = ['sales' => 9334000, 'cogs' => 2627000, 'expenses' => 1860000, 'netProfit' => 4847000] + self::ZERO;

        $this->assertSame(['tone' => 'bad', 'headline' => 'Bulan ini rugi Rp25.900.', 'detail' => 'Bulan lalu untung Rp4,8 jt. Penyebab terbesar: penjualan turun 99%.'], $story->profit($now, $earlier, $range));

        // Better than before: what pushed it up, and costs can be the reason.
        $better = ['sales' => 100000, 'cogs' => 20000, 'expenses' => 10000, 'netProfit' => 70000] + self::ZERO;
        $worse = ['sales' => 100000, 'cogs' => 20000, 'expenses' => 60000, 'netProfit' => 20000] + self::ZERO;
        $this->assertSame(['tone' => 'good', 'headline' => 'Bulan ini untung Rp70.000.', 'detail' => 'Bulan lalu untung Rp20.000. Pendorong terbesar: biaya turun 83%.'], $story->profit($better, $worse, $range));

        // A jump too big for a percentage to mean anything is said in rupiah.
        $small = ['sales' => 172000, 'cogs' => 50400, 'expenses' => 60000, 'netProfit' => 61600] + self::ZERO;
        $big = ['sales' => 9334000, 'cogs' => 2627000, 'expenses' => 1860000, 'netProfit' => 4847000] + self::ZERO;
        $this->assertStringEndsWith('Pendorong terbesar: penjualan naik Rp9,2 jt.', $story->profit($big, $small, $range)['detail']);

        // Still a profit, but smaller: worth a look, not an alarm.
        $this->assertSame('warning', $story->profit($worse, $better, $range)['tone']);
        $this->assertSame('Belum ada pembanding dari periode sebelumnya.', $story->profit($better, self::ZERO, $range)['detail']);
        $this->assertSame(['tone' => 'neutral', 'headline' => 'Belum ada penjualan atau biaya bulan ini.', 'detail' => 'Laporan ini terisi sendiri dari catatan transaksi.'], $story->profit(self::ZERO, self::ZERO, $range));
    }

    public function test_the_other_stories_say_what_their_report_shows(): void
    {
        $story = app(ReportStory::class);
        $range = ReportRange::forPeriod(Period::BulanIni);

        $position = ['assets' => ['cash' => 100000, 'nonCash' => 0, 'receivables' => 0, 'inventory' => 300000, 'equipment' => 700000, 'accumulatedDepreciation' => -100000, 'total' => 1000000], 'liabilities' => ['total' => 250000], 'equity' => ['total' => 750000], 'balanced' => true];
        $this->assertSame(['tone' => 'good', 'headline' => 'Dari tiap Rp100 aset, Rp75 milik pemilik.', 'detail' => 'Total aset Rp1 jt, paling banyak di peralatan. Yang masih harus dibayar Rp250.000.'], $story->position($position));

        $this->assertSame('Uang usaha berkurang Rp20.000 bulan ini.', $story->cash(['opening' => 100000, 'closing' => 80000, 'totalIn' => 10000, 'totalOut' => 30000], $range)['headline']);

        $products = [['name' => 'Es Teh', 'profit' => 75], ['name' => 'Kopi', 'profit' => 25]];
        $this->assertSame(['tone' => 'warning', 'headline' => 'Es Teh menyumbang 75% laba kotor bulan ini.', 'detail' => '2 produk jarang laku menahan Rp30.000 di stok.'], $story->products($products, [['value' => 10000], ['value' => 20000]], $range));

        $assets = [['cost' => 1000000, 'accumulated' => 250000, 'bookValue' => 750000, 'lifeMonths' => 12], ['cost' => 500000, 'accumulated' => 0, 'bookValue' => 500000, 'lifeMonths' => null]];
        $this->assertSame(['tone' => 'warning', 'headline' => 'Peralatan bernilai Rp1,3 jt sekarang.', 'detail' => '1 peralatan belum punya umur pakai, jadi belum disusutkan.'], $story->assets($assets));

        $reconciliation = ['netProfit' => 100000, 'steps' => [['key' => 'inventory', 'amount' => -150000], ['key' => 'drawings', 'amount' => -20000]], 'cashChange' => -70000, 'difference' => 0];
        $this->assertSame(['tone' => 'warning', 'headline' => 'Bulan ini untung Rp100.000, sedangkan uang usaha turun Rp70.000.', 'detail' => 'Selisih terbesar: uang jadi stok (belanja bahan/barang) Rp150.000.'], $story->profitVsCash($reconciliation, $range));
    }

    public function test_the_trial_balance_says_whether_the_books_balance(): void
    {
        $story = app(ReportStory::class);

        $this->assertSame('good', $story->trialBalance(5000, 5000)['tone']);
        $this->assertSame(['tone' => 'bad', 'headline' => 'Pembukuan belum seimbang.', 'detail' => 'Selisihnya Rp300. Cek mutasi di Buku besar.'], $story->trialBalance(5000, 5300));
    }

    public function test_big_amounts_read_short_and_the_trend_matches_each_month(): void
    {
        $this->assertSame(['Rp999.999', 'Rp1 jt', 'Rp4,8 jt', 'Rp1,2 M', '−Rp2,5 jt'], array_map(Format::compact(...), [999999, 1000000, 4847000, 1234000000, -2500000]));

        $business = Business::factory()->create();
        $service = app(TransactionService::class);
        $service->record($business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'pribadi', 'amount' => 40000, 'occurred_at' => '2026-09-03 08:00']);
        $service->record($business, ['type' => 'biaya', 'kind' => 'upah', 'method' => 'pribadi', 'amount' => 10000, 'occurred_at' => '2026-11-02 08:00']);
        $reports = app(Reports::class);

        $trend = $reports->monthlyTrend($business);

        $this->assertCount(6, $trend);
        $this->assertSame(['2026-06', '2026-11'], [$trend[0]['month'], $trend[5]['month']]);
        $this->assertSame(-40000, $trend[3]['netProfit']);
        $this->assertSame($reports->profitAndLoss($business, CarbonImmutable::parse('2026-11-01'))['netProfit'], $trend[5]['netProfit']);
    }
}
