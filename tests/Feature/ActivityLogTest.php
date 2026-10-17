<?php

namespace Tests\Feature;

use App\Enums\StockReason;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\Inventory;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_cancelling_and_editing_a_transaction_logs_who_did_it(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 4000);
        $this->actingAs($business->user);
        $sale = fn (int $qty) => ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => $qty, 'price' => 4000]]];

        $first = $this->service()->record($business, $sale(3));
        $this->service()->cancel($first, 'Salah input');
        $second = $this->service()->record($business, $sale(3));
        $replacement = $this->service()->replace($second, $sale(2));

        $this->assertSame(
            [['created', 'Mencatat penjualan: 3 Air Mineral', 'Total Rp12.000'], ['cancelled', 'Membatalkan penjualan: 3 Air Mineral', 'Alasan: Salah input']],
            $this->entries($first),
        );
        $this->assertSame(['replaced', 'Mengubah penjualan: 3 Air Mineral', null], $this->entries($second)[1]);
        $this->assertSame([['created', 'Mencatat ulang penjualan: 2 Air Mineral', 'Total Rp8.000']], $this->entries($replacement));

        $replaced = $this->resource(Activity::query()->forSubject($second)->where('event', 'replaced')->sole());
        $this->assertSame([
            ['label' => 'Total', 'old' => 'Rp12.000', 'new' => 'Rp8.000'],
            ['label' => 'Keterangan', 'old' => '3 Air Mineral', 'new' => '2 Air Mineral'],
        ], $replaced['changes']);
        $this->assertSame($business->user->name, $replaced['actor']);
        $this->assertSame('web', $replaced['source']);
        $this->assertSame([$business->id], Activity::query()->where('log_name', 'transaksi')->distinct()->pluck('business_id')->all());
    }

    public function test_a_price_change_is_logged_but_stock_moving_on_a_sale_is_not(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 15000);
        $this->actingAs($business->user);

        $water->update(['price' => 12000]);
        $this->service()->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 12000]]]);

        $this->assertSame([['created', 'Menambah produk Air Mineral', null], ['updated', 'Mengubah produk Air Mineral', null]], $this->entries($water));
        $this->assertSame(
            [['label' => 'Harga jual', 'old' => 'Rp15.000', 'new' => 'Rp12.000']],
            $this->resource(Activity::query()->forSubject($water)->where('event', 'updated')->sole())['changes'],
        );
    }

    public function test_a_new_product_logs_only_what_the_owner_changes(): void
    {
        $business = Business::factory()->create();
        // Only what the owner types; the toggles keep their database defaults.
        $water = $business->items()->create(['kind' => 'produk', 'name' => 'Air Mineral', 'unit' => 'botol', 'price' => 4000]);

        app(Inventory::class)->receive($water, 24, 2500, StockReason::StokAwal, '3101');
        $this->actingAs($business->user)->put(route('items.update', $water), ['name' => 'Air Mineral', 'unit' => 'botol', 'price' => 5000]);

        $this->assertSame([['created', 'Menambah produk Air Mineral', null], ['updated', 'Mengubah produk Air Mineral', null]], $this->entries($water));
        $this->assertSame(
            [['label' => 'Harga jual', 'old' => 'Rp4.000', 'new' => 'Rp5.000']],
            $this->resource(Activity::query()->forSubject($water)->where('event', 'updated')->sole())['changes'],
        );
    }

    public function test_a_recipe_change_and_a_stock_count_are_logged_with_old_and_new(): void
    {
        $business = Business::factory()->create();
        $rice = $this->stocked($business, 'Beras', 10, 14000, material: true);
        $egg = $this->stocked($business, 'Telur', 30, 2200, material: true);
        $friedRice = Item::factory()->recipe()->for($business)->create(['name' => 'Nasi Goreng', 'unit' => 'porsi', 'price' => 15000]);
        $friedRice->recipeLines()->create(['material_id' => $rice->id, 'qty' => 0.15]);
        $this->actingAs($business->user);
        $update = fn (array $recipe) => $this->put(route('items.update', $friedRice), ['name' => 'Nasi Goreng', 'unit' => 'porsi', 'price' => 15000, 'recipe' => $recipe]);

        $update([['material_id' => $rice->id, 'qty' => 0.15], ['material_id' => $egg->id, 'qty' => 1]])->assertSessionHasNoErrors();
        $update([['material_id' => $rice->id, 'qty' => 0.15], ['material_id' => $egg->id, 'qty' => 1]])->assertSessionHasNoErrors();
        $this->post(route('items.adjust-stock', $egg), ['physical_stock' => 28])->assertSessionHasNoErrors();

        $recipe = Activity::query()->forSubject($friedRice)->where('event', 'recipe_updated')->sole();
        $this->assertSame([['label' => 'Resep', 'old' => '0,15 kg Beras', 'new' => '0,15 kg Beras, 1 butir Telur']], $this->resource($recipe)['changes']);

        $count = $this->resource(Activity::query()->forSubject($egg)->where('event', 'stock_adjusted')->sole());
        $this->assertSame('Mengoreksi stok Telur', $count['description']);
        $this->assertSame([['label' => 'Stok', 'old' => '30 butir', 'new' => '28 butir']], $count['changes']);
        $this->assertSame('Nilai selisih Rp4.400', $count['detail']);
    }

    public function test_archiving_a_product_is_logged(): void
    {
        $business = Business::factory()->create();
        $chips = Item::factory()->for($business)->create(['name' => 'Kerupuk', 'price' => 2000]);

        $this->actingAs($business->user)->delete(route('items.destroy', $chips));

        $archived = $this->resource(Activity::query()->forSubject($chips)->where('event', 'deleted')->sole());
        $this->assertSame('Mengarsipkan produk Kerupuk', $archived['description']);
        // An archived product has no edit page to link to.
        $this->assertNull($archived['href']);
    }

    public function test_sign_ins_and_failed_attempts_are_logged_for_the_account_without_secrets(): void
    {
        $business = Business::factory()->create();
        $email = $business->user->email;

        $this->post(route('login.store'), ['email' => $email, 'password' => 'salah-sekali']);
        $this->post(route('login.store'), ['email' => $email, 'password' => 'password']);
        $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])->assertSessionHasNoErrors();

        $entries = Activity::query()->where('business_id', $business->id)->where('log_name', 'akun')->oldest('id')->get();
        $this->assertSame(['login_failed', 'login', 'password_changed'], $entries->pluck('event')->all());

        $failed = $this->resource($entries[0]);
        $this->assertSame('Tidak dikenal', $failed['actor']);
        $this->assertSame("Email yang dicoba: {$email} · IP 127.0.0.1", $failed['detail']);
        $this->assertSame($business->user->name, $this->resource($entries[1])['actor']);

        $stored = Activity::query()->get()->toJson();
        $this->assertStringNotContainsString('salah-sekali', $stored);
        $this->assertStringNotContainsString('rahasia-baru', $stored);
    }

    public function test_the_log_is_append_only(): void
    {
        $business = Business::factory()->create();
        $entry = Item::factory()->for($business)->create()->activitiesAsSubject()->sole();

        foreach ([fn () => $entry->update(['description' => 'Diubah diam-diam']), fn () => $entry->delete()] as $tamper) {
            try {
                $tamper();
                $this->fail('An activity was changed or deleted.');
            } catch (LogicException) {
                $this->assertSame(1, Activity::query()->count());
            }
        }

        $this->assertNotSame('Diubah diam-diam', $entry->refresh()->description);
    }

    public function test_the_log_page_shows_the_own_business_only_and_filters_by_category(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        Item::factory()->for($business)->create(['name' => 'Kopi']);
        $this->service()->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 100000]);
        Item::factory()->for(Business::factory()->create())->create(['name' => 'Milik orang lain']);

        $this->get(route('activity.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('activity/index')
            ->has('activities.data', 2)
            ->where('activities.data.0.description', 'Mencatat modal: Setor modal')
            ->where('activities.data.0.href', route('transactions.show', Transaction::query()->sole()))
            ->where('activities.data.1.description', 'Menambah produk Kopi'));

        $this->get(route('activity.index', ['kategori' => ['produk']]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activities.data', 1)
            ->where('activities.query.kategori', ['produk']));
    }

    public function test_each_record_shows_its_own_history(): void
    {
        $business = Business::factory()->create();
        $water = $this->stocked($business, 'Air Mineral', 10, 2500, price: 4000);
        $this->actingAs($business->user);
        $sale = $this->service()->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 1, 'price' => 4000]]]);

        $this->get(route('transactions.show', $sale))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activities', 1)
            ->where('activities.0.description', 'Mencatat penjualan: 1 Air Mineral'));
        $this->get(route('items.edit', $water))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activities', 1)
            ->where('activities.0.description', 'Menambah produk Air Mineral'));
    }

    private function service(): TransactionService
    {
        return app(TransactionService::class);
    }

    /**
     * Event, description, and detail of each entry about $subject, oldest first.
     *
     * @return list<array{0: string|null, 1: string, 2: string|null}>
     */
    private function entries(Transaction|Item $subject): array
    {
        return Activity::query()->forSubject($subject)->with(['causer', 'subject'])->oldest('id')->get()
            ->map(fn (Activity $activity) => [$activity->event, $activity->description, $this->resource($activity)['detail']])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(Activity $activity): array
    {
        return ActivityResource::make($activity->load(['causer', 'subject']))->resolve();
    }

    private function stocked(Business $business, string $name, float $qty, int $unitCost, ?int $price = null, bool $material = false): Item
    {
        $factory = $material ? Item::factory()->material() : Item::factory();
        $item = $factory->for($business)->create(['name' => $name, 'price' => $material ? null : $price, 'unit' => $name === 'Telur' ? 'butir' : ($material ? 'kg' : 'botol')]);
        app(Inventory::class)->receive($item, $qty, $unitCost, StockReason::StokAwal, '3101');

        return $item->refresh();
    }
}
