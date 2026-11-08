<?php

namespace App\Models;

use App\Enums\OnlinePaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One DOKU Checkout page for an order. Created and settled only through
 * App\Services\Doku; the money itself is the uang muka transaction it makes.
 *
 * @property int $id
 * @property int $order_id
 * @property string $invoice_number
 * @property int $amount
 * @property string $url
 * @property OnlinePaymentStatus $status
 * @property string|null $channel
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 */
#[Fillable(['invoice_number', 'amount', 'url', 'status', 'channel', 'expires_at', 'paid_at'])]
class OnlinePayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OnlinePaymentStatus::class,
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Waiting for the customer, and the page still takes payments.
     */
    public function isOpen(): bool
    {
        return $this->status === OnlinePaymentStatus::Menunggu && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
