<?php

namespace Database\Seeders;

use App\Enums\ItemKind;
use App\Enums\MoneyAccountKind;
use App\Enums\StockReason;
use App\Models\Account;
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
     * @var list<array{0: string, 1: ItemKind, 2: string, 3: int|null, 4: float|null, 5: float, 6: int, 7: list<string>}>
     */
    private const STOCKED = [
        ['Beras', ItemKind::Bahan, 'kg', null, 5, 9, 14000, []],
        ['Telur', ItemKind::Bahan, 'butir', null, 30, 40, 2200, []],
        ['Minyak goreng', ItemKind::Bahan, 'liter', null, 2, 4, 18000, ['minyak']],
        ['Bumbu & kecap', ItemKind::Bahan, 'porsi', null, 20, 60, 1540, ['bumbu']],
        ['Mie telur', ItemKind::Bahan, 'bungkus', null, 15, 45, 3000, ['mie']],
        ['Teh celup', ItemKind::Bahan, 'kantong', null, 50, 90, 400, ['teh']],
        ['Gula pasir', ItemKind::Bahan, 'kg', null, 1, 2.5, 16000, ['gula']],
        ['Air Mineral 600 ml', ItemKind::Produk, 'botol', 4000, 12, 9, 2500, ['aqua', 'air mineral', 'air']],
        ['Kerupuk', ItemKind::Produk, 'bungkus', 2000, 20, 80, 1000, ['krupuk']],
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
        $user = User::query()->where('email', 'demo@lais.test')->first() ?? User::query()->first();

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

        // The rekening a warung typically has: its bank and an e-wallet for QRIS.
        $business->moneyAccounts()->where('kind', MoneyAccountKind::NonTunai)->first()?->update(['name' => 'BCA']);
        $business->moneyAccounts()->firstOrCreate(['name' => 'GoPay'], ['kind' => MoneyAccountKind::NonTunai]);

        $items = [];

        foreach (self::STOCKED as [$name, $kind, $unit, $price, $min, $stock, $cost, $aliases]) {
            $item = $business->items()->create([
                'kind' => $kind,
                'name' => $name,
                'aliases' => $aliases,
                'unit' => $unit,
                'price' => $price,
                'min_stock' => $min,
                'show_in_store' => $kind === ItemKind::Produk,
            ]);
            $inventory->receive($item, $stock, $cost, StockReason::StokAwal, Account::OWNER_CAPITAL);
            $items[$name] = $item;
        }

        foreach (self::RECIPES as [$name, $price, $aliases, $ingredients]) {
            $product = $business->items()->create([
                'kind' => ItemKind::Komposisi,
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
