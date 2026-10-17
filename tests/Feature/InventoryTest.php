<?php

namespace Tests\Feature;

use App\Enums\StockReason;
use App\Models\Business;
use App\Models\Item;
use App\Services\Inventory;
use App\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_stock_updates_the_weighted_average_cost(): void
    {
        $telur = Item::factory()->material()->create(['unit' => 'butir']);
        $inventory = app(Inventory::class);

        $inventory->receive($telur, 30, 2000, StockReason::StokAwal, '3101');
        $inventory->receive($telur->refresh(), 30, 2400, StockReason::StokAwal, '3101');

        $this->assertSame(2200.0, $telur->refresh()->averageCost());
        $this->assertSame(132_000, $telur->stock_value);
    }

    public function test_recipe_cost_is_the_sum_of_ingredients_at_average_cost(): void
    {
        $business = Business::factory()->create();
        $inventory = app(Inventory::class);
        $nasiGoreng = Item::factory()->recipe()->for($business)->create(['price' => 15000]);

        foreach ([[0.15, 10, 14000], [1, 30, 2200], [0.02, 2, 18000], [1, 50, 1540], [1, 40, 1000]] as [$qty, $stock, $cost]) {
            $material = Item::factory()->material()->for($business)->create();
            $inventory->receive($material, $stock, $cost, StockReason::StokAwal, '3101');
            $nasiGoreng->recipeLines()->create(['material_id' => $material->id, 'qty' => $qty]);
        }

        $nasiGoreng->load('recipeLines.material');

        $this->assertSame(7200, $nasiGoreng->unitCost());
        $this->assertSame(52.0, $nasiGoreng->marginPercent());
    }

    public function test_counting_less_stock_than_recorded_books_the_shortage(): void
    {
        $beras = Item::factory()->material()->create();
        $inventory = app(Inventory::class);
        $inventory->receive($beras, 10, 14000, StockReason::StokAwal, '3101');

        $inventory->adjust($beras->refresh(), 8.5);

        $this->assertSame('8.500', $beras->refresh()->stock);
        $this->assertSame(119_000, $beras->stock_value);
        $this->assertSame(21_000, app(Ledger::class)->balance($beras->business, '6201'));
    }

    public function test_counting_down_to_zero_leaves_no_value_behind(): void
    {
        $gula = Item::factory()->material()->create();
        $inventory = app(Inventory::class);
        $inventory->receive($gula, 3, 16333, StockReason::StokAwal, '3101');

        $inventory->adjust($gula->refresh(), 0);

        $this->assertSame(0, $gula->refresh()->stock_value);
        $this->assertSame(0, app(Ledger::class)->balance($gula->business, '1301'));
    }
}
