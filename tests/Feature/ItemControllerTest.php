<?php

namespace Tests\Feature;

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
        app(Inventory::class)->receive($beras, 3, 14000, 'stok_awal', '3101');
        Item::factory()->create(['name' => 'Milik orang lain']);

        $this->actingAs($business->user)
            ->get(route('items.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('items/index')
                ->has('items', 1)
                ->where('items.0.name', 'Beras')
                ->where('items.0.unitCost', 14000)
                ->where('items.0.lowStock', true)
                ->where('summary.inventoryValue', 42000));
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
        app(Inventory::class)->receive($item, 10, 1000, 'stok_awal', '3101');

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
        app(Inventory::class)->receive($beras, 10, 14000, 'stok_awal', '3101');

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
        app(Inventory::class)->receive($item, 3, 12000, 'stok_awal', '3101');

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
