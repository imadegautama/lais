<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $material_id
 * @property string $qty
 */
#[Fillable(['material_id', 'qty'])]
class RecipeLine extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'material_id');
    }
}
