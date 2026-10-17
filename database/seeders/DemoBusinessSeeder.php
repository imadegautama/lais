<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use App\Services\Inventory;
use Illuminate\Database\Seeder;

/**
 * Example business "Warung Bu Rina" for the demo and the video (PRD §2.6).
 * Safe to run more than once: it skips when the business already has items.
 */
class DemoBusinessSeeder extends Seeder
{
    /**
     * name, kind, unit, price, min stock, opening stock, unit cost, aliases
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int|null, 4: float|null, 5: float, 6: int, 7: list<string>}>
     */
    private const STOCKED = [
        ['Beras', 'bahan', 'kg', null, 5, 9, 14000, []],
        ['Telur', 'bahan', 'butir', null, 30, 40, 2200, []],
        ['Minyak goreng', 'bahan', 'liter', null, 2, 4, 18000, ['minyak']],
        ['Bumbu & kecap', 'bahan', 'porsi', null, 20, 60, 1540, ['bumbu']],
        ['Mie telur', 'bahan', 'bungkus', null, 15, 45, 3000, ['mie']],
        ['Teh celup', 'bahan', 'kantong', null, 50, 90, 400, ['teh']],
        ['Gula pasir', 'bahan', 'kg', null, 1, 2.5, 16000, ['gula']],
        ['Air Mineral 600 ml', 'produk', 'botol', 4000, 12, 9, 2500, ['aqua', 'air mineral', 'air']],
        ['Kerupuk', 'produk', 'bungkus', 2000, 20, 80, 1000, ['krupuk']],
    ];

    /**
     * name, price, aliases, [ingredient => qty]
     *
     * @var list<array{0: string, 1: int, 2: list<string>, 3: array<string, float>}>
     */
    private const RECIPES = [
        ['Nasi Goreng', 15000, ['nasgor', 'nasi goreng biasa'], ['Beras' => 0.15, 'Telur' => 1, 'Minyak goreng' => 0.02, 'Bumbu & kecap' => 1, 'Kerupuk' => 1]],
        ['Mie Goreng', 14000, ['miegor', 'mie goreng biasa'], ['Mie telur' => 1, 'Telur' => 1, 'Minyak goreng' => 0.02, 'Bumbu & kecap' => 1]],
        ['Es Teh', 5000, ['esteh', 'es teh manis', 'teh manis'], ['Teh celup' => 1, 'Gula pasir' => 0.03]],
    ];

    public function run(Inventory $inventory): void
    {
        $user = User::query()->where('email', 'demo@laris.test')->first() ?? User::query()->first();

        if (! $user) {
            return;
        }

        $business = Business::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'Warung Bu Rina', 'slug' => 'warung-bu-rina'],
        );

        if ($business->items()->exists()) {
            return;
        }

        $items = [];

        foreach (self::STOCKED as [$name, $kind, $unit, $price, $min, $stock, $cost, $aliases]) {
            $item = $business->items()->create([
                'kind' => $kind,
                'name' => $name,
                'aliases' => $aliases,
                'unit' => $unit,
                'price' => $price,
                'min_stock' => $min,
                'show_in_store' => $kind === 'produk',
            ]);
            $inventory->receive($item, $stock, $cost, 'stok_awal', '3101');
            $items[$name] = $item;
        }

        foreach (self::RECIPES as [$name, $price, $aliases, $ingredients]) {
            $product = $business->items()->create([
                'kind' => 'komposisi',
                'name' => $name,
                'aliases' => $aliases,
                'unit' => $name === 'Es Teh' ? 'gelas' : 'porsi',
                'price' => $price,
            ]);

            foreach ($ingredients as $ingredient => $qty) {
                $product->recipeLines()->create(['material_id' => $items[$ingredient]->id, 'qty' => $qty]);
            }
        }
    }
}
