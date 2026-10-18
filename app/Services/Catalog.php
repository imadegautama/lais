<?php

namespace App\Services;

use App\Enums\ItemKind;
use App\Enums\StockReason;
use App\Models\Account;
use App\Models\Business;
use App\Models\Item;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds products and ingredients the way the product form does: the item, its
 * recipe, and its opening stock booked against Modal Pemilik. The form, the
 * onboarding wizard, and the demo seeder all come through here.
 */
class Catalog
{
    public function __construct(private Inventory $inventory) {}

    /**
     * @param  array<string, mixed>  $data  validated ItemRequest data; recipe as material_id + qty
     * @param  CarbonInterface|null  $openedAt  dates the opening stock (onboarding's start date)
     */
    public function create(Business $business, array $data, ?CarbonInterface $openedAt = null): Item
    {
        return DB::transaction(function () use ($business, $data, $openedAt) {
            $kind = $data['kind'] instanceof ItemKind ? $data['kind'] : ItemKind::from((string) $data['kind']);
            $item = $business->items()->create(['kind' => $kind] + self::attributes($data, $kind));

            if ($kind === ItemKind::Komposisi) {
                $item->recipeLines()->createMany($data['recipe']);
            }

            if ((float) ($data['opening_stock'] ?? 0) > 0) {
                $this->inventory->receive($item, (float) $data['opening_stock'], (int) $data['opening_cost'], StockReason::StokAwal, Account::OWNER_CAPITAL, $openedAt);
            }

            return $item;
        });
    }

    /**
     * Several new items at once (onboarding, the demo seeder). Ingredients and
     * goods are created first, so recipe products can name their ingredients
     * among them or among the business's items.
     *
     * @param  list<array<string, mixed>>  $rows  like create(), with recipe as a list of ['name' => ..., 'qty' => ...]
     * @param  string  $errorKey  where an unknown ingredient is reported: "{$errorKey}.{index}.recipe"
     * @return list<Item>
     */
    public function createMany(Business $business, array $rows, ?CarbonInterface $openedAt = null, string $errorKey = 'products'): array
    {
        return DB::transaction(function () use ($business, $rows, $openedAt, $errorKey) {
            $kindOf = fn (array $row) => $row['kind'] instanceof ItemKind ? $row['kind'] : ItemKind::from((string) $row['kind']);
            $created = [];

            foreach ($rows as $index => $row) {
                if ($kindOf($row) !== ItemKind::Komposisi) {
                    $created[$index] = $this->create($business, ['recipe' => null] + $row, $openedAt);
                }
            }

            $stocked = $business->items()->whereIn('items.kind', ItemKind::stocked())->pluck('items.id', 'items.name');

            foreach ($rows as $index => $row) {
                if ($kindOf($row) === ItemKind::Komposisi) {
                    $recipe = array_map(fn (array $line) => [
                        'material_id' => $stocked[$line['name']] ?? throw ValidationException::withMessages([
                            "{$errorKey}.{$index}.recipe" => "Bahan {$line['name']} untuk {$row['name']} tidak ada di daftar.",
                        ]),
                        'qty' => $line['qty'],
                    ], $row['recipe'] ?? []);

                    $created[$index] = $this->create($business, ['recipe' => $recipe, 'opening_stock' => null] + $row, $openedAt);
                }
            }

            ksort($created);

            return array_values($created);
        });
    }

    /**
     * The columns the owner sets, normalised for the item's kind.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data, ItemKind $kind): array
    {
        return [
            'name' => $data['name'],
            'aliases' => $data['aliases'] ?? [],
            'unit' => $data['unit'],
            'price' => $kind->isSold() ? $data['price'] : null,
            'min_stock' => $kind->isStocked() ? ($data['min_stock'] ?? null) : null,
            'show_in_store' => $kind->isSold() && ($data['show_in_store'] ?? true),
            // PRD US-08: stocked items are sold out by their stock; only recipe products have a switch.
            'available' => $kind->isStocked() || ($data['available'] ?? true),
        ];
    }
}
