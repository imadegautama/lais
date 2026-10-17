<?php

namespace Tests\Feature;

use App\Enums\StockReason;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use App\Services\Inventory;
use App\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('items.index'))->assertRedirect(route('login'));
    }

    public function test_the_list_shows_only_the_owners_items_with_cost_and_stock_flags(): void
    {
        $business = Business::factory()->create();
        $beras = Item::factory()->material()->for($business)->create(['name' => 'Beras', 'min_stock' => 5]);
        app(Inventory::class)->receive($beras, 3, 14000, StockReason::StokAwal, '3101');
        Item::factory()->create(['name' => 'Milik orang lain']);

        $this->actingAs($business->user)
            ->get(route('items.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('items/index')
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Beras')
                ->where('items.data.0.unitCost', 14000)
                ->where('items.data.0.lowStock', true)
                ->where('summary.itemCount', 1)
                ->where('summary.inventoryValue', 42000)
                ->where('summary.lowStockNames', ['Beras']));
    }

    public function test_the_list_is_paginated_twenty_per_page(): void
    {
        $business = Business::factory()->create();
        Item::factory()->count(25)->for($business)->create();
        $this->actingAs($business->user);

        $this->get(route('items.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('items.data', 20)
            ->where('items.meta', ['page' => 1, 'perPage' => 20, 'total' => 25, 'lastPage' => 2]));

        $this->get(route('items.index', ['halaman' => 2]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('items.data', 5)
            ->where('items.meta.page', 2));

        $this->get(route('items.index', ['halaman' => 99]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('items.data', 5)
            ->where('items.meta.page', 2));

        $this->get(route('items.index', ['halaman' => 'abc']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.meta.page', 1));
    }

    public function test_search_matches_names_and_aliases_within_the_business(): void
    {
        $business = Business::factory()->create();
        Item::factory()->for($business)->create(['name' => 'Air Mineral', 'aliases' => ['aqua', 'air']]);
        Item::factory()->for($business)->create(['name' => 'Kopi Susu']);
        Item::factory()->create(['name' => 'Aqua Botol']);
        $this->actingAs($business->user);

        $names = fn (array $query) => array_column($this->get(route('items.index', $query))->inertiaProps('items.data'), 'name');

        $this->assertSame(['Air Mineral'], $names(['cari' => 'AQUA']));
        $this->assertSame(['Kopi Susu'], $names(['cari' => 'kopi']));
    }

    public function test_the_kind_filter_keeps_only_known_kinds(): void
    {
        $business = Business::factory()->create();
        Item::factory()->material()->for($business)->create(['name' => 'Beras']);
        Item::factory()->for($business)->create(['name' => 'Kerupuk']);
        $this->actingAs($business->user);

        $this->get(route('items.index', ['jenis' => ['bahan', 'palsu']]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Beras')
                ->where('items.query.jenis', ['bahan']));

        $this->get(route('items.index', ['jenis' => [['palsu']]]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items.data', 2));
    }

    public function test_sorting_by_margin_and_cost_follows_the_php_numbers_with_unpriced_items_last(): void
    {
        $business = Business::factory()->create();
        $inventory = app(Inventory::class);
        $thin = Item::factory()->for($business)->create(['name' => 'Tipis', 'price' => 10000]);
        $inventory->receive($thin, 1, 8000, StockReason::StokAwal, '3101');
        $wide = Item::factory()->for($business)->create(['name' => 'Lebar', 'price' => 10000]);
        $inventory->receive($wide, 1, 2500, StockReason::StokAwal, '3101');
        $material = Item::factory()->material()->for($business)->create(['name' => 'Bahan']);
        $inventory->receive($material, 10, 6000, StockReason::StokAwal, '3101');
        $recipe = Item::factory()->recipe()->for($business)->create(['name' => 'Resep', 'price' => 10000]);
        $recipe->recipeLines()->create(['material_id' => $material->id, 'qty' => 0.5]);
        $this->actingAs($business->user);

        $names = fn (string $sort) => array_column($this->get(route('items.index', ['urut' => $sort]))->inertiaProps('items.data'), 'name');

        // Margins: Tipis 20%, Resep 70%, Lebar 75%; Bahan has no price.
        $this->assertSame(['Tipis', 'Resep', 'Lebar', 'Bahan'], $names('margin'));
        $this->assertSame(['Lebar', 'Resep', 'Tipis', 'Bahan'], $names('-margin'));
        // Costs: Lebar 2.500, Resep 3.000 (0,5 × 6.000), Bahan 6.000, Tipis 8.000.
        $this->assertSame(['Lebar', 'Resep', 'Bahan', 'Tipis'], $names('biaya'));
    }

    public function test_sorting_by_cost_keeps_fractions_of_a_rupiah(): void
    {
        $business = Business::factory()->create();
        $inventory = app(Inventory::class);
        $fraction = Item::factory()->material()->for($business)->create(['name' => 'Pecahan']);
        $inventory->receive($fraction, 1, 3333, StockReason::StokAwal, '3101');
        $inventory->receive($fraction->refresh(), 2, 3334, StockReason::StokAwal, '3101');
        $whole = Item::factory()->material()->for($business)->create(['name' => 'Bulat']);
        $inventory->receive($whole, 1, 3333, StockReason::StokAwal, '3101');
        $this->actingAs($business->user);

        // 10.001 / 3 = 3.333,67 sorts after 3.333; integer division would tie them.
        $order = array_column($this->get(route('items.index', ['urut' => 'biaya']))->inertiaProps('items.data'), 'name');

        $this->assertSame(['Bulat', 'Pecahan'], $order);
    }

    public function test_an_unknown_sort_falls_back_to_name(): void
    {
        $business = Business::factory()->create();
        Item::factory()->for($business)->create(['name' => 'Zebra']);
        Item::factory()->for($business)->create(['name' => 'Apel']);
        $this->actingAs($business->user);

        foreach (['password', ['x']] as $sort) {
            $this->get(route('items.index', ['urut' => $sort]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('items.data.0.name', 'Apel')
                    ->where('items.query.urut', 'nama'));
        }
    }

    public function test_reloading_the_table_skips_the_summary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('items.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->reloadOnly('items', fn (AssertableInertia $reload) => $reload->missing('summary')));
    }

    public function test_storing_a_product_with_opening_stock_books_it_against_owners_capital(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('items.store'), [
                'kind' => 'produk',
                'name' => 'Air Mineral',
                'aliases' => 'aqua, air',
                'unit' => 'botol',
                'price' => 4000,
                'opening_stock' => 24,
                'opening_cost' => 2500,
            ])
            ->assertRedirect(route('items.index'));

        $item = Item::query()->sole();
        $this->assertSame(['aqua', 'air'], $item->aliases);
        $this->assertSame(60_000, $item->stock_value);
        $this->assertSame(60_000, app(Ledger::class)->balance($user->business, '1301'));
        $this->assertSame(60_000, app(Ledger::class)->balance($user->business, '3101'));
    }

    public function test_storing_a_recipe_product_saves_its_ingredients(): void
    {
        $business = Business::factory()->create();
        $beras = Item::factory()->material()->for($business)->create();

        $this->actingAs($business->user)
            ->post(route('items.store'), [
                'kind' => 'komposisi',
                'name' => 'Nasi Goreng',
                'unit' => 'porsi',
                'price' => 15000,
                'recipe' => [['material_id' => $beras->id, 'qty' => '0.15']],
            ])
            ->assertRedirect(route('items.index'));

        $product = Item::query()->where('name', 'Nasi Goreng')->sole();
        $this->assertSame('0.150', $product->recipeLines()->sole()->qty);
        $this->assertSame('0.000', $product->stock);
    }

    public function test_the_unit_is_stored_trimmed_and_in_lower_case(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('items.store'), ['kind' => 'bahan', 'name' => 'Beras', 'unit' => '  Kg '])
            ->assertRedirect(route('items.index'));

        $this->assertSame('kg', Item::query()->sole()->unit);
    }

    public function test_only_recipe_products_can_be_switched_off_as_unavailable(): void
    {
        $business = Business::factory()->create();
        $beras = Item::factory()->material()->for($business)->create();
        $this->actingAs($business->user);

        $this->post(route('items.store'), ['kind' => 'produk', 'name' => 'Kerupuk', 'unit' => 'bungkus', 'price' => 2000, 'available' => false]);
        $this->post(route('items.store'), [
            'kind' => 'komposisi', 'name' => 'Nasi Goreng', 'unit' => 'porsi', 'price' => 15000, 'available' => false,
            'recipe' => [['material_id' => $beras->id, 'qty' => 0.15]],
        ]);

        // A stocked product is sold out by its stock, so the switch does not apply to it.
        $this->assertTrue(Item::query()->where('name', 'Kerupuk')->sole()->available);
        $this->assertFalse(Item::query()->where('name', 'Nasi Goreng')->sole()->available);
    }

    public function test_a_recipe_product_needs_at_least_one_ingredient(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('items.store'), ['kind' => 'komposisi', 'name' => 'Nasi Goreng', 'unit' => 'porsi', 'price' => 15000])
            ->assertSessionHasErrors(['recipe' => 'Tambahkan minimal satu bahan ke resep.']);
    }

    public function test_a_recipe_cannot_use_another_businesses_ingredient(): void
    {
        $user = User::factory()->create();
        $foreign = Item::factory()->material()->create();

        $this->actingAs($user)
            ->post(route('items.store'), [
                'kind' => 'komposisi',
                'name' => 'Nasi Goreng',
                'unit' => 'porsi',
                'price' => 15000,
                'recipe' => [['material_id' => $foreign->id, 'qty' => 1]],
            ])
            ->assertSessionHasErrors(['recipe.0.material_id' => 'Bahan tidak ditemukan.']);
    }

    public function test_opening_stock_needs_a_unit_cost(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('items.store'), ['kind' => 'bahan', 'name' => 'Beras', 'unit' => 'kg', 'opening_stock' => 10])
            ->assertSessionHasErrors(['opening_cost' => 'Isi biaya per unit untuk stok awal.']);
    }

    public function test_updating_an_item_does_not_touch_its_stock(): void
    {
        $business = Business::factory()->create();
        $item = Item::factory()->for($business)->create(['name' => 'Kerupuk']);
        app(Inventory::class)->receive($item, 10, 1000, StockReason::StokAwal, '3101');

        $this->actingAs($business->user)
            ->put(route('items.update', $item), ['name' => 'Kerupuk Udang', 'unit' => 'bungkus', 'price' => 2500])
            ->assertRedirect(route('items.index'));

        $item->refresh();
        $this->assertSame('Kerupuk Udang', $item->name);
        $this->assertSame('10.000', $item->stock);
        $this->assertSame(10_000, $item->stock_value);
    }

    public function test_another_businesses_item_is_not_found(): void
    {
        $user = User::factory()->create();
        $foreign = Item::factory()->create();

        $this->actingAs($user)->get(route('items.edit', $foreign))->assertNotFound();
        $this->actingAs($user)->put(route('items.update', $foreign), ['name' => 'x', 'unit' => 'pcs', 'price' => 1])->assertNotFound();
    }

    public function test_a_stock_count_corrects_stock_through_the_journal(): void
    {
        $business = Business::factory()->create();
        $beras = Item::factory()->material()->for($business)->create();
        app(Inventory::class)->receive($beras, 10, 14000, StockReason::StokAwal, '3101');

        $this->actingAs($business->user)
            ->from(route('items.index'))
            ->post(route('items.adjust-stock', $beras), ['physical_stock' => 8])
            ->assertRedirect(route('items.index'));

        $this->assertSame('8.000', $beras->refresh()->stock);
        $this->assertSame(28_000, app(Ledger::class)->balance($business, '6201'));
    }

    public function test_an_ingredient_used_by_a_recipe_cannot_be_archived(): void
    {
        $business = Business::factory()->create();
        $beras = Item::factory()->material()->for($business)->create();
        $product = Item::factory()->recipe()->for($business)->create();
        $product->recipeLines()->create(['material_id' => $beras->id, 'qty' => 0.15]);

        $this->actingAs($business->user)
            ->from(route('items.index'))
            ->delete(route('items.destroy', $beras))
            ->assertRedirect(route('items.index'));

        $this->assertNotSoftDeleted($beras);
    }

    public function test_an_item_with_stock_on_hand_cannot_be_archived(): void
    {
        $business = Business::factory()->create();
        $item = Item::factory()->for($business)->create();
        app(Inventory::class)->receive($item, 3, 12000, StockReason::StokAwal, '3101');

        $this->actingAs($business->user)
            ->from(route('items.edit', $item))
            ->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.edit', $item));

        $this->assertNotSoftDeleted($item);
    }

    public function test_an_unused_item_is_archived(): void
    {
        $business = Business::factory()->create();
        $item = Item::factory()->for($business)->create();

        $this->actingAs($business->user)
            ->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.index'));

        $this->assertSoftDeleted($item);
    }
}
