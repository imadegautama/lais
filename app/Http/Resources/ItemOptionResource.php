<?php

namespace App\Http\Resources;

use App\Models\Item;
use App\Models\RecipeLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item to pick in a form: a recipe ingredient (C6) or a line of a
 * transaction (C3). The recipe comes along when recipeLines is loaded, so the
 * form can show which ingredients a sale uses.
 *
 * @mixin Item
 */
class ItemOptionResource extends JsonResource
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
            'averageCost' => round($this->averageCost(), 4),
            'recipe' => $this->whenLoaded('recipeLines', fn () => $this->recipeLines->map(fn (RecipeLine $line) => [
                'materialId' => $line->material_id,
                'qty' => (float) $line->qty,
            ])->values()->all()),
        ];
    }
}
