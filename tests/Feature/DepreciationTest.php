<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Business;
use App\Models\FixedAsset;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Services\Depreciation;
use App\Services\Reports;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00'));
        $this->business = Business::factory()->create();
        app(TransactionService::class)->record($this->business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 5000000, 'occurred_at' => '2026-07-01 08:00']);
    }

    public function test_a_purchase_is_spread_evenly_from_its_month_and_the_rest_falls_last(): void
    {
        // 1.000.000 over 3 months: 333.333, 333.333, 333.334, with no residual value (par. 11.12).
        $asset = $this->buy(1000000, 3, '2026-08-15 09:00');

        $this->assertSame(3, app(Depreciation::class)->run());

        $amounts = JournalLine::query()->whereHas('account', fn ($query) => $query->where('code', Account::DEPRECIATION))->orderBy('id')->pluck('debit')->all();
        $this->assertSame([333333, 333333, 333334], array_map('intval', $amounts));
        $this->assertSame('2026-10-31', $asset->refresh()->depreciated_through->toDateString());

        $book = app(Depreciation::class)->book($this->business)[0];
        $this->assertSame([1000000, 1000000, 0], [$book['cost'], $book['accumulated'], $book['bookValue']]);
    }

    public function test_each_month_is_dated_its_last_day_and_lands_in_that_period(): void
    {
        $this->buy(1200000, 12, '2026-09-10 09:00');
        app(Depreciation::class)->run();
        $reports = app(Reports::class);

        $october = $reports->profitAndLoss($this->business, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'));
        $this->assertSame([100000, -100000], [$october['depreciation'], $october['netProfit']]);
        // November has not ended, so nothing is posted for it.
        $this->assertSame(0, $reports->profitAndLoss($this->business, CarbonImmutable::parse('2026-11-01'))['depreciation']);
    }

    public function test_running_again_posts_nothing_new_and_a_late_run_catches_up(): void
    {
        $this->buy(1200000, 12, '2026-09-10 09:00');
        $depreciation = app(Depreciation::class);

        $this->assertSame(2, $depreciation->run());
        $this->assertSame(0, $depreciation->run());

        $this->travelTo(CarbonImmutable::parse('2027-02-03 10:00'));
        $this->assertSame(3, $depreciation->run());
        $this->assertSame('2027-01-31', FixedAsset::query()->sole()->depreciated_through->toDateString());
    }

    public function test_it_stops_at_the_end_of_the_useful_life(): void
    {
        $this->buy(600000, 2, '2026-09-10 09:00');

        $this->assertSame(2, app(Depreciation::class)->run());

        $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00'));
        $this->assertSame(0, app(Depreciation::class)->run());
    }

    public function test_the_books_still_balance_with_depreciation(): void
    {
        $this->buy(1200000, 12, '2026-09-10 09:00');
        app(Depreciation::class)->run();
        $reports = app(Reports::class);

        $position = $reports->balanceSheet($this->business, now());
        $this->assertTrue($position['balanced']);
        $this->assertSame([1200000, -200000], [$position['assets']['equipment'], $position['assets']['accumulatedDepreciation']]);

        $reconciliation = $reports->cashReconciliation($this->business, CarbonImmutable::parse('2026-09-01'));
        $this->assertSame(0, $reconciliation['difference']);
        $this->assertContains(['key' => 'depreciation', 'amount' => 200000], $reconciliation['steps']);
    }

    public function test_cancelling_the_purchase_takes_its_depreciation_with_it(): void
    {
        $asset = $this->buy(1200000, 12, '2026-09-10 09:00');
        app(Depreciation::class)->run();

        app(TransactionService::class)->cancel(Transaction::query()->findOrFail($asset->transaction_id));

        $reports = app(Reports::class);
        $october = $reports->profitAndLoss($this->business, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01'));
        $this->assertSame(0, $october['depreciation']);
        $this->assertTrue($reports->balanceSheet($this->business, now())['balanced']);
        $this->assertSame(0, $reports->balanceSheet($this->business, now())['assets']['accumulatedDepreciation']);
        $this->assertSame([], app(Depreciation::class)->book($this->business));
        $this->assertSame(0, app(Depreciation::class)->run());
    }

    public function test_an_asset_without_a_life_waits_for_the_owner(): void
    {
        $asset = $this->buy(1200000, 12, '2026-09-10 09:00');
        $asset->update(['useful_life_months' => null]);

        $this->assertSame(0, app(Depreciation::class)->run());
        $this->assertSame(0, JournalLine::query()->whereHas('account', fn ($query) => $query->where('code', Account::DEPRECIATION))->count());

        $this->actingAs($this->business->user)->patch(route('fixed-assets.update', $asset), ['useful_life_months' => 12])->assertRedirect();

        // Setting it catches up on the months already over, once.
        $this->assertSame('2026-10-31', $asset->refresh()->depreciated_through->toDateString());
        $this->patch(route('fixed-assets.update', $asset), ['useful_life_months' => 24]);
        $this->assertSame(12, $asset->refresh()->useful_life_months);
    }

    public function test_the_form_sets_the_life_and_another_business_cannot_touch_it(): void
    {
        $this->actingAs($this->business->user)->post(route('transactions.store'), [
            'type' => 'pembelian', 'kind' => 'peralatan', 'method' => 'tunai', 'amount' => 900000, 'note' => 'Kompor', 'useful_life_months' => 36,
        ])->assertSessionHasNoErrors();
        $asset = FixedAsset::query()->sole();
        $this->assertSame(['Kompor', 900000, 36], [$asset->name, $asset->cost, $asset->useful_life_months]);

        $this->post(route('transactions.store'), ['type' => 'biaya', 'kind' => 'lain', 'method' => 'tunai', 'amount' => 1000, 'useful_life_months' => 36])
            ->assertSessionHasErrors('useful_life_months');

        $this->actingAs(Business::factory()->create()->user)->patch(route('fixed-assets.update', $asset), ['useful_life_months' => 12])->assertNotFound();
    }

    public function test_a_record_with_no_life_gets_the_default_and_the_page_lists_it(): void
    {
        $this->buy(480000, null, '2026-11-02 09:00');
        $this->assertSame(FixedAsset::DEFAULT_LIFE_MONTHS, FixedAsset::query()->sole()->useful_life_months);

        $this->actingAs($this->business->user)->get(route('fixed-assets.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/fixed-assets')
            ->where('assets.0.bookValue', 480000)
            ->where('assets.0.locked', false));
    }

    private function buy(int $cost, ?int $months, string $at): FixedAsset
    {
        app(TransactionService::class)->record($this->business, array_filter([
            'type' => 'pembelian', 'kind' => 'peralatan', 'method' => 'tunai', 'amount' => $cost, 'note' => 'Kompor', 'occurred_at' => $at, 'useful_life_months' => $months,
        ], fn (mixed $value) => $value !== null));

        return FixedAsset::query()->latest('id')->firstOrFail();
    }
}
