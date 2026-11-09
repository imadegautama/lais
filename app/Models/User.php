<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $whatsapp 62…, linked through the bot (WhatsAppLink)
 * @property string|null $whatsapp_lid
 * @property string|null $telegram_chat_id
 * @property Carbon|null $whatsapp_linked_at
 * @property Carbon|null $tour_completed_at
 * @property string|null $password Null for an account made with Google until a password is set in Keamanan.
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Audit log: only profile edits. Sign-ins, password and two-factor changes
     * are logged by App\Listeners\LogAccountActivity; secrets never are.
     *
     * @var list<string>
     */
    protected static $recordEvents = ['updated'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityCategory::Akun)
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn () => 'Mengubah profil');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_linked_at' => 'datetime',
            'tour_completed_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<Business, $this>
     */
    public function business(): HasOne
    {
        return $this->hasOne(Business::class);
    }

    /**
     * The business every page and record is scoped to.
     * shortcut: created on first access, replace with the onboarding wizard in M0.
     */
    public function currentBusiness(): Business
    {
        return $this->business()->firstOrCreate([], [
            'name' => "Usaha {$this->name}",
            'slug' => Str::slug($this->name).'-'.$this->id,
        ]);
    }

    /**
     * Browsers and phones that show this owner's notifications (web push).
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Where the bot reaches this owner: the number, the linked id when
     * WhatsApp only gave that, or the Telegram chat. Null until linked (Pengaturan > Bot).
     */
    public function whatsappAddress(): ?string
    {
        return $this->whatsapp_linked_at === null ? null : ($this->whatsapp ?? $this->whatsapp_lid ?? $this->telegram_chat_id);
    }
}
