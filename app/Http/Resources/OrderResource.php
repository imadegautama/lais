<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Enums\TransactionKind;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Orders;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order for the owner's list and detail (C9, C10). The shop's nota (A4)
 * uses public() instead, which leaves out what only the owner needs.
 * Load lines.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orders = app(Orders::class);
        $payments = $orders->postedPayments($this->resource);
        $paid = $orders->paid($this->resource);

        return [
            ...$this->public($paid),
            // Settled from the debts page (Debts), so it is not among this order's payments.
            'paidElsewhere' => max(0, $paid - (int) $payments->sum('total')),
            'customerPhone' => $this->customer_phone,
            'source' => $this->source,
            'saleId' => $this->transaction_id,
            'payments' => $payments->map(fn (Transaction $payment) => [
                'id' => $payment->id,
                'occurredAt' => $payment->occurred_at->toIso8601String(),
                'label' => $payment->kind === TransactionKind::UangMuka ? 'Uang muka' : 'Pelunasan',
                'method' => $payment->method,
                'moneyAccount' => $payment->moneyAccount?->name,
                'amount' => $payment->total,
                'hasProof' => $payment->proof_path !== null,
            ])->values(),
        ];
    }

    /**
     * What the customer sees on the nota: no phone, contact, or payment records.
     *
     * @return array<string, mixed>
     */
    public function public(?int $paid = null): array
    {
        $paid ??= app(Orders::class)->paid($this->resource);

        return [
            'code' => $this->code,
            'status' => $this->status,
            'customerName' => $this->customer_name,
            'fulfillment' => $this->fulfillment,
            'address' => $this->address,
            'wantedAt' => $this->wanted_at?->toIso8601String(),
            'note' => $this->note,
            'total' => $this->total,
            'paid' => $paid,
            'paymentStatus' => PaymentStatus::for($paid, $this->total),
            'createdAt' => $this->created_at->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
            'readyAt' => $this->ready_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'rejectReason' => $this->reject_reason,
            'token' => $this->token,
            'lines' => $this->lines->map(fn ($line) => [
                'name' => $line->name,
                'unit' => $line->unit,
                'qty' => $line->qty,
                'price' => $line->price,
                'subtotal' => $line->subtotal,
            ])->values(),
        ];
    }
}
