<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use App\Enums\MoneyAccountKind;
use Database\Factories\MoneyAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Kas & rekening: where the business keeps its money. Every business has one
 * Tunai ("Kas tunai") and one or more NonTunai (bank, e-wallet). Its balance
 * is the sum of its journal lines (Ledger::moneyBalances()); archiving is a
 * soft delete, so its history stays.
 *
 * @property int $id
 * @property int $business_id
 * @property MoneyAccountKind $kind
 * @property string $name
 */
#[Fillable(['kind', 'name'])]
class MoneyAccount extends Model
{
    /** @use HasFactory<MoneyAccountFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => MoneyAccountKind::class];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityCategory::Rekening)
            ->logOnly(['kind', 'name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => ActivityEvent::from($event)->verb()." rekening {$this->name}");
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
