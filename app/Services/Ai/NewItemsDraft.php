<?php

namespace App\Services\Ai;

use App\Enums\ItemKind;
use Illuminate\Support\Collection;

/**
 * Goods a draft mentions that the catalog does not have yet, on their way into
 * it: one draft for all of them, carrying the record they came from. Each row
 * goes through ItemDraft, so ItemRequest judges it like the product form.
 */
final class NewItemsDraft
{
    public const FORM = 'barang';

    /**
     * The record's waiting goods, or only those the owner named.
     *
     * @param  array<string, mixed>  $record  a transaction draft's values
     * @param  array<string, mixed>  $args  tambah_ke_daftar's arguments
     * @return array{items: list<array{name: string, kind: string|null, unit: string, price: int|null}>, record: array<string, mixed>}
     */
    public static function fromRecord(array $record, array $args): array
    {
        $rows = collect(is_array($record['unknown'] ?? null) ? $record['unknown'] : [])
            ->filter(fn (mixed $line) => is_array($line) && self::text($line['name'] ?? null) !== '')
            ->map(fn (array $line) => ['name' => self::cased(self::text($line['name'])), 'kind' => null, 'unit' => 'pcs', 'price' => null])
            // A receipt can list the same goods twice; the catalog holds them once.
            ->unique(fn (array $row) => mb_strtolower($row['name']))
            ->values();
        $named = $rows->filter(fn (array $row) => self::given($row, $args) !== null)->values();

        return ['items' => array_values(($named->isEmpty() ? $rows : $named)->all()), 'record' => $record];
    }

    /**
     * What the owner said about the rows; a name outside them changes nothing.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $values
     * @return array{items: list<array{name: string, kind: string|null, unit: string, price: int|null}>, record: array<string, mixed>}
     */
    public static function fill(array $args, array $values): array
    {
        $rows = [];

        foreach (is_array($values['items'] ?? null) ? $values['items'] : [] as $row) {
            if (! is_array($row) || self::text($row['name'] ?? null) === '') {
                continue;
            }

            $kept = [
                'name' => mb_substr(self::text($row['name']), 0, 120),
                'kind' => self::kind($row['kind'] ?? null),
                'unit' => mb_substr(self::text($row['unit'] ?? null) ?: 'pcs', 0, 20),
                'price' => is_numeric($row['price'] ?? null) ? (int) $row['price'] : null,
            ];
            $given = self::given($kept, $args);

            if ($given !== null) {
                $kept['kind'] = self::kind($given['jenis'] ?? null) ?? $kept['kind'];
                $kept['unit'] = mb_substr(self::text($given['satuan'] ?? null), 0, 20) ?: $kept['unit'];
                // Models sometimes write the shorter word, whatever the schema says.
                $price = $given['harga_jual'] ?? $given['harga'] ?? null;
                $kept['price'] = is_numeric($price) && (int) $price >= 0 ? (int) $price : $kept['price'];
            }

            $rows[] = $kept;
        }

        return ['items' => $rows, 'record' => is_array($values['record'] ?? null) ? $values['record'] : []];
    }

    /**
     * What the product form would send for one row.
     *
     * @param  array{name: string, kind: string|null, unit: string, price: int|null}  $row
     * @return array<string, mixed>
     */
    public static function payload(array $row): array
    {
        return ItemDraft::payload(ItemDraft::fill([
            'jenis' => $row['kind'],
            'nama' => $row['name'],
            'satuan' => $row['unit'],
            'harga' => $row['price'],
        ], [], new Collection)['values']);
    }

    /**
     * @param  array{items: list<array{name: string, kind: string|null, unit: string, price: int|null}>, record: array<string, mixed>}  $values
     * @return array{title: string, lines: list<array{name: string, qty: float|null, unit: string|null, price: int|null, amount: int|null}>, total: array{label: string, amount: int}|null, notes: list<string>}
     */
    public static function card(array $values): array
    {
        $names = fn (ItemKind $kind) => array_column(array_filter($values['items'], fn (array $row) => $row['kind'] === $kind->value), 'name');

        return [
            'title' => 'Tambah ke daftar produk',
            'lines' => array_map(fn (array $row) => ['name' => "{$row['name']} (per {$row['unit']})", 'qty' => null, 'unit' => null, 'price' => null, 'amount' => $row['price']], $values['items']),
            'total' => null,
            'notes' => array_values(array_filter([
                in_array(true, array_map(fn (array $row) => $row['price'] !== null, $values['items']), true) ? 'Angka di kanan adalah harga jual.' : '',
                $names(ItemKind::Produk) === [] ? '' : 'Dijual lagi: '.implode(', ', $names(ItemKind::Produk)),
                $names(ItemKind::Bahan) === [] ? '' : 'Bahan baku: '.implode(', ', $names(ItemKind::Bahan)),
            ])),
        ];
    }

    /**
     * @param  array{name: string}  $row
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    private static function given(array $row, array $args): ?array
    {
        $own = mb_strtolower($row['name']);

        foreach (is_array($args['barang'] ?? null) ? $args['barang'] : [] as $line) {
            $name = is_array($line) ? mb_strtolower(self::text($line['nama'] ?? null)) : '';

            // "frestea" for "Frestea madu", the way owners shorten a receipt's names.
            if ($name !== '' && (str_contains($own, $name) || str_contains($name, $own))) {
                return $line;
            }
        }

        return null;
    }

    /** As ItemDraft saves it, so the card shows the name that will be stored. */
    private static function cased(string $name): string
    {
        return $name === mb_strtolower($name) ? mb_convert_case($name, MB_CASE_TITLE) : $name;
    }

    /** Recipes need the product form, so only goods bought ready are registered here. */
    private static function kind(mixed $value): ?string
    {
        $kind = ItemKind::tryFrom(self::text($value));

        return $kind !== null && $kind->isStocked() ? $kind->value : null;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }
}
