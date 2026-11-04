<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the product list (C5), also the base of the edit form (C6).
 * Komposisi items need recipeLines.material loaded for the cost.
 *
 * @mixin Item
 */
class ItemResource extends JsonResource
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
            'kind' => $this->kind,
            'name' => $this->name,
            'aliases' => $this->aliases ?? [],
            'unit' => $this->unit,
            'price' => $this->price,
            'stock' => (float) $this->stock,
            'stockValue' => $this->stock_value,
            'minStock' => $this->min_stock !== null ? (float) $this->min_stock : null,
            'unitCost' => $this->unitCost(),
            'marginPercent' => $this->marginPercent(),
            'showInStore' => $this->show_in_store,
            'available' => $this->available,
            'photoUrl' => $this->photoUrl(),
            'lowStock' => $this->isLowStock(),
            'negativeStock' => $this->isNegativeStock(),
        ];
    }
}
