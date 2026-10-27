<?php

namespace App\Services\Ai;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Support\Format;
use Illuminate\Support\Collection;

/**
 * The new product form, filled from the model's isi_produk arguments. Values
 * follow ItemFormData (features/items/item-form.ts) laid over what the form
 * already holds; payload() is what Simpan sends (toItemPayload), so
 * ItemRequest can judge the draft.
 */
final class ItemDraft
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $current  the form as the page holds it
     * @param  Collection<int, Item>  $items  the business's items, for recipe ingredients
     * @return array{values: array<string, mixed>, notes: list<string>}
     */
    public static function fill(array $args, array $current, Collection $items): array
    {
        $notes = [];
        $values = self::start($current);
        $kind = ItemKind::tryFrom(self::text($args['jenis'] ?? null));

        if ($kind !== null && $kind->value !== $values['kind']) {
            $values['kind'] = $kind->value;
            // The unit default follows the kind (vocabulary.ts defaultUnit) unless one was typed.
            $values['unit'] = in_array($values['unit'], ['', 'pcs', 'porsi'], true) ? self::defaultUnit($kind) : $values['unit'];
        }

        $kind = ItemKind::from($values['kind']);

        if (self::text($args['nama'] ?? null) !== '') {
            $name = mb_substr(self::text($args['nama']), 0, 120);
            // Owners type in lowercase; a name they cased themselves ("Es Teh BCA") stays as typed.
            $values['name'] = $name === mb_strtolower($name) ? mb_convert_case($name, MB_CASE_TITLE) : $name;
        }

        if (self::text($args['satuan'] ?? null) !== '') {
            $values['unit'] = mb_substr(self::text($args['satuan']), 0, 20);
        }

        if (is_array($args['alias'] ?? null) && $args['alias'] !== []) {
            $values['aliases'] = implode(', ', array_slice(array_filter(array_map(self::text(...), $args['alias'])), 0, 10));
        }

        if (is_numeric($args['harga'] ?? null) && (int) $args['harga'] >= 0) {
            $values['price'] = (int) $args['harga'];
        }

        if ($kind->isStocked()) {
            foreach (['stok_awal' => 'opening_stock', 'stok_minimum' => 'min_stock'] as $arg => $field) {
                if (is_numeric($args[$arg] ?? null) && (float) $args[$arg] >= 0) {
                    $values[$field] = Format::decimalInput((float) $args[$arg]);
                }
            }

            if (is_numeric($args['biaya_per_unit'] ?? null) && (int) $args['biaya_per_unit'] >= 0) {
                $values['opening_cost'] = (int) $args['biaya_per_unit'];
            }
        }

        if ($kind === ItemKind::Komposisi && is_array($args['resep'] ?? null) && $args['resep'] !== []) {
            $materials = $items->filter(fn (Item $item) => $item->kind->isStocked());
            $recipe = [];

            foreach ($args['resep'] as $row) {
                $material = is_array($row) ? Names::match($materials, $row['bahan'] ?? null, fn (Item $item) => [$item->name, ...($item->aliases ?? [])]) : null;

                if ($material === null) {
                    $notes[] = 'Bahan "'.self::text(is_array($row) ? ($row['bahan'] ?? null) : null).'" belum ada. Tambahkan dulu sebagai bahan baku.';

                    continue;
                }

                $qty = is_numeric($row['qty'] ?? null) && (float) $row['qty'] > 0 ? (float) $row['qty'] : 1;
                $recipe[] = ['material_id' => (string) $material->id, 'qty' => Format::decimalInput($qty)];
            }

            if ($recipe !== []) {
                $values['recipe'] = $recipe;
            }
        }

        return ['values' => $values, 'notes' => $notes];
    }

    /**
     * What Simpan sends for a new item (item-form.ts toItemPayload).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function payload(array $values): array
    {
        $kind = ItemKind::from((string) $values['kind']);

        return [
            'kind' => $kind->value,
            'name' => $values['name'],
            'aliases' => $values['aliases'],
            'unit' => $values['unit'],
            'price' => $kind->isSold() ? $values['price'] : null,
            'min_stock' => $kind->isStocked() ? self::decimal($values['min_stock']) : null,
            'show_in_store' => $kind->isSold() && $values['show_in_store'],
            'available' => $values['available'],
            ...($kind->isStocked() ? ['opening_stock' => self::decimal($values['opening_stock']), 'opening_cost' => $values['opening_cost']] : []),
            ...($kind === ItemKind::Komposisi ? ['recipe' => array_map(fn (array $line) => [
                'material_id' => $line['material_id'] === '' ? null : (int) $line['material_id'],
                'qty' => self::decimal($line['qty']),
            ], $values['recipe'])] : []),
        ];
    }

    /**
     * What the chat shows before the owner confirms: the recipe as lines,
     * the selling price as the total, and the rest as short notes.
     *
     * @param  array<string, mixed>  $values
     * @param  Collection<int, Item>  $items
     * @return array{title: string, lines: list<array{name: string, qty: float|null, unit: string|null, price: int|null, amount: int|null}>, total: array{label: string, amount: int}|null, notes: list<string>}
     */
    public static function card(array $values, Collection $items): array
    {
        $kind = ItemKind::from((string) $values['kind']);
        $unit = $values['unit'] ?: self::defaultUnit($kind);
        $lines = [];

        foreach ($kind === ItemKind::Komposisi ? $values['recipe'] : [] as $line) {
            $material = $items->firstWhere('id', (int) $line['material_id']);

            if ($material !== null) {
                $lines[] = ['name' => $material->name, 'qty' => self::decimal($line['qty']), 'unit' => $material->unit, 'price' => null, 'amount' => null];
            }
        }

        $notes = [$kind->label(), $values['aliases'] === '' ? '' : "Nama lain: {$values['aliases']}"];

        if ($kind->isStocked() && self::decimal($values['opening_stock']) !== null) {
            $notes[] = "Stok awal {$values['opening_stock']} {$unit}"
                .(is_int($values['opening_cost']) ? ', modal '.Format::rupiah($values['opening_cost'])." per {$unit}" : '');
        }

        return [
            'title' => $values['name'] ?: 'Produk baru',
            'lines' => $lines,
            'total' => $kind->isSold() && is_int($values['price']) ? ['label' => "Harga jual per {$unit}", 'amount' => $values['price']] : null,
            'notes' => array_values(array_filter($notes, fn (string $note) => $note !== '')),
        ];
    }

    /**
     * The form's starting values (item-form.ts initialItemForm for a new item).
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private static function start(array $current): array
    {
        $kind = ItemKind::tryFrom(self::text($current['kind'] ?? null)) ?? ItemKind::Produk;
        $defaults = [
            'kind' => $kind->value,
            'name' => '',
            'aliases' => '',
            'unit' => self::defaultUnit($kind),
            'price' => null,
            'min_stock' => '',
            'show_in_store' => true,
            'available' => true,
            'opening_stock' => '',
            'opening_cost' => null,
            'recipe' => [],
        ];
        $values = array_merge($defaults, array_intersect_key($current, $defaults), ['kind' => $kind->value]);

        foreach (['name', 'aliases', 'unit', 'min_stock', 'opening_stock'] as $key) {
            $values[$key] = self::text($values[$key]);
        }

        foreach (['price', 'opening_cost'] as $key) {
            $values[$key] = is_numeric($values[$key]) ? (int) $values[$key] : null;
        }

        foreach (['show_in_store', 'available'] as $key) {
            $values[$key] = (bool) $values[$key];
        }

        $values['recipe'] = array_map(fn (array $line) => [
            'material_id' => self::text($line['material_id'] ?? null),
            'qty' => self::text($line['qty'] ?? null),
        ], array_values(array_filter(is_array($values['recipe']) ? $values['recipe'] : [], 'is_array')));

        return $values;
    }

    private static function defaultUnit(ItemKind $kind): string
    {
        return $kind === ItemKind::Komposisi ? 'porsi' : 'pcs';
    }

    private static function decimal(mixed $text): ?float
    {
        $value = str_replace(',', '.', self::text($text));

        return is_numeric($value) ? (float) $value : null;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }
}
