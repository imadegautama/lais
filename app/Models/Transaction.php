<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One recorded transaction (PRD M2). Created only through TransactionService, which posts its journal.
 *
 * @property int $id
 * @property int $business_id
 * @property TransactionType $type
 * @property TransactionKind|null $kind
 * @property PaymentMethod $method
 * @property Carbon $occurred_at
 * @property int $total
 * @property int $paid_amount
 * @property int $cash_flow
 * @property string $description
 * @property string|null $note
 * @property int|null $contact_id
 * @property TransactionSource $source
 * @property TransactionStatus $status
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $replaced_by_id
 */
#[Fillable(['type', 'kind', 'method', 'occurred_at', 'total', 'paid_amount', 'cash_flow', 'description', 'note', 'contact_id', 'source'])]
class Transaction extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'kind' => TransactionKind::class,
            'method' => PaymentMethod::class,
            'source' => TransactionSource::class,
            'status' => TransactionStatus::class,
            'occurred_at' => 'datetime',
            'voided_at' => 'datetime',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'cash_flow' => 'integer',
        ];
    }

    public function isVoid(): bool
    {
        return $this->status === TransactionStatus::Void;
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
     * The record "Ubah" saved in place of this voided one.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'replaced_by_id');
    }

    /**
     * The voided record this one replaced.
     *
     * @return HasOne<Transaction, $this>
     */
    public function replaces(): HasOne
    {
        return $this->hasOne(Transaction::class, 'replaced_by_id');
    }

    /**
     * @return HasMany<TransactionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
