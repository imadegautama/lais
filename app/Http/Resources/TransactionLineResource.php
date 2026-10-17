<?php

namespace App\Http\Resources;

use App\Models\TransactionLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load item; archived items still show by name.
 *
 * @mixin TransactionLine
 */
class TransactionLineResource extends JsonResource
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
            'name' => $this->item->name,
            'unit' => $this->item->unit,
            'qty' => (float) $this->qty,
            'price' => $this->price,
            'subtotal' => $this->subtotal,
            'hpp' => $this->hpp,
        ];
    }
}
