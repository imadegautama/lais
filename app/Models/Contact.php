<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer (pelanggan) or supplier. Balances live in the journal, see Ledger::contactBalances().
 *
 * @property int $id
 * @property int $business_id
 * @property string $type
 * @property string $name
 * @property string|null $phone
 */
#[Fillable(['type', 'name', 'phone'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    public const TYPES = ['pelanggan', 'supplier'];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
