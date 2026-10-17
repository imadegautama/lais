<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One recorded transaction (PRD M2). Created only through TransactionService, which posts its journal.
 *
 * @property int $id
 * @property int $business_id
 * @property string $type
 * @property string|null $kind
 * @property string $method
 * @property Carbon $occurred_at
 * @property int $total
 * @property int $paid_amount
 * @property int $cash_flow
 * @property string $description
 * @property string|null $note
 * @property int|null $contact_id
 * @property string $source
 * @property string $status
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 */
#[Fillable(['type', 'kind', 'method', 'occurred_at', 'total', 'paid_amount', 'cash_flow', 'description', 'note', 'contact_id', 'source'])]
class Transaction extends Model
{
    public const TYPES = ['penjualan', 'pembelian', 'biaya', 'utang_piutang', 'modal'];

    /**
     * Sub-kinds per type; penjualan has none.
     *
     * @var array<string, list<string>>
     */
    public const KINDS = [
        'pembelian' => ['barang', 'peralatan'],
        'biaya' => ['listrik_air', 'gas_bbm', 'sewa', 'upah', 'transport', 'pemasaran', 'lain'],
        'utang_piutang' => ['terima_piutang', 'bayar_utang'],
        'modal' => ['setor', 'prive'],
    ];

    /**
     * Payment methods each type accepts (PRD US-05: uang pribadi only for purchases and expenses).
     *
     * @var array<string, list<string>>
     */
    public const METHODS = [
        'penjualan' => ['tunai', 'non_tunai', 'kredit'],
        'pembelian' => ['tunai', 'non_tunai', 'kredit', 'pribadi'],
        'biaya' => ['tunai', 'non_tunai', 'kredit', 'pribadi'],
        'utang_piutang' => ['tunai', 'non_tunai'],
        'modal' => ['tunai', 'non_tunai'],
    ];

    public const SOURCES = ['web', 'wa', 'toko', 'ocr'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'voided_at' => 'datetime',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'cash_flow' => 'integer',
        ];
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
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
