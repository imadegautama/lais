<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Catalog;
use App\Services\Onboarding;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Sunday afternoon; the week began on Monday 12 October.
        $this->travelTo(CarbonImmutable::parse('2026-10-18 14:00'));
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_the_numbers_come_from_the_books(): void
    {
        $business = $this->openBooks();
        $water = Item::query()->where('name', 'Air Mineral')->sole();
        $sell = fn (string $at, int $qty, string $method = 'tunai', array $extra = []) => $this->record($business, [
            'type' => 'penjualan', 'method' => $method, 'occurred_at' => "2026-10-{$at}", 'lines' => [['item_id' => $water->id, 'qty' => $qty, 'price' => 4000]],
        ] + $extra);

        // Bu Sari owes 12.000 and 8.000, then pays 12.000: the 9 October sale is still open.
        $tab = $sell('05 10:00', 3, 'kredit', ['contact_name' => 'Bu Sari']);
        $sell('09 10:00', 2, 'kredit', ['contact_id' => $tab->contact_id]);
        $this->record($business, ['type' => 'utang_piutang', 'kind' => 'terima_piutang', 'method' => 'tunai', 'contact_id' => $tab->contact_id, 'amount' => 12000, 'occurred_at' => '2026-10-12 09:00']);
        $sell('10 09:00', 5);
        $sell('17 10:00', 10);
        $sell('18 08:00', 10);
        $sell('18 09:00', 5, 'non_tunai');
        $this->record($business, ['type' => 'biaya', 'kind' => 'gas_bbm', 'method' => 'tunai', 'amount' => 10000, 'occurred_at' => '2026-10-18 10:00']);
        $this->record($business, ['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 30000, 'occurred_at' => '2026-10-18 11:00']);
        $this->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => 'kredit', 'contact_name' => 'Toko Sumber', 'occurred_at' => '2026-10-18 12:00', 'lines' => [['item_id' => $water->id, 'qty' => 20, 'price' => 2500]]]);
        app(TransactionService::class)->cancel($sell('18 13:00', 2));

        // Another business's records stay out.
        $this->record(Business::factory()->create(), ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 500000]);

        $this->actingAs($business->user)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('isEmpty', false)
            ->where('needsOnboarding', false)
            ->where('period', ['key' => 'hari_ini', 'label' => 'Hari ini', 'comparisonLabel' => 'vs kemarin di jam yang sama'])
            // Today: 60.000 sold (the cancelled 8.000 nets out), 15 bottles at Rp2.500, Rp10.000 gas.
            ->where('summary.sales', ['value' => 60000, 'previous' => 40000, 'count' => 2])
            ->where('summary.netProfit', ['value' => 12500, 'previous' => 15000])
            ->where('summary.personalDraw', ['value' => 30000, 'previous' => 0])
            ->where('summary.cash', ['total' => 1592000, 'onHand' => 572000, 'nonCash' => 1020000])
            // October: 140.000 sold − 35 bottles − 10.000 = 42.500; the opening balance sheet is modal awal.
            ->where('wallets', ['businessCash' => 1592000, 'drawnThisMonth' => 30000, 'netProfitThisMonth' => 42500, 'equityOpening' => 2950000, 'equityNow' => 2962500])
            ->where('health.0.passed', true)
            ->where('health.1.detail', 'Kas Rp1.592.000, pengeluaran rata-rata sebulan Rp60.000')
            ->where('health.2.detail', 'Utang Rp350.000, modal Rp2.962.500')
            ->where('health.3.passed', true)
            ->where('salesChart.29', ['date' => '2026-10-18', 'sales' => 60000])
            ->where('salesChart.28', ['date' => '2026-10-17', 'sales' => 40000])
            ->where('salesChart.20.sales', 8000)
            ->where('stockAlerts', [])
            ->has('actions', 1)
            ->where('actions.0.kind', 'piutang')
            ->where('actions.0.title', 'Bu Sari belum bayar Rp8.000')
            ->where('actions.0.detail', 'Belum lunas sejak 9 Oktober 2026, sudah 9 hari')
            ->has('recentTransactions', 8)
            ->where('recentTransactions.0.voided', true));

        $this->get(route('dashboard', ['periode' => 'minggu_ini']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.sales', ['value' => 100000, 'previous' => 40000, 'count' => 3]));
    }

    public function test_a_month_at_a_loss_without_drawings_does_not_fail_the_drawings_rule(): void
    {
        $business = $this->openBooks();
        $this->record($business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 100000]);

        $this->actingAs($business->user)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('health.0', ['key' => 'laba', 'label' => 'Usaha untung bulan ini', 'passed' => false, 'detail' => 'Laba bersih −Rp100.000'])
            ->where('health.3.passed', true));
    }

    public function test_stock_alerts_estimate_days_left_from_the_last_seven_days(): void
    {
        $business = Business::factory()->create();
        app(Catalog::class)->createMany($business, [
            ['name' => 'Beras', 'kind' => 'bahan', 'unit' => 'kg', 'opening_stock' => 8, 'opening_cost' => 14000],
            ['name' => 'Telur', 'kind' => 'bahan', 'unit' => 'butir', 'min_stock' => 50, 'opening_stock' => 40, 'opening_cost' => 2000],
            ['name' => 'Gula', 'kind' => 'bahan', 'unit' => 'kg', 'min_stock' => 1, 'opening_stock' => 5, 'opening_cost' => 16000],
            ['name' => 'Nasi Goreng', 'kind' => 'komposisi', 'unit' => 'porsi', 'price' => 15000, 'recipe' => [['name' => 'Beras', 'qty' => 0.5], ['name' => 'Telur', 'qty' => 1]]],
        ]);
        $friedRice = Item::query()->where('name', 'Nasi Goreng')->sole();
        $sell = fn (string $at) => $this->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'occurred_at' => $at, 'lines' => [['item_id' => $friedRice->id, 'qty' => 6, 'price' => 15000]]]);

        $sell('2026-10-13 10:00');
        $sell('2026-10-16 10:00');

        // 5,17 days of records: 6 kg rice and 12 eggs used. Gula is fine and unused.
        $this->actingAs($business->user)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stockAlerts.0', ['id' => Item::query()->where('name', 'Beras')->value('id'), 'name' => 'Beras', 'unit' => 'kg', 'stock' => 2, 'minimum' => null, 'daysLeft' => 1.7])
            ->where('stockAlerts.1.name', 'Telur')
            ->where('stockAlerts.1.daysLeft', 12.1)
            ->has('stockAlerts', 2)
            ->where('actions.0.title', 'Beras habis ±1,7 hari lagi')
            ->where('actions.0.detail', 'Sisa 2 kg')
            ->where('actions.0.target.href', route('transactions.create', ['jenis' => 'pembelian'])));

        // Under three days of records the estimate waits; the minimum still warns.
        Transaction::query()->update(['occurred_at' => now()->subHour()]);

        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stockAlerts', [['id' => Item::query()->where('name', 'Telur')->value('id'), 'name' => 'Telur', 'unit' => 'butir', 'stock' => 28, 'minimum' => 50, 'daysLeft' => null]])
            ->where('actions', []));
    }

    public function test_with_only_the_opening_balance_the_dashboard_shows_the_first_steps(): void
    {
        $business = Business::factory()->create();
        app(Onboarding::class)->complete($business, ['name' => 'Warung Ani', 'started_on' => today()->toDateString(), 'accounts' => [['id' => $business->cashBox()->id, 'name' => 'Kas tunai', 'balance' => 250000]]]);

        $this->actingAs($business->user)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('isEmpty', true)
            ->where('summary.cash.total', 250000)
            ->where('summary.sales.previous', null)
            ->where('period.comparisonLabel', null)
            ->where('wallets.equityOpening', 250000));
    }

    /**
     * Opened on 1 October: Rp500.000 cash, Rp1 jt in the bank, a Rp1,5 jt
     * stove, 100 bottles at Rp2.500, and Rp300.000 owed to a supplier.
     */
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
    private function record(Business $business, array $data): Transaction
    {
        return app(TransactionService::class)->record($business, $data);
    }
}
