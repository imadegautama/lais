<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The chart of accounts (PRD §4.4): the fixed accounts every business shares
 * (business_id null), seeded by migration and locked because the journal rules
 * name them, plus the income and expense accounts a business adds for itself.
 *
 * @property int $id
 * @property int|null $business_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property string $normal_balance
 * @property Carbon|null $archived_at
 */
#[Fillable(['code', 'name', 'type', 'normal_balance', 'archived_at'])]
class Account extends Model
{
    use LogsActivity;

    // Codes of the fixed chart. Plain constants, not an enum, because they are array keys in Ledger::lines().
    public const CASH = '1101';

    public const NON_CASH = '1102';

    public const RECEIVABLE = '1201';

    public const INVENTORY = '1301';

    public const EQUIPMENT = '1401';

    public const ACCUMULATED_DEPRECIATION = '1402';

    public const PAYABLE = '2101';

    public const CUSTOMER_DEPOSITS = '2201';

    public const OWNER_CAPITAL = '3101';

    public const DRAWINGS = '3201';

    public const SALES = '4101';

    public const COGS = '5101';

    public const EXPENSES = '6101';

    public const STOCK_DIFFERENCE = '6201';

    public const DEPRECIATION = '6301';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'archived_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityCategory::BaganAkun)
            ->logOnly(['name', 'archived_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => ActivityEvent::from($event)->verb()." akun {$this->name}");
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The accounts one business can see and post to: never another's own.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, Business $business): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('accounts.business_id')->orWhere('accounts.business_id', $business->id));
    }

    public function isSystem(): bool
    {
        return $this->business_id === null;
    }

    /**
     * The next free code for an account this business adds.
     */
    public static function nextCode(Business $business, AccountType $type): string
    {
        $last = (int) self::query()->where('accounts.business_id', $business->id)->where('accounts.type', $type)->max('accounts.code');

        return (string) max((int) $type->firstOwnCode(), $last + 1);
    }
}
