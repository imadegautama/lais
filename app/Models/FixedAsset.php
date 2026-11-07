<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peralatan that lasts more than a period (SAK EMKM Bab 11), depreciated
 * month by month through App\Services\Depreciation. Its balances live in the
 * journal; only what the schedule needs is kept here.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $transaction_id
 * @property string $name
 * @property int $cost
 * @property CarbonImmutable $acquired_on
 * @property int|null $useful_life_months null until the owner sets it: nothing is depreciated
 * @property CarbonImmutable|null $depreciated_through
 * @property CarbonImmutable|null $cancelled_at
 */
#[Fillable(['transaction_id', 'name', 'cost', 'acquired_on', 'useful_life_months', 'depreciated_through', 'cancelled_at'])]
class FixedAsset extends Model
{
    /** Used when a record does not say; the owner can change it (par. 11.14). */
    public const DEFAULT_LIFE_MONTHS = 48;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'depreciated_through' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
