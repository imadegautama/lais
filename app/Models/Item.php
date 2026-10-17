<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $business_id
 * @property string $kind
 * @property string $name
 * @property list<string>|null $aliases
 * @property string $unit
 * @property int|null $price
 * @property string $stock
 * @property int $stock_value
 * @property string|null $min_stock
 * @property bool $show_in_store
 * @property bool $available
 */
#[Fillable(['kind', 'name', 'aliases', 'unit', 'price', 'min_stock', 'show_in_store', 'available'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    public const KINDS = ['produk', 'bahan', 'komposisi'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'price' => 'integer',
            'stock' => 'decimal:3',
            'stock_value' => 'integer',
            'min_stock' => 'decimal:3',
            'show_in_store' => 'boolean',
            'available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Ingredients of a komposisi product.
     *
     * @return HasMany<RecipeLine, $this>
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'product_id');
    }

    /**
     * Recipes that use this item as an ingredient.
     *
     * @return HasMany<RecipeLine, $this>
     */
    public function usedInRecipes(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'material_id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function hasStock(): bool
    {
        return $this->kind !== 'komposisi';
    }

    public function isSold(): bool
    {
        return $this->kind !== 'bahan';
    }

    /**
     * Weighted average cost per unit, derived from the rupiah value on hand.
     */
    public function averageCost(): float
    {
        $stock = (float) $this->stock;

        return $stock > 0 ? $this->stock_value / $stock : 0.0;
    }

    /**
     * Cost of one unit: the recipe total for komposisi, the average cost otherwise.
     * Callers must eager load recipeLines.material for komposisi items.
     */
    public function unitCost(): int
    {
        if ($this->kind !== 'komposisi') {
            return (int) round($this->averageCost());
        }

        return (int) round($this->recipeLines->sum(
            fn (RecipeLine $line) => (float) $line->qty * ($line->material?->averageCost() ?? 0.0),
        ));
    }

    /**
     * Margin as a percentage of the selling price, null when there is no price.
     */
    public function marginPercent(): ?float
    {
        if (! $this->price) {
            return null;
        }

        return round(($this->price - $this->unitCost()) / $this->price * 100, 1);
    }
}
