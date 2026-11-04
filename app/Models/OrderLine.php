<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of an order, with its name, unit and price as they were ordered.
 *
 * @property int $id
 * @property int $order_id
 * @property int $item_id
 * @property string $name
 * @property string $unit
 * @property int $qty
 * @property int $price
 * @property int $subtotal
 */
#[Fillable(['item_id', 'name', 'unit', 'qty', 'price', 'subtotal'])]
class OrderLine extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['qty' => 'integer', 'price' => 'integer', 'subtotal' => 'integer'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
