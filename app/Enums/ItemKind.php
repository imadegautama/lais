<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * What an item is. Produk and bahan keep their own stock; komposisi (a recipe
 * product) takes its stock from its ingredients when sold.
 */
enum ItemKind: string
{
    use EnumValues;

    case Produk = 'produk';
    case Bahan = 'bahan';
    case Komposisi = 'komposisi';

    public function label(): string
    {
        return match ($this) {
            self::Produk => 'Produk biasa',
            self::Bahan => 'Bahan baku',
            self::Komposisi => 'Produk resep',
        };
    }

    public function isSold(): bool
    {
        return $this !== self::Bahan;
    }

    public function isStocked(): bool
    {
        return $this !== self::Komposisi;
    }

    /**
     * @return list<self>
     */
    public static function sold(): array
    {
        return [self::Produk, self::Komposisi];
    }

    /**
     * Kinds with a stock row: what can be bought and used in a recipe.
     *
     * @return list<self>
     */
    public static function stocked(): array
    {
        return [self::Produk, self::Bahan];
    }
}
