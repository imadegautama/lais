<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use App\Enums\ItemKind;
use App\Support\Format;
use App\Support\Media;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $business_id
 * @property ItemKind $kind
 * @property string $name
 * @property list<string>|null $aliases
 * @property string|null $photo_path
 * @property string $unit
 * @property int|null $price
 * @property string $stock
 * @property int $stock_value
 * @property string|null $min_stock
 * @property bool $show_in_store
 * @property bool $available
 */
#[Fillable(['kind', 'name', 'aliases', 'unit', 'price', 'min_stock', 'show_in_store', 'available', 'photo_path'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * What a new item holds before it is read back: the toggles' database
     * defaults, and no aliases as the form saves them ([]). Otherwise its
     * first save logs them as changed from empty.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'aliases' => '[]',
        'show_in_store' => true,
        'available' => true,
    ];

    /**
     * Audit log: what the owner sets. Stock moves on every sale and already
     * has its own history (stock_movements), so it is left out.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityCategory::Produk)
            ->logOnly(['kind', 'name', 'aliases', 'unit', 'price', 'min_stock', 'show_in_store', 'available'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => ActivityEvent::from($event)->verb()." produk {$this->name}");
    }

    /**
     * "0,15 kg Beras, 1 butir Telur". Load recipeLines.material.
     */
    public function recipeText(): string
    {
        return $this->recipeLines
            ->map(fn (RecipeLine $line) => Format::qty($line->qty)." {$line->material->unit} {$line->material->name}")
            ->join(', ');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ItemKind::class,
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
        return $this->kind->isStocked();
    }

    public function isSold(): bool
    {
        return $this->kind->isSold();
    }

    /**
     * Below its minimum but not minus; minus has its own warning.
     */
    public function isLowStock(): bool
    {
        return $this->hasStock() && $this->min_stock !== null
            && (float) $this->stock >= 0 && (float) $this->stock < (float) $this->min_stock;
    }

    public function isNegativeStock(): bool
    {
        return $this->hasStock() && (float) $this->stock < 0;
    }

    /**
     * Items with a stock row of their own.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function stocked(Builder $query): void
    {
        $query->whereIn('items.kind', ItemKind::stocked());
    }

    /**
     * SQL twin of isLowStock().
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->stocked()->whereNotNull('items.min_stock')->where('items.stock', '>=', 0)->whereColumn('items.stock', '<', 'items.min_stock');
    }

    /**
     * SQL twin of isNegativeStock().
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function negativeStock(Builder $query): void
    {
        $query->stocked()->where('items.stock', '<', 0);
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
     * SQL twin of unitCost(), used only to sort the product list; keep the two in step.
     * "* 1.0" stops SQLite from dividing integers.
     * shortcut: a correlated subquery per row, fine for a few thousand items; store unit_cost if it gets slow.
     */
    public const UNIT_COST_SQL = "(case when items.kind = '".ItemKind::Komposisi->value."' then coalesce((
            select sum(rl.qty * case when m.stock > 0 then m.stock_value * 1.0 / m.stock else 0 end)
            from recipe_lines rl join items m on m.id = rl.material_id and m.deleted_at is null
            where rl.product_id = items.id), 0)
        else case when items.stock > 0 then items.stock_value * 1.0 / items.stock else 0 end end)";

    /**
     * Margin as a fraction of price, null without a price. Written as 1 - cost/price
     * because price - cost overflows MySQL's unsigned BIGINT when cost is higher.
     */
    public const MARGIN_SQL = '(case when items.price > 0 then 1 - '.self::UNIT_COST_SQL.' * 1.0 / items.price end)';

    /**
     * Cost of one unit: the recipe total for komposisi, the average cost otherwise.
     * Callers must eager load recipeLines.material for komposisi items.
     */
    public function unitCost(): int
    {
        if ($this->kind !== ItemKind::Komposisi) {
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

    /**
     * The lowest price, rounded up to Rp500, that keeps $percent of it as
     * margin. Same rule as suggestedPrice in features/items/cost.ts.
     */
    public static function priceForMargin(int $unitCost, float $percent): int
    {
        // Rounded first: 7200 / 0.6 can land a hair above 12000 and cost a whole Rp500 step.
        return (int) ceil(round($unitCost / (1 - $percent / 100) / 500, 6)) * 500;
    }

    /**
     * The shop photo (public disk); asset() follows the host the page was opened on.
     */
    public function photoUrl(): ?string
    {
        return Media::url($this->photo_path);
    }
}
