<?php

namespace App\Support;

use App\Enums\BusinessCategory;
use App\Enums\ItemKind;

/**
 * Example products per kind of business, offered by "Pakai contoh" in the
 * onboarding wizard and used by the demo seeder. Prices and stock are
 * examples the owner edits; the wizard labels them so.
 *
 * @phpstan-type Row array{name: string, kind: ItemKind, unit: string, price: int|null, min_stock: float|null, opening_stock: float, opening_cost: int, aliases: list<string>, recipe: list<array{name: string, qty: float}>}
 */
final class StarterCatalog
{
    /**
     * @return list<Row>
     */
    public static function for(BusinessCategory $category): array
    {
        return match ($category) {
            BusinessCategory::Kuliner => self::kuliner(),
            BusinessCategory::Kelontong => self::kelontong(),
            BusinessCategory::Fashion => self::fashion(),
            BusinessCategory::Kerajinan => self::kerajinan(),
            BusinessCategory::Lainnya => [],
        };
    }

    /**
     * One example row; ingredients come before the recipes that use them.
     *
     * @param  list<string>  $aliases
     * @param  array<string, float|int>  $recipe  ingredient name => qty per unit
     * @return Row
     */
    private static function row(string $name, ItemKind $kind, string $unit, ?int $price, float|int|null $minStock, float|int $openingStock, int $openingCost, array $aliases, array $recipe = []): array
    {
        return [
            'name' => $name,
            'kind' => $kind,
            'unit' => $unit,
            'price' => $price,
            'min_stock' => $minStock === null ? null : (float) $minStock,
            'opening_stock' => (float) $openingStock,
            'opening_cost' => $openingCost,
            'aliases' => $aliases,
            'recipe' => array_map(fn (string $ingredient, float|int $qty) => ['name' => $ingredient, 'qty' => (float) $qty], array_keys($recipe), $recipe),
        ];
    }

    /**
     * @return list<Row>
     */
    private static function kuliner(): array
    {
        return [
            self::row('Beras', ItemKind::Bahan, 'kg', null, 5, 9, 14000, []),
            self::row('Telur', ItemKind::Bahan, 'butir', null, 30, 40, 2200, []),
            self::row('Minyak goreng', ItemKind::Bahan, 'liter', null, 2, 4, 18000, ['minyak']),
            self::row('Bumbu & kecap', ItemKind::Bahan, 'porsi', null, 20, 60, 1540, ['bumbu']),
            self::row('Mie telur', ItemKind::Bahan, 'bungkus', null, 15, 45, 3000, ['mie']),
            self::row('Teh celup', ItemKind::Bahan, 'kantong', null, 50, 90, 400, ['teh']),
            self::row('Gula pasir', ItemKind::Bahan, 'kg', null, 1, 2.5, 16000, ['gula']),
            self::row('Air Mineral 600 ml', ItemKind::Produk, 'botol', 4000, 12, 9, 2500, ['aqua', 'air mineral', 'air']),
            self::row('Kerupuk', ItemKind::Produk, 'bungkus', 2000, 20, 80, 1000, ['krupuk']),
            self::row('Nasi Goreng', ItemKind::Komposisi, 'porsi', 15000, null, 0, 0, ['nasgor', 'nasi goreng biasa'], ['Beras' => 0.15, 'Telur' => 1, 'Minyak goreng' => 0.02, 'Bumbu & kecap' => 1, 'Kerupuk' => 1]),
            self::row('Mie Goreng', ItemKind::Komposisi, 'porsi', 14000, null, 0, 0, ['miegor', 'mie goreng biasa'], ['Mie telur' => 1, 'Telur' => 1, 'Minyak goreng' => 0.02, 'Bumbu & kecap' => 1]),
            self::row('Es Teh', ItemKind::Komposisi, 'gelas', 5000, null, 0, 0, ['esteh', 'es teh manis', 'teh manis'], ['Teh celup' => 1, 'Gula pasir' => 0.03]),
        ];
    }

    /**
     * @return list<Row>
     */
    private static function kelontong(): array
    {
        return [
            self::row('Beras 5 kg', ItemKind::Produk, 'karung', 75000, 3, 10, 68000, ['beras']),
            self::row('Minyak goreng 1 liter', ItemKind::Produk, 'botol', 18000, 6, 24, 16000, ['minyak']),
            self::row('Gula pasir 1 kg', ItemKind::Produk, 'bungkus', 17000, 5, 20, 15500, ['gula']),
            self::row('Telur', ItemKind::Produk, 'kg', 29000, 2, 10, 26500, []),
            self::row('Mie instan', ItemKind::Produk, 'bungkus', 3500, 20, 80, 2800, ['indomie', 'mie']),
            self::row('Kopi sachet', ItemKind::Produk, 'sachet', 2000, 20, 60, 1500, ['kopi']),
            self::row('Air mineral 600 ml', ItemKind::Produk, 'botol', 4000, 12, 48, 2500, ['aqua', 'air']),
            self::row('Sabun cuci piring', ItemKind::Produk, 'botol', 15000, 4, 12, 12000, ['sabun']),
        ];
    }

    /**
     * @return list<Row>
     */
    private static function fashion(): array
    {
        return [
            self::row('Kaos polos', ItemKind::Produk, 'pcs', 65000, 5, 30, 40000, ['kaos']),
            self::row('Kemeja batik', ItemKind::Produk, 'pcs', 150000, 3, 15, 95000, ['batik', 'kemeja']),
            self::row('Celana chino', ItemKind::Produk, 'pcs', 135000, 3, 12, 85000, ['celana']),
            self::row('Hijab segi empat', ItemKind::Produk, 'pcs', 45000, 5, 40, 25000, ['hijab', 'kerudung']),
            self::row('Kain katun', ItemKind::Bahan, 'meter', null, 10, 20, 25000, ['kain']),
            self::row('Kancing', ItemKind::Bahan, 'pcs', null, 50, 200, 300, []),
        ];
    }

    /**
     * @return list<Row>
     */
    private static function kerajinan(): array
    {
        return [
            self::row('Rotan', ItemKind::Bahan, 'kg', null, 2, 10, 30000, []),
            self::row('Benang', ItemKind::Bahan, 'gulung', null, 5, 20, 12000, []),
            self::row('Cat kayu', ItemKind::Bahan, 'kaleng', null, 1, 5, 45000, ['cat']),
            self::row('Tas anyaman', ItemKind::Produk, 'pcs', 120000, 2, 8, 70000, ['tas']),
            self::row('Gantungan kunci', ItemKind::Produk, 'pcs', 15000, 10, 50, 6000, ['gantungan']),
            self::row('Hiasan dinding', ItemKind::Produk, 'pcs', 85000, 2, 6, 45000, ['hiasan']),
        ];
    }
}
