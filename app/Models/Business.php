<?php

namespace App\Models;

use App\Enums\MoneyAccountKind;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['name', 'slug'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * Every business starts with its cash box and one rekening the owner renames
     * (BCA, GoPay), so recording works before onboarding asks for more.
     */
    protected static function booted(): void
    {
        // Set up by the system, not by the owner, so the log stays about what people did.
        static::created(fn (Business $business) => activity()->withoutLogging(fn () => $business->moneyAccounts()->createMany([
            ['kind' => MoneyAccountKind::Tunai, 'name' => 'Kas tunai'],
            ['kind' => MoneyAccountKind::NonTunai, 'name' => 'Rekening'],
        ])));
    }

    /**
     * Active rekening; archived ones are reached withTrashed().
     *
     * @return HasMany<MoneyAccount, $this>
     */
    public function moneyAccounts(): HasMany
    {
        return $this->hasMany(MoneyAccount::class);
    }

    /**
     * The one Tunai rekening, "Kas tunai".
     */
    public function cashBox(): MoneyAccount
    {
        return $this->moneyAccounts()->where('money_accounts.kind', MoneyAccountKind::Tunai)->firstOrFail();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
