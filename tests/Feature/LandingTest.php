<?php

namespace Tests\Feature;

use App\Enums\BusinessCategory;
use App\Models\Business;
use App\Models\Item;
use App\Services\Catalog;
use App\Services\Ledger;
use App\Services\ReportAnswers;
use App\Services\Reports;
use App\Services\TransactionService;
use App\Support\StarterCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_offers_only_what_the_example_shop_can_sell(): void
    {
        $shop = $this->kitchen(['name' => 'Warung Bu Rina', 'slug' => 'warung-bu-rina', 'whatsapp' => '081234567890']);
        $shop->items()->where('items.name', 'Kerupuk')->update(['show_in_store' => false]);
        $shop->items()->where('items.name', 'Air Mineral 600 ml')->update(['stock' => 0]);
        $shop->items()->where('items.name', 'Mie Goreng')->update(['photo_path' => 'items/mie.webp']);

        $this->get(route('home'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->where('demoShop.name', 'Warung Bu Rina')
            ->where('demoShop.slug', 'warung-bu-rina')
            ->missing('demoShop.whatsapp')
            ->where('demoShop.items.0.name', 'Mie Goreng')
            ->where('demoShop.items', fn ($items) => collect($items)->pluck('name')->sort()->values()->all() === ['Es Teh', 'Mie Goreng', 'Nasi Goreng']));
    }

    public function test_the_landing_page_opens_without_the_example_shop(): void
    {
        $this->get(route('home'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('demoShop', null));
    }

    public function test_the_landing_page_leaves_out_an_example_shop_with_nothing_to_sell(): void
    {
        Business::factory()->create(['slug' => 'warung-bu-rina']);

        $this->get(route('home'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('demoShop', null));
    }

    /**
     * The numbers in resources/js/features/landing/examples.ts are promises to
     * a reader, so they are checked against the books here.
     */
    public function test_the_landing_demo_shows_what_the_books_really_do(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00'));
        $business = $this->kitchen();
        $service = app(TransactionService::class);
        $ledger = app(Ledger::class);
        $today = '2026-11-15 09:00';
        $item = fn (string $name): Item => $business->items()->where('items.name', $name)->firstOrFail();
        $stockBefore = $business->items()->pluck('items.stock', 'items.name')->map(fn ($stock) => (float) $stock);
        $change = fn (string $name) => round((float) $item($name)->stock - $stockBefore[$name], 3);
        $service->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 500_000, 'occurred_at' => '2026-10-02 09:00']);

        // "jual 3 nasgor 1 aqua qris"
        $sale = $service->record($business, ['type' => 'penjualan', 'method' => 'non_tunai', 'occurred_at' => $today, 'lines' => [
            ['item_id' => $item('Nasi Goreng')->id, 'qty' => 3, 'price' => 15_000],
            ['item_id' => $item('Air Mineral 600 ml')->id, 'qty' => 1, 'price' => 4_000],
        ]]);
        $this->assertSame(49_000, $sale->total);
        $this->assertSame(-0.45, $change('Beras'));
        $this->assertSame(-3.0, $change('Telur'));
        $this->assertSame(-3.0, $change('Kerupuk'));
        $this->assertSame(-0.06, $change('Minyak goreng'));
        $this->assertSame(-3.0, $change('Bumbu & kecap'));
        $this->assertSame(-1.0, $change('Air Mineral 600 ml'));

        $from = CarbonImmutable::parse('2026-11-01');
        $pnl = app(Reports::class)->profitAndLoss($business, $from, $from->addMonth());
        $this->assertSame(['sales' => 49_000, 'cogs' => 24_100, 'netProfit' => 24_900], [
            'sales' => $pnl['sales'], 'cogs' => $pnl['cogs'], 'netProfit' => $pnl['netProfit'],
        ]);

        // "beli telur 30 butir 66rb tunai"
        $inventory = $ledger->balance($business, '1301');
        $purchase = $service->record($business, ['type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'occurred_at' => $today, 'lines' => [
            ['item_id' => $item('Telur')->id, 'qty' => 30, 'price' => 2_200],
        ]]);
        $this->assertSame(66_000, $purchase->total);
        $this->assertSame(27.0, $change('Telur'));
        $this->assertSame(66_000, $ledger->balance($business, '1301') - $inventory);

        // "ambil 200rb buat rumah"
        $service->record($business, ['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 200_000, 'occurred_at' => $today]);
        $this->assertSame(200_000, $ledger->balance($business, '3201'));

        // "kenapa laba naik tapi kas turun?"
        $answer = app(ReportAnswers::class)->profitVsCash($business);
        $this->assertStringContainsString('Bulan ini laba bersih Rp24.900, sedangkan uang usaha turun Rp217.000.', $answer);
        $this->assertStringContainsString('Uang jadi stok (belanja bahan/barang): −Rp41.900', $answer);
        $this->assertStringContainsString('Diambil untuk pribadi: −Rp200.000', $answer);
    }

    /**
     * A warung opened on the kuliner starter catalog, as the demo seeder does.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function kitchen(array $attributes = []): Business
    {
        $opened = CarbonImmutable::parse('2026-10-01');
        $business = Business::factory()->create([...$attributes, 'started_on' => $opened]);
        app(Catalog::class)->createMany($business, StarterCatalog::for(BusinessCategory::Kuliner), $opened);

        return $business;
    }
}
