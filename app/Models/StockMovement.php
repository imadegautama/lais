<?php

namespace App\Models;

use App\Enums\StockReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $item_id
 * @property string $qty
 * @property string|null $stock_after
 * @property int $value
 * @property int|null $transaction_id
 * @property StockReason $reason
 * @property Carbon $occurred_at
 */
#[Fillable(['business_id', 'item_id', 'qty', 'stock_after', 'value', 'reason', 'journal_entry_id', 'transaction_id', 'occurred_at'])]
class StockMovement extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'value' => 'integer',
            'reason' => StockReason::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
