<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\Onboarding;
use App\Services\Reports;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid November: October is "bulan lalu", and the books opened on 1 October.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:00'));
        $this->business = $this->openBooks();
        $water = Item::query()->where('name', 'Air Mineral')->sole();
        $sell = fn (string $at, int $qty, string $method = 'tunai', array $extra = []) => $this->record([
            'type' => 'penjualan', 'method' => $method, 'occurred_at' => $at, 'lines' => [['item_id' => $water->id, 'qty' => $qty, 'price' => 4000]],
        ] + $extra);

        $sell('2026-10-05 10:00', 10);
        $tab = $sell('2026-10-06 10:00', 5, 'kredit', ['contact_name' => 'Bu Sari']);
        $this->record(['type' => 'biaya', 'kind' => 'listrik_air', 'method' => 'tunai', 'amount' => 50000, 'occurred_at' => '2026-10-07 09:00']);
        $this->record(['type' => 'biaya', 'kind' => 'upah', 'method' => 'non_tunai', 'amount' => 100000, 'occurred_at' => '2026-10-08 09:00']);
        $this->record(['type' => 'biaya', 'kind' => 'gas_bbm', 'method' => 'pribadi', 'amount' => 25000, 'occurred_at' => '2026-10-09 09:00']);
        app(TransactionService::class)->cancel($this->record(['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 200000, 'occurred_at' => '2026-10-10 09:00']));
        $this->record(['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 300000, 'occurred_at' => '2026-10-11 09:00']);
        $this->record(['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 70000, 'occurred_at' => '2026-10-12 09:00']);
        $this->record(['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $tab->contact_id, 'amount' => 15000, 'occurred_at' => '2026-10-13 09:00']);
        $this->record(['type' => 'pembelian', 'kind' => 'barang', 'method' => 'kredit', 'contact_name' => 'Toko Sumber', 'occurred_at' => '2026-10-14 09:00', 'lines' => [['item_id' => $water->id, 'qty' => 20, 'price' => 2500]]]);
        $sell('2026-11-02 10:00', 1);
    }

    public function test_each_statement_shows_the_period_before_it(): void
    {
        $this->actingAs($this->business->user);

        // This month beside the whole of October, not October up to today's date.
        $this->get(route('reports.profit-loss'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/profit-and-loss')
            ->where('report.sales', 4000)
            ->where('previous.sales', 60000)
            ->where('previous.expensesByKind.listrik_air', 50000)
            ->where('previousLabel', 'Bulan lalu'));
        $this->get(route('reports.profit-loss', ['periode' => 'bulan_lalu']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.sales', 60000)
            ->where('previous.sales', 0)
            ->where('previousLabel', 'Bulan sebelumnya'));

        $this->get(route('reports.balance-sheet', ['tanggal' => '2026-11-18']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/balance-sheet')
            ->where('previousDate', '2026-10-31')
            ->where('previous.balanced', true)
            ->where('previous.assets.total', app(Reports::class)->balanceSheet($this->business, CarbonImmutable::parse('2026-10-31'))['assets']['total']));
    }

    public function test_the_notes_add_up_to_the_lines_of_the_position(): void
    {
        $this->actingAs($this->business->user)->get(route('reports.notes', ['periode' => 'bulan_lalu']))
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('reports/notes');
                $props = $page->toArray()['props'];
                $sum = fn (string $list) => array_sum(array_column($props['details'][$list], 'amount'));

                $this->assertSame($props['position']['assets']['cash'] + $props['position']['assets']['nonCash'], $sum('money'));
                $this->assertSame($props['position']['assets']['inventory'], $sum('inventory'));
                $this->assertSame($props['position']['assets']['receivables'], $sum('receivables'));
                $this->assertSame($props['position']['liabilities']['payables'], $sum('payables'));
                $this->assertEquals(['upah' => 100000, 'listrik_air' => 50000, 'gas_bbm' => 25000], $props['expensesByKind']);
            });
    }

    public function test_the_cash_book_runs_from_the_opening_to_the_closing_balance(): void
    {
        $reports = app(Reports::class);
        $from = CarbonImmutable::parse('2026-10-01');
        $before = CarbonImmutable::parse('2026-11-01');
        $book = $reports->cashBook($this->business, $from, $before);
        $position = $reports->balanceSheet($this->business, CarbonImmutable::parse('2026-10-31'));

        $this->assertSame($position['assets']['cash'] + $position['assets']['nonCash'], $book['closing']);
        $this->assertSame($book['opening'] + $book['totalIn'] - $book['totalOut'], $book['closing']);
        $this->assertSame($book['closing'], end($book['lines'])['balance']);

        // One rekening alone, and a rekening of another business is ignored.
        $this->actingAs($this->business->user);
        $cash = $this->business->cashBox();
        $this->get(route('reports.cash-book', ['periode' => 'bulan_lalu', 'rekening' => $cash->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/cash-book')
            ->where('moneyAccountId', $cash->id)
            ->where('book.closing', $reports->cashBook($this->business, $from, $before, $cash->id)['closing']));
        $other = Business::factory()->create()->cashBox();
        $this->get(route('reports.cash-book', ['rekening' => $other->id]))->assertInertia(fn (AssertableInertia $page) => $page->where('moneyAccountId', null));
    }

    public function test_product_profit_adds_up_to_the_gross_profit_and_slow_stock_is_named(): void
    {
        $reports = app(Reports::class);
        $from = CarbonImmutable::parse('2026-10-01');
        $before = CarbonImmutable::parse('2026-11-01');
        $products = $reports->productPerformance($this->business, $from, $before);

        $this->assertSame(['Air Mineral', 15.0, 60000, 22500], [$products[0]['name'], $products[0]['qty'], $products[0]['sales'], $products[0]['profit']]);
        $this->assertSame($reports->profitAndLoss($this->business, $from, $before)['grossProfit'], array_sum(array_column($products, 'profit')));

        // Sold on 2 November, so not slow on the 18th; a month later it is.
        $this->assertSame([], $reports->slowStock($this->business));
        $this->travelTo(CarbonImmutable::parse('2026-12-20 10:00'));
        $this->assertSame('Air Mineral', $reports->slowStock($this->business)[0]['name']);

        $this->actingAs($this->business->user)->get(route('reports.products', ['periode' => 'bulan_lalu']))->assertInertia(fn (AssertableInertia $page) => $page->component('reports/products'));
    }

    public function test_profit_and_loss_lists_each_expense_kind_from_the_journal(): void
    {
        [$from, $before] = $this->october();
        $reports = app(Reports::class);

        // 15 bottles sold at 4.000 with a cost of 2.500; the cancelled rent nets out.
        $this->assertSame(['sales' => 60000, 'cogs' => 37500, 'grossProfit' => 22500, 'otherIncome' => 0, 'expenses' => 175000, 'depreciation' => 0, 'otherExpenses' => 0, 'stockDifference' => 0, 'netProfit' => -152500], $reports->profitAndLoss($this->business, $from, $before));
        $this->assertEqualsCanonicalizing(['listrik_air' => 50000, 'upah' => 100000, 'gas_bbm' => 25000], $reports->expensesByKind($this->business, $from, $before));
    }

    public function test_equity_changes_start_from_the_opening_balance_sheet_and_end_at_the_books(): void
    {
        [$from, $before] = $this->october();
        $reports = app(Reports::class);

        // Saldo awal is modal awal, not a deposit; gas paid with personal money is one.
        $this->assertSame(
            ['opening' => 2950000, 'deposits' => 325000, 'netProfit' => -152500, 'drawings' => 70000, 'closing' => 3052500],
            $reports->equityChanges($this->business, $from, $before),
        );
        $this->assertSame(
            ['opening' => 3052500, 'deposits' => 0, 'netProfit' => 1500, 'drawings' => 0, 'closing' => 3054000],
            $reports->equityChanges($this->business, CarbonImmutable::parse('2026-11-01')),
        );
        // A period that ends before the books open starts and ends at zero.
        $this->assertSame(0, $reports->equityChanges($this->business, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-10-01'))['closing']);
    }

    public function test_the_balance_sheet_balances_on_every_date(): void
    {
        $reports = app(Reports::class);

        $this->assertSame([
            'assets' => ['cash' => 735000, 'nonCash' => 900000, 'receivables' => 5000, 'inventory' => 262500, 'equipment' => 1500000, 'accumulatedDepreciation' => 0, 'total' => 3402500],
            'liabilities' => ['payables' => 350000, 'customerDeposits' => 0, 'total' => 350000],
            'equity' => ['capital' => 3275000, 'drawings' => -70000, 'profit' => -152500, 'total' => 3052500],
            'balanced' => true,
        ], $reports->balanceSheet($this->business, CarbonImmutable::parse('2026-10-31')));

        $opening = $reports->balanceSheet($this->business, CarbonImmutable::parse('2026-10-01'));
        $this->assertSame([3250000, 300000, 2950000, true], [$opening['assets']['total'], $opening['liabilities']['total'], $opening['equity']['total'], $opening['balanced']]);

        $before = $reports->balanceSheet($this->business, CarbonImmutable::parse('2026-09-30'));
        $this->assertSame([0, 0, true], [$before['assets']['total'], $before['equity']['total'], $before['balanced']]);
    }

    public function test_profit_and_cash_reconcile_to_zero(): void
    {
        [$from, $before] = $this->october();

        // A loss of 152.500, yet cash grew 135.000: deposits and an unpaid purchase.
        $this->assertSame([
            'netProfit' => -152500,
            'steps' => [
                ['key' => 'inventory', 'amount' => -12500],
                ['key' => 'receivables', 'amount' => -5000],
                ['key' => 'payables', 'amount' => 50000],
                ['key' => 'deposits', 'amount' => 325000],
                ['key' => 'drawings', 'amount' => -70000],
            ],
            'cashChange' => 135000,
            'difference' => 0,
        ], app(Reports::class)->cashReconciliation($this->business, $from, $before));
    }

    public function test_the_report_pages_show_the_chosen_period_of_this_business_only(): void
    {
        $this->recordFor(Business::factory()->create(), ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 900000, 'occurred_at' => '2026-10-20 09:00']);
        $this->actingAs($this->business->user);

        $this->get(route('reports.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/index')
            ->where('period.key', 'bulan_ini')
            ->where('netProfit', 1500)
            ->where('closingEquity', 3054000)
            ->where('balanceSheet.balanced', true)
            ->where('startedOn', '2026-10-01'));

        $this->get(route('reports.profit-loss', ['periode' => 'bulan_lalu']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/profit-and-loss')
            ->where('period', ['key' => 'bulan_lalu', 'label' => 'Bulan lalu'])
            ->where('business.name', 'Warung Ani')
            ->where('report.netProfit', -152500)
            ->where('report.expensesByKind.upah', 100000)
            ->missing('report.expensesByKind.sewa'));

        $this->get(route('reports.equity', ['periode' => 'bulan_lalu']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/equity')
            ->where('report.closing', 3052500)
            ->where('reconciliation.difference', 0));

        $this->get(route('reports.balance-sheet', ['tanggal' => '2026-10-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/balance-sheet')
            ->where('date', '2026-10-31')
            ->where('report.assets.total', 3402500)
            ->where('report.balanced', true));

        // A future or unreadable date falls back to today.
        $this->get(route('reports.balance-sheet', ['tanggal' => '2027-01-01']))->assertInertia(fn (AssertableInertia $page) => $page->where('date', '2026-11-18'));
        $this->get(route('reports.balance-sheet', ['tanggal' => 'kemarin']))->assertInertia(fn (AssertableInertia $page) => $page->where('date', '2026-11-18'));
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        auth()->logout();

        $this->get(route('reports.profit-loss'))->assertRedirect(route('login'));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function october(): array
    {
        return [CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01')];
    }

    private function openBooks(): Business
    {
        $business = Business::factory()->create();
        [$cashBox, $rekening] = $business->moneyAccounts()->orderBy('id')->get()->all();

        app(Onboarding::class)->complete($business, [
            'name' => 'Warung Ani',
            'started_on' => '2026-10-01',
            'accounts' => [['id' => $cashBox->id, 'name' => 'Kas tunai', 'balance' => 500000], ['id' => $rekening->id, 'name' => 'BCA', 'balance' => 1000000]],
            'equipment' => [['name' => 'Kompor', 'amount' => 1500000]],
            'products' => [['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'min_stock' => 20, 'opening_stock' => 100, 'opening_cost' => 2500]],
            'debts' => [['name' => 'Toko Sumber', 'amount' => 300000]],
        ]);

        return $business->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(array $data): Transaction
    {
        return $this->recordFor($this->business, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordFor(Business $business, array $data): Transaction
    {
        return app(TransactionService::class)->record($business, $data);
    }
}
