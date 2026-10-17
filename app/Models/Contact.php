<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use App\Enums\ContactType;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A customer (pelanggan) or supplier. Balances live in the journal, see Ledger::contactBalances().
 *
 * @property int $id
 * @property int $business_id
 * @property ContactType $type
 * @property string $name
 * @property string|null $phone
 */
#[Fillable(['type', 'name', 'phone'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityCategory::Kontak)
            ->logOnly(['type', 'name', 'phone'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => ActivityEvent::from($event)->verb()." kontak {$this->name}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
