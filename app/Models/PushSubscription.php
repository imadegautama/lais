<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One browser or phone that may show the owner's notifications (web push).
 * The keys only let a push service deliver to it; they are never shown.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint_hash sha256 of the endpoint, for the unique index
 * @property string $endpoint
 * @property string $public_key p256dh, base64url
 * @property string $auth_token base64url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token'])]
#[Hidden(['public_key', 'auth_token'])]
class PushSubscription extends Model
{
    /** Phones and browsers kept per owner; the oldest goes when a new one comes. */
    public const PER_USER = 10;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
