<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One stock change, on an item's history (C6) and a transaction's detail (C4).
 * Name and unit come along when item is loaded, for lists that mix items.
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurredAt' => $this->occurred_at->toIso8601String(),
            'reason' => $this->reason,
            'qty' => (float) $this->qty,
            'value' => $this->value,
            'stockAfter' => $this->stock_after === null ? null : (float) $this->stock_after,
            'name' => $this->whenLoaded('item', fn () => $this->item->name),
            'unit' => $this->whenLoaded('item', fn () => $this->item->unit),
        ];
    }
}
