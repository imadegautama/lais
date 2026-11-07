<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * The transaction on its detail page (C4): the list row plus what only the detail shows.
 */
class TransactionDetailResource extends TransactionResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'kind' => $this->kind,
            'note' => $this->note,
            'paidAmount' => $this->paid_amount,
            'cashFlow' => $this->cash_flow,
            'voidedAt' => $this->voided_at?->toIso8601String(),
            'voidReason' => $this->void_reason,
            // "Ubah" links: the record saved in place of this one, and the one this replaced (load replaces).
            'replacedById' => $this->replaced_by_id,
            'replacesId' => $this->whenLoaded('replaces', fn () => $this->replaces?->id),
            // Set when an order made it; the order is where it is changed (load order).
            'orderCode' => $this->whenLoaded('order', fn () => $this->order?->code),
            // A route that checks the owner, never a public file URL.
            'proofUrl' => $this->proof_path === null ? null : route('transactions.proof', $this->resource),
        ];
    }
}
