<?php

namespace App\Models;

use App\Enums\OrderFulfillment;
use App\Enums\OrderStatus;
use App\Enums\TransactionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pesanan (PRD M4). Created and moved along only through App\Services\Orders;
 * its money is in the transactions that point to it.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $contact_id
 * @property int|null $transaction_id
 * @property string $code
 * @property string $token
 * @property OrderStatus $status
 * @property TransactionSource $source
 * @property string $customer_name
 * @property string $customer_phone
 * @property OrderFulfillment $fulfillment
 * @property string|null $address
 * @property Carbon|null $wanted_at
 * @property string|null $note
 * @property int $total
 * @property Carbon|null $accepted_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $rejected_at
 * @property string|null $reject_reason
 * @property Carbon $created_at
 */
#[Fillable(['code', 'token', 'status', 'source', 'customer_name', 'customer_phone', 'fulfillment', 'address', 'wanted_at', 'note', 'total', 'contact_id', 'transaction_id', 'accepted_at', 'ready_at', 'completed_at', 'rejected_at', 'reject_reason'])]
class Order extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => TransactionSource::class,
            'fulfillment' => OrderFulfillment::class,
            'total' => 'integer',
            'wanted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The sale it became when done.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * DOKU Checkout pages opened for it (App\Services\Doku).
     *
     * @return HasMany<OnlinePayment, $this>
     */
    public function onlinePayments(): HasMany
    {
        return $this->hasMany(OnlinePayment::class);
    }

    /**
     * Uang muka, the sale, and payments after it.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
