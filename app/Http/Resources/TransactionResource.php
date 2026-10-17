<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the transaction list (C2) and of the dashboard's recent list.
 * Load contact to show its name.
 *
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
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
            'type' => $this->type,
            'description' => $this->description,
            'method' => $this->method,
            'source' => $this->source,
            'amount' => $this->total,
            // Credit and personal-money records move no business cash, so they carry no +/− sign.
            'direction' => $this->method === PaymentMethod::Kredit || $this->cash_flow === 0
                ? 'none'
                : ($this->cash_flow > 0 ? 'in' : 'out'),
            'contact' => $this->contact?->name,
            'voided' => $this->isVoid(),
        ];
    }
}
