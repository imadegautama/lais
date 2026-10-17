<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $item_id
 * @property string $qty
 * @property int $price
 * @property int $subtotal
 * @property int|null $hpp
 */
#[Fillable(['item_id', 'qty', 'price', 'subtotal', 'hpp'])]
class TransactionLine extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'price' => 'integer',
            'subtotal' => 'integer',
            'hpp' => 'integer',
        ];
    }

    /**
     * Archived items stay visible in old transactions.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
